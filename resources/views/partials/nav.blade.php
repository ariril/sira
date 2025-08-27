<nav x-data="{ open:false, prof:false }"
     class="bg-slate-800 sticky top-0 z-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6">
        <div class="flex items-center justify-between">
            {{-- Desktop menu --}}
            <ul class="hidden md:flex items-stretch text-slate-200">
                <li>
                    <a href="{{ route('home') }}"
                       class="flex items-center gap-2 px-6 py-4 border-b-2 border-transparent hover:bg-slate-700 hover:text-blue-400 {{ request()->routeIs('home')?'bg-slate-700 text-blue-400 border-blue-400':'' }}">
                        <i class="fa-solid fa-house"></i> Beranda
                    </a>
                </li>

                {{-- Dropdown Profil --}}
                <li class="relative"
                    x-data="{show:false}"
                    @mouseenter="show=true" @mouseleave="show=false">
                    <button class="flex items-center gap-2 px-6 py-4 hover:bg-slate-700 hover:text-blue-400 border-b-2 border-transparent">
                        <i class="fa-solid fa-circle-info"></i> Profil
                        <i class="fa-solid fa-chevron-down text-xs"></i>
                    </button>
                    <ul x-cloak x-transition.opacity x-show="show"
                        class="absolute left-0 top-full min-w-[220px] bg-slate-800 shadow-xl">
                        <li><a href="#" class="block px-5 py-3 hover:bg-slate-700">Tugas dan Fungsi</a></li>
                        <li><a href="#" class="block px-5 py-3 hover:bg-slate-700">Struktur Organisasi</a></li>
                        <li><a href="#" class="block px-5 py-3 hover:bg-slate-700">Visi Misi</a></li>
                    </ul>
                </li>

                <li>
                    <a href="{{ route('data') }}"
                       class="flex items-center gap-2 px-6 py-4 border-b-2 border-transparent hover:bg-slate-700 hover:text-blue-400 {{ request()->routeIs('data')?'bg-slate-700 text-blue-400 border-blue-400':'' }}">
                        <i class="fa-solid fa-database"></i> Data Remunerasi
                    </a>
                </li>
                <li><a href="#" class="flex items-center gap-2 px-6 py-4 border-b-2 border-transparent hover:bg-slate-700 hover:text-blue-400"><i class="fa-regular fa-newspaper"></i> Berita</a></li>
                <li><a href="#faq" class="flex items-center gap-2 px-6 py-4 border-b-2 border-transparent hover:bg-slate-700 hover:text-blue-400"><i class="fa-regular fa-circle-question"></i> FAQ</a></li>
                <li><a href="#" class="flex items-center gap-2 px-6 py-4 border-b-2 border-transparent hover:bg-slate-700 hover:text-blue-400"><i class="fa-solid fa-phone"></i> Kontak</a></li>
            </ul>

            {{-- Mobile toggle --}}
            <button @click="open=!open" class="md:hidden text-slate-200 p-3">
                <i :class="open ? 'fa-solid fa-xmark' : 'fa-solid fa-bars'"></i>
            </button>
        </div>

        {{-- Mobile menu --}}
        <ul x-cloak x-show="open" x-transition
            class="md:hidden flex flex-col text-slate-200 pb-3">
            <li><a href="{{ route('home') }}" class="px-4 py-3 hover:bg-slate-700">Beranda</a></li>
            <li x-data="{sub:false}">
                <button @click="sub=!sub" class="w-full text-left px-4 py-3 hover:bg-slate-700 flex items-center justify-between">
                    <span>Profil</span><i class="fa-solid fa-chevron-down text-xs" :class="sub?'rotate-180':''"></i>
                </button>
                <ul x-show="sub" x-transition class="bg-slate-700/60">
                    <li><a href="#" class="block px-6 py-3 hover:bg-slate-700">Tugas dan Fungsi</a></li>
                    <li><a href="#" class="block px-6 py-3 hover:bg-slate-700">Struktur Organisasi</a></li>
                    <li><a href="#" class="block px-6 py-3 hover:bg-slate-700">Visi Misi</a></li>
                </ul>
            </li>
            <li><a href="{{ route('data') }}" class="px-4 py-3 hover:bg-slate-700">Data Remunerasi</a></li>
            <li><a href="#" class="px-4 py-3 hover:bg-slate-700">Berita</a></li>
            <li><a href="#faq" class="px-4 py-3 hover:bg-slate-700">FAQ</a></li>
            <li><a href="#" class="px-4 py-3 hover:bg-slate-700">Kontak</a></li>
        </ul>
    </div>
</nav>
