<x-app-layout title="Penilaian Saya">
    <x-slot name="header">
        <h1 class="text-2xl font-semibold text-slate-800">Penilaian Saya</h1>
    </x-slot>
    <div class="container-px py-6 space-y-6">
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
            <x-ui.table min-width="960px">
                <x-slot name="head">
                    <tr>
                        <th class="px-6 py-4 text-left whitespace-nowrap">Periode</th>
                        <th class="px-6 py-4 text-left whitespace-nowrap">Tanggal</th>
                        <th class="px-6 py-4 text-left whitespace-nowrap">Status</th>
                        <th class="px-6 py-4 text-left whitespace-nowrap">
                            <span>Skor Kinerja</span>
                            <span class="ml-1 text-slate-400" title="Skor Kinerja di sini adalah Skor Kinerja Relatif (0–100), yaitu hasil agregasi nilai relatif berbobot. Angka ini belum dibagi/diratakan terhadap seluruh pegawai; jika dibagi terhadap semua pegawai maka angkanya akan menjadi kecil.">
                                <i class="fa-solid fa-circle-exclamation"></i>
                            </span>
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
                                $kinerjaScore = $kinerjaTotalsByAssessmentId[$a->id] ?? null;
                            @endphp
                            {{ $kinerjaScore !== null ? number_format((float) $kinerjaScore, 2) : ($a->total_wsm_score !== null ? number_format($a->total_wsm_score, 2) : '-') }}
                        </td>
                        <td class="px-6 py-4 text-right">
                            <div class="inline-flex items-center gap-2 justify-end">
                                <a href="{{ route('pegawai_medis.assessments.show', $a) }}" class="px-3 py-1.5 rounded-md ring-1 ring-slate-200 text-slate-700 hover:bg-slate-50">Lihat</a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-8 text-center text-slate-500">
                            Penilaian akan muncul saat periode masuk tahap persetujuan.
                        </td>
                    </tr>
                @endforelse
            </x-ui.table>
        </div>

        <div class="pt-2 flex justify-end">{{ $assessments->withQueryString()->links() }}</div>
    </div>
</x-app-layout>
