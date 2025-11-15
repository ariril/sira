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
            // form uses profession_id; keep legacy 'profesi_id' support via prepareForValidation
            'profession_id' => ['nullable', 'required_if:role,' . User::ROLE_PEGAWAI_MEDIS, 'exists:professions,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'profession_id.required_if' => 'Silakan pilih profesi Anda saat login sebagai Pegawai Medis.',
            'profession_id.exists'      => 'Profesi yang dipilih tidak ditemukan. Pilih dari daftar yang tersedia.',
            'email.required'            => 'Masukkan email Anda.',
            'email.email'               => 'Format email tidak valid.',
            'password.required'         => 'Masukkan kata sandi Anda.',
            'role.required'             => 'Pilih peran Anda untuk login.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Backward compatibility: map legacy values/keys to new ones
        $role = (string) $this->input('role');
        if ($role === 'administrasi') {
            $this->merge(['role' => User::ROLE_ADMINISTRASI]); // 'admin_rs'
        }

        if ($this->has('profesi_id') && !$this->has('profession_id')) {
            $this->merge(['profession_id' => $this->input('profesi_id')]);
        }
    }

    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        if (!Auth::attempt($this->only('email', 'password'), $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());
            throw ValidationException::withMessages([
                // Bahasa Indonesia yang lebih umum
                'email' => 'Email atau kata sandi salah.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());

        $user = $this->user();
        $chosenRole = (string)$this->input('role');

        if ($user->role !== $chosenRole) {
            Auth::logout();

            // Gunakan bahasa manusia (judul-kapital) tanpa tanda petik atau titik dua
            $actualLabel = Str::headline($user->role);
            $chosenLabel = Str::headline($chosenRole);

            throw ValidationException::withMessages([
                'role' => 'Akun Anda terdaftar sebagai ' . $actualLabel . ', bukan ' . $chosenLabel . '.',
            ])->redirectTo(url()->previous());
        }

        if ($user->role === User::ROLE_PEGAWAI_MEDIS
            && $this->filled('profession_id')
            && (int)$user->profession_id !== (int)$this->input('profession_id')) {

            Auth::logout();
            throw ValidationException::withMessages([
                'profesi_id' => 'Profesi yang dipilih tidak sesuai dengan akun Anda.',
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
