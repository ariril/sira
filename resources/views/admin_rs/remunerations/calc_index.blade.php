<x-app-layout title="Perhitungan Remunerasi">
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold text-slate-800">Perhitungan Remunerasi</h1>
            @if (!empty($selectedId))
                <div class="flex items-center gap-3">
                    <form method="POST" action="{{ route('admin_rs.remunerations.calc.audit') }}">
                        @csrf
                        <input type="hidden" name="period_id" value="{{ $selectedId }}" />
                        <x-ui.button type="submit" variant="outline" class="h-12 px-6 text-base">
                            <i class="fa-solid fa-magnifying-glass mr-2"></i> Audit
                        </x-ui.button>
                    </form>
                    <form method="POST" action="{{ route('admin_rs.remunerations.calc.force') }}"
                        onsubmit="return confirm('Jalankan perhitungan paksa? Sistem akan mengisi nilai 0 untuk WSM kosong dan alokasi yang belum dipublish.');">
                        @csrf
                        <input type="hidden" name="period_id" value="{{ $selectedId }}" />
                        <x-ui.button type="submit" variant="danger" class="h-12 px-6 text-base">
                            <i class="fa-solid fa-bolt mr-2"></i> Lakukan Perhitungan
                        </x-ui.button>
                    </form>

                </div>
            @endif
        </div>
    </x-slot>

    <div class="container-px py-6 space-y-6">
        {{-- FILTER PERIODE --}}
        <form method="GET" class="bg-white rounded-2xl shadow-sm p-6 border border-slate-100">
            <div class="grid gap-5 md:grid-cols-12">
                <div class="md:col-span-4">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Periode Penilaian</label>
                    <x-ui.select name="period_id" :options="$periods->pluck('name', 'id')" :value="$selectedId" placeholder="Pilih periode" />
                </div>
            </div>
            <div class="mt-6 flex justify-end gap-3">
                <a href="{{ route('admin_rs.remunerations.calc.index') }}"
                    class="inline-flex items-center gap-2 h-12 px-6 rounded-xl text-[15px] font-medium text-slate-600 bg-white border border-slate-200 hover:bg-slate-50">
                    <i class="fa-solid fa-rotate-left"></i> Atur Ulang
                </a>
                <button type="submit"
                    class="inline-flex items-center gap-2 h-12 px-6 rounded-xl text-[15px] font-medium text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 shadow-sm">
                    <i class="fa-solid fa-filter"></i> Terapkan
                </button>
            </div>
        </form>

        @if (!empty($selectedId))
            {{-- CHECKLIST PRASYARAT --}}
            @if (!empty($prerequisites))
                <div class="bg-white rounded-2xl shadow-sm p-6 border border-slate-100">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <div class="text-base font-semibold text-slate-800">Checklist Prasyarat</div>
                            <div class="text-sm text-slate-600 mt-1">Perhitungan hanya dapat dijalankan jika semua
                                prasyarat terpenuhi.</div>
                        </div>
                        @if (!empty($lastCalculated?->calculated_at))
                            <div class="text-sm text-slate-600 text-right">
                                <div>Terakhir dihitung:</div>
                                <div class="font-medium text-slate-800">
                                    {{ optional($lastCalculated->calculated_at)->format('d M Y H:i') }}
                                    @if (!empty($lastCalculated->revisedBy?->name))
                                        • {{ $lastCalculated->revisedBy->name }}
                                    @endif
                                </div>
                            </div>
                        @endif
                    </div>

                    <div class="mt-4 space-y-2">
                        @foreach ($prerequisites as $pr)
                            <div class="flex items-center justify-between rounded-xl ring-1 ring-slate-100 p-3">
                                <div>
                                    <div class="font-medium">{{ $pr['label'] ?? '-' }}</div>
                                    @if (!empty($pr['detail']))
                                        <div class="text-sm text-slate-600">{{ $pr['detail'] }}</div>
                                    @endif
                                </div>
                                @if (!empty($pr['ok']))
                                    <span
                                        class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-100">OK</span>
                                @else
                                    <span
                                        class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-rose-50 text-rose-700 border border-rose-100">Belum</span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- RINGKASAN --}}
            @php
                $allocated = (float) ($allocSummary['total'] ?? 0);
                $remTotal = (float) ($summary['total'] ?? 0);
                $diff = $allocated - $remTotal;
            @endphp
            <div class="grid gap-5 md:grid-cols-3">
                <x-stat-card label="Total Alokasi Dipublikasikan" value="{{ number_format($allocated, 2) }}"
                    icon="fa-sack-dollar" accent="from-emerald-500 to-teal-600" />
                <x-stat-card label="Total Remunerasi Terhitung" value="{{ number_format($remTotal, 2) }}"
                    icon="fa-wallet" accent="from-sky-500 to-indigo-600" />
                <x-stat-card label="Sisa Belum Tersalurkan" value="{{ number_format(max($diff, 0), 2) }}"
                    icon="fa-circle-exclamation" accent="from-amber-500 to-orange-600" />
            </div>

            {{-- TABEL HASIL --}}
            <x-ui.table min-width="880px">
                <x-slot name="head">
                    <tr>
                        <th class="px-6 py-4 text-left whitespace-nowrap">Nama</th>
                        <th class="px-6 py-4 text-left whitespace-nowrap">Unit</th>
                        <th class="px-6 py-4 text-right whitespace-nowrap">Jumlah</th>
                        <th class="px-6 py-4 text-left whitespace-nowrap">Status Publikasi</th>
                    </tr>
                </x-slot>

                @forelse($remunerations as $it)
                    <tr class="hover:bg-slate-50">
                        <td class="px-6 py-4">{{ $it->user->name ?? '-' }}</td>
                        <td class="px-6 py-4">{{ $it->user->unit->name ?? '-' }}</td>
                        <td class="px-6 py-4 text-right">{{ number_format((float) ($it->amount ?? 0), 2) }}</td>
                        <td class="px-6 py-4">
                            @if (!empty($it->published_at))
                                <span
                                    class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-100">Dipublikasikan</span>
                            @else
                                <span
                                    class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-amber-50 text-amber-700 border border-amber-100">Draf</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-6 py-8 text-center text-slate-500">Belum ada remunerasi terhitung.
                            Jalankan perhitungan setelah memilih periode dengan alokasi published.</td>
                    </tr>
                @endforelse
            </x-ui.table>

            @if (session('auditRows') && (int) session('auditPeriodId') === (int) $selectedId)
                <x-section title="Audit (Alokasi vs Kinerja vs Remunerasi)" class="mt-6">
                    <div class="text-sm text-slate-600 mb-3">
                        Audit ini mengecek per grup (unit + profesi): total kinerja, kinerja kosong (belum ada nilai), total perkiraan (alokasi ×
                        porsi) vs total realisasi (data remunerasi), dan jumlah pengguna yang berbeda.
                    </div>

                    <x-ui.table min-width="980px">
                        <x-slot name="head">
                            <tr>
                                <th class="px-6 py-4 text-left whitespace-nowrap">Unit</th>
                                <th class="px-6 py-4 text-left whitespace-nowrap">Profesi</th>
                                <th class="px-6 py-4 text-right whitespace-nowrap">Alokasi</th>
                                <th class="px-6 py-4 text-right whitespace-nowrap">Jumlah Pengguna</th>
                                <th class="px-6 py-4 text-right whitespace-nowrap">Total Kinerja</th>
                                <th class="px-6 py-4 text-right whitespace-nowrap">Kinerja Kosong</th>
                                <th class="px-6 py-4 text-right whitespace-nowrap">Perkiraan</th>
                                <th class="px-6 py-4 text-right whitespace-nowrap">Realisasi</th>
                                <th class="px-6 py-4 text-right whitespace-nowrap">Pengguna Berbeda</th>
                            </tr>
                        </x-slot>
                        @php
                            $auditRows = (array) session('auditRows', []);
                        @endphp

                        @if (count($auditRows) > 0)
                            @foreach ($auditRows as $r)
                                @php
                                    $wsmTotal = (float) ($r['wsm_total'] ?? 0);
                                    $wsmNull = (int) ($r['wsm_null'] ?? 0);
                                    $diffUsers = (int) ($r['diff_users'] ?? 0);
                                    $mode = (string) ($r['mode'] ?? '');
                                    $sumExpected = (float) ($r['sum_expected'] ?? 0);
                                    $sumActual = (float) ($r['sum_actual'] ?? 0);
                                    $nominalMismatch = abs($sumExpected - $sumActual) >= 0.01;

                                    // Highlight only true problems:
                                    // - WSM NULL (incomplete inputs)
                                    // - WSM total <= 0 in TOTAL_UNIT mode (cannot distribute)
                                    // - total expected != total actual (nominal mismatch)
                                    // Diff Users is informational (rounding/adjustments can cause per-user diffs).
                                    $warn = $wsmNull > 0 || ($mode === 'total_unit_proportional' && $wsmTotal <= 0) || $nominalMismatch;
                                @endphp

                                <tr class="{{ $warn ? 'bg-amber-50' : '' }}">
                                    <td class="px-6 py-4">{{ $r['unit'] ?? '-' }}</td>
                                    <td class="px-6 py-4">{{ $r['profession'] ?? '-' }}</td>
                                    <td class="px-6 py-4 text-right">
                                        {{ number_format((float) ($r['allocation'] ?? 0), 2) }}</td>
                                    <td class="px-6 py-4 text-right">{{ (int) ($r['users'] ?? 0) }}</td>
                                    <td class="px-6 py-4 text-right">
                                        {{ number_format((float) ($r['wsm_total'] ?? 0), 2) }}</td>
                                    <td class="px-6 py-4 text-right">{{ (int) ($r['wsm_null'] ?? 0) }}</td>
                                    <td class="px-6 py-4 text-right">
                                        {{ number_format((float) ($r['sum_expected'] ?? 0), 2) }}</td>
                                    <td class="px-6 py-4 text-right">
                                        {{ number_format((float) ($r['sum_actual'] ?? 0), 2) }}</td>
                                    <td class="px-6 py-4 text-right">{{ (int) ($r['diff_users'] ?? 0) }}</td>
                                </tr>
                            @endforeach
                        @else
                            <tr>
                                <td colspan="9" class="px-6 py-8 text-center text-slate-500">
                                    Audit tidak memiliki data untuk ditampilkan.
                                </td>
                            </tr>
                        @endif


                    </x-ui.table>
                </x-section>
            @endif
        @endif
    </div>
</x-app-layout>
