<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'nama' => ['required', 'string', 'min:3', 'max:50', 'unique:users,nama'],
            'phone_number' => ['required', 'string', 'regex:/^62\d{8,}$/', 'max:20', 'unique:users,phone_number'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $hash = Hash::make($validated['password'], ['rounds' => 12]);

        $username = trim($validated['nama']);
        $phoneNumber = trim($validated['phone_number']);

        $userId = DB::transaction(function () use ($username, $phoneNumber, $hash) {
            return DB::table('users')->insertGetId([
                'nama' => $username,
                'phone_number' => $phoneNumber,
                'password' => $hash,
            ]);
        });

        $user = User::findOrFail($userId);

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }
}
