@props(['name'=>null,'rows'=>3,'id'=>null,'value'=>null,'placeholder'=>'Ketik di sini…'])
@php($textValue = $name ? old($name,$value) : $value)
<textarea
    {{ $attributes->merge([
      'class'=>'w-full rounded-xl border-slate-300 text-[15px] shadow-sm focus:border-blue-500 focus:ring-blue-500 p-4'
    ]) }}
    name="{{ $name }}" id="{{ $id ?? $name ?? '' }}" rows="{{ $rows }}" placeholder="{{ $placeholder }}">{{ $textValue }}</textarea>
{{-- Inline error dihapus: sekarang hanya tampil global di layout --}}
