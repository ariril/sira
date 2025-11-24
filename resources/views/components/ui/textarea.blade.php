@props(['name'=>null,'rows'=>3,'id'=>$name,'value'=>null,'placeholder'=>'Ketik di sini…'])
<textarea
    {{ $attributes->merge([
      'class'=>'w-full rounded-xl border-slate-300 text-[15px] shadow-sm focus:border-blue-500 focus:ring-blue-500 p-4'
    ]) }}
    name="{{ $name }}" id="{{ $id }}" rows="{{ $rows }}" placeholder="{{ $placeholder }}">{{ old($name,$value) }}</textarea>
{{-- Inline error dihapus: sekarang hanya tampil global di layout --}}
