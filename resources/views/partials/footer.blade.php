<footer class="bg-slate-800 text-slate-200 mt-16">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-12">
        <div class="grid gap-8 sm:grid-cols-2 lg:grid-cols-4">
            <section>
                <h3 class="text-white font-semibold mb-3">{{ $site->nama_singkat ?? 'Unit Remuneration' }}</h3>
                <p class="text-slate-300">
                    {{ $site->nama ?? 'RSUD ATAMBUA' }}
                </p>
                <div class="mt-3 space-y-2 text-slate-300">
                    @if($site?->alamat)
                        <p class="flex items-center gap-2"><i class="fa-solid fa-location-dot"></i> {{ $site->alamat }}</p>
                    @endif
                    @if($site?->telepon)
                        <p class="flex items-center gap-2"><i class="fa-solid fa-phone"></i> {{ $site->telepon }}</p>
                    @endif
                    @if($site?->email)
                        <p class="flex items-center gap-2"><i class="fa-solid fa-envelope"></i> {{ $site->email }}</p>
                    @endif
                </div>
            </section>

            <section>
                <h3 class="text-white font-semibold mb-3">Menu Utama</h3>
                <ul class="space-y-2">
                    <li><a href="{{ route('home') }}" class="hover:text-blue-400">Beranda</a></li>
                    <li>
                        @if($profilPages->firstWhere('tipe','profil_rs'))
                            <a href="{{ route('profil.show','profil_rs') }}" class="hover:text-blue-400">Profil</a>
                        @else
                            <span class="text-slate-500">Profil</span>
                        @endif
                    </li>
                    <li><a href="{{ route('data') }}" class="hover:text-blue-400">Data Remunerasi</a></li>
                    <li><a href="#" class="hover:text-blue-400">Berita</a></li>
                    <li><a href="{{ route('pertanyaan_umum.index') }}" class="hover:text-blue-400">FAQ</a></li>
                </ul>
            </section>

            <section>
                <h3 class="text-white font-semibold mb-3">Layanan</h3>
                <ul class="space-y-2">
                    <li><a href="#" class="hover:text-blue-400">Logbook Online</a></li>
                    <li><a href="#" class="hover:text-blue-400">SKP Digital</a></li>
                    <li><a href="#" class="hover:text-blue-400">Laporan Kinerja</a></li>
                    <li><a href="{{ route('profil.show','tugas_fungsi') }}" class="hover:text-blue-400">Panduan Teknis</a></li>
                </ul>
            </section>

            <section>
                <h3 class="text-white font-semibold mb-3">Ikuti Kami</h3>
                <div class="flex items-center gap-4 text-[1.4rem]">
                    <a href="{{ $site->url_facebook ?? '#' }}" class="hover:text-blue-400"><i class="fa-brands fa-facebook"></i></a>
                    <a href="{{ $site->url_twitter ?? '#' }}" class="hover:text-blue-400"><i class="fa-brands fa-x-twitter"></i></a>
                    <a href="{{ $site->url_instagram ?? '#' }}" class="hover:text-blue-400"><i class="fa-brands fa-instagram"></i></a>
                    <a href="{{ $site->url_youtube ?? '#' }}" class="hover:text-blue-400"><i class="fa-brands fa-youtube"></i></a>
                </div>
            </section>
        </div>

        <div class="border-t border-slate-700 mt-10 pt-4 text-center text-slate-400 text-sm">
            {{ $site->teks_footer ?? '© '.date('Y').' Unit Remuneration - Universitas Sebelas Maret. All rights reserved.' }}
        </div>
    </div>
</footer>
