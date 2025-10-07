<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class SupportChatController extends Controller
{
    private const CHAT_ENDPOINT = 'https://n8n-new.chiefaiofficer.id/webhook/chatLangchain';

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $user = $request->user();

        $payload = [
            'contactName' => $user->nama,
            'from' => $user->phone_number,
            'message' => $validated['message'],
        ];

        $response = Http::timeout(120)->post(self::CHAT_ENDPOINT, $payload);

        if ($response->failed()) {
            return response()->json([
                'message' => 'Unable to send message at this time.',
                'details' => $response->json(),
            ], $response->status() ?: 500);
        }

        return response()->json($response->json());
    }
}
