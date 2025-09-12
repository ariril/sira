<header class="bg-gradient-to-tr from-indigo-900 to-blue-500 text-white shadow">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-4">
        <div class="flex items-center justify-between flex-wrap gap-4">
            <div class="flex items-center gap-4">
                <img src="{{ $site?->path_logo ? Storage::url($site->path_logo) : Storage::url('images/logo-rsudmgr.jpeg') }}"
                     alt="{{ $site->nama ?? 'Logo' }}"
                     class="w-[60px] h-[60px] rounded-lg md:w-[60px] md:h-[60px]">
                <div>
                    <h1 class="text-[1.8rem] font-semibold leading-tight">
                        {{ $site->nama_singkat ?? 'Unit Remunerasi' }}
                    </h1>
                    <p class="text-white/90 text-sm m-0">
                        {{ $site->nama ?? 'Universitas Sebelas Maret' }}
                    </p>
                </div>
            </div>

            <a href="#login" @click.prevent="$store.authModal.show()"
               class="inline-flex items-center gap-2 rounded-lg border border-white/20 bg-white/10 px-4 py-2 backdrop-blur transition hover:bg-white/20">
                <i class="fa-solid fa-right-to-bracket"></i>
                <span>Login</span>
            </a>
        </div>
    </div>
</header>
