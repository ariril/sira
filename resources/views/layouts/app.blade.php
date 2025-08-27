<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title','Unit Remunerasi - Universitas Sebelas Maret')</title>

    {{-- Tailwind v4 (via Vite) --}}
    @vite(['resources/css/app.css','resources/js/app.js'])

    {{-- Font Awesome (ikon sesuai mockup) --}}
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    @stack('styles')
</head>
<body class="bg-slate-50 text-slate-800 antialiased">

{{-- Top header --}}
@include('partials.header')

{{-- Navigation (sticky) --}}
@include('partials.nav')

<main class="min-h-[60vh]">
    @yield('content')
</main>

{{-- Footer --}}
@include('partials.footer')
@stack('scripts')
</body>
</html>
