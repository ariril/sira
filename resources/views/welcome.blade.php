@extends('layouts.public')
@section('title','Unit Remunerasi - Universitas Sebelas Maret')

@section('content')
    <div class="max-w-7xl mx-auto px-4 sm:px-6">

        {{-- Hero --}}
        <section class="grid md:grid-cols-2 gap-12 items-center py-16">
            <div>
                <h2 class="text-4xl md:text-5xl font-semibold text-slate-800 mb-4">
                    Selamat Datang di Portal Remunerasi UNS
                </h2>
                <p class="text-slate-500 mb-8">
                    Sistem informasi pengelolaan remunerasi dan kinerja pegawai Universitas Sebelas Maret
                </p>
                <div class="flex flex-wrap gap-3">
                    <a href="#announcements" class="btn-primary">
                        Lihat Pengumuman
                    </a>
                    <a href="{{ route('data') }}" class="btn-outline">
                        Akses Data
                    </a>
                </div>
            </div>
            <div>
                <img class="w-full h-auto rounded-xl shadow-2xl"
                     src="{{ Storage::url('images/hero.jpeg') }}"
                     alt="RSUD MGR GABRIEL MANEK">
            </div>
        </section>

        {{-- Quick Stats --}}
        <section class="mb-16">
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                @php
                    $stats = [
                      ['icon'=>'fa-users','value'=>'2,847','label'=>'Total Pegawai'],
                      ['icon'=>'fa-chart-line','value'=>'94.2%','label'=>'Capaian Kinerja'],
                      ['icon'=>'fa-calendar-check','value'=>'Juli 2025','label'=>'Periode Aktif'],
                      ['icon'=>'fa-file-lines','value'=>'1,423','label'=>'Logbook Terisi'],
                    ];
                @endphp
                @foreach($stats as $s)
                    <div class="bg-white rounded-xl shadow-md p-6 flex items-center gap-4 hover:-translate-y-1 transition">
                        <div class="w-14 h-14 rounded-xl grid place-content-center text-white text-xl
                      bg-gradient-to-tr from-blue-500 to-indigo-600">
                            <i class="fa-solid {{ $s['icon'] }}"></i>
                        </div>
                        <div>
                            <div class="text-2xl font-semibold text-slate-800">{{ $s['value'] }}</div>
                            <p class="text-slate-500 m-0">{{ $s['label'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- Pengumuman --}}
        <section id="announcements" class="mb-16">
            <h2 class="text-center text-3xl font-semibold text-slate-800 mb-10">Pengumuman Terbaru</h2>

            <div class="grid gap-8 md:grid-cols-2 xl:grid-cols-3">
                {{-- Card 1 (featured) --}}
                <article class="bg-white rounded-xl p-6 shadow-md border-l-4 border-red-600 hover:-translate-y-1 transition">
                    <div class="flex items-center justify-between mb-3">
                        <span class="px-3 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-600 uppercase">Penting</span>
                        <time class="text-slate-500">01 Juli 2025</time>
                    </div>
                    <h3 class="text-xl font-semibold text-slate-800 mb-3">Jadwal Pengisian Logbook Bulan Juli 2025</h3>
                    <p class="text-slate-500">Berikut adalah jadwal pengisian logbook kerja harian untuk tenaga kependidikan UNS periode Juli 2025. Harap perhatikan batas waktu pengisian...</p>
                    <div class="flex items-center justify-between mt-5">
                        <span class="px-3 py-1 rounded-full text-xs bg-slate-100 text-slate-500">Remunerasi</span>
                        <a href="#" class="text-blue-600 font-medium inline-flex items-center gap-1">Baca selengkapnya <i class="fa-solid fa-arrow-right text-sm"></i></a>
                    </div>
                </article>

                {{-- Card 2 --}}
                <article class="bg-white rounded-xl p-6 shadow-md hover:-translate-y-1 transition">
                    <div class="flex items-center justify-between mb-3">
                        <span class="px-3 py-1 rounded-full text-xs font-semibold bg-blue-100 text-blue-600 uppercase">Info</span>
                        <time class="text-slate-500">28 Juni 2025</time>
                    </div>
                    <h3 class="text-xl font-semibold text-slate-800 mb-3">Pemutakhiran Data SKP Semester Genap 2025</h3>
                    <p class="text-slate-500">Pemutakhiran data Sasaran Kerja Pegawai (SKP) untuk semester genap tahun 2025 akan dilaksanakan mulai...</p>
                    <div class="flex items-center justify-between mt-5">
                        <span class="px-3 py-1 rounded-full text-xs bg-slate-100 text-slate-500">Kinerja</span>
                        <a href="#" class="text-blue-600 font-medium inline-flex items-center gap-1">Baca selengkapnya <i class="fa-solid fa-arrow-right text-sm"></i></a>
                    </div>
                </article>

                {{-- Card 3 --}}
                <article class="bg-white rounded-xl p-6 shadow-md hover:-translate-y-1 transition">
                    <div class="flex items-center justify-between mb-3">
                        <span class="px-3 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-600 uppercase">Update</span>
                        <time class="text-slate-500">25 Juni 2025</time>
                    </div>
                    <h3 class="text-xl font-semibold text-slate-800 mb-3">Panduan Teknis Aplikasi Remunerasi Versi 3.2</h3>
                    <p class="text-slate-500">Telah tersedia panduan teknis terbaru untuk penggunaan aplikasi remunerasi versi 3.2 dengan fitur-fitur baru...</p>
                    <div class="flex items-center justify-between mt-5">
                        <span class="px-3 py-1 rounded-full text-xs bg-slate-100 text-slate-500">Panduan</span>
                        <a href="#" class="text-blue-600 font-medium inline-flex items-center gap-1">Baca selengkapnya <i class="fa-solid fa-arrow-right text-sm"></i></a>
                    </div>
                </article>
            </div>

            <div class="text-center mt-8">
                <a href="#" class="btn-outline">Lihat Semua Pengumuman</a>
            </div>
        </section>

        {{-- FAQ --}}
        <section id="faq" class="mb-16">
            <h2 class="text-center text-3xl font-semibold text-slate-800">Frequently Asked Questions</h2>
            <p class="text-center text-slate-500 mb-8">Pertanyaan terkait UPSDM dan Remunerasi</p>

            <div class="max-w-3xl mx-auto">
                @php
                    $faqs = [
                      ['q'=>'Mengapa realisasi SKP menjadi lebih rendah daripada target SKP?','a'=>'Realisasi SKP dapat lebih rendah dari target karena beberapa faktor seperti kendala teknis, perubahan prioritas kerja, atau kondisi eksternal.'],
                      ['q'=>'Bagaimana caranya untuk membuka isian logbook yang sudah terkunci?','a'=>'Silakan hubungi admin unit kerja Anda atau Unit Remunerasi melalui email/telepon pada halaman kontak.'],
                      ['q'=>'Bagaimana cara mencetak SKP?','a'=>'Masuk ke sistem, pilih menu SKP, kemudian klik tombol Cetak pada halaman detail SKP Anda.'],
                      ['q'=>'Bagaimana cara mengupload/menambah responden pada bagian pengguna layanan?','a'=>'Masuk ke Pengguna Layanan → Tambah Responden, lengkapi form dan upload file pendukung.'],
                    ];
                @endphp

                <div class="space-y-4">
                    @foreach($faqs as $i => $f)
                        <div x-data="{open:false}" class="bg-white rounded-lg shadow">
                            <button @click="open=!open"
                                    class="w-full px-6 py-4 text-left font-medium flex items-center justify-between hover:bg-slate-50">
                                <span>{{ $f['q'] }}</span>
                                <i class="fa-solid fa-chevron-down text-sm transition" :class="open?'rotate-180':''"></i>
                            </button>
                            <div x-show="open" x-collapse x-cloak class="px-6 pb-5 text-slate-600">
                                {{ $f['a'] }}
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="text-center mt-8">
                    <a href="#" class="btn-outline">Lihat FAQ Lainnya</a>
                </div>
            </div>
        </section>

        {{-- Akses Cepat --}}
        <section class="mb-20">
            <h2 class="text-center text-3xl font-semibold text-slate-800 mb-10">Akses Cepat</h2>
            <div class="grid gap-8 sm:grid-cols-2 lg:grid-cols-4">
                @php
                    $links = [
                      ['icon'=>'fa-table','title'=>'Data Remunerasi','desc'=>'Lihat data remunerasi pegawai','href'=>route('data')],
                      ['icon'=>'fa-clipboard-list','title'=>'Logbook Harian','desc'=>'Isi logbook kerja harian','href'=>'#'],
                      ['icon'=>'fa-chart-bar','title'=>'Laporan SKP','desc'=>'Lihat laporan kinerja SKP','href'=>'#'],
                      ['icon'=>'fa-download','title'=>'Unduh Formulir','desc'=>'Download formulir terbaru','href'=>'#'],
                    ];
                @endphp
                @foreach($links as $l)
                    <a href="{{ $l['href'] }}" class="bg-white rounded-xl shadow p-8 text-center hover:-translate-y-1 transition">
                        <div class="w-20 h-20 mx-auto mb-4 rounded-full grid place-content-center text-3xl text-white
                      bg-gradient-to-tr from-amber-500 to-amber-600">
                            <i class="fa-solid {{ $l['icon'] }}"></i>
                        </div>
                        <h3 class="text-xl font-semibold text-slate-800">{{ $l['title'] }}</h3>
                        <p class="text-slate-500 m-0">{{ $l['desc'] }}</p>
                    </a>
                @endforeach
            </div>
        </section>
    </div>
@endsection
@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // cek query ?modal=1
            const openFromQuery = new URLSearchParams(window.location.search).get('modal') === '1';
            // cek kalau ada error login atau status session
            const hasServerFlag = @json($errors->any() || session('status'));
            if (openFromQuery || hasServerFlag) {
                if (window.Alpine?.store) Alpine.store('authModal').show();
            }
        });
    </script>
@endpush
