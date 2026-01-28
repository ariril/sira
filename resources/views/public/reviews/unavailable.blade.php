@extends('layouts.public')

@section('title','Link Kedaluwarsa')

@section('content')
<div class="max-w-xl mx-auto px-4 py-16">
    <div class="bg-white rounded-3xl border border-slate-100 shadow-sm p-6 space-y-3">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Informasi</p>
        <h1 class="text-2xl font-semibold text-slate-900">Link ulasan sudah kedaluwarsa</h1>
        <p class="text-sm text-slate-600">
            {{ session('notice') ?? 'Maaf, link ulasan ini sudah kedaluwarsa sehingga tidak dapat digunakan.' }}
        </p>
        <div class="pt-2">
            <a href="{{ route('home') }}" class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-2xl text-sm font-semibold text-slate-700 bg-slate-100 hover:bg-slate-200">Kembali ke Beranda</a>
        </div>
    </div>
</div>
@endsection
