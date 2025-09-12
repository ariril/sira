@extends('layouts.public')
@section('title', 'Pengumuman')

@section('content')
    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-10">

        {{-- Header + Search --}}
        <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-6 mb-10">
            <div>
                <h1 class="text-3xl md:text-4xl font-semibold text-slate-800">Pengumuman</h1>
                <p class="text-slate-500 mt-2">Informasi terbaru terkait remunerasi, kinerja, dan layanan.</p>
            </div>

            <form method="GET" action="{{ route('pengumuman.index') }}" class="w-full md:w-[420px]">
                <label class="sr-only" for="q">Cari</label>
                <div class="relative">
                    <input id="q" name="q" value="{{ request('q') }}"
                           placeholder="Cari judul atau isi pengumuman…"
                           class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 pr-12 focus:outline-none focus:ring-2 focus:ring-blue-500" />
                    <button class="absolute right-2 top-1/2 -translate-y-1/2 px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-600">
                        <i class="fa-solid fa-magnifying-glass"></i>
                    </button>
                </div>
            </form>
        </div>

        {{-- Kategori / Label chips (opsional) --}}
        <div class="flex flex-wrap gap-2 mb-8">
            @php
                $activeCat = request('kategori'); $activeLbl = request('label');
                $chip = fn($active) => $active ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200';
            @endphp

            <a href="{{ route('pengumuman.index') }}"
               class="px-3 py-1.5 rounded-full text-sm {{ $chip(!$activeCat && !$activeLbl) }}">Semua</a>

            @foreach (['remunerasi','kinerja','panduan','lainnya'] as $k)
                <a href="{{ route('pengumuman.index', array_filter(['kategori'=>$k,'label'=>$activeLbl,'q'=>request('q')])) }}"
                   class="px-3 py-1.5 rounded-full text-sm {{ $chip($activeCat===$k) }}">{{ ucfirst($k) }}</a>
            @endforeach

            <span class="mx-2 text-slate-300">|</span>

            @foreach (['penting','info','update'] as $l)
                <a href="{{ route('pengumuman.index', array_filter(['label'=>$l,'kategori'=>$activeCat,'q'=>request('q')])) }}"
                   class="px-3 py-1.5 rounded-full text-sm {{ $chip($activeLbl===$l) }}">Label: {{ ucfirst($l) }}</a>
            @endforeach
        </div>

        {{-- Daftar kartu --}}
        <div class="grid gap-8 md:grid-cols-2 xl:grid-cols-3">
            @forelse ($items as $i => $p)
                @php
                    $badge = match($p->label){
                      'penting' => ['bg' => 'bg-red-100',   'text' => 'text-red-600'],
                      'info'    => ['bg' => 'bg-blue-100',  'text' => 'text-blue-600'],
                      'update'  => ['bg' => 'bg-green-100', 'text' => 'text-green-600'],
                      default   => ['bg' => 'bg-slate-100', 'text' => 'text-slate-600'],
                    };
                    $isFeatured = $i === 0 || (bool) $p->disorot;
                @endphp

                <article class="bg-white rounded-xl p-6 shadow-md hover:shadow-lg hover:-translate-y-1 transition border border-slate-100 {{ $isFeatured ? 'outline outline-2 outline-red-500/20' : '' }}">
                    <div class="flex items-center justify-between mb-3">
          <span class="px-3 py-1 rounded-full text-xs font-semibold {{ $badge['bg'] }} {{ $badge['text'] }} uppercase">
            {{ ucfirst($p->label ?? 'info') }}
          </span>
                        <time class="text-slate-500">{{ optional($p->dipublikasikan_pada)->translatedFormat('d F Y') }}</time>
                    </div>

                    <h2 class="text-xl font-semibold text-slate-800 mb-2">
                        <a href="{{ route('pengumuman.show', $p->slug) }}" class="hover:underline">{{ $p->judul }}</a>
                    </h2>

                    <p class="text-slate-600">
                        {{ $p->ringkasan ? \Illuminate\Support\Str::limit(strip_tags($p->ringkasan), 150)
                                         : \Illuminate\Support\Str::limit(strip_tags($p->konten), 150) }}
                    </p>

                    <div class="flex items-center justify-between mt-5">
          <span class="px-3 py-1 rounded-full text-xs bg-slate-100 text-slate-600">
            {{ ucfirst($p->kategori) }}
          </span>

                        <a href="{{ route('pengumuman.show', $p->slug) }}"
                           class="text-blue-600 font-medium inline-flex items-center gap-1">
                            Baca <i class="fa-solid fa-arrow-right text-sm"></i>
                        </a>
                    </div>
                </article>
            @empty
                <p class="text-slate-500 md:col-span-2 xl:col-span-3">Belum ada pengumuman.</p>
            @endforelse
        </div>

        {{-- Pagination --}}
        <div class="mt-10">
            {{ $items->onEachSide(1)->links() }}
        </div>
    </div>
@endsection
