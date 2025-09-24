@extends('layouts.public')
@section('title', $page->title ?? 'Profil')

@section('content')
    <div class="max-w-5xl mx-auto px-4 sm:px-6 py-10">
        <h1 class="text-3xl font-semibold mb-4">{{ $page->title ?? ucfirst(str_replace('_',' ', $page->type)) }}</h1>
        <article class="prose max-w-none">{!! $page->content !!}</article>
    </div>
@endsection
