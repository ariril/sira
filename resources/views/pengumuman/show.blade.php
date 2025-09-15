@extends('layouts.public')
@section('title', $item->judul)

@section('content')
    <div class="max-w-4xl mx-auto px-4 sm:px-6 py-10">

        {{-- Breadcrumb --}}
        <nav class="text-sm text-slate-500 mb-6">
            <a href="{{ route('home') }}" class="hover:underline">Beranda</a>
            <span class="mx-2">/</span>
            <a href="{{ route('pengumuman.index') }}" class="hover:underline">Pengumuman</a>
            <span class="mx-2">/</span>
            <span class="text-slate-700">{{ \Illuminate\Support\Str::limit($item->judul, 60) }}</span>
        </nav>

        {{-- Header --}}
        @php
            $badge = match($item->label){
              'penting' => ['bg' => 'bg-red-100',   'text' => 'text-red-600'],
              'info'    => ['bg' => 'bg-blue-100',  'text' => 'text-blue-600'],
              'update'  => ['bg' => 'bg-green-100', 'text' => 'text-green-600'],
              default   => ['bg' => 'bg-slate-100', 'text' => 'text-slate-600'],
            };
        @endphp

        <div class="bg-white rounded-2xl shadow-md border border-slate-100 p-6 md:p-8">
            <div class="flex items-center gap-3 mb-4">
      <span class="px-3 py-1 rounded-full text-xs font-semibold {{ $badge['bg'] }} {{ $badge['text'] }} uppercase">
        {{ ucfirst($item->label ?? 'info') }}
      </span>
                <span class="text-slate-400">•</span>
                <span class="px-3 py-1 rounded-full text-xs bg-slate-100 text-slate-600">
        {{ ucfirst($item->kategori) }}
      </span>
                <span class="ml-auto text-sm text-slate-500">
        {{ optional($item->dipublikasikan_pada)->translatedFormat('d F Y, H:i') }}
      </span>
            </div>

            <h1 class="text-3xl md:text-4xl font-semibold text-slate-800 leading-tight">
                {{ $item->judul }}
            </h1>

            @if($item->ringkasan)
                <p class="mt-3 text-slate-600 italic">{{ $item->ringkasan }}</p>
            @endif

            {{-- Konten --}}
            <article class="prose max-w-none mt-8 prose-slate">
                {!! $item->konten !!}
            </article>

            {{-- Footer actions --}}
            <div class="mt-8 flex flex-wrap gap-3">
                <a href="{{ route('pengumuman.index') }}" class="btn-outline inline-flex items-center gap-2">
                    <i class="fa-solid fa-arrow-left"></i> Kembali
                </a>
                <a href="#top" class="btn-primary inline-flex items-center gap-2">
                    <i class="fa-solid fa-arrow-up"></i> Ke Atas
                </a>
            </div>
        </div>

        {{-- Optional: kartu rekomendasi singkat --}}
        @php
            $lainnya = \App\Models\Announcement::where('id','<>',$item->id)
              ->orderByDesc('dipublikasikan_pada')->limit(3)->get();
        @endphp

        @if($lainnya->count())
            <h2 class="text-xl font-semibold text-slate-800 mt-10 mb-4">Pengumuman Lainnya</h2>
            <div class="grid gap-6 md:grid-cols-3">
                @foreach($lainnya as $p)
                    <a href="{{ route('pengumuman.show', $p->slug) }}"
                       class="block bg-white rounded-xl p-5 shadow hover:shadow-lg hover:-translate-y-1 transition border border-slate-100">
                        <div class="flex items-center justify-between mb-2">
            <span class="text-xs font-semibold uppercase {{ match($p->label){'penting'=>'text-red-600','info'=>'text-blue-600','update'=>'text-green-600',default=>'text-slate-600'} }}">
              {{ ucfirst($p->label ?? 'info') }}
            </span>
                            <time class="text-xs text-slate-500">
                                {{ optional($p->dipublikasikan_pada)->translatedFormat('d M Y') }}
                            </time>
                        </div>
                        <div class="font-semibold text-slate-800">{{ $p->judul }}</div>
                        <p class="text-sm text-slate-600 mt-1">
                            {{ \Illuminate\Support\Str::limit(strip_tags($p->ringkasan ?: $p->konten), 90) }}
                        </p>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
@endsection
