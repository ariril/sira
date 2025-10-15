@props(['title' => 'Section', 'actions' => null])

<section {{ $attributes->merge(['class' => 'rounded-2xl bg-white shadow-sm ring-1 ring-slate-100']) }}>
    <header class="flex items-center justify-between px-5 py-4 border-b">
        <h2 class="font-semibold text-slate-800">{{ $title }}</h2>
        <div class="flex items-center gap-2">{{ $actions }}</div>
    </header>
    <div class="p-5">
        {{ $slot }}
    </div>
</section>
