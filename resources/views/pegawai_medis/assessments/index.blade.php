<x-app-layout title="Penilaian Saya">
    <div class="container-px py-6 space-y-6">
        <h1 class="text-2xl font-semibold text-slate-800">Penilaian Saya</h1>

        @if($activePeriodHasWeights === false)
            <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-amber-800">
                <div class="font-semibold">Skor kinerja periode aktif belum tersedia</div>
                <div class="text-sm">Bobot kriteria untuk periode aktif belum berstatus <span class="font-semibold">aktif</span>. Skor kinerja akan muncul setelah bobot diaktifkan.</div>
            </div>
        @endif

        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
            <x-ui.table min-width="960px">
                <x-slot name="head">
                    <tr>
                        <th class="px-6 py-4 text-left whitespace-nowrap">Periode</th>
                        <th class="px-6 py-4 text-left whitespace-nowrap">Tanggal</th>
                        <th class="px-6 py-4 text-left whitespace-nowrap">Status</th>
                        <th class="px-6 py-4 text-left whitespace-nowrap">
                            <span title="WSM (Weighted Sum Method)">Skor Kinerja</span>
                        </th>
                        <th class="px-6 py-4 text-right whitespace-nowrap">Aksi</th>
                    </tr>
                </x-slot>
                @forelse($assessments as $a)
                    <tr class="hover:bg-slate-50">
                        <td class="px-6 py-4">{{ $a->assessmentPeriod->name ?? '-' }}</td>
                        <td class="px-6 py-4">{{ optional($a->assessment_date)->format('d M Y') }}</td>
                        <td class="px-6 py-4">{{ $a->validation_status?->value ?? '-' }}</td>
                        <td class="px-6 py-4">
                            @php
                                $periodIsActive = (bool) ($a->assessmentPeriod?->is_active ?? false);
                                $kinerjaScore = $kinerjaTotalsByAssessmentId[$a->id] ?? null;
                            @endphp
                            @if($periodIsActive)
                                {{ $kinerjaScore !== null ? number_format((float) $kinerjaScore, 2) : '-' }}
                            @else
                                {{ $a->total_wsm_score !== null ? number_format($a->total_wsm_score, 2) : '-' }}
                            @endif
                        </td>
                        <td class="px-6 py-4 text-right">
                            <div class="inline-flex items-center gap-2 justify-end">
                                <a href="{{ route('pegawai_medis.assessments.show', $a) }}" class="px-3 py-1.5 rounded-md ring-1 ring-slate-200 text-slate-700 hover:bg-slate-50">Lihat</a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-8 text-center text-slate-500">Belum ada penilaian.</td>
                    </tr>
                @endforelse
            </x-ui.table>
        </div>

        <div class="pt-2 flex justify-end">{{ $assessments->withQueryString()->links() }}</div>
    </div>
</x-app-layout>
