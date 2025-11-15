@extends('layouts.public')

@section('title', 'FAQ - Pertanyaan Umum')

@section('content')
    <div class="max-w-4xl mx-auto py-10">
        <h1 class="text-3xl font-semibold mb-6">Frequently Asked Questions</h1>

        <div class="space-y-4">
            @forelse($items as $faq)
                <div class="bg-white rounded-lg shadow p-6">
                    <h2 class="font-semibold text-lg mb-2">{{ $faq->question }}</h2>
                    <div class="text-slate-600">{!! $faq->answer !!}</div>
                </div>
            @empty
                <p class="text-slate-500">Belum ada pertanyaan umum.</p>
            @endforelse
        </div>

        <div class="mt-6">
            {{ $items->links() }}
        </div>
    </div>
@endsection
