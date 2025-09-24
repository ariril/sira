<nav x-data="{ open:false }" class="bg-slate-800 sticky top-0 z-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6">
        <div class="flex items-center justify-between">
            <ul class="hidden md:flex items-stretch text-slate-200">
                <li>
                    <a href="{{ route('home') }}"
                       class="flex items-center gap-2 px-6 py-4 border-b-2 border-transparent hover:bg-slate-700 hover:text-blue-400 {{ request()->routeIs('home')?'bg-slate-700 text-blue-400 border-blue-400':'' }}">
                        <i class="fa-solid fa-house"></i> Beranda
                    </a>
                </li>

                {{-- Dropdown Profil (dinamis) --}}
                <li class="relative" x-data="{show:false}" @mouseenter="show=true" @mouseleave="show=false">
                    <button class="flex items-center gap-2 px-6 py-4 hover:bg-slate-700 hover:text-blue-400 border-b-2 border-transparent">
                        <i class="fa-solid fa-circle-info"></i> Profil
                        <i class="fa-solid fa-chevron-down text-xs"></i>
                    </button>
                    <ul x-cloak x-transition.opacity x-show="show"
                        class="absolute left-0 top-full min-w-[220px] bg-slate-800 shadow-xl">
                        @forelse($profilPages as $p)
                            @php $type = $p['type'] ?? $p['tipe'] ?? null; @endphp
                            <li>
                                <a href="{{ $type ? route('about_pages.show', $type) : '#' }}" class="block px-5 py-3 hover:bg-slate-700">
                                    {{ $p['label'] }}
                                </a>
                            </li>
                        @empty
                            <li><span class="block px-5 py-3 text-slate-400">Belum ada konten</span></li>
                        @endforelse
                    </ul>
                </li>

                <li>
                    <a href="{{ route('remuneration.data') }}"
                       class="flex items-center gap-2 px-6 py-4 border-b-2 border-transparent hover:bg-slate-700 hover:text-blue-400 {{ request()->routeIs('remuneration.data')?'bg-slate-700 text-blue-400 border-blue-400':'' }}">
                        <i class="fa-solid fa-database"></i> Data Remunerasi
                    </a>
                </li>

                <li>
                    <a href="{{ route('announcements.index') }}"
                       class="flex items-center gap-2 px-6 py-4 border-b-2 border-transparent hover:bg-slate-700 hover:text-blue-400">
                        <i class="fa-regular fa-newspaper"></i> Berita
                    </a>
                </li>
                <li>
                    <a href="{{ route('faqs.index') }}"
                       class="flex items-center gap-2 px-6 py-4 border-b-2 border-transparent hover:bg-slate-700 hover:text-blue-400">
                        <i class="fa-regular fa-circle-question"></i> FAQ
                    </a>
                </li>
                <li>
                    <a href="{{ route('about_pages.show','profil_rs') }}"
                       class="flex items-center gap-2 px-6 py-4 border-b-2 border-transparent hover:bg-slate-700 hover:text-blue-400">
                        <i class="fa-solid fa-phone"></i> Kontak
                    </a>
                </li>
            </ul>

            <button @click="open=!open" class="md:hidden text-slate-200 p-3">
                <i :class="open ? 'fa-solid fa-xmark' : 'fa-solid fa-bars'"></i>
            </button>
        </div>

        {{-- Mobile --}}
        <ul x-cloak x-show="open" x-transition class="md:hidden flex flex-col text-slate-200 pb-3">
            <li><a href="{{ route('home') }}" class="px-4 py-3 hover:bg-slate-700">Beranda</a></li>
            <li x-data="{sub:false}">
                <button @click="sub=!sub" class="w-full text-left px-4 py-3 hover:bg-slate-700 flex items-center justify-between">
                    <span>Profil</span><i class="fa-solid fa-chevron-down text-xs" :class="sub?'rotate-180':''"></i>
                </button>
                <ul x-show="sub" x-transition class="bg-slate-700/60">
                    @forelse($profilPages as $p)
                        @php $type = $p['type'] ?? $p['tipe'] ?? null; @endphp
                        <li><a href="{{ $type ? route('about_pages.show', $type) : '#' }}" class="block px-6 py-3 hover:bg-slate-700">{{ $p['label'] }}</a></li>
                    @empty
                        <li><span class="block px-6 py-3 text-slate-400">Belum ada konten</span></li>
                    @endforelse
                </ul>
            </li>
            <li><a href="{{ route('remuneration.data') }}" class="px-4 py-3 hover:bg-slate-700">Data Remunerasi</a></li>
            <li><a href="{{ route('announcements.index') }}" class="px-4 py-3 hover:bg-slate-700">Berita</a></li>
            <li><a href="{{ route('faqs.index') }}" class="px-4 py-3 hover:bg-slate-700">FAQ</a></li>
            <li><a href="{{ route('about_pages.show','profil_rs') }}" class="px-4 py-3 hover:bg-slate-700">Kontak</a></li>
        </ul>
    </div>
</nav>
