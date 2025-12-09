@props([
    'title' => 'Section',
    'actions' => null,
    'description' => null,
])

<section {{ $attributes->merge(['class' => 'rounded-2xl bg-white shadow-sm border border-slate-100']) }}>
    <header class="px-6 pt-5 pb-4 border-b border-slate-100 flex items-center justify-between gap-4 flex-wrap">
        <div class="space-y-1">
            <h2 class="text-lg font-semibold text-slate-800">{{ $title }}</h2>
            @if($description)
                <p class="text-sm text-slate-500">{{ $description }}</p>
            @endif
        </div>
        @if($actions)
            <div class="flex items-center gap-2">
                {{ $actions }}
            </div>
        @endif
    </header>
    <div class="px-6 pb-6 pt-4">
        {{ $slot }}
    </div>
</section>