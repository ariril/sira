<?php

namespace App\Http\Requests\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
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
            'email'       => ['required','string','email'],
            'password'    => ['required','string'],
            'remember'    => ['nullable','boolean'],
            'role'        => ['required','in:pegawai_medis,kepala_unit,administrasi,super_admin'],
            'profesi_id'  => ['nullable','required_if:role,pegawai_medis','exists:profesis,id'],
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

        if (! Auth::attempt($this->only('email','password'), $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());
            throw ValidationException::withMessages([
                'email' => __('Email atau password tidak sesuai.'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());

        // cek role/profesi sesuai pilihan user
        $user       = $this->user();
        $chosenRole = (string) $this->input('role'); // <-- ambil string murni

        if ($user->role !== $chosenRole) {
            Auth::logout();

            $actual = str_replace('_', ' ', $user->role);
            $chosen = str_replace('_', ' ', $chosenRole);

            throw ValidationException::withMessages([
                'role' => __('Akun Anda terdaftar sebagai ":actual", bukan ":chosen".', compact('actual','chosen')),
            ])->redirectTo(url()->previous());
        }

        // opsional: validasi kecocokan profesi untuk pegawai medis
        if ($user->role === 'pegawai_medis'
            && $this->filled('profesi_id')
            && (int) $user->profesi_id !== (int) $this->input('profesi_id')) {

            Auth::logout();
            throw ValidationException::withMessages([
                'profesi_id' => __('Profesi yang dipilih tidak sesuai dengan akun Anda.'),
            ])->redirectTo(url()->previous());
        }
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
            'email' => trans('auth.throttle', [
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
        // gunakan string biasa agar aman
        $email = (string) $this->input('email');
        return Str::transliterate(Str::lower($email).'|'.$this->ip());
    }
}
