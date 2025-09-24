@php
    // daftar profesi (kalau belum disupply via View composer)
    $profesis = $profesis ?? \App\Models\Profession::select('id','name as nama')->orderBy('nama')->get();
    $oldRole  = old('role', ''); // default kosong -> memaksa user memilih role
@endphp

<div
    x-cloak
    x-data="{ role: '{{ $oldRole }}', showPass: false }"
    x-show="$store.authModal.open"
    x-transition.opacity
    @keydown.escape.window="$store.authModal.hide()"
    class="fixed inset-0 z-[100] flex items-start justify-center p-4"
    aria-modal="true" role="dialog">

    {{-- backdrop --}}
    <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="$store.authModal.hide()"></div>

    {{-- panel --}}
    <div
        x-transition.scale.origin.center
        class="relative w-full max-w-xl rounded-2xl bg-white shadow-2xl ring-1 ring-black/10 overflow-hidden">

        {{-- header --}}
        <div class="bg-gradient-to-r from-blue-600 to-indigo-600 px-6 py-5">
            <h2 class="text-2xl font-semibold text-white">Login</h2>
            <p class="text-white/80 text-sm">Masuk untuk mengakses dashboard sesuai peran.</p>
        </div>

        {{-- body --}}
        <div class="p-6 space-y-5">
            @if ($errors->any())
                <div class="rounded-lg bg-red-50 text-red-700 text-sm px-3 py-2">{{ $errors->first() }}</div>
            @endif
            @if (session('status'))
                <div class="rounded-lg bg-green-50 text-green-700 text-sm px-3 py-2">{{ session('status') }}</div>
            @endif

            <form method="POST" action="{{ route('login') }}" class="space-y-5">
                @csrf

                {{-- Email --}}
                <div>
                    <label for="email" class="block text-[15px] font-medium text-slate-700">Email</label>
                    <input id="email" name="email" type="email" required autofocus autocomplete="username"
                           value="{{ old('email') }}"
                           class="mt-1 w-full h-12 px-4 rounded-xl border-slate-300 text-[15px] shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    @error('email')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>

                {{-- Password + eye toggle --}}
                <div>
                    <label for="password" class="block text-[15px] font-medium text-slate-700">Password</label>
                    <div class="relative">
                        <input :type="showPass ? 'text' : 'password'"
                               id="password" name="password" required autocomplete="current-password"
                               class="mt-1 w-full h-12 px-4 pr-12 rounded-xl border-slate-300 text-[15px] shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <button type="button" @click="showPass = !showPass"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-500 hover:text-slate-700"
                                aria-label="Toggle password visibility">
                            <svg x-show="!showPass" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none"
                                 viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                      d="M2.036 12.322a1.012 1.012 0 010-.644C3.423 7.51 7.36 5 12 5c4.64 0 8.577 2.51 9.964 6.678.07.21.07.434 0 .644C20.577 16.49 16.64 19 12 19c-4.64 0-8.577-2.51-9.964-6.678z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                      d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            <svg x-show="showPass" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none"
                                 viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                      d="M3.98 8.223A10.477 10.477 0 001.934 12C3.28 15.978 7.285 19 12 19c1.51 0 2.944-.29 4.243-.82M9.88 9.88a3 3 0 104.24 4.24M6.1 6.1l11.8 11.8"/>
                            </svg>
                        </button>
                    </div>
                    @error('password')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>

                {{-- Role --}}
                <div>
                    <label for="role" class="block text-[15px] font-medium text-slate-700">Login sebagai</label>
                    <select id="role" name="role" x-model="role" required
                            class="mt-1 w-full h-12 px-4 rounded-xl border-slate-300 text-[15px] shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="" disabled {{ $oldRole==='' ? 'selected' : '' }}>— Pilih Role —</option>
                        <option value="pegawai_medis">Pegawai Medis</option>
                        <option value="kepala_unit">Kepala Unit</option>
                        <option value="kepala_poliklinik">Kepala Poliklinik</option>
                        <option value="administrasi">Staf Administration</option>
                        <option value="super_admin">Super Admin</option>
                    </select>
                    @error('role')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>

                {{-- Profession (muncul jika pegawai medis) --}}
                <div x-show="role === 'pegawai_medis'">
                    <label for="profesi_id" class="block text-[15px] font-medium text-slate-700">Profesi</label>
                    <select id="profesi_id" name="profesi_id"
                            class="mt-1 w-full h-12 px-4 rounded-xl border-slate-300 text-[15px] shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">— Pilih Profesi —</option>
                        @foreach($profesis as $p)
                            <option value="{{ $p->id }}" @selected(old('profesi_id')==$p->id)>{{ $p->nama }}</option>
                        @endforeach
                    </select>
                    @error('profesi_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>

                {{-- Remember & Forgot --}}
                <div class="flex items-center justify-between">
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="remember"
                               class="h-4 w-4 rounded border-slate-300 text-blue-600" @checked(old('remember'))>
                        <span class="text-sm text-slate-600">Remember me</span>
                    </label>
                    <a href="{{ route('password.request') }}" class="text-sm text-blue-600 hover:underline">Forgot?</a>
                </div>

                {{-- Submit --}}
                <button type="submit"
                        class="inline-flex items-center justify-center w-full h-12 gap-2 px-6 rounded-xl text-base font-medium text-white bg-gradient-to-tr from-blue-500 to-indigo-600 shadow hover:-translate-y-0.5 transition">
                    Login
                </button>
            </form>
        </div>
    </div>
</div>

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const hasServerFlag = @json($errors->any() || session('status'));
            if (hasServerFlag && window.Alpine?.store) Alpine.store('authModal').show();
        });
    </script>
@endpush
