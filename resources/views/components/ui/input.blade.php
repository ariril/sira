@props([
  'type' => 'text',
  'name' => null,
  'value' => null,
  'placeholder' => 'Ketik di sini…',
  'id' => null,
  'addonLeft' => null, // icon class FA
  'required' => false,
  'preserveOld' => true,
])
@php($inputValue = ($preserveOld && $name) ? old($name, $value) : $value)
@if($type === 'checkbox')
    <input
        {{ $attributes->merge([
          'class' => 'h-5 w-5 text-blue-600 border-slate-300 focus:ring-blue-500 rounded'
        ]) }}
        type="checkbox" name="{{ $name }}" id="{{ $id ?? $name ?? '' }}"
        value="{{ $value ?? 1 }}" @checked(($preserveOld && $name) ? old($name, $value) : $value) />
@else
    <div class="relative">
        @if($addonLeft)
            <i class="fa-solid {{ $addonLeft }} absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
        @endif
        <input
            {{ $attributes->merge([
              'class' => 'w-full h-12 '.($addonLeft ? 'pl-10' : 'pl-4').' pr-4 rounded-xl border-slate-300 text-[15px] shadow-sm focus:border-blue-500 focus:ring-blue-500'
            ]) }}
            type="{{ $type }}" name="{{ $name }}" id="{{ $id ?? $name ?? '' }}"
            value="{{ $inputValue }}" placeholder="{{ $placeholder }}" @required($required) />
    </div>
@endif
{{-- Inline error dihapus: sekarang hanya tampil global di layout --}}
