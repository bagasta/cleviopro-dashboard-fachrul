const csrfTokenElement = document.head.querySelector('meta[name="csrf-token"]');
const csrfToken = csrfTokenElement ? csrfTokenElement.getAttribute('content') : '';

const requestJson = async (url, { method = 'POST', payload } = {}) => {
        const headers = { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken };
    const options = { method, headers };

    if (payload !== undefined) {
        headers['Content-Type'] = 'application/json';
        options.body = JSON.stringify(payload);
    }

    const response = await fetch(url, options);

    let data = null;
    try {
        data = await response.json();
    } catch (error) {
        data = null;
    }

    if (!response.ok) {
        const errorMessage = data?.message ?? `Unexpected error (status ${response.status}).`;
        const details = data?.details;
        throw new Error(typeof details === 'string' ? details : errorMessage);
    }

    return data ?? {};
};

const postJson = (url, payload = {}) => requestJson(url, { method: 'POST', payload });

const bubbleClasses = {
    user: 'ml-auto max-w-[80%] rounded-2xl px-4 py-2 text-sm bg-indigo-600 text-white shadow',
    bot: 'mr-auto max-w-[80%] rounded-2xl px-4 py-2 text-sm bg-slate-100 text-slate-900 border border-slate-200 shadow-sm',
};

const knowledgeMessages = {
    chooseFile: 'Please choose at least one file to upload.',
    invalidFile: 'Only .pdf, .doc, .docx, .odt, .ppt, .pptx, or .odp files are allowed.',
    fileLimit: 'You can upload up to 20 files at once.',
    modalTitle: 'Add Knowledge',
    modalDescriptionPrefix: 'Upload relevant documents to extend the knowledge base for',
    uploading: 'Uploading...',
    uploadSuccess: 'Knowledge uploaded successfully.',
    uploadFailed: 'Upload failed.',
};

const defaultSupportReply = 'Sent successfully.';
const acknowledgementMessages = new Set([
    defaultSupportReply.toLowerCase(),
    'message sent successfully.',
    'ok',
]);

const pickReplyString = (value) => {
    if (typeof value === 'string') {
        const trimmed = value.trim();
        if (trimmed === '') {
            return null;
        }

        if (acknowledgementMessages.has(trimmed.toLowerCase())) {
            return null;
        }

        return trimmed;
    }

    if (value && typeof value === 'object') {
        const nestedCandidates = ['json', 'data', 'response', 'result', 'body', 'value'];
        for (const nestedKey of nestedCandidates) {
            if (Object.prototype.hasOwnProperty.call(value, nestedKey)) {
                const nestedValue = value[nestedKey];
                if (!nestedValue || nestedValue === value) {
                    continue;
                }

                const nestedResult = pickReplyString(nestedValue);
                if (nestedResult) {
                    return nestedResult;
                }
            }
        }

        const candidateKeys = ['output', 'reply', 'text', 'content', 'message'];
        for (const key of candidateKeys) {
            const candidate = value[key];
            if (typeof candidate === 'string') {
                const trimmedCandidate = candidate.trim();
                if (trimmedCandidate !== '' && !acknowledgementMessages.has(trimmedCandidate.toLowerCase())) {
                    return trimmedCandidate;
                }
            }

            if (candidate && typeof candidate === 'object' && candidate !== value) {
                const nestedResult = pickReplyString(candidate);
                if (nestedResult) {
                    return nestedResult;
                }
            }
        }
    }

    if (Array.isArray(value)) {
        for (const item of value) {
            const extracted = pickReplyString(item);
            if (extracted) {
                return extracted;
            }
        }
    }

    return null;
};

const extractSupportReply = (data) => pickReplyString(data) ?? defaultSupportReply;

const appendBubble = (container, text, role = 'bot') => {
    const bubble = document.createElement('div');
    bubble.className = bubbleClasses[role] ?? bubbleClasses.bot;
    bubble.textContent = typeof text === 'string' ? text : JSON.stringify(text);
    container.appendChild(bubble);
    container.scrollTop = container.scrollHeight;
};

const initAgentSessions = () => {
    const modal = document.getElementById('agent-connect-modal');
    if (!modal) {
        return;
    }

    const titleEl = document.getElementById('agent-connect-title');
    const messageEl = document.getElementById('agent-connect-message');
    const qrContainer = document.getElementById('agent-connect-qr');
    const closeButton = document.getElementById('agent-connect-close');

    const closeModal = () => {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    };

    closeButton?.addEventListener('click', closeModal);
    modal.addEventListener('click', (event) => {
        if (event.target === modal) {
            closeModal();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !modal.classList.contains('hidden')) {
            closeModal();
        }
    });

    const updateSessionButtons = (container, state) => {
        if (!container) {
            return;
        }

        const connectBtn = container.querySelector('[data-agent-action="connect"]');
        const disconnectBtn = container.querySelector('[data-agent-action="disconnect"]');
        const reconnectBtn = container.querySelector('[data-agent-action="reconnect"]');

        if (state === 'connected') {
            connectBtn?.classList.add('hidden');
            disconnectBtn?.classList.remove('hidden');
            reconnectBtn?.classList.remove('hidden');
        } else {
            connectBtn?.classList.remove('hidden');
            disconnectBtn?.classList.add('hidden');
            reconnectBtn?.classList.add('hidden');
        }

        container.dataset.sessionState = state;
    };

    const renderSessionModal = ({ title, message, qr }) => {
        titleEl.textContent = title ?? 'Agent session';
        messageEl.innerHTML = '';

        if (message) {
            const messageParagraph = document.createElement('p');
            messageParagraph.textContent = message;
            messageEl.appendChild(messageParagraph);
        }

        qrContainer.innerHTML = '';
        if (qr?.base64) {
            const qrImage = document.createElement('img');
            qrImage.src = `data:${qr.contentType ?? 'image/png'};base64,${qr.base64}`;
            qrImage.alt = 'WhatsApp QR Code';
            qrImage.className = 'max-w-full';
            qrContainer.appendChild(qrImage);
        }

        modal.classList.remove('hidden');
        modal.classList.add('flex');
    };

    const workingLabels = {
        connect: 'Connecting...',
        disconnect: 'Disconnecting...',
        reconnect: 'Refreshing...',
    };

    document.querySelectorAll('.js-agent-session').forEach((button) => {
        if (!button.dataset.originalLabel) {
            button.dataset.originalLabel = button.textContent.trim();
        }
        button.addEventListener('click', async () => {
            const action = button.dataset.agentAction;
            const endpoint = button.dataset.endpoint;
            const container = button.closest('[data-agent-buttons]');
            const agentName = button.dataset.agentName ?? 'Agent';
            const userId = button.dataset.userId;
            const agentId = button.dataset.agentId;
            if (!action || !endpoint || !container || !userId || !agentId) {
                return;
            }

            const payload = action === 'disconnect'
                ? undefined
                : {
                    userId: /^\d+$/.test(userId) ? Number(userId) : userId,
                    agentId,
                    agentName,
                };

            const method = action === 'disconnect' ? 'DELETE' : 'POST';

            const relatedButtons = container.querySelectorAll('.js-agent-session');
            relatedButtons.forEach((btn) => {
                btn.disabled = true;
                btn.classList.add('opacity-60', 'pointer-events-none');
                if (!btn.dataset.originalLabel) {
                    btn.dataset.originalLabel = btn.textContent.trim();
                }
            });

            if (workingLabels[action]) {
                button.textContent = workingLabels[action];
            }

            try {
                const response = await requestJson(endpoint, { method, payload });
                const message = response?.message ?? 'Request completed.';
                renderSessionModal({
                    title: agentName,
                    message,
                    qr: response?.qr,
                });

                const nextState = action === 'disconnect' ? 'disconnected' : 'connected';
                updateSessionButtons(container, nextState);
            } catch (error) {
                renderSessionModal({
                    title: agentName,
                    message: error.message ?? 'Unable to process request.',
                });
            } finally {
                relatedButtons.forEach((btn) => {
                    btn.disabled = false;
                    btn.classList.remove('opacity-60', 'pointer-events-none');
                    if (btn.dataset.originalLabel) {
                        btn.textContent = btn.dataset.originalLabel;
                    }
                });
            }
        });
    });
};

const initAgentKnowledge = () => {
    const modal = document.getElementById('agent-knowledge-modal');
    if (!modal) {
        return;
    }

    const titleEl = document.getElementById('agent-knowledge-title');
    const descriptionEl = document.getElementById('agent-knowledge-description');
    const form = document.getElementById('agent-knowledge-form');
    const fileInput = document.getElementById('agent-knowledge-file');
    const errorEl = document.getElementById('agent-knowledge-error');
    const statusEl = document.getElementById('agent-knowledge-status');
    const submitBtn = document.getElementById('agent-knowledge-submit');
    const closeBtn = document.getElementById('agent-knowledge-close');
    const cancelBtn = document.getElementById('agent-knowledge-cancel');

    if (!titleEl || !descriptionEl || !form || !fileInput || !errorEl || !statusEl || !submitBtn) {
        console.warn('Agent knowledge modal is missing required elements.');
        return;
    }

    let endpoint = '';
    let agentName = '';

    const resetModal = () => {
        form.reset();
        errorEl.classList.add('hidden');
        errorEl.textContent = '';
        statusEl.textContent = '';
        statusEl.classList.remove('text-green-600', 'text-red-600');
    };

    const closeModal = () => {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        endpoint = '';
        agentName = '';
        resetModal();
    };

    const allowedExtensions = ['pdf', 'doc', 'docx', 'odt', 'ppt', 'pptx', 'odp'];
    const allowedMime = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.oasis.opendocument.text',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/vnd.oasis.opendocument.presentation',
    ];

    const validateFiles = (files) => {
        if (!files || files.length === 0) {
            errorEl.textContent = knowledgeMessages.chooseFile;
            errorEl.classList.remove('hidden');
            return false;
        }

        if (files.length > 20) {
            errorEl.textContent = knowledgeMessages.fileLimit;
            errorEl.classList.remove('hidden');
            return false;
        }

        for (const file of files) {
            const ext = file.name.split('.').pop()?.toLowerCase();

            if (!ext || !allowedExtensions.includes(ext)) {
                errorEl.textContent = knowledgeMessages.invalidFile;
                errorEl.classList.remove('hidden');
                return false;
            }

            if (file.type && !allowedMime.includes(file.type)) {
                errorEl.textContent = knowledgeMessages.invalidFile;
                errorEl.classList.remove('hidden');
                return false;
            }
        }

        errorEl.classList.add('hidden');
        errorEl.textContent = '';
        return true;
    };

    document.querySelectorAll('.js-agent-knowledge').forEach((button) => {
        button.addEventListener('click', () => {
            endpoint = button.dataset.endpoint ?? '';
            agentName = button.dataset.agentName ?? 'Agent';

            if (!endpoint) {
                return;
            }

            resetModal();
            titleEl.textContent = `${knowledgeMessages.modalTitle} - ${agentName}`;
            descriptionEl.textContent = `${knowledgeMessages.modalDescriptionPrefix} ${agentName}.`;

            modal.classList.remove('hidden');
            modal.classList.add('flex');
        });
    });

    const submitKnowledge = async (event) => {
        event.preventDefault();
        const files = Array.from(fileInput.files ?? []);

        if (!validateFiles(files) || !endpoint) {
            return;
        }

        const formData = new FormData();
        files.forEach((file) => {
            formData.append('files[]', file);
        });

        const originalLabel = submitBtn.textContent;
        submitBtn.disabled = true;
        submitBtn.textContent = knowledgeMessages.uploading;
        statusEl.textContent = '';
        statusEl.classList.remove('text-green-600', 'text-red-600');

        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken, Accept: 'application/json' },
                body: formData,
            });

            const data = await response.json().catch(() => ({}));

            if (!response.ok) {
                throw new Error(data?.message ?? 'Unable to upload file.');
            }

            statusEl.textContent = data?.message ?? knowledgeMessages.uploadSuccess;
            statusEl.classList.add('text-green-600');
        } catch (error) {
            statusEl.textContent = error.message ?? knowledgeMessages.uploadFailed;
            statusEl.classList.add('text-red-600');
        } finally {
            submitBtn.disabled = false;
            submitBtn.textContent = originalLabel;
        }
    };

    form.addEventListener('submit', submitKnowledge);
    closeBtn?.addEventListener('click', closeModal);
    cancelBtn?.addEventListener('click', closeModal);
};

const initSupportChat = () => {
    const container = document.getElementById('floating-chat');
    if (!container) {
        return;
    }

    const endpoint = container.getAttribute('data-endpoint');
    const toggleButton = document.getElementById('floating-chat-toggle');
    const panel = document.getElementById('floating-chat-panel');
    const closeButton = document.getElementById('floating-chat-close');
    const form = document.getElementById('floating-chat-form');
    const input = document.getElementById('floating-chat-input');
    const messages = document.getElementById('floating-chat-messages');

    if (!endpoint || !toggleButton || !panel || !form || !input || !messages) {
        return;
    }

    const togglePanel = (show) => {
        if (show) {
            panel.classList.remove('hidden');
        } else {
            panel.classList.add('hidden');
        }
    };

    toggleButton.addEventListener('click', () => {
        togglePanel(panel.classList.contains('hidden'));
    });

    closeButton?.addEventListener('click', () => togglePanel(false));

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const message = input.value.trim();
        if (!message) {
            return;
        }

        appendBubble(messages, message, 'user');
        input.value = '';

        try {
            const response = await postJson(endpoint, { message });
            const reply = extractSupportReply(response);
            appendBubble(messages, reply, 'bot');
        } catch (error) {
            appendBubble(messages, error.message ?? 'Unable to send message.', 'bot');
        }
    });
};

const initAgentChatPage = () => {
    const wrapper = document.querySelector('[data-agent-chat]');
    if (!wrapper) {
        return;
    }

    const endpoint = wrapper.getAttribute('data-send-endpoint');
    const agentName = wrapper.getAttribute('data-agent-name') ?? 'Agent';
    const form = document.getElementById('agent-chat-form');
    const messageInput = document.getElementById('agent-chat-message');
    const log = document.getElementById('agent-chat-log');
    const emptyState = document.getElementById('agent-chat-empty');
    const status = document.getElementById('agent-chat-status');

    if (!endpoint || !form || !messageInput || !log || !status) {
        return;
    }

    if (form.hasAttribute('data-disabled')) {
        return;
    }

    const removeEmptyState = () => {
        if (emptyState && emptyState.parentNode) {
            emptyState.remove();
        }
    };

    const append = (text, role) => {
        removeEmptyState();
        appendBubble(log, text, role);
    };

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const text = messageInput.value.trim();
        if (!text) {
            return;
        }

        append(text, 'user');
        messageInput.value = '';
        status.textContent = 'Sending...';

        try {
            const response = await postJson(endpoint, { message: text });
            const reply = response?.response ?? response?.message ?? response?.answer ?? response?.data ?? 'Received.';
            append(reply, 'bot');
            status.textContent = `${agentName} responded.`;
        } catch (error) {
            append(error.message ?? 'Agent failed to respond.', 'bot');
            status.textContent = 'Agent response failed.';
        }
    });
};

if (typeof window !== 'undefined') {
    document.addEventListener('DOMContentLoaded', () => {
        initAgentSessions();
        initAgentKnowledge();
        initSupportChat();
        initAgentChatPage();
    });
}

