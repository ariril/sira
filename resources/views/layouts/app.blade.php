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

    @stack('head')

    {{-- Assets --}}
    @vite(['resources/css/app.css','resources/js/app.js'])
</head>
@php
    $role = auth()->check() ? auth()->user()->role : null;
    $useAdminShell = in_array($role, ['super_admin','administrasi']); // nanti bisa ditambah 'kepala_unit','pegawai_medis'
@endphp
<body class="font-sans antialiased bg-gray-100 min-h-screen">

{{-- NAV --}}
@auth
    @if($useAdminShell)
        @include('admin.partials.navigation')  {{-- Topbar + Sidebar admin (role-aware) --}}
    @else
        {{-- kalau bukan admin shell, kamu bebas pakai nav publicmu --}}
        @includeIf('partials.nav')
    @endif
@endauth

{{-- Header slot (judul halaman, breadcrumb) --}}
@isset($header)
    <header class="bg-white shadow {{ $useAdminShell ? 'lg:ml-64' : '' }}">
        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
            {{ $header }}
        </div>
    </header>
@endisset

{{-- Flash status --}}
@if (session('status'))
    <div class="{{ $useAdminShell ? 'lg:ml-64' : '' }}">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-4">
            <div class="rounded-lg bg-green-50 text-green-800 px-4 py-3 text-sm">
                {{ session('status') }}
            </div>
        </div>
    </div>
@endif

{{-- Page content --}}
<main class="py-6 {{ $useAdminShell ? 'lg:ml-64' : '' }}">
    {{ $slot }}
</main>

{{-- Footer --}}
<div class="{{ $useAdminShell ? 'lg:ml-64' : '' }}">
    @if($useAdminShell)
        @include('admin.partials.footer')
    @else
        @includeIf('partials.footer')
    @endif
</div>

@stack('scripts')
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
</body>
</html>
