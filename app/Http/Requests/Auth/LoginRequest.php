<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nama' => ['required', 'string', 'max:50'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $credentials = [
            'nama' => (string) $this->string('nama')->trim(),
            'password' => $this->input('password'),
        ];

        $user = $this->attemptAuthentication($credentials);

        if (! $user) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'nama' => trans('auth.failed'),
            ]);
        }

        Auth::login($user, $this->boolean('remember'));

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Attempt to locate and authenticate the given credentials.
     */
    protected function attemptAuthentication(array $credentials): ?User
    {
        $user = User::where('nama', $credentials['nama'])->first();

        if (! $user) {
            return null;
        }

        $hashedPassword = (string) $user->getAuthPassword();
        $normalizedHash = $this->normalizeBcryptHash($hashedPassword);

        if ($normalizedHash !== $hashedPassword) {
            DB::table($user->getTable())
                ->where($user->getKeyName(), $user->getKey())
                ->update(['password' => $normalizedHash]);
        }

        try {
            if (! Hash::check($credentials['password'], $normalizedHash)) {
                return null;
            }
        } catch (\RuntimeException) {
            return null;
        }

        return $user;
    }

    /**
     * Normalize legacy bcrypt hashes that were stored with an unsupported prefix.
     */
    protected function normalizeBcryptHash(string $hash): string
    {
        if (str_starts_with($hash, '$2b$')) {
            return '$2y$'.substr($hash, 4);
        }

        return $hash;
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'nama' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower((string) $this->string('nama')->trim()).'|'.$this->ip());
    }
}
