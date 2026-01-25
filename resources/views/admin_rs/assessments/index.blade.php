<x-app-layout title="Review Penilaian (Level 1)">
    <x-slot name="header">
        <h1 class="text-2xl font-semibold">Review Penilaian - Admin RS</h1>
    </x-slot>

    <div class="container-px py-6 space-y-6">
        {{-- FILTERS (match Super Admin style) --}}
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
                            // Default ke (Semua) jika tidak dipilih
                            $selectedPeriodId = request('period_id') ?? '';
                        @endphp
                        <x-ui.select name="period_id" :options="$periodOptions" :value="$selectedPeriodId"
                            class="focus:border-emerald-500 focus:ring-emerald-500" />
                    </div>

                    {{-- Status --}}
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-slate-600 mb-1">Status</label>
                        @php
                            // Use explicit sentinel "all" to avoid empty-string stripping in pagination/query appends
                            $statusOptions = [
                                'all' => '(Semua)',
                                'pending_l1' => 'Pending (Level 1)',
                                'approved_l1' => 'Approved (Level 1)',
                                'rejected_l1' => 'Rejected (Level 1)',
                                'pending_all' => 'Pending (Semua)',
                                'approved_all' => 'Approved (Semua)',
                                'rejected_all' => 'Rejected (Semua)',
                            ];

                            // Default Level 1: pending_l1
                            $selectedStatus = request('status', $status ?? 'pending_l1');
                        @endphp
                        <x-ui.select name="status" :options="$statusOptions" :value="$selectedStatus"
                            class="focus:border-emerald-500 focus:ring-emerald-500" />
                    </div>

                    {{-- Tampil / per page --}}
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-slate-600 mb-1">Tampil</label>
                        @php
                            $perPageSelectOptions = [];
                            if (isset($perPageOptions) && is_iterable($perPageOptions)) {
                                foreach ($perPageOptions as $n) {
                                    $perPageSelectOptions[$n] = $n . ' / halaman';
                                }
                            }
                            $selectedPerPage = (int) request('per_page', $perPage ?? 12);
                        @endphp
                        <x-ui.select name="per_page" :options="$perPageSelectOptions" :value="$selectedPerPage"
                            class="focus:border-emerald-500 focus:ring-emerald-500" />
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <a href="{{ route('admin_rs.assessments.pending') }}"
                        class="inline-flex items-center gap-2 h-12 px-6 rounded-xl text-[15px] font-medium text-slate-600 bg-white border border-slate-200 hover:bg-slate-50">
                        <i class="fa-solid fa-rotate-left"></i>
                        Reset
                    </a>

                    <button type="submit"
                        class="inline-flex items-center gap-2 h-12 px-6 rounded-xl text-[15px] font-medium text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 shadow-sm">
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
                    <td class="px-6 py-4">
                        @php($st = $it->status ?? 'pending')
                        @if ($st === 'approved')
                            <span
                                class="px-2 py-1 rounded text-xs bg-emerald-100 text-emerald-700">{{ ucfirst($st) }}</span>
                        @elseif($st === 'rejected')
                            <span class="px-2 py-1 rounded text-xs bg-rose-100 text-rose-700">{{ ucfirst($st) }}</span>
                        @else
                            <span
                                class="px-2 py-1 rounded text-xs bg-amber-100 text-amber-700">{{ ucfirst($st) }}</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-right">
                        @php($st = $it->status ?? 'pending')
                        @php($lvl = (int) ($it->level ?? 1))
                        @php($lvl2Approved = (bool) ($it->has_lvl2_approved ?? false))
                        @php($periodRejected = !empty($it->period_rejected_at) && (string)($it->period_status ?? '') === 'approval')
                        <div class="inline-flex gap-2">
                            <a href="{{ route('admin_rs.assessments.detail', $it->id) }}"
                               class="inline-flex items-center gap-2 h-9 px-3 rounded-lg text-xs font-medium text-slate-600 bg-white border border-slate-200 hover:bg-slate-50">
                                <i class="fa-solid fa-eye"></i>
                                Detail
                            </a>
                            @if ($st === 'pending' && $lvl === 1 && !$periodRejected)
                                <form method="POST" action="{{ route('admin_rs.assessments.approve', $it->id) }}">
                                    @csrf
                                    <x-ui.button type="submit" variant="success" class="h-9 px-3 text-xs">
                                        Approve
                                    </x-ui.button>
                                </form>

                                @if (!$lvl2Approved)
                                    <form method="POST" action="{{ route('admin_rs.assessments.reject', $it->id) }}"
                                        onsubmit="const note = prompt('Catatan penolakan (wajib):'); if(!note){ return false; } this.note.value = note; return confirm('Tolak penilaian ini?');">
                                        @csrf
                                        <input type="hidden" name="note" value="">
                                        <x-ui.button type="submit" variant="danger" class="h-9 px-3 text-xs">
                                            Reject
                                        </x-ui.button>
                                    </form>
                                @endif
                            @endif

                            @if ($st === 'rejected')
                                <form method="POST" action="{{ route('admin_rs.assessments.resubmit', $it->id) }}"
                                      onsubmit="return confirm('Ajukan ulang penilaian ini?')">
                                    @csrf
                                    <x-ui.button type="submit" variant="warning" class="h-9 px-3 text-xs">
                                        Ajukan Ulang
                                    </x-ui.button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="px-6 py-8 text-center text-slate-500">
                        Belum ada data.
                    </td>
                </tr>
            @endforelse
        </x-ui.table>

        {{-- FOOTER PAGINATION (same as Super Admin) --}}
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
