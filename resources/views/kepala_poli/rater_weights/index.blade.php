<x-app-layout title="Approval Bobot Penilai 360">
    <x-slot name="header">
        <h1 class="text-2xl font-semibold text-slate-800">Approval Bobot Penilai 360</h1>
    </x-slot>

    <div class="container-px py-6 space-y-6">
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5">
            <form method="GET" class="grid gap-4 md:grid-cols-12 items-end">
                <div class="md:col-span-3">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Periode</label>
                    <x-ui.select name="assessment_period_id" :options="$periods->pluck('name','id')" :value="request('assessment_period_id')" placeholder="Semua" />
                </div>
                <div class="md:col-span-3">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Kriteria</label>
                    <x-ui.select name="performance_criteria_id" :options="$criteriaOptions" :value="request('performance_criteria_id')" placeholder="Semua" />
                </div>
                <div class="md:col-span-3">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Profesi</label>
                    <x-ui.select name="assessee_profession_id" :options="$professions->pluck('name','id')" :value="request('assessee_profession_id')" placeholder="Semua" />
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Status</label>
                    <x-ui.select name="status" :options="['pending'=>'Pending','active'=>'Aktif','rejected'=>'Ditolak','archived'=>'Arsip','all'=>'Semua']" :value="$filters['status'] ?? 'pending'" />
                </div>
                <div class="md:col-span-1 flex gap-2">
                    <x-ui.button type="submit" variant="outline" class="w-full">Filter</x-ui.button>
                </div>
            </form>
        </div>

        @if(($pendingCount ?? 0) > 0)
            <div class="p-4 rounded-xl bg-amber-50 border border-amber-200 text-amber-800 text-sm">
                Ada <span class="font-semibold">{{ $pendingCount }}</span> bobot penilai 360 menunggu persetujuan.
            </div>
        @endif

        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-x-auto">
            <x-ui.table min-width="1040px">
                <x-slot name="head">
                    <tr>
                        <th class="px-6 py-4 text-left">Periode</th>
                        <th class="px-6 py-4 text-left">Unit</th>
                        <th class="px-6 py-4 text-left">Kriteria</th>
                        <th class="px-6 py-4 text-left">Profesi</th>
                        <th class="px-6 py-4 text-left">Tipe Penilai</th>
                        <th class="px-6 py-4 text-right">Bobot (%)</th>
                        <th class="px-6 py-4 text-left">Status</th>
                        <th class="px-6 py-4 text-left">Pengusul</th>
                        <th class="px-6 py-4 text-left">Diputuskan</th>
                        <th class="px-6 py-4 text-right">Aksi</th>
                    </tr>
                </x-slot>

                @forelse($items as $row)
                    @php($st = $row->status?->value ?? (string) $row->status)
                    <tr class="hover:bg-slate-50">
                        <td class="px-6 py-4">{{ $row->period?->name ?? '-' }}</td>
                        <td class="px-6 py-4">{{ $row->unit?->name ?? '-' }}</td>
                        <td class="px-6 py-4">{{ $row->criteria?->name ?? '-' }}</td>
                        <td class="px-6 py-4">{{ $row->assesseeProfession?->name ?? '-' }}</td>
                        <td class="px-6 py-4">{{ $row->assessor_label }}</td>
                        <td class="px-6 py-4 text-right">{{ number_format((float) $row->weight, 2) }}</td>
                        <td class="px-6 py-4">
                            @if($st==='active')
                                <span class="px-2 py-1 rounded text-xs bg-emerald-100 text-emerald-700">Aktif</span>
                            @elseif($st==='pending')
                                <span class="px-2 py-1 rounded text-xs bg-amber-100 text-amber-700">Pending</span>
                            @elseif($st==='rejected')
                                <span class="px-2 py-1 rounded text-xs bg-rose-100 text-rose-700">Ditolak</span>
                            @elseif($st==='archived')
                                <span class="px-2 py-1 rounded text-xs bg-slate-100 text-slate-700">Arsip</span>
                            @else
                                <span class="px-2 py-1 rounded text-xs bg-slate-100 text-slate-700">Draft</span>
                            @endif
                        </td>
                        <td class="px-6 py-4">{{ $row->proposedBy?->name ?? '-' }}</td>
                        <td class="px-6 py-4">
                            @if($row->decided_at)
                                <div class="text-sm text-slate-700">{{ $row->decidedBy?->name ?? '-' }}</div>
                                <div class="text-xs text-slate-500">{{ $row->decided_at?->format('d/m/Y H:i') }}</div>
                            @else
                                <span class="text-sm text-slate-500">-</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-right">
                            @if($st==='pending')
                                <div class="inline-flex gap-2">
                                    <form method="POST" action="{{ route('kepala_poliklinik.rater_weights.approve', $row) }}" onsubmit="return confirm('Setujui dan aktifkan bobot ini? Bobot aktif sebelumnya (jika ada) akan diarsipkan.')">
                                        @csrf
                                        <x-ui.button type="submit" variant="violet" class="h-10 px-4">Approve</x-ui.button>
                                    </form>
                                    <x-ui.button type="button" variant="danger" class="h-10 px-4" x-on:click="$dispatch('open-modal', 'rw-reject-{{ (int) $row->id }}')">Tolak</x-ui.button>

                                    <x-modal name="rw-reject-{{ (int) $row->id }}" :show="false" maxWidth="lg">
                                        <div class="p-6">
                                            <div class="flex items-start justify-between gap-3">
                                                <h2 class="text-lg font-semibold text-slate-800">Tolak Bobot Penilai 360</h2>
                                                <button type="button" class="text-slate-400 hover:text-slate-600" x-on:click="$dispatch('close-modal', 'rw-reject-{{ (int) $row->id }}')">
                                                    <i class="fa-solid fa-xmark"></i>
                                                </button>
                                            </div>

                                            <form method="POST" action="{{ route('kepala_poliklinik.rater_weights.reject', $row) }}" class="mt-4 space-y-4">
                                                @csrf
                                                <div>
                                                    <label class="block text-sm font-medium text-slate-700 mb-1">Komentar</label>
                                                    <textarea name="comment" rows="4" class="w-full rounded-xl border-slate-300 focus:border-slate-400 focus:ring-slate-300" placeholder="Tuliskan alasan penolakan / arahan perbaikan..." required></textarea>
                                                    <div class="mt-1 text-xs text-slate-500">Komentar ini akan terlihat oleh Kepala Unit.</div>
                                                </div>

                                                <div class="flex justify-end gap-2">
                                                    <x-ui.button type="button" variant="outline" x-on:click="$dispatch('close-modal', 'rw-reject-{{ (int) $row->id }}')">Batal</x-ui.button>
                                                    <x-ui.button type="submit" variant="danger">Kirim Penolakan</x-ui.button>
                                                </div>
                                            </form>
                                        </div>
                                    </x-modal>
                                </div>
                            @else
                                <span class="text-xs text-slate-400">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="px-6 py-10 text-center text-sm text-slate-500">Belum ada data.</td>
                    </tr>
                @endforelse
            </x-ui.table>
        </div>

        <div>
            {{ $items->links() }}
        </div>
    </div>
</x-app-layout>
