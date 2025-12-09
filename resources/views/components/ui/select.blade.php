@props([
  'name' => null,
  'id' => null,
  'options' => [],        // ['value' => 'Label']
  'placeholder' => null,  // string/null
  'value' => null,
  'required' => false,
])
@php($selectedValue = $name ? old($name, $value) : $value)
<div class="relative">
    <select
        {{ $attributes->merge([
          'class' => 'w-full h-12 pl-4 pr-10 rounded-xl border-slate-300 text-[15px] shadow-sm appearance-none focus:border-blue-500 focus:ring-blue-500'
        ]) }}
        name="{{ $name }}" id="{{ $id ?? $name ?? '' }}" @required($required)>
        @if($placeholder !== null)
            <option value="">{{ $placeholder }}</option>
        @endif
        @foreach($options as $val => $label)
      <option value="{{ $val }}" @selected($selectedValue==$val)>{{ $label }}</option>
        @endforeach
    </select>
    <i class="fa-solid fa-chevron-down pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
</div>
{{-- Inline error dihapus: sekarang hanya tampil global di layout --}}
