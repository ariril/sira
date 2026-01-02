<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title','Unit Remuneration - Universitas Sebelas Maret')</title>

    {{-- Tailwind v4 (via Vite) --}}
    @php
        $hasVite = file_exists(public_path('hot')) || file_exists(public_path('build/manifest.json'));
    @endphp
    @if ($hasVite)
        @vite(['resources/css/app.css','resources/js/app.js'])
    @endif

    {{-- Font Awesome (ikon sesuai mockup) --}}
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    @stack('styles')
    <style>[x-cloak]{ display:none !important }</style>
</head>
<body x-data class="bg-slate-50 text-slate-800 antialiased">

{{-- Top header --}}
@include('partials.header')

{{-- Navigation (sticky) --}}
@include('partials.nav')

<main class="min-h-[60vh]">
    @include('partials.global-notification')
    @yield('content')
</main>

{{-- Footer --}}
@include('partials.footer')
@include('partials.login-modal')
@stack('scripts')
</body>
</html>
