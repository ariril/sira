@props([
    'as'      => 'button',     // 'button' | 'a'
    'href'    => '#',          // untuk link
    'type'    => 'button',     // dipakai jika as='button'
    'variant' => 'primary',    // primary | success | danger | orange | sky | outline
])

@php
    $base = 'inline-flex items-center justify-center gap-2 font-medium rounded-xl shadow-sm transition select-none focus:outline-none focus:ring-2 focus:ring-offset-1';
    $size = 'h-12 px-5 text-[15px]';

    $color = match ($variant) {
        'success' => 'text-white bg-gradient-to-tr from-emerald-500 to-teal-600 hover:brightness-110 focus:ring-emerald-500',
        'danger'  => 'text-white bg-gradient-to-tr from-rose-500 to-red-600 hover:brightness-110 focus:ring-rose-500',
        'approve' => 'text-white bg-gradient-to-tr from-emerald-500 to-teal-600 hover:brightness-110 focus:ring-emerald-500',
        'reject'  => 'text-white bg-gradient-to-tr from-rose-500 to-red-600 hover:brightness-110 focus:ring-rose-500',
        'orange'  => 'text-white bg-gradient-to-tr from-amber-500 to-orange-600 hover:brightness-110 focus:ring-amber-500',
        // Biru muda untuk nuansa Pegawai Medis
        'sky'     => 'text-white bg-gradient-to-tr from-sky-400 to-blue-500 hover:brightness-110 focus:ring-sky-400',
        'violet'  => 'text-white bg-gradient-to-tr from-fuchsia-500 to-violet-600 hover:brightness-110 focus:ring-fuchsia-500',
        'outline' => 'border border-slate-300 text-slate-700 hover:bg-slate-50 focus:ring-slate-300',
        default   => 'text-white bg-gradient-to-tr from-blue-500 to-indigo-600 hover:brightness-110 focus:ring-indigo-500',
    };

    $classes = trim("$base $size $color");
@endphp

@if ($as === 'a')
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </button>
@endif
