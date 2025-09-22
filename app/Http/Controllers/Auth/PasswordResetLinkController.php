<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PasswordResetLinkController extends Controller
{
    private const WHATSAPP_RESET_URL = 'https://wa.me/6283890930647?text=Want%20to%20change%20password.';

    /**
     * Display the password reset link request view.
     */
    public function create(): RedirectResponse
    {
        return redirect()->away(self::WHATSAPP_RESET_URL);
    }

    /**
     * Handle an incoming password reset link request.
     */
    public function store(Request $request): RedirectResponse
    {
        return redirect()->away(self::WHATSAPP_RESET_URL);
    }
}
