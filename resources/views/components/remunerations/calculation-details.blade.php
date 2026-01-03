@props(['details' => null])

@php
    $d = is_array($details) ? $details : (is_object($details) ? (array)$details : null);
    $fmt = function($n, $dec = 2) {
        if (!is_numeric($n)) return $n;
        $num = (float)$n;
        if (abs($num - round($num)) < 0.000001) {
            return (string)(int) round($num);
        }
        $formatted = number_format($num, $dec, '.', '');
        return rtrim(rtrim($formatted, '0'), '.');
    };
@endphp

@if(!$d)
    <div class="text-slate-500 text-sm">Tidak ada catatan.</div>
@else
    {{-- 1) Jika ada struktur 'wsm' khusus, tampilkan tabelnya --}}
    @if(isset($d['wsm']) && is_array($d['wsm']) && isset($d['wsm']['criteria']) && is_array($d['wsm']['criteria']))
        <div class="overflow-auto rounded-xl border border-slate-200">
            <table class="min-w-[760px] w-full text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left">Kriteria</th>
                        <th class="px-4 py-3 text-right">Bobot (%)</th>
                        <th class="px-4 py-3 text-right">Nilai</th>
                        <th class="px-4 py-3 text-right">Ternormalisasi</th>
                        <th class="px-4 py-3 text-right">Kontribusi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($d['wsm']['criteria'] as $row)
                        <tr class="border-t border-slate-100">
                            <td class="px-4 py-2">{{ $row['name'] ?? '-' }}</td>
                            <td class="px-4 py-2 text-right">{{ $fmt($row['weight'] ?? 0, 2) }}</td>
                            <td class="px-4 py-2 text-right">{{ $fmt($row['score'] ?? 0, 2) }}</td>
                            <td class="px-4 py-2 text-right">{{ $fmt($row['normalized'] ?? 0, 4) }}</td>
                            <td class="px-4 py-2 text-right">{{ $fmt($row['contribution'] ?? 0, 4) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                @if(isset($d['wsm']['total']))
                <tfoot>
                    <tr class="bg-slate-50 border-t border-slate-200">
                        <td colspan="4" class="px-4 py-2 text-right font-medium">Total Skor Kinerja</td>
                        <td class="px-4 py-2 text-right font-semibold">{{ $fmt($d['wsm']['total'], 4) }}</td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    @elseif(isset($d['komponen']) && is_array($d['komponen']))
        {{-- 2) Struktur 'komponen' (contoh pada screenshot) --}}
        <div class="grid gap-3 md:grid-cols-2">
            @foreach($d['komponen'] as $label => $val)
                <div class="flex items-center justify-between bg-slate-50 border border-slate-200 rounded-xl px-4 py-3">
                    <div class="text-slate-600">{{ \Illuminate\Support\Str::title(str_replace('_',' ', $label)) }}</div>
                    <div class="font-semibold text-slate-800">{{ $fmt(is_array($val) ? ($val['nilai'] ?? 0) : $val) }}</div>
                </div>
            @endforeach
        </div>
        @if(isset($d['rincian']) && is_array($d['rincian']))
            <div class="mt-4">
                <div class="text-slate-600 text-sm mb-1">Rincian</div>
                <ul class="list-disc pl-6 text-sm text-slate-700">
                    @foreach($d['rincian'] as $r)
                        <li>{{ is_array($r) ? json_encode($r, JSON_UNESCAPED_UNICODE) : $r }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        @if(!empty($d['catatan']))
            <div class="mt-3 text-sm text-slate-700">Catatan: {{ $d['catatan'] }}</div>
        @endif
    @else
        {{-- 3) Fallback: tampilkan key-value secara rapi --}}
        <div class="space-y-2">
            @foreach($d as $k => $v)
                <div class="bg-slate-50 border border-slate-200 rounded-xl p-3">
                    <div class="text-xs text-slate-500">{{ \Illuminate\Support\Str::title(str_replace('_',' ', (string)$k)) }}</div>
                    <div class="text-sm text-slate-800">
                        @if(is_array($v))
                            <pre class="text-[13px] whitespace-pre-wrap">{{ json_encode($v, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                        @else
                            {{ is_numeric($v) ? $fmt($v) : (string)$v }}
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
@endif
