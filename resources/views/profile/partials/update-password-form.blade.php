<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">
            {{ __('Perbarui Kata Sandi') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            {{ __('Pastikan akun Anda menggunakan kata sandi yang kuat dan acak untuk tetap aman.') }}
        </p>
    </header>

    <form method="post" action="{{ route('password.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('put')

        @if ($errors->updatePassword->any())
            <div class="rounded-xl border border-rose-200 bg-rose-50 text-rose-800 px-4 py-3">
                <div class="flex items-start gap-3">
                    <i class="fa-solid fa-circle-exclamation mt-0.5"></i>
                    <div>
                        <p class="font-semibold">{{ __('Gagal menyimpan') }}</p>
                        <p class="text-sm">{{ __('Form tidak valid. Silakan periksa kembali isian Anda:') }}</p>
                        <ul class="mt-2 text-sm list-disc ps-5 space-y-0.5">
                            @foreach ($errors->updatePassword->all() as $message)
                                <li>{{ $message }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        @endif

        <div>
            <label for="update_password_current_password" class="block text-xs font-medium text-slate-600 mb-1">{{ __('Kata Sandi Saat Ini') }}</label>
            <x-ui.input id="update_password_current_password" name="current_password" type="password" autocomplete="current-password" addonLeft="fa-lock" />
            
        </div>

        <div>
            <label for="update_password_password" class="block text-xs font-medium text-slate-600 mb-1">{{ __('Kata Sandi Baru') }}</label>
            <x-ui.input id="update_password_password" name="password" type="password" autocomplete="new-password" addonLeft="fa-key" />
            
        </div>

        <div>
            <label for="update_password_password_confirmation" class="block text-xs font-medium text-slate-600 mb-1">{{ __('Konfirmasi Kata Sandi') }}</label>
            <x-ui.input id="update_password_password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" addonLeft="fa-key" />
            
        </div>

        <div class="flex items-center gap-4">
            <x-ui.button type="submit" class="h-11 px-5">{{ __('Simpan') }}</x-ui.button>
        </div>
    </form>
</section>
