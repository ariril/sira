<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">
            {{ __('Informasi Profil') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            {{ __('Perbarui informasi profil dan alamat email akun Anda.') }}
        </p>
    </header>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('patch')

        @if (session('status') === 'profile-updated')
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 text-emerald-800 px-4 py-3">
                <div class="flex items-start gap-3">
                    <i class="fa-solid fa-circle-check mt-0.5"></i>
                    <div>
                        <p class="font-semibold">{{ __('Berhasil') }}</p>
                        <p class="text-sm">{{ __('Profil berhasil diperbarui.') }}</p>
                    </div>
                </div>
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-xl border border-rose-200 bg-rose-50 text-rose-800 px-4 py-3">
                <div class="flex items-start gap-3">
                    <i class="fa-solid fa-circle-exclamation mt-0.5"></i>
                    <div>
                        <p class="font-semibold">{{ __('Gagal menyimpan') }}</p>
                        <p class="text-sm">{{ __('Form tidak valid. Silakan periksa kembali isian Anda:') }}</p>
                        <ul class="mt-2 text-sm list-disc ps-5 space-y-0.5">
                            @foreach ($errors->all() as $message)
                                <li>{{ $message }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        @endif

        <div>
            <label for="name" class="block text-xs font-medium text-slate-600 mb-1">{{ __('Nama') }}</label>
            <x-ui.input id="name" name="name" type="text" :value="old('name', $user->name)" required autofocus autocomplete="name" addonLeft="fa-user" />
            
        </div>

        <div>
            <label for="email" class="block text-xs font-medium text-slate-600 mb-1">{{ __('Email') }}</label>
            <x-ui.input id="email" name="email" type="email" :value="old('email', $user->email)" required autocomplete="username" addonLeft="fa-envelope" />
            

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div>
                    <p class="text-sm mt-2 text-gray-800">
                        {{ __('Alamat email Anda belum terverifikasi.') }}

                        <button form="send-verification" class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            {{ __('Klik di sini untuk mengirim ulang email verifikasi.') }}
                        </button>
                    </p>

                    @if (session('status') === 'verification-link-sent')
                        <p class="mt-2 font-medium text-sm text-green-600">
                            {{ __('Tautan verifikasi baru telah dikirim ke alamat email Anda.') }}
                        </p>
                    @endif
                </div>
            @endif
        </div>

        <div class="flex items-center gap-4">
            <x-ui.button type="submit" class="h-11 px-5">{{ __('Simpan') }}</x-ui.button>
        </div>
    </form>
</section>
