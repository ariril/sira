@props([
  'label' => 'Label',
  'value' => '0',
  'icon'  => 'fa-chart-column',
  'hint'  => null,
  'accent'=> 'from-blue-500 to-indigo-600', // gradient
])

<div class="group relative overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-100 hover:shadow-md transition">
    <div class="absolute -right-8 -top-8 h-28 w-28 rounded-full bg-gradient-to-br {{ $accent }} opacity-10"></div>
    <div class="p-4 sm:p-5">
        <div class="flex items-center gap-3">
            <div class="inline-grid h-10 w-10 place-items-center rounded-xl bg-gradient-to-br {{ $accent }} text-white shadow-sm">
                <i class="fa-solid {{ $icon }}"></i>
            </div>
            <div class="min-w-0">
                <p class="text-xs uppercase tracking-wide text-slate-500">{{ $label }}</p>
                <p class="text-2xl font-semibold text-slate-800 leading-tight">{{ $value }}</p>
            </div>
        </div>
        @if($hint)
            <p class="mt-2 text-xs text-slate-500">{{ $hint }}</p>
        @endif
    </div>
</div>
