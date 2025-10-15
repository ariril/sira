<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
            'role' => [
                'required',
                Rule::in([
                    User::ROLE_PEGAWAI_MEDIS,
                    User::ROLE_KEPALA_UNIT,
                    User::ROLE_KEPALA_POLIKLINIK,
                    User::ROLE_ADMINISTRASI,
                    User::ROLE_SUPER_ADMIN,
                ]),
            ],
            // pakai tabel English sesuai dump: professions
            'profesi_id' => ['nullable', 'required_if:role,' . User::ROLE_PEGAWAI_MEDIS, 'exists:professions,id'],
        ];
    }

    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        if (!Auth::attempt($this->only('email', 'password'), $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());
            throw ValidationException::withMessages([
                'email' => __('Email or password is incorrect.'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());

        $user = $this->user();
        $chosenRole = (string)$this->input('role');

        if ($user->role !== $chosenRole) {
            Auth::logout();

            $actual = str_replace('_', ' ', $user->role);
            $chosen = str_replace('_', ' ', $chosenRole);

            throw ValidationException::withMessages([
                'role' => __('Your account is registered as ":actual", not ":chosen".', compact('actual', 'chosen')),
            ])->redirectTo(url()->previous());
        }

        if ($user->role === User::ROLE_PEGAWAI_MEDIS
            && $this->filled('profesi_id')
            && (int)$user->profession_id !== (int)$this->input('profesi_id')) {

            Auth::logout();
            throw ValidationException::withMessages([
                'profesi_id' => __('The selected profession does not match your account.'),
            ])->redirectTo(url()->previous());
        }
    }

    public function ensureIsNotRateLimited(): void
    {
        if (!RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));
        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    public function throttleKey(): string
    {
        $email = (string)$this->input('email');
        return Str::transliterate(Str::lower($email) . '|' . $this->ip());
    }
}
