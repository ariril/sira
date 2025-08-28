{{-- resources/views/partials/login-modal.blade.php --}}
<div
    x-cloak
    x-data
    x-show="$store.authModal.open"
    x-transition.opacity
    @keydown.escape.window="$store.authModal.hide()"
    class="fixed inset-0 z-[100] flex items-start justify-center"
    aria-modal="true" role="dialog">

    {{-- backdrop --}}
    <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="$store.authModal.hide()"></div>

    {{-- panel --}}
    <div
        x-transition.scale.origin.center
        class="relative mx-4 my-10 w-full max-w-lg rounded-xl bg-white shadow-2xl ring-1 ring-black/5">

        {{-- header --}}
        <div class="flex items-center justify-between px-6 py-4 border-b">
            <h3 class="text-lg font-semibold">Login Remunerasi</h3>
            <button class="p-2 rounded hover:bg-slate-100" @click="$store.authModal.hide()" aria-label="Tutup">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        {{-- body / form --}}
        <form method="POST" action="{{ route('login') }}" class="px-6 py-5 space-y-4">
            @csrf

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Username</label>
                <input name="username" type="text" required autofocus
                       class="w-full rounded-lg border-2 border-slate-200 px-3 py-2 outline-none focus:border-blue-500">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Password</label>
                <input name="password" type="password" required
                       class="w-full rounded-lg border-2 border-slate-200 px-3 py-2 outline-none focus:border-blue-500">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Login sebagai</label>
                <select name="role"
                        class="w-full rounded-lg border-2 border-slate-200 px-3 py-2 outline-none focus:border-blue-500">
                    <option value="pegawai" selected>Pegawai</option>
                    <option value="dosen">Dosen</option>
                    <option value="admin">Admin</option>
                </select>
            </div>

            {{-- reCAPTCHA placeholder (ganti dengan widget kamu) --}}
            <div class="rounded-lg border-2 border-slate-200 p-4">
                <div class="h-16 grid place-content-center text-slate-400 text-sm">
                    reCAPTCHA here
                </div>
            </div>

            <div class="flex items-center justify-between">
                <button type="submit"
                        class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg text-white bg-blue-600 hover:bg-blue-700">
                    <i class="fa-solid fa-right-to-bracket"></i> Login
                </button>

                <a href="#" class="text-sm text-blue-600 hover:underline">Forgot Password?</a>
            </div>

            <div class="relative text-center py-2">
                <span class="px-3 text-xs uppercase tracking-wider text-slate-400 bg-white relative z-10">Login dengan</span>
                <span class="absolute left-6 right-6 top-1/2 -translate-y-1/2 h-px bg-slate-200"></span>
            </div>

            <a href="{{ route('sso.redirect') }}"
               class="w-full inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-lg border hover:bg-slate-50">
                <i class="fa-regular fa-circle-user text-lg"></i> SSO Single Sign On
            </a>
        </form>

    </div>
</div>
