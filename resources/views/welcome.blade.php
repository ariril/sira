@extends('layouts.public')
@section('title', ($site->nama_singkat ?? 'Unit Remuneration') . ' - Universitas Sebelas Maret')

@section('content')
    <div class="max-w-7xl mx-auto px-4 sm:px-6">

        {{-- Hero --}}
        <section class="grid md:grid-cols-2 gap-12 items-center py-16">
            <div>
                <h2 class="text-4xl md:text-5xl font-semibold text-slate-800 mb-4">
                    {{ ($site->nama_singkat ?? 'Unit Remuneration') }}
                </h2>
                <p class="text-slate-500 mb-8">
                    {{ $site->nama ? 'Sistem informasi pengelolaan remunerasi & kinerja - '.$site->nama : 'Sistem informasi pengelolaan remunerasi dan kinerja pegawai RSUD MGR GM ATAMBUA' }}
                </p>

                <div class="flex flex-wrap gap-3">
                    <a href="#announcements" class="btn-primary">
                        Lihat Pengumuman
                    </a>
                    <a href="{{ route('data') }}" class="btn-outline">
                        Akses Data
                    </a>
                </div>

                @isset($jadwalDokterBesok)
                    <p class="mt-6 text-sm text-slate-500">
                        Jadwal tenaga medis besok: <span class="font-semibold text-slate-700">{{ $jadwalDokterBesok }}</span> slot.
                    </p>
                @endisset
            </div>
            <div>
                <img class="w-full h-auto rounded-xl shadow-2xl"
                     src="{{ $site?->path_favicon ? Storage::url($site->path_favicon) : Storage::url('images/hero.jpeg') }}"
                     alt="{{ $site->nama ?? 'Portal Remuneration' }}">
            </div>
        </section>

        {{-- Quick Stats --}}
        <section class="mb-16">
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                @forelse($stats as $s)
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
                @empty
                    <div class="text-slate-500">Belum ada statistik.</div>
                @endforelse
            </div>
        </section>

        {{-- Announcement --}}
        <section id="announcements" class="mb-16">
            <h2 class="text-center text-3xl font-semibold text-slate-800 mb-10">Pengumuman Terbaru</h2>

            <div class="grid gap-8 md:grid-cols-2 xl:grid-cols-3">
                @forelse($announcements as $i => $a)
                    @php
                        $badge = match($a->label){
                            'penting' => ['bg' => 'bg-red-100', 'text' => 'text-red-600'],
                            'info'    => ['bg' => 'bg-blue-100', 'text' => 'text-blue-600'],
                            'update'  => ['bg' => 'bg-green-100','text' => 'text-green-600'],
                            default   => ['bg' => 'bg-slate-100','text' => 'text-slate-600'],
                        };
                        $isFeatured = $i === 0 || (bool)$a->disorot;
                    @endphp

                    <article class="bg-white rounded-xl p-6 shadow-md {{ $isFeatured ? 'border-l-4 border-red-600' : '' }} hover:-translate-y-1 transition">
                        <div class="flex items-center justify-between mb-3">
                        <span class="px-3 py-1 rounded-full text-xs font-semibold {{ $badge['bg'] }} {{ $badge['text'] }} uppercase">
                            {{ ucfirst($a->label ?? 'info') }}
                        </span>
                            <time class="text-slate-500">
                                {{ optional($a->dipublikasikan_pada)->translatedFormat('d F Y') }}
                            </time>
                        </div>
                        <h3 class="text-xl font-semibold text-slate-800 mb-3">
                            {{ $a->judul }}
                        </h3>
                        <p class="text-slate-500">
                            {{ $a->ringkasan ? \Illuminate\Support\Str::limit(strip_tags($a->ringkasan), 140) : \Illuminate\Support\Str::limit(strip_tags($a->konten), 140) }}
                        </p>
                        <div class="flex items-center justify-between mt-5">
                        <span class="px-3 py-1 rounded-full text-xs bg-slate-100 text-slate-500">
                            {{ ucfirst($a->kategori) }}
                        </span>
                            <a href="{{ route('pengumuman.show', $a->slug ?? $a->id) }}"
                               class="text-blue-600 font-medium inline-flex items-center gap-1">
                                Baca selengkapnya <i class="fa-solid fa-arrow-right text-sm"></i>
                            </a>
                        </div>
                    </article>
                @empty
                    <p class="text-slate-500">Belum ada pengumuman.</p>
                @endforelse
            </div>

            <div class="text-center mt-8">
                <a href="{{ route('pengumuman.index') }}" class="btn-outline">Lihat Semua Pengumuman</a>
            </div>
        </section>

        {{-- FAQ --}}
        <section id="faq" class="mb-16">
            <h2 class="text-center text-3xl font-semibold text-slate-800">Frequently Asked Questions</h2>
            <p class="text-center text-slate-500 mb-8">Pertanyaan terkait UPSDM dan Remunerasi</p>

            <div class="max-w-3xl mx-auto">
                <div class="space-y-4">
                    @forelse($faqs as $i => $f)
                        <div x-data="{open:false}" class="bg-white rounded-lg shadow">
                            <button @click="open=!open"
                                    class="w-full px-6 py-4 text-left font-medium flex items-center justify-between hover:bg-slate-50">
                                <span>{{ $f->pertanyaan }}</span>
                                <i class="fa-solid fa-chevron-down text-sm transition" :class="open?'rotate-180':''"></i>
                            </button>
                            <div x-show="open" x-collapse x-cloak class="px-6 pb-5 text-slate-600">
                                {!! $f->jawaban !!}
                            </div>
                        </div>
                    @empty
                        <p class="text-slate-500 text-center">Belum ada FAQ.</p>
                    @endforelse
                </div>

                <div class="text-center mt-8">
                    <a href="{{ route('pertanyaan_umum.index') }}" class="btn-outline">Lihat FAQ Lainnya</a>
                </div>
            </div>
        </section>

        {{-- Akses Cepat --}}
        <section class="mb-20">
            <h2 class="text-center text-3xl font-semibold text-slate-800 mb-10">Akses Cepat</h2>
            <div class="grid gap-8 sm:grid-cols-2 lg:grid-cols-4">
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
            const openFromQuery = new URLSearchParams(window.location.search).get('modal') === '1';
            const hasServerFlag = @json($errors->any() || session('status'));
            if (openFromQuery || hasServerFlag) {
                if (window.Alpine?.store) Alpine.store('authModal').show();
            }
        });
    </script>
@endpush
