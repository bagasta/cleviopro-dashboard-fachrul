<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class PasswordController extends Controller
{
    /**
     * Update the user's password.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validateWithBag('updatePassword', [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        $hash = Hash::make($validated['password'], ['rounds' => 12]);

        if (str_starts_with($hash, '$2y$')) {
            $hash = '$2b$'.substr($hash, 4);
        }

        DB::table('users')
            ->where('id', $request->user()->id)
            ->update(['password' => $hash]);

        $request->user()->refresh();

        return back()->with('status', 'password-updated');
    }
}
