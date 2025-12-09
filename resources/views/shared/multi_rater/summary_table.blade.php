@props([
    'title' => 'Ringkasan Nilai 360 Anda',
    'periods' => collect(),
    'selectedPeriod' => null,
    'rows' => collect(),
    'param' => 'summary_period_id',
    'buttonVariant' => 'sky',
])

@php
    $periodOptions = ($periods instanceof \Illuminate\Support\Collection ? $periods : collect($periods))->pluck('name','id');
    $rowsCollection = $rows instanceof \Illuminate\Support\Collection ? $rows : collect($rows);
@endphp

<div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5">
    <div class="flex items-center justify-between mb-4">
        <h3 class="font-semibold text-slate-800">{{ $title }}</h3>
    </div>
    @if($periodOptions->isEmpty())
        <p class="text-sm text-slate-500">Belum ada periode penilaian yang tersedia.</p>
    @else
        <form method="GET" class="flex flex-wrap items-end gap-3 mb-4">
            @foreach(request()->except($param) as $key => $value)
                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
            @endforeach
            <div class="min-w-[220px]">
                <label class="block text-sm font-medium text-slate-600 mb-1">Periode</label>
                <x-ui.select name="{{ $param }}" :options="$periodOptions" :value="optional($selectedPeriod)->id" />
            </div>
            <x-ui.button type="submit" variant="{{ $buttonVariant }}" class="h-10 px-4 text-sm">Terapkan</x-ui.button>
        </form>

        @if($rowsCollection->isEmpty())
            <div class="text-sm text-slate-600 bg-slate-50 border border-slate-200 px-4 py-3 rounded-xl">
                Belum ada penilaian diterima pada periode ini.
            </div>
        @else
            <x-ui.table min-width="640px">
                <x-slot name="head">
                    <tr>
                        <th class="px-6 py-4 text-left whitespace-nowrap">Kriteria</th>
                        <th class="px-6 py-4 text-left whitespace-nowrap">Tipe</th>
                        <th class="px-6 py-4 text-left whitespace-nowrap">Rata-rata</th>
                    </tr>
                </x-slot>
                @foreach($rowsCollection as $row)
                    <tr class="hover:bg-slate-50">
                        <td class="px-6 py-3">{{ $row['name'] ?? '-' }}</td>
                        <td class="px-6 py-3">
                            @php($type = $row['type'] ?? 'benefit')
                            <span class="px-2 py-1 rounded text-xs border {{ $type === 'cost' ? 'bg-rose-50 text-rose-700 border-rose-200' : 'bg-emerald-50 text-emerald-700 border-emerald-200' }}">
                                {{ $type === 'cost' ? 'Cost' : 'Benefit' }}
                            </span>
                        </td>
                        <td class="px-6 py-3 font-semibold text-slate-700">
                            @if(is_null($row['avg_score']))
                                <span class="text-slate-400">-</span>
                            @else
                                {{ number_format((float) $row['avg_score'], 2) }}
                            @endif
                        </td>
                    </tr>
                @endforeach
            </x-ui.table>
        @endif
    @endif
</div>
