<x-app-layout title="Bobot Penilai 360">
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h1 class="text-2xl font-semibold text-slate-800">Bobot Penilai 360</h1>
            <x-ui.button variant="success" as="a" href="{{ route('kepala_unit.rater_weights.create') }}">Tambah Draft</x-ui.button>
        </div>
    </x-slot>

    <div class="container-px py-6 space-y-6">
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5">
            <form method="GET" class="grid gap-4 md:grid-cols-12 items-end">
                <div class="md:col-span-4">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Periode</label>
                    <x-ui.select name="assessment_period_id" :options="$periods->pluck('name','id')" :value="request('assessment_period_id')" placeholder="Semua" />
                </div>
                <div class="md:col-span-4">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Profesi</label>
                    <x-ui.select name="assessee_profession_id" :options="$professions->pluck('name','id')" :value="request('assessee_profession_id')" placeholder="Semua" />
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Status</label>
                    <x-ui.select name="status" :options="$statuses" :value="request('status')" placeholder="Semua" />
                </div>
                <div class="md:col-span-2 flex gap-2">
                    <x-ui.button type="submit" variant="success" class="w-full">Filter</x-ui.button>
                </div>
            </form>
        </div>

        @if(!empty($totals))
            @php
                $draftTotal = (float) ($totals['draft_total'] ?? 0);
                $pendingTotal = (float) ($totals['pending_total'] ?? 0);
                $activeTotal = (float) ($totals['active_total'] ?? 0);

                $draftNeedsFix = $draftTotal > 0 && round($draftTotal, 2) !== 100.00;
                $pendingNeedsFix = $pendingTotal > 0 && round($pendingTotal, 2) !== 100.00;
                $activeNeedsFix = $activeTotal > 0 && round($activeTotal, 2) !== 100.00;
                $needsFix = $draftNeedsFix || $pendingNeedsFix || $activeNeedsFix;
            @endphp

            <div class="p-4 rounded-xl border text-sm {{ $needsFix ? 'bg-amber-50 border-amber-200 text-amber-800' : 'bg-emerald-50 border-emerald-200 text-emerald-800' }}">
                <div class="font-semibold">Jumlah bobot harus 100% per (Periode, Profesi).</div>
                <div class="mt-1">
                    Draft/Revisi: <span class="font-semibold">{{ number_format($draftTotal, 2) }}%</span>
                    &nbsp;|&nbsp; Pending: <span class="font-semibold">{{ number_format($pendingTotal, 2) }}%</span>
                    &nbsp;|&nbsp; Aktif: <span class="font-semibold">{{ number_format($activeTotal, 2) }}%</span>
                </div>
            </div>
        @endif

        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-x-auto">
            <x-ui.table min-width="960px">
                <x-slot name="head">
                    <tr>
                        <th class="px-6 py-4 text-left">Periode</th>
                        <th class="px-6 py-4 text-left">Profesi</th>
                        <th class="px-6 py-4 text-left">Tipe Penilai</th>
                        <th class="px-6 py-4 text-right">Bobot (%)</th>
                        <th class="px-6 py-4 text-left">Status</th>
                        <th class="px-6 py-4 text-right">Aksi</th>
                    </tr>
                </x-slot>

                @forelse($items as $row)
                    @php($st = $row->status?->value ?? (string) $row->status)
                    <tr class="hover:bg-slate-50">
                        <td class="px-6 py-4">{{ $row->period?->name ?? '-' }}</td>
                        <td class="px-6 py-4">{{ $row->assesseeProfession?->name ?? '-' }}</td>
                        <td class="px-6 py-4">{{ $assessorTypes[$row->assessor_type] ?? $row->assessor_type }}</td>
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
                        <td class="px-6 py-4">
                            <div class="flex justify-end gap-2">
                                @if(in_array($st, ['draft','rejected']))
                                    <x-ui.button as="a" href="{{ route('kepala_unit.rater_weights.edit', $row) }}" variant="outline" class="h-10 px-4">Edit</x-ui.button>

                                    <form method="POST" action="{{ route('kepala_unit.rater_weights.submit', $row) }}" onsubmit="return confirm('Ajukan bobot ini untuk persetujuan?')">
                                        @csrf
                                        <x-ui.button type="submit" variant="orange" class="h-10 px-4">Ajukan</x-ui.button>
                                    </form>
                                @endif

                                @if($st==='draft')
                                    <form method="POST" action="{{ route('kepala_unit.rater_weights.destroy', $row) }}" onsubmit="return confirm('Hapus draft ini?')">
                                        @csrf
                                        @method('DELETE')
                                        <x-ui.button type="submit" variant="danger" class="h-10 px-4">Hapus</x-ui.button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-6 py-10 text-center text-sm text-slate-500">Belum ada data.</td>
                    </tr>
                @endforelse
            </x-ui.table>
        </div>

        <div>
            {{ $items->links() }}
        </div>
    </div>
</x-app-layout>
