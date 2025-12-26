@php(
    $summaryData = $summary ?? [
        'periods' => collect(),
        'selected_period' => null,
        'rows' => collect(),
    ]
)

<x-app-layout title="Penilaian 360">
    <x-slot name="header">
        <h1 class="text-2xl font-semibold text-slate-800">Penilaian 360°</h1>
    </x-slot>
    <div class="container-px py-6 space-y-6">
        @include('shared.multi_rater.window_notice', [
            'window' => $window,
            'periodName' => $activePeriod->name ?? null,
        ])

        @if($window && !empty($periodId) && !empty($unitId) && $windowIsActive)
            @include('shared.multi_rater.simple_form', [
                'periodId' => $periodId,
                'unitId' => $unitId,
                'raterRole' => 'pegawai_medis',
                'targets' => $unitPeers,
                'criteriaOptions' => $criteriaOptions,
                'postRoute' => 'pegawai_medis.multi_rater.store',
                'remainingAssignments' => $remainingAssignments,
                'totalAssignments' => $totalAssignments,
                'buttonClasses' => 'bg-gradient-to-r from-sky-500 to-blue-600 hover:from-sky-600 hover:to-blue-700 shadow-sm text-white',
                'savedTableKey' => 'pegawai-medis',
                'canSubmit' => (bool) ($canSubmit ?? false),
            ])
        @endif

        @if(isset($savedScores))
            @php($allowInlineEdit = ($windowEndsAt ?? null) && now()->lte($windowEndsAt) && (bool) ($canSubmit ?? false))
            <div class="mt-6 bg-white rounded-2xl shadow-sm border border-slate-100" data-saved-table-key="pegawai-medis" data-edit-url="{{ route('pegawai_medis.multi_rater.store') }}" data-period-id="{{ $periodId }}" data-csrf="{{ csrf_token() }}" data-allow-inline-edit="{{ $allowInlineEdit ? 'true' : 'false' }}" data-inline-variant="sky">
                <x-ui.table min-width="840px">
                    <x-slot name="head">
                        <tr>
                            <th class="px-6 py-4 text-left whitespace-nowrap">Nama Rekan</th>
                            <th class="px-6 py-4 text-left whitespace-nowrap">Kriteria</th>
                            <th class="px-6 py-4 text-left whitespace-nowrap">Tipe</th>
                            <th class="px-6 py-4 text-left whitespace-nowrap">Nilai</th>
                            <th class="px-6 py-4 text-left whitespace-nowrap">Waktu</th>
                        </tr>
                    </x-slot>
                    @forelse($savedScores as $s)
                        <tr class="hover:bg-slate-50">
                            <td class="px-6 py-4">{{ $s->target_name }}</td>
                            <td class="px-6 py-4">{{ $s->criteria_name ?? 'Umum' }}</td>
                            <td class="px-6 py-4">
                                @if($s->criteria_type)
                                    <span class="px-2 py-1 rounded text-xs border {{ $s->criteria_type === 'cost' ? 'bg-rose-50 text-rose-700 border-rose-200' : 'bg-emerald-50 text-emerald-700 border-emerald-200' }}">
                                        {{ $s->criteria_type === 'cost' ? 'Cost' : 'Benefit' }}
                                    </span>
                                @else
                                    <span class="text-xs text-slate-500">-</span>
                                @endif
                            </td>
                            <td class="px-6 py-3">
                                @if($allowInlineEdit)
                                    <form class="inline-flex items-center gap-2" onsubmit="event.preventDefault(); const fd=new FormData(this); fetch('{{ route('pegawai_medis.multi_rater.store') }}',{method:'POST',headers:{'X-CSRF-TOKEN':'{{ csrf_token() }}','Accept':'application/json'},body:fd}).then(r=>r.json()).then(()=>location.reload());">
                                        <input type="hidden" name="assessment_period_id" value="{{ $periodId }}">
                                        <input type="hidden" name="target_user_id" value="{{ $s->target_user_id }}">
                                        <input type="hidden" name="performance_criteria_id" value="{{ $s->performance_criteria_id }}">
                                        <x-ui.input type="number" min="1" max="100" name="score" :value="$s->score" class="h-10 w-24 text-right text-sm" />
                                        <x-ui.button type="submit" variant="sky" class="h-10 px-4 text-sm font-semibold">Ubah</x-ui.button>
                                    </form>
                                @else
                                    <span class="text-slate-500 text-sm">Tidak dapat diubah</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-slate-600">{{ ($s->updated_at ?? $s->created_at)?->timezone('Asia/Jakarta')->format('d M Y H:i') }}</td>
                        </tr>
                    @empty
                        <tr data-saved-empty>
                            <td colspan="5" class="px-6 py-10 text-center text-slate-500 text-sm">Belum ada penilaian tersimpan.</td>
                        </tr>
                    @endforelse
                </x-ui.table>
            </div>
        @endif

        @include('shared.multi_rater.summary_table', [
            'periods' => $summaryData['periods'] ?? collect(),
            'selectedPeriod' => $summaryData['selected_period'] ?? null,
            'rows' => $summaryData['rows'] ?? collect(),
            'title' => 'Ringkasan Nilai 360 Anda',
            'buttonVariant' => 'sky',
        ])
    </div>
</x-app-layout>
