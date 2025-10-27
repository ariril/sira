<x-app-layout title="Penilaian Saya">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div class="flex items-center justify-between mb-4">
            <h1 class="text-2xl font-semibold">Penilaian Saya</h1>
        </div>

        <div class="bg-white rounded-xl border overflow-hidden">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-slate-600">
                    <tr>
                        <th class="text-left px-4 py-3">Periode</th>
                        <th class="text-left px-4 py-3">Tanggal</th>
                        <th class="text-left px-4 py-3">Status</th>
                        <th class="text-left px-4 py-3">Skor WSM</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($assessments as $a)
                        <tr class="border-t">
                            <td class="px-4 py-3">{{ $a->assessmentPeriod->name ?? '-' }}</td>
                            <td class="px-4 py-3">{{ optional($a->assessment_date)->format('d M Y') }}</td>
                            <td class="px-4 py-3">{{ $a->validation_status?->value ?? '-' }}</td>
                            <td class="px-4 py-3">{{ $a->total_wsm_score !== null ? number_format($a->total_wsm_score, 2) : '-' }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2 justify-end">
                                    <a href="{{ route('pegawai_medis.assessments.show', $a) }}" class="px-3 py-1.5 border rounded-md text-slate-700 hover:bg-slate-50">Lihat</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-6 text-center text-slate-500">Belum ada penilaian.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $assessments->links() }}</div>
    </div>
</x-app-layout>
