<x-app-layout title="Penilaian Saya">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div class="flex items-center justify-between mb-4">
            <h1 class="text-2xl font-semibold">Penilaian Saya</h1>
        </div>

        <x-section title="Daftar Penilaian" class="overflow-hidden">
            <x-ui.table min-width="900px">
                <x-slot name="head">
                    <tr>
                        <th class="text-left px-4 py-3 whitespace-nowrap">Periode</th>
                        <th class="text-left px-4 py-3 whitespace-nowrap">Tanggal</th>
                        <th class="text-left px-4 py-3 whitespace-nowrap">Status</th>
                        <th class="text-left px-4 py-3 whitespace-nowrap">Skor WSM</th>
                        <th class="px-4 py-3 text-right whitespace-nowrap">Aksi</th>
                    </tr>
                </x-slot>
                @forelse($assessments as $a)
                    <tr>
                        <td class="px-4 py-3">{{ $a->assessmentPeriod->name ?? '-' }}</td>
                        <td class="px-4 py-3">{{ optional($a->assessment_date)->format('d M Y') }}</td>
                        <td class="px-4 py-3">{{ $a->validation_status?->value ?? '-' }}</td>
                        <td class="px-4 py-3">{{ $a->total_wsm_score !== null ? number_format($a->total_wsm_score, 2) : '-' }}</td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2 justify-end">
                                <a href="{{ route('pegawai_medis.assessments.show', $a) }}" class="px-3 py-1.5 rounded-md ring-1 ring-slate-200 text-slate-700 hover:bg-slate-50">Lihat</a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-6 text-center text-slate-500">Belum ada penilaian.</td>
                    </tr>
                @endforelse
            </x-ui.table>
        </x-section>

        <div class="mt-4">{{ $assessments->links() }}</div>
    </div>
</x-app-layout>
