<x-app-layout title="Review Penilaian (Level 2)">
    <x-slot name="header">
        <h1 class="text-2xl font-semibold">Review Penilaian - Kepala Unit</h1>
    </x-slot>

    <div class="container-px py-6 space-y-6">
        {{-- FILTERS --}}
        <div class="bg-white rounded-2xl shadow-sm p-6 border border-slate-100">
            <form method="GET">
                <div class="grid gap-5 md:grid-cols-12">
                    {{-- Cari --}}
                    <div class="md:col-span-4">
                        <label class="block text-sm font-medium text-slate-600 mb-1">Cari</label>
                        <x-ui.input name="q" placeholder="Nama pegawai / periode" addonLeft="fa-magnifying-glass"
                            :value="$q" class="focus:border-emerald-500 focus:ring-emerald-500" />
                    </div>

                    {{-- Periode --}}
                    <div class="md:col-span-4">
                        <label class="block text-sm font-medium text-slate-600 mb-1">Periode</label>
                        @php
                            // default ke (Semua) jika tidak dipilih
                            $selectedPeriodId = request('period_id', '');
                        @endphp
                        <x-ui.select name="period_id" :options="$periodOptions" :value="$selectedPeriodId"
                            class="focus:border-emerald-500 focus:ring-emerald-500" />
                    </div>

                    {{-- Status --}}
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-slate-600 mb-1">Status</label>
                        @php
                            // Use sentinel "all" so "(Semua)" survives pagination
                            $statusOptions = [
                                'all' => '(Semua)',
                                'pending_l2' => 'Pending (Level 2)',
                                'approved_l2' => 'Approved (Level 2)',
                                'rejected_l2' => 'Rejected (Level 2)',
                                'pending_all' => 'Pending (Semua)',
                                'approved_all' => 'Approved (Semua)',
                                'rejected_all' => 'Rejected (Semua)',
                            ];

                            // default level 2 = pending_l2
                            $selectedStatus = request('status', $status ?? 'pending_l2');
                        @endphp
                        <x-ui.select name="status" :options="$statusOptions" :value="$selectedStatus"
                            class="focus:border-emerald-500 focus:ring-emerald-500" />
                    </div>

                    {{-- Tampil / per page --}}
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-slate-600 mb-1">Tampil</label>
                        @php
                            $perPageSelectOptions = [];
                            foreach ($perPageOptions ?? [] as $n) {
                                $perPageSelectOptions[$n] = $n . ' / halaman';
                            }

                            $selectedPerPage = (int) request('per_page', $perPage ?? 12);
                        @endphp
                        <x-ui.select name="per_page" :options="$perPageSelectOptions" :value="$selectedPerPage"
                            class="focus:border-emerald-500 focus:ring-emerald-500" />
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <a href="{{ route('kepala_unit.assessments.pending') }}" class="inline-flex items-center gap-2 h-12 px-6 rounded-xl text-[15px] font-medium text-slate-600 bg-white border border-slate-200 hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-200 transition-colors">
                        <i class="fa-solid fa-rotate-left"></i> Reset
                    </a>
                    <button type="submit" class="inline-flex items-center gap-2 h-12 px-6 rounded-xl text-[15px] font-medium text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 shadow-sm text-base">
                        <i class="fa-solid fa-filter"></i> Terapkan
                    </button>
                </div>
            </form>
        </div>

        {{-- TABLE --}}
        <x-ui.table min-width="900px">
            <x-slot name="head">
                <tr>
                    <th class="px-6 py-4 text-left whitespace-nowrap">Periode</th>
                    <th class="px-6 py-4 text-left whitespace-nowrap">Pegawai</th>
                    <th class="px-6 py-4 text-left whitespace-nowrap">Skor</th>
                    <th class="px-6 py-4 text-left whitespace-nowrap">Level</th>
                    <th class="px-6 py-4 text-left whitespace-nowrap">Waktu</th>
                    <th class="px-6 py-4 text-left whitespace-nowrap">Status</th>
                    <th class="px-6 py-4 text-right whitespace-nowrap">Aksi</th>
                </tr>
            </x-slot>

            @forelse($items as $it)
                <tr class="hover:bg-slate-50">
                    <td class="px-6 py-4">{{ $it->period_name ?? '-' }}</td>
                    <td class="px-6 py-4">{{ $it->user_name ?? '-' }}</td>
                    <td class="px-6 py-4">{{ $it->total_wsm_score ?? '-' }}</td>
                    <td class="px-6 py-4">{{ $it->level ?? '-' }}</td>

                    @php
                        $st = $it->status ?? 'pending';
                        $submittedAt = $it->created_at
                            ? \Illuminate\Support\Carbon::parse($it->created_at)->format('d M Y H:i')
                            : '-';
                        $processedAt = $it->acted_at
                            ? \Illuminate\Support\Carbon::parse($it->acted_at)->format('d M Y H:i')
                            : ($st === 'pending'
                                ? 'Menunggu'
                                : '-');
                    @endphp

                    <td class="px-6 py-4 text-sm text-slate-600">
                        <div>Diajukan: <span class="font-medium text-slate-800">{{ $submittedAt }}</span></div>
                        <div>Diproses: <span class="font-medium text-slate-800">{{ $processedAt }}</span></div>
                    </td>

                    <td class="px-6 py-4">
                        @if ($st === 'approved')
                            <span
                                class="px-2 py-1 rounded text-xs bg-emerald-100 text-emerald-700">{{ ucfirst($st) }}</span>
                        @elseif ($st === 'rejected')
                            <span class="px-2 py-1 rounded text-xs bg-rose-100 text-rose-700">{{ ucfirst($st) }}</span>
                        @else
                            <span
                                class="px-2 py-1 rounded text-xs bg-amber-100 text-amber-700">{{ ucfirst($st) }}</span>
                        @endif
                    </td>

                    <td class="px-6 py-4 text-right">
                        @php($st = $it->status ?? 'pending')
                        @php($lvl1Approved = (bool) ($it->has_lvl1_approved ?? false))
                        @php($isMyLevel = (int) ($it->level ?? 0) === 2)
                        @php($periodRejected = !empty($it->period_rejected_at) && (string)($it->period_status ?? '') === 'approval')

                        <div class="inline-flex gap-2">
                            <a href="{{ route('kepala_unit.assessments.detail', $it->id) }}"
                               class="inline-flex items-center gap-2 h-9 px-3 rounded-lg text-xs font-medium text-slate-600 bg-white border border-slate-200 hover:bg-slate-50">
                                <i class="fa-solid fa-eye"></i>
                                Detail
                            </a>
                            @if ($isMyLevel && $st === 'pending' && !$periodRejected)
                                <form method="POST" action="{{ route('kepala_unit.assessments.approve', $it->id) }}">
                                    @csrf
                                    <x-ui.button type="submit" variant="success" class="h-9 px-3 text-xs"
                                        :disabled="!$lvl1Approved">
                                        Approve
                                    </x-ui.button>
                                </form>

                                <form method="POST" action="{{ route('kepala_unit.assessments.reject', $it->id) }}"
                                    onsubmit="const note = prompt('Catatan penolakan (wajib):'); if(!note){ return false; } this.note.value = note; return confirm('Tolak penilaian ini?');">
                                    @csrf
                                    <input type="hidden" name="note" value="">
                                    <x-ui.button type="submit" variant="danger" class="h-9 px-3 text-xs">
                                        Reject
                                    </x-ui.button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="px-6 py-8 text-center text-slate-500">
                        Belum ada data.
                    </td>
                </tr>
            @endforelse
        </x-ui.table>

        {{-- FOOTER PAGINATION --}}
        <div class="pt-2 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div class="text-sm text-slate-600">
                Menampilkan
                <span class="font-medium text-slate-800">{{ $items->firstItem() ?? 0 }}</span>
                -
                <span class="font-medium text-slate-800">{{ $items->lastItem() ?? 0 }}</span>
                dari
                <span class="font-medium text-slate-800">{{ $items->total() }}</span>
                data
            </div>

            <div>{{ $items->withQueryString()->links() }}</div>
        </div>
    </div>
</x-app-layout>
