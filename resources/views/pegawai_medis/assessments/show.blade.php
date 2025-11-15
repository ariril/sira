<x-app-layout title="Detail Penilaian">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div class="flex items-center justify-between mb-4">
            <h1 class="text-2xl font-semibold">Detail Penilaian</h1>
            <div class="flex items-center gap-2">
                <a href="{{ route('pegawai_medis.assessments.index') }}" class="px-3 py-2 rounded-lg border">Kembali</a>
            </div>
        </div>

        <div class="grid sm:grid-cols-2 gap-4 mb-4">
            <div class="p-4 rounded-2xl bg-white shadow-sm ring-1 ring-slate-100">
                <div class="text-sm text-slate-500">Periode</div>
                <div class="text-lg font-semibold">{{ $assessment->assessmentPeriod->name ?? '-' }}</div>
            </div>
            <div class="p-4 rounded-2xl bg-white shadow-sm ring-1 ring-slate-100">
                <div class="text-sm text-slate-500">Tanggal</div>
                <div class="text-lg font-semibold">{{ optional($assessment->assessment_date)->format('d M Y') }}</div>
            </div>
            <div class="p-4 rounded-2xl bg-white shadow-sm ring-1 ring-slate-100">
                <div class="text-sm text-slate-500">Status</div>
                <div class="text-lg font-semibold">{{ $assessment->validation_status?->value ?? '-' }}</div>
            </div>
            <div class="p-4 rounded-2xl bg-white shadow-sm ring-1 ring-slate-100">
                <div class="text-sm text-slate-500">Skor WSM</div>
                <div class="text-lg font-semibold">{{ $assessment->total_wsm_score !== null ? number_format($assessment->total_wsm_score, 2) : '-' }}</div>
            </div>
        </div>

        <x-section title="Detail Kriteria" class="overflow-hidden">
            <x-ui.table min-width="640px">
                <x-slot name="head">
                    <tr>
                        <th class="text-left px-4 py-3 whitespace-nowrap">Kriteria</th>
                        <th class="text-left px-4 py-3 whitespace-nowrap">Tipe</th>
                        <th class="text-left px-4 py-3 whitespace-nowrap">Nilai</th>
                    </tr>
                </x-slot>
                @forelse($assessment->details as $d)
                    <tr>
                        <td class="px-4 py-3">{{ $d->performanceCriteria->name ?? '-' }}</td>
                        <td class="px-4 py-3">{{ optional($d->performanceCriteria->type)->value }}</td>
                        <td class="px-4 py-3">{{ number_format($d->score, 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="px-4 py-6 text-center text-slate-500">Belum ada detail penilaian.</td>
                    </tr>
                @endforelse
            </x-ui.table>
        </x-section>
    </div>
</x-app-layout>
