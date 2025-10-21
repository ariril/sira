@props([
    'icon'    => 'fa-pen-to-square',
    'variant' => 'outline',   // outline | success | danger
    'as'      => 'button',    // 'button' | 'a'
    'href'    => '#',
])

@php
    $base = 'inline-flex items-center justify-center w-10 h-10 rounded-xl text-[15px]';
    switch ($variant) {
        case 'success':
            $color = 'bg-emerald-50 text-emerald-700 border border-emerald-100 hover:bg-emerald-100'; break;
        case 'danger':
            $color = 'bg-rose-50 text-rose-700 border border-rose-100 hover:bg-rose-100'; break;
        default: // outline
            $color = 'border border-slate-200 text-slate-700 hover:bg-slate-50'; break;
    }
    $classes = trim("$base $color");
@endphp

@if ($as === 'a')
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        <i class="fa-solid {{ $icon }}"></i>
    </a>
@else
    <button {{ $attributes->merge(['class' => $classes]) }}>
        <i class="fa-solid {{ $icon }}"></i>
    </button>
@endif
