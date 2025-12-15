<x-app-layout title="Detail Remunerasi">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div class="flex items-center justify-between mb-4">
            <h1 class="text-2xl font-semibold">Detail Remunerasi</h1>
            <a href="{{ route('pegawai_medis.remunerations.index') }}" class="px-3 py-2 rounded-lg border">Kembali</a>
        </div>

        <div class="grid sm:grid-cols-3 gap-4 mb-4">
            <div class="p-4 rounded-2xl bg-white shadow-sm ring-1 ring-slate-100">
                <div class="text-sm text-slate-500">Periode Penilaian</div>
                <div class="text-lg font-semibold">{{ $item->assessmentPeriod->name ?? '-' }}</div>
            </div>
            <div class="p-4 rounded-2xl bg-white shadow-sm ring-1 ring-slate-100">
                <div class="text-sm text-slate-500">Jumlah yang Anda terima</div>
                <div class="text-lg font-semibold">{{ $item->amount !== null ? 'Rp '.number_format($item->amount,0,',','.') : '-' }}</div>
            </div>
            <div class="p-4 rounded-2xl bg-white shadow-sm ring-1 ring-slate-100">
                <div class="text-sm text-slate-500">Alokasi!</div>
                <div class="text-lg font-semibold">{{ isset($allocationAmount) ? 'Rp '.number_format((float)$allocationAmount,0,',','.') : '-' }}</div>
                <div class="text-xs text-slate-500 mt-1">{{ $allocationLabel ?? 'Alokasi profesi-unit' }}</div>
            </div>
        </div>

        <div class="grid sm:grid-cols-3 gap-4 mb-6">
            <div class="p-4 rounded-2xl bg-white shadow-sm ring-1 ring-slate-100">
                <div class="text-sm text-slate-500">Dipublikasikan</div>
                <div class="text-lg font-semibold">{{ optional($item->published_at)->format('d M Y H:i') ?? '-' }}</div>
                <div class="text-xs text-slate-500 mt-1">Informasi diterbitkan untuk periode ini.</div>
            </div>
            <div class="p-4 rounded-2xl bg-white shadow-sm ring-1 ring-slate-100">
                <div class="text-sm text-slate-500">Status Pembayaran</div>
                <div class="text-lg font-semibold">{{ $item->payment_status?->value ?? '-' }}</div>
                <div class="text-xs text-slate-500 mt-1">{{ $item->payment_status?->value === 'Dibayar' ? 'Sudah dibayar oleh admin RS' : 'Menunggu proses pembayaran admin RS' }}</div>
            </div>
            <div class="p-4 rounded-2xl bg-white shadow-sm ring-1 ring-slate-100">
                <div class="text-sm text-slate-500">Kontribusi Periode Ini</div>
                @if(isset($contributions) && $contributions->count())
                    <ul class="mt-1 space-y-1 text-sm">
                        @foreach($contributions as $c)
                            <li class="flex items-start justify-between gap-2">
                                <span class="font-medium text-slate-800">{{ $c->title }}</span>
                                <span class="text-xs text-slate-500">{{ $c->validation_status }}</span>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <div class="text-sm text-slate-500 mt-1">Tidak ada kontribusi tercatat pada periode ini.</div>
                @endif
            </div>
        </div>

        <x-section title="Rincian Perhitungan">
            @php
                $calc = $calc ?? ($item->calculation_details ?? []);
            @endphp

            @if(isset($quantities) && count($quantities))
                @foreach(array_chunk($quantities, 3) as $chunk)
                    <div class="grid gap-4 sm:grid-cols-3 mb-4">
                        @foreach($chunk as $q)
                            @php
                                $icon = $q['icon'] ?? 'fa-user-injured';
                                $val = $q['value'];
                            @endphp
                            <div class="p-4 rounded-2xl bg-white shadow-sm ring-1 ring-slate-100 flex items-center gap-3">
                                <div class="w-12 h-12 rounded-xl grid place-content-center text-white bg-gradient-to-tr from-cyan-500 to-sky-600">
                                    <i class="fa-solid {{ $icon }}"></i>
                                </div>
                                <div>
                                    <div class="text-xl font-semibold">{{ is_numeric($val) ? number_format((float)$val, (floor($val) != $val) ? 1 : 0) : ($val ?? '-') }}</div>
                                    <div class="text-slate-500 text-sm">{{ $q['label'] }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endforeach
            @endif

            @php
                $money = function($v){ return 'Rp '.number_format((float)($v ?? 0),0,',','.'); };
                $rows = [
                    ['label' => 'Absensi', 'path' => 'komponen.absensi'],
                    ['label' => 'Kedisiplinan (360)', 'path' => 'komponen.kedisiplinan'],
                    ['label' => 'Kontribusi Tambahan', 'path' => 'komponen.kontribusi_tambahan'],
                    ['label' => 'Pasien Ditangani', 'path' => 'komponen.pasien_ditangani'],
                    ['label' => 'Ulasan Pasien (Rating)', 'path' => 'komponen.review_pelanggan'],
                ];
                $totalComp = 0;
            @endphp

            <x-ui.table min-width="520px">
                <x-slot name="head">
                    <tr>
                        <th class="text-left px-4 py-3 whitespace-nowrap">Komponen</th>
                        <th class="text-right px-4 py-3 whitespace-nowrap">Nilai</th>
                    </tr>
                </x-slot>
                @foreach($rows as $r)
                    @php
                        $nilai = data_get($calc, $r['path'].'.nilai', 0);
                        $totalComp += (float)$nilai;
                    @endphp
                    <tr>
                        <td class="px-4 py-3">{{ $r['label'] }}</td>
                        <td class="px-4 py-3 text-right">{{ $money($nilai) }}</td>
                    </tr>
                @endforeach
                <tr class="font-semibold">
                    <td class="px-4 py-3">Total Komponen</td>
                    <td class="px-4 py-3 text-right">{{ $money($totalComp) }}</td>
                </tr>
            </x-ui.table>
        </x-section>
    </div>
</x-app-layout>
