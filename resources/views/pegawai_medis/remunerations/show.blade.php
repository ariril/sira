<x-app-layout title="Detail Remunerasi">
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h1 class="text-2xl font-semibold text-slate-800">Detail Remunerasi</h1>
            <x-ui.button as="a" href="{{ route('pegawai_medis.remunerations.index') }}" variant="outline" class="h-10 px-4">
                Kembali
            </x-ui.button>
        </div>
    </x-slot>
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
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
                <div class="text-sm text-slate-500">Alokasi</div>
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
                <div class="text-sm text-slate-500">Tugas Tambahan Periode Ini</div>
                <div class="text-lg font-semibold">{{ data_get($calc ?? ($item->calculation_details ?? []), 'komponen.kontribusi_tambahan.jumlah', 0) }}</div>
                <div class="text-xs text-slate-500 mt-1">Jumlah tugas tambahan yang dihitung dalam periode ini.</div>
            </div>
        </div>

        <x-section title="Rincian Perhitungan">
            @php
                $calc = $calc ?? ($item->calculation_details ?? []);
            @endphp

            @php
                $userWsm = data_get($calc, 'wsm.user_total');
                if ($userWsm === null) $userWsm = data_get($calc, 'allocation.user_wsm_score');

                $groupWsm = data_get($calc, 'wsm.unit_total');
                if ($groupWsm === null) $groupWsm = data_get($calc, 'allocation.unit_total_wsm');

                $sharePct = data_get($calc, 'allocation.share_percent');
                $allocation = $allocationAmount ?? null;

                $computed = null;
                if (is_numeric($allocation) && is_numeric($userWsm) && is_numeric($groupWsm) && (float) $groupWsm > 0) {
                    $computed = (float) $allocation * ((float) $userWsm / (float) $groupWsm);
                }
            @endphp

            @if(is_numeric($allocation) || is_numeric($userWsm) || is_numeric($groupWsm) || is_numeric($sharePct))
                <div class="mb-5 rounded-2xl bg-white shadow-sm ring-1 ring-slate-100 p-4">
                    <div class="text-sm font-semibold text-slate-800">Dasar Perhitungan (sesuai Excel)</div>
                    <div class="mt-3 grid gap-3 sm:grid-cols-3">
                        <div class="text-sm">
                            <div class="text-slate-500">Total Kinerja Anda</div>
                            <div class="font-semibold">{{ is_numeric($userWsm) ? number_format((float)$userWsm, 2) : '-' }}</div>
                        </div>
                        <div class="text-sm">
                            <div class="text-slate-500 inline-flex items-center">
                                Total Kinerja Grup
                                <i class="fa-solid fa-circle-exclamation ml-1 text-slate-400" title="Dikelompokkan berdasarkan {{ ($professionName ?? '-') }} di {{ ($unitName ?? '-') }}"></i>
                            </div>
                            <div class="font-semibold">{{ is_numeric($groupWsm) ? number_format((float)$groupWsm, 2) : '-' }}</div>
                        </div>
                        <div class="text-sm">
                            <div class="text-slate-500">Porsi (share)</div>
                            <div class="font-semibold">{{ is_numeric($sharePct) ? number_format((float)$sharePct, 2).'%' : '-' }}</div>
                        </div>
                    </div>
                </div>
            @endif

            <x-ui.table min-width="520px">
                <x-slot name="head">
                    <tr>
                        <th class="text-left px-4 py-3 whitespace-nowrap">Ringkasan Nominal</th>
                        <th class="text-right px-4 py-3 whitespace-nowrap">Nilai</th>
                    </tr>
                </x-slot>
                @php
                    $criteriaAllocations = $criteriaAllocations ?? [];
                @endphp

                @if(!empty($criteriaAllocations))
                    @foreach($criteriaAllocations as $ca)
                        <tr>
                            <td class="px-4 py-3">{{ $ca['criteria_name'] ?? '-' }}</td>
                            <td class="px-4 py-3 text-right">{{ 'Rp '.number_format((float)($ca['nominal'] ?? 0),0,',','.') }}</td>
                        </tr>
                    @endforeach
                    <tr class="font-semibold">
                        <td class="px-4 py-3">Total</td>
                        <td class="px-4 py-3 text-right">{{ $item->amount !== null ? 'Rp '.number_format($item->amount,0,',','.') : '-' }}</td>
                    </tr>
                @endif

                @if(data_get($calc, 'penalty.total') !== null)
                    <tr>
                        <td class="px-4 py-3">Potongan (penalty)</td>
                        <td class="px-4 py-3 text-right">{{ 'Rp '.number_format((float) data_get($calc, 'penalty.total'),0,',','.') }}</td>
                    </tr>
                @endif
            </x-ui.table>
        </x-section>
    </div>
</x-app-layout>
