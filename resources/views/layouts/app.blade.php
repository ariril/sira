<!DOCTYPE html>
<html lang="{{ str_replace('_','-',app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? config('app.name','Laravel') }}</title>

    {{-- Fonts --}}
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet"/>
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.2/css/all.min.css">

    @stack('head')

    {{-- Assets --}}
    @vite(['resources/css/app.css','resources/js/app.js'])
</head>
@php
    $role = auth()->user()?->getActiveRoleSlug();
    // layout ini untuk semua role selain pegawai_medis
@endphp
<body class="font-sans antialiased bg-gray-100 min-h-screen pt-14">

{{-- Topbar + Sidebar (fixed) --}}
@include('partials.nonpublic.navigation')

{{-- CONTENT WRAPPER (sticky footer) --}}
<div class="lg:ml-64 flex flex-col min-h-[calc(100vh-3.5rem)]"> {{-- 3.5rem = h-14 topbar height --}}
    {{-- Header slot (opsional) --}}
    @isset($header)
        <header class="bg-white shadow">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 min-h-[84px] flex items-center">
                {{ $header }}
            </div>
        </header>
    @endisset

    @include('partials.global-notification')

    {{-- Konten utama --}}
    <main class="py-6 flex-1"> {{-- flex-1 pushes footer to bottom when content is short --}}
        @hasSection('content')
            @yield('content')
        @else
            {{ $slot ?? '' }}
        @endif
    </main>

    {{-- Footer --}}
    <div>
        @include('partials.nonpublic.footer')
    </div>
</div>

@stack('scripts')
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
</body>
</html>
