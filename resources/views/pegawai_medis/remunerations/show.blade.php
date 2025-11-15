<x-app-layout title="Detail Remunerasi">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div class="flex items-center justify-between mb-4">
            <h1 class="text-2xl font-semibold">Detail Remunerasi</h1>
            <a href="{{ route('pegawai_medis.remunerations.index') }}" class="px-3 py-2 rounded-lg border">Kembali</a>
        </div>

        <div class="grid sm:grid-cols-2 gap-4 mb-4">
            <div class="p-4 rounded-2xl bg-white shadow-sm ring-1 ring-slate-100">
                <div class="text-sm text-slate-500">Periode</div>
                <div class="text-lg font-semibold">{{ $item->assessmentPeriod->name ?? '-' }}</div>
            </div>
            <div class="p-4 rounded-2xl bg-white shadow-sm ring-1 ring-slate-100">
                <div class="text-sm text-slate-500">Jumlah</div>
                <div class="text-lg font-semibold">{{ $item->amount !== null ? 'Rp '.number_format($item->amount,0,',','.') : '-' }}</div>
            </div>
            <div class="p-4 rounded-2xl bg-white shadow-sm ring-1 ring-slate-100">
                <div class="text-sm text-slate-500">Dipublikasikan</div>
                <div class="text-lg font-semibold">{{ optional($item->published_at)->format('d M Y H:i') ?? '-' }}</div>
            </div>
            <div class="p-4 rounded-2xl bg-white shadow-sm ring-1 ring-slate-100">
                <div class="text-sm text-slate-500">Status Pembayaran</div>
                <div class="text-lg font-semibold">{{ $item->payment_status?->value ?? '-' }}</div>
            </div>
        </div>

        <x-section title="Rincian Perhitungan">
            @php
                $calc = $calc ?? ($item->calculation_details ?? []);
                $cards = [
                    ['label'=>'Pasien Ditangani','value'=> $patientsHandled ?? (data_get($calc,'komponen.pasien_ditangani.jumlah') ?? '-') , 'icon'=>'fa-user-injured'],
                    ['label'=>'Jumlah Review','value'=> $reviewCount ?? 0, 'icon'=>'fa-star'],
                    ['label'=>'Kontribusi Tambahan','value'=> isset($contributions) ? $contributions->count() : (data_get($calc,'komponen.kontribusi_tambahan.jumlah') ?? 0), 'icon'=>'fa-hand-holding-heart'],
                ];
            @endphp

            <div class="grid gap-4 sm:grid-cols-3 mb-6">
                @foreach($cards as $c)
                    <div class="p-4 rounded-2xl bg-white shadow-sm ring-1 ring-slate-100 flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl grid place-content-center text-white bg-gradient-to-tr from-cyan-500 to-sky-600">
                            <i class="fa-solid {{ $c['icon'] }}"></i>
                        </div>
                        <div>
                            <div class="text-xl font-semibold">{{ is_numeric($c['value']) ? number_format($c['value']) : $c['value'] }}</div>
                            <div class="text-slate-500 text-sm">{{ $c['label'] }}</div>
                        </div>
                    </div>
                @endforeach
            </div>

            @php
                $money = function($v){ return 'Rp '.number_format((float)($v ?? 0),0,',','.'); };
                $dasar = data_get($calc,'komponen.dasar',0);
                $pdJml = data_get($calc,'komponen.pasien_ditangani.jumlah');
                $pdVal = data_get($calc,'komponen.pasien_ditangani.nilai');
                $rvJml = data_get($calc,'komponen.review_pelanggan.jumlah');
                $rvVal = data_get($calc,'komponen.review_pelanggan.nilai');
                $ktJml = data_get($calc,'komponen.kontribusi_tambahan.jumlah');
                $ktVal = data_get($calc,'komponen.kontribusi_tambahan.nilai');
                $totalComp = (float)$dasar + (float)$pdVal + (float)$rvVal + (float)$ktVal;
            @endphp

            <x-ui.table min-width="720px">
                <x-slot name="head">
                    <tr>
                        <th class="text-left px-4 py-3 whitespace-nowrap">Komponen</th>
                        <th class="text-left px-4 py-3 whitespace-nowrap">Kuantitas</th>
                        <th class="text-right px-4 py-3 whitespace-nowrap">Nilai</th>
                    </tr>
                </x-slot>
                <tr>
                    <td class="px-4 py-3">Dasar</td>
                    <td class="px-4 py-3">-</td>
                    <td class="px-4 py-3 text-right">{{ $money($dasar) }}</td>
                </tr>
                <tr>
                    <td class="px-4 py-3">Pasien Ditangani</td>
                    <td class="px-4 py-3">{{ $pdJml ?? 0 }}</td>
                    <td class="px-4 py-3 text-right">{{ $money($pdVal) }}</td>
                </tr>
                <tr>
                    <td class="px-4 py-3">Ulasan Pasien</td>
                    <td class="px-4 py-3">{{ $rvJml ?? 0 }}</td>
                    <td class="px-4 py-3 text-right">{{ $money($rvVal) }}</td>
                </tr>
                <tr>
                    <td class="px-4 py-3">Kontribusi Tambahan</td>
                    <td class="px-4 py-3">{{ $ktJml ?? 0 }}</td>
                    <td class="px-4 py-3 text-right">{{ $money($ktVal) }}</td>
                </tr>
                <tr class="font-semibold">
                    <td class="px-4 py-3">Total Komponen</td>
                    <td class="px-4 py-3">&nbsp;</td>
                    <td class="px-4 py-3 text-right">{{ $money($totalComp) }}</td>
                </tr>
            </x-ui.table>

            @if(isset($contributions) && $contributions->count())
                <div class="mt-4">
                    <h3 class="font-semibold mb-2">Kontribusi pada Periode Ini</h3>
                    <ul class="list-disc pl-5 space-y-1 text-slate-700">
                        @foreach($contributions as $c)
                            <li>
                                <span class="font-medium">{{ $c->title }}</span>
                                <span class="text-xs text-slate-500">- {{ $c->validation_status }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </x-section>
    </div>
</x-app-layout>
