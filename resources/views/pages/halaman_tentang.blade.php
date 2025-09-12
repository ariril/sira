@extends('layouts.public')
@section('title', $page->judul ?? 'Profil')

@section('content')
    <div class="max-w-5xl mx-auto px-4 sm:px-6 py-10">
        <h1 class="text-3xl font-semibold mb-4">{{ $page->judul ?? ucfirst($page->tipe) }}</h1>
        <article class="prose max-w-none">{!! $page->konten !!}</article>
    </div>
@endsection
