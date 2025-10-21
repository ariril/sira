@props([
  'type' => 'text',
  'name' => null,
  'value' => null,
  'placeholder' => null,
  'id' => $name,
  'addonLeft' => null, // icon class FA
  'required' => false,
])
<div class="relative">
    @if($addonLeft)
        <i class="fa-solid {{ $addonLeft }} absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
    @endif
    <input
        {{ $attributes->merge([
          'class' => 'w-full h-12 '.($addonLeft ? 'pl-10' : 'pl-4').' pr-4 rounded-xl border-slate-300 text-[15px] shadow-sm focus:border-blue-500 focus:ring-blue-500'
        ]) }}
        type="{{ $type }}" name="{{ $name }}" id="{{ $id }}"
        value="{{ old($name, $value) }}" placeholder="{{ $placeholder }}" @required($required) />
</div>
@error($name)<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
