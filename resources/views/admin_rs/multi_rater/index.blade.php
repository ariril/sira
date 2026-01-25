<x-app-layout title="Penilaian 360">
    <x-slot name="header">
        <h1 class="text-2xl font-semibold text-slate-800">Penilaian 360°</h1>
    </x-slot>

    <div class="container-px py-6 space-y-6">
        <div class="bg-white rounded-2xl shadow-sm border border-emerald-100 p-6 space-y-4">

            @php $activePeriod = $periods->firstWhere('status', 'active'); @endphp
            @php
                $manageablePeriod = $periods->firstWhere('status', 'active')
                    ?? $periods->firstWhere('status', 'revision');
            @endphp
            @unless($manageablePeriod)
                <div class="rounded-xl border border-rose-200 bg-rose-50 text-rose-800 px-4 py-3 text-sm">
                    <div class="font-semibold">Tidak ada periode yang bisa dikelola saat ini.</div>
                    <div>Aktifkan periode (ACTIVE) atau buka revisi (REVISION) di menu Periode Penilaian sebelum mengatur jadwal Penilaian 360.</div>
                </div>
            @endunless

            {{-- Filter periode --}}
            <form method="GET" class="grid md:grid-cols-4 gap-4 items-end">
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Periode</label>
                    <x-ui.select
                        name="assessment_period_id"
                        :options="$periods->pluck('name','id')->toArray()"
                        :value="optional($period)->id"
                    />
                </div>
                <div>
                    <x-ui.button type="submit" variant="success">Lihat</x-ui.button>
                </div>
            </form>

            {{-- Konten jika periode terpilih --}}
            @if ($period)
                @php $isRevision = (string) ($period->status ?? '') === 'revision'; @endphp
                @php
                    $windowValue = $window ?: ($latestWindow ?? null);
                    $windowClosed = $summary['windowClosed'] ?? false;
                    $completeness = $completeness ?? null;
                @endphp
                @php
                    $periodStart = \Illuminate\Support\Carbon::parse($period->start_date);
                    $periodEnd = \Illuminate\Support\Carbon::parse($period->end_date);
                @endphp
                <div class="pt-2 text-sm text-slate-600">
                    Periode: <b>{{ $period->name }}</b>
                    ({{ $periodStart->format('d M Y') }} – {{ $periodEnd->format('d M Y') }})
                </div>

                @if(($period->status !== 'active') && !$window && !($summary['windowClosed'] ?? false))
                    <div class="mt-3 rounded-xl border border-sky-200 bg-sky-50 text-sky-900 px-4 py-3 text-sm space-y-1">
                        <div class="font-semibold">Penilaian 360 tidak dibuka pada periode {{ $period->name }}.</div>
                        <div>Buka jadwal untuk mengaktifkan penilaian 360 pada periode-periode berikutnya.</div>
                    </div>
                @endif

                @if($summary['windowClosed'] ?? false)
                    <div class="mt-3 rounded-xl border border-amber-200 bg-amber-50 text-amber-900 px-4 py-3 text-sm space-y-1">
                        <div class="font-semibold">Penilaian 360 untuk periode ini sudah ditutup.</div>
                        <div>Jumlah penilaian 360 yang sudah terisi: <b>{{ $summary['submittedCount'] ?? 0 }}</b></div>
                        @if($isRevision)
                            <div class="text-amber-800">Karena periode sedang REVISION, Admin RS masih boleh membuka ulang jadwal untuk perbaikan.</div>
                        @endif
                    </div>
                @endif

                {{-- Banner status jadwal aktif --}}
                @if($window)
                    <div class="mt-3 rounded-xl ring-1 ring-emerald-100 bg-gradient-to-r from-emerald-50 to-white p-4 flex items-start gap-3">
                        <i class="fa-solid fa-bullhorn text-emerald-600 mt-0.5"></i>
                        <div class="flex-1">
                            <div class="font-medium text-emerald-800">Penilaian 360 AKTIF</div>
                            <div class="text-sm text-emerald-700 space-y-1">
                                <div>
                                    Jadwal: <b>{{ optional($window->start_date)->format('d M Y') }}</b>
                                    – <b>{{ optional($window->end_date)->format('d M Y') }}</b>
                                </div>
                                <div>Kriteria aktif 360: <b>{{ $summary['active360Criteria'] ?? 0 }}</b></div>
                            </div>
                        </div>
                    </div>
                @endif

                @php
                    $today   = now()->toDateString();
                    $minDate = $isRevision
                        ? $periodStart->toDateString()
                        : max($periodStart->toDateString(), $today);
                    $maxDate = $isRevision
                        ? $periodEnd->copy()->addMonthNoOverflow()->day(10)->toDateString()
                        : $periodEnd->toDateString();
                @endphp

                @if($isRevision)
                    <div class="mt-3 rounded-xl border border-slate-200 bg-slate-50 text-slate-800 px-4 py-3 text-sm">
                        Pada periode <b>REVISION</b>, jadwal penilaian 360 boleh di-extend sampai <b>{{ \Illuminate\Support\Carbon::parse($maxDate)->format('d M Y') }}</b>.
                    </div>
                @endif

                {{-- Form buka/ubah jadwal --}}
                <form
                    id="open-360-window"
                    method="POST"
                    action="{{ route('admin_rs.multi_rater.open') }}"
                    class="grid md:grid-cols-4 gap-4 items-end mt-3"
                >
                    @csrf
                    <input type="hidden" name="assessment_period_id" value="{{ $period->id }}">

                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-slate-600 mb-1">Mulai</label>
                        <x-ui.input
                            type="date"
                            name="start_date"
                            value="{{ optional($windowValue)->start_date?->toDateString() }}"
                            min="{{ $minDate }}"
                            max="{{ $maxDate }}"
                            required
                            :disabled="$windowClosed && !$isRevision"
                        />
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-slate-600 mb-1">Selesai</label>
                        <x-ui.input
                            type="date"
                            name="end_date"
                            value="{{ optional($windowValue)->end_date?->toDateString() }}"
                            min="{{ $minDate }}"
                            max="{{ $maxDate }}"
                            required
                            :disabled="$windowClosed && !$isRevision"
                        />
                    </div>
                </form>

                <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm space-y-2">
                    <div class="font-semibold text-slate-800">Kelengkapan Penilaian 360</div>
                    @if($completeness)
                        @if($completeness['is_complete'])
                            <div class="text-emerald-700">Penilaian 360 sudah lengkap.</div>
                        @else
                            <div class="text-amber-700">Masih ada Penilaian 360 yang belum lengkap.</div>
                            <div>Jumlah kekurangan: <b>{{ $completeness['missing_count'] ?? 0 }}</b></div>
                            <div>
                                <x-ui.button
                                    type="button"
                                    variant="outline"
                                    class="h-9 px-3"
                                    x-data
                                    @click="$dispatch('open-modal', 'detail-kelengkapan-360')"
                                >
                                    Lihat Detail Kekurangan
                                </x-ui.button>
                            </div>
                        @endif
                    @else
                        <div class="text-slate-600">Belum ada data untuk diperiksa.</div>
                    @endif
                </div>

                <div class="flex flex-wrap gap-3 items-center mt-3">
                    <x-ui.button
                        type="submit"
                        form="open-360-window"
                        variant="success"
                        :disabled="$windowClosed && !$isRevision"
                    >
                        Buka / Ubah Jadwal
                    </x-ui.button>
                </div>
            @endif
        </div>
    </div>

    @if($period)
        <x-modal name="detail-kelengkapan-360" maxWidth="2xl">
            @php
                $assesseeSummary = collect($completeness['assessee_summary'] ?? []);
                $totalPeople = $assesseeSummary->count();
                $completeTotal = (int) ($completeness['complete_count_total'] ?? 0);
                $incompleteTotal = (int) ($completeness['incomplete_count_total'] ?? 0);
                $filterOptions = [
                    'all' => 'Semua',
                    'incomplete' => 'Belum lengkap',
                    'complete' => 'Sudah lengkap',
                ];
            @endphp
            <div class="p-6 space-y-4" x-data="{ q: '', filter: 'incomplete' }">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <div class="text-lg font-semibold text-slate-800">Detail Kekurangan Penilaian 360</div>
                        <div class="text-sm text-slate-600">Daftar pegawai yang masih memiliki penilaian 360 belum lengkap.</div>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">
                            Total {{ $totalPeople }} pegawai
                        </span>
                        <span class="inline-flex items-center rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700">
                            {{ $completeTotal }} lengkap
                        </span>
                        <span class="inline-flex items-center rounded-full bg-rose-100 px-3 py-1 text-xs font-semibold text-rose-700">
                            {{ $incompleteTotal }} belum lengkap
                        </span>
                    </div>
                </div>

                <div class="flex flex-wrap gap-3">
                    <div class="flex-1">
                        <x-ui.input
                            type="text"
                            x-model="q"
                            placeholder="Cari nama pegawai"
                            addon-left="fa-magnifying-glass"
                        />
                    </div>
                    <div class="min-w-[200px]">
                        <x-ui.select
                            x-model="filter"
                            :options="$filterOptions"
                        />
                    </div>
                </div>

                <div class="max-h-[60vh] overflow-auto space-y-3">
                    @if($assesseeSummary->isEmpty())
                        <div class="rounded-xl border border-emerald-100 bg-emerald-50 p-4 text-sm text-emerald-800">
                            Tidak ada kekurangan. Penilaian 360 sudah lengkap.
                        </div>
                    @else
                        @foreach($assesseeSummary as $summaryRow)
                            @php
                                $assesseeId = (int) ($summaryRow['assessee_id'] ?? 0);
                                $assesseeName = (string) ($summaryRow['assessee_name'] ?? '-');
                                $unitName = (string) ($summaryRow['unit_name'] ?? '-');
                                $professionName = (string) ($summaryRow['profession_name'] ?? '-');
                                $status = (string) ($summaryRow['status'] ?? 'incomplete');
                                $missingCount = (int) ($summaryRow['missing_count'] ?? 0);
                                $progressGroups = collect($summaryRow['assessor_progress'] ?? [])->mapWithKeys(function ($row) {
                                    $criteria = (string) ($row['criteria_name'] ?? '-');
                                    $assessors = collect($row['assessors'] ?? []);
                                    return [$criteria => $assessors];
                                });
                            @endphp

                            <details
                                class="rounded-xl border border-slate-200 bg-white shadow-sm"
                                x-data="{ name: '{{ strtolower($assesseeName) }}', state: '{{ $status }}' }"
                                x-show="(!q || name.includes(q.toLowerCase())) && (filter === 'all' || filter === state)"
                                @if($loop->first) open @endif
                            >
                                <summary class="cursor-pointer list-none px-4 py-3 flex items-center justify-between gap-3">
                                    <div>
                                        <div class="font-semibold text-slate-800">{{ $assesseeName }}</div>
                                        <div class="text-xs text-slate-500">{{ $unitName }} @if($professionName !== '-') • {{ $professionName }} @endif</div>
                                    </div>
                                    @if($status === 'complete')
                                        <span class="inline-flex items-center rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700">Lengkap</span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-rose-100 px-3 py-1 text-xs font-semibold text-rose-700">Belum lengkap</span>
                                    @endif
                                </summary>
                                <div class="px-4 pb-4 space-y-3">
                                    @if($status === 'complete')
                                        <div class="rounded-lg border border-emerald-100 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
                                            Semua penilaian 360 untuk pegawai ini sudah lengkap.
                                        </div>
                                    @else
                                        <div class="text-xs text-slate-500">{{ $missingCount }} kekurangan</div>
                                    @endif

                                    @if($progressGroups->isEmpty())
                                        <div class="rounded-lg border border-slate-100 bg-slate-50 px-3 py-2 text-sm text-slate-600">
                                            Belum ada data progres penilai.
                                        </div>
                                    @else
                                        @foreach($progressGroups as $criteriaName => $items)
                                            <div class="rounded-lg border border-slate-100 bg-slate-50 px-3 py-2">
                                                <div class="text-sm font-semibold text-slate-800">{{ $criteriaName }}</div>
                                                <ul class="mt-2 space-y-2 text-sm text-slate-700">
                                                    @foreach($items as $item)
                                                        @php
                                                            $statusBadgeClass = ($item['status'] ?? '') === 'filled'
                                                                ? 'bg-emerald-100 text-emerald-700'
                                                                : 'bg-rose-100 text-rose-700';
                                                        @endphp
                                                        <li class="rounded-lg border border-white bg-white px-3 py-2">
                                                            <div class="flex flex-wrap items-center justify-between gap-2">
                                                                <div>
                                                                    <div class="font-medium text-slate-800">{{ $item['relation_label'] ?? '-' }}</div>
                                                                    <div class="text-slate-700">{{ $item['assessor_name'] ?? '-' }}</div>
                                                                    <div class="text-xs text-slate-500">
                                                                        @if(!empty($item['assessor_role_label']))
                                                                            <span>{{ $item['assessor_role_label'] }}</span>
                                                                        @endif
                                                                        @if(!empty($item['profession_name']))
                                                                            <span>@if(!empty($item['assessor_role_label'])) • @endif{{ $item['profession_name'] }}</span>
                                                                        @endif
                                                                        @if(!empty($item['level']))
                                                                            <span> • Level {{ $item['level'] }}</span>
                                                                        @endif
                                                                    </div>
                                                                </div>
                                                                <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold {{ $statusBadgeClass }}">
                                                                    {{ $item['status_label'] ?? '-' }}
                                                                </span>
                                                            </div>
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        @endforeach
                                    @endif
                                </div>
                            </details>
                        @endforeach
                    @endif
                </div>

                <div class="flex justify-end">
                    <x-ui.button type="button" variant="outline" x-data @click="$dispatch('close-modal', 'detail-kelengkapan-360')">Tutup</x-ui.button>
                </div>
            </div>
        </x-modal>

    @endif
</x-app-layout>
