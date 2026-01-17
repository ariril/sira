<x-app-layout title="Kinerja Saya">
    <x-slot name="header">
        <h1 class="text-2xl font-semibold text-slate-800">Kinerja Saya</h1>
    </x-slot>
    <div class="container-px py-6 space-y-6">
        @if(!$period)
            <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-slate-700">
                Belum ada periode berjalan saat ini.
            </div>
        @else
            <div class="rounded-2xl bg-white border border-slate-100 shadow-sm p-6">
                <div class="text-sm text-slate-600">Periode berjalan</div>
                <div class="text-lg font-semibold text-slate-800">{{ $period->name }}</div>
                <div class="mt-1 text-sm text-slate-600">
                    {{ optional($period->start_date)->format('d M Y') }} - {{ optional($period->end_date)->format('d M Y') }}
                </div>

                @if(!empty($scope['unit']) || !empty($scope['profession']))
                    <div class="mt-2 text-sm text-slate-600">
                        Scope pembanding:
                        <span class="font-medium text-slate-800">{{ $scope['unit'] ?? '-' }}</span>
                        @if(!empty($scope['profession']))
                            <span class="text-slate-400">•</span>
                            <span class="font-medium text-slate-800">{{ $scope['profession'] }}</span>
                        @endif
                    </div>
                @endif

                @php
                    $source = (string) (($data['calculation_source'] ?? null) ?: 'live');
                    $sourceLabel = $source === 'snapshot' ? 'Snapshot' : 'Live';
                    $sourceHint = $source === 'snapshot'
                        ? 'Periode sudah frozen; nilai diambil dari snapshot dan tidak berubah.'
                        : 'Periode masih berjalan; nilai mengikuti data dan konfigurasi terbaru.';
                @endphp
                <div class="mt-2 text-xs text-slate-500" title="{{ $sourceHint }}">
                    Sumber: <span class="px-2 py-0.5 rounded-full border border-slate-200 bg-slate-50 text-slate-700">{{ $sourceLabel }}</span>
                </div>
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                <x-ui.table min-width="960px">
                    <x-slot name="head">
                        <tr>
                            <th class="px-6 py-4 text-left whitespace-nowrap">Kriteria</th>
                            <th class="px-6 py-4 text-right whitespace-nowrap">Bobot</th>
                            <th class="px-6 py-4 text-left whitespace-nowrap">Status</th>
                            <th class="px-6 py-4 text-right whitespace-nowrap">
                                <span>Nilai Mentah</span>
                                <span class="ml-1 text-slate-400" title="Nilai asli sebelum disesuaikan/dirapikan untuk perbandingan.">
                                    <i class="fa-solid fa-circle-exclamation"></i>
                                </span>
                            </th>
                            <th class="px-6 py-4 text-right whitespace-nowrap">
                                <span>Nilai (Normalisasi)</span>
                                <span class="ml-1 text-slate-400" title="Nilai yang sudah disesuaikan agar adil saat dibandingkan dengan pegawai lain dalam unit/scope yang sama.">
                                    <i class="fa-solid fa-circle-exclamation"></i>
                                </span>
                            </th>
                            <th class="px-6 py-4 text-right whitespace-nowrap">
                                <span>Nilai (Relatif)</span>
                                <span class="ml-1 text-slate-400" title="Nilai Relatif = skala ulang dari Nilai Normalisasi terhadap nilai tertinggi pada unit/scope (nilai tertinggi ditampilkan sebagai 100). Berlaku untuk basis total_unit dan Custom Target—mis. bila tidak ada yang mencapai target sehingga max normalisasi < 100, maka nilai tertinggi tersebut menjadi 100 di relatif. Untuk basis max_unit/rata-rata, umumnya Nilai Relatif sama dengan Nilai Normalisasi karena max normalisasi sudah 100.">
                                    <i class="fa-solid fa-circle-exclamation"></i>
                                </span>
                            </th>
                        </tr>
                    </x-slot>

                    @php
                        $rows = is_array($data) ? (array) ($data['criteria'] ?? []) : [];
                    @endphp

                    @forelse($rows as $r)
                        @php
                            $isActive = (bool) ($r['is_active'] ?? false);
                            $readiness = (string) (($r['readiness_status'] ?? null) ?: 'missing_data');
                            $readinessMsg = $r['readiness_message'] ?? null;
                            $weightStatus = (string) (($r['weight_status'] ?? null) ?: 'unknown');
                            $weight = (float) ($r['weight'] ?? 0);

                            $is360 = (bool) ($r['is_360'] ?? false);

                            $weightActive = $weightStatus === 'active' && $weight > 0;
                            $whyNotText = !$weightActive ? 'Bobot belum diatur/aktif.' : '';
                        @endphp
                        <tr class="hover:bg-slate-50">
                            <td class="px-6 py-4">
                                <div class="font-medium text-slate-800">{{ $r['criteria_name'] ?? '-' }}</div>
                                <div class="text-xs text-slate-500">
                                    Basis: {{ $r['normalization_basis'] ?? '-' }}
                                    @if(!empty($r['custom_target_value']))
                                        <span class="text-slate-400">•</span>
                                        Target: {{ number_format((float) $r['custom_target_value'], 2) }}
                                    @endif
                                </div>
                            </td>

                            <td class="px-6 py-4 text-right">
                                {{ number_format($weight, 2) }}
                            </td>

                            <td class="px-6 py-4">
                                <div class="flex flex-wrap items-center gap-2">
                                    @if($weightActive)
                                        <span class="px-2 py-0.5 rounded text-xs bg-emerald-100 text-emerald-700" title="Dihitung ke Skor Kinerja.">Dihitung</span>
                                    @else
                                        <span class="px-2 py-0.5 rounded text-xs bg-amber-100 text-amber-700" title="Ditampilkan saja (tidak dihitung ke Skor Kinerja).">Tidak dihitung</span>
                                        @if($whyNotText !== '')
                                            <span class="text-slate-400" title="{{ $whyNotText }}">
                                                <i class="fa-solid fa-circle-exclamation"></i>
                                            </span>
                                        @endif
                                    @endif

                                    @if(!$isActive)
                                        <span class="px-2 py-0.5 rounded text-xs bg-slate-200 text-slate-700">Nonaktif</span>
                                    @endif

                                    @if($readiness !== 'ready')
                                        <span class="px-2 py-0.5 rounded text-xs bg-slate-100 text-slate-700" title="{{ is_string($readinessMsg) ? $readinessMsg : '' }}">Belum siap</span>
                                        <span class="text-slate-400" title="{{ is_string($readinessMsg) && $readinessMsg !== '' ? $readinessMsg : 'Data belum siap.' }}">
                                            <i class="fa-solid fa-circle-exclamation"></i>
                                        </span>
                                    @endif

                                    @if($weightStatus !== 'active' && $weight > 0)
                                        <span class="px-2 py-0.5 rounded text-xs bg-slate-100 text-slate-700" title="Status bobot: {{ $weightStatus }}">Bobot {{ $weightStatus }}</span>
                                    @endif
                                </div>
                            </td>

                            <td class="px-6 py-4 text-right">
                                @if($is360 && $readiness !== 'ready')
                                    -
                                @else
                                    {{ number_format((float) ($r['raw'] ?? 0), 2) }}
                                @endif
                            </td>

                            <td class="px-6 py-4 text-right">
                                @if($is360 && $readiness !== 'ready')
                                    -
                                @else
                                    {{ number_format((float) ($r['nilai_normalisasi'] ?? 0), 2) }}
                                @endif
                            </td>

                            <td class="px-6 py-4 text-right">
                                @php
                                    $basis = (string) (($r['normalization_basis'] ?? null) ?: '');
                                    $rel = $basis === 'total_unit' ? ($r['nilai_relativ_unit'] ?? null) : null;
                                @endphp
                                @if($is360 && $readiness !== 'ready')
                                    -
                                @else
                                    {{ $rel !== null ? number_format((float) $rel, 2) : '-' }}
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-8 text-center text-slate-500">
                                Data kinerja belum tersedia.
                            </td>
                        </tr>
                    @endforelse
                </x-ui.table>
            </div>
        @endif
    </div>
</x-app-layout>
