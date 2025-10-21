@props(['name'=>null,'rows'=>3,'id'=>$name,'value'=>null])
<textarea
    {{ $attributes->merge([
      'class'=>'w-full rounded-xl border-slate-300 text-[15px] shadow-sm focus:border-blue-500 focus:ring-blue-500 p-4'
    ]) }}
    name="{{ $name }}" id="{{ $id }}" rows="{{ $rows }}">{{ old($name,$value) }}</textarea>
@error($name)<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
