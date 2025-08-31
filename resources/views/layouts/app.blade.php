<!DOCTYPE html>
<html lang="{{ str_replace('_','-',app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? config('app.name','Laravel') }}</title>

    {{-- Fonts --}}
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    {{-- Extra per-page head (SEO/meta) --}}
    @stack('head')

    {{-- Assets --}}
    @vite(['resources/css/app.css','resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-gray-100 min-h-screen">
{{-- Nav privat hanya saat login --}}
@auth
    @include('layouts.navigation')
@endauth

{{-- Header slot opsional --}}
@isset($header)
    <header class="bg-white shadow">
        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
            {{ $header }}
        </div>
    </header>
@endisset

{{-- Flash status --}}
@if (session('status'))
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-4">
        <div class="rounded-lg bg-green-50 text-green-800 px-4 py-3 text-sm">
            {{ session('status') }}
        </div>
    </div>
@endif

{{-- Page content --}}
<main class="py-6">
    {{ $slot }}
</main>

{{-- Script stacks (Chart.js/ApexCharts, dll) --}}
@stack('scripts')
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
</body>
</html>
