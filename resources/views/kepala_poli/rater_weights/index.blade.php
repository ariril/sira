<x-app-layout title="Approval Bobot Penilai 360">
    <x-slot name="header">
        <h1 class="text-2xl font-semibold text-slate-800">Approval Bobot Penilai 360</h1>
    </x-slot>

    <div class="container-px py-6 space-y-6">
        {{-- FILTERS --}}
        <form method="GET" class="bg-white rounded-2xl shadow-sm p-6 border border-slate-100">
            <div class="grid gap-5 md:grid-cols-12">
                <div class="md:col-span-5">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Cari</label>
                    <x-ui.input name="q" placeholder="Unit / kriteria / profesi" value="{{ $filters['q'] ?? '' }}" addonLeft="fa-magnifying-glass" />
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Status</label>
                    <x-ui.select name="status" :options="['all' => '(Semua)','pending'=>'Pending','active'=>'Active','rejected'=>'Rejected','archived'=>'Archived']" :value="$filters['status'] ?? 'pending'" />
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Periode</label>
                    <x-ui.select name="assessment_period_id" :options="$periods->pluck('name','id')" :value="$filters['assessment_period_id'] ?? null" placeholder="Semua" />
                </div>
                <div class="md:col-span-3">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Kriteria</label>
                    <x-ui.select name="performance_criteria_id" :options="$criteriaOptions" :value="$filters['performance_criteria_id'] ?? null" placeholder="Semua" />
                </div>
            </div>

            <div class="grid gap-5 md:grid-cols-12 mt-4">
                <div class="md:col-span-3">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Profesi Dinilai</label>
                    <x-ui.select name="assessee_profession_id" :options="$professions->pluck('name','id')" :value="$filters['assessee_profession_id'] ?? null" placeholder="Semua" />
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <a href="{{ route('kepala_poliklinik.rater_weights.index') }}" class="inline-flex items-center gap-2 h-12 px-6 rounded-xl text-[15px] font-medium text-slate-600 bg-white border border-slate-200 hover:bg-slate-50">
                    <i class="fa-solid fa-rotate-left"></i> Reset
                </a>
                <button type="submit" class="inline-flex items-center gap-2 h-12 px-6 rounded-xl text-[15px] font-medium text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 shadow-sm">
                    <i class="fa-solid fa-filter"></i> Terapkan
                </button>
            </div>
        </form>

        {{-- PER-UNIT COLLAPSIBLE LIST --}}
        <div class="space-y-4">
            @forelse($units as $u)
                @php
                    $unitId = (int) ($u->id ?? 0);
                    $rows = $itemsByUnit->get($unitId) ?? collect();
                    $pendingCount = (int) ($pendingByUnit[$unitId] ?? 0);
                @endphp

                <div class="bg-white rounded-2xl shadow-sm border border-slate-100" x-data="{ open: false, rejecting: false }">
                    <button type="button" class="w-full px-6 py-4 flex items-center justify-between gap-3" x-on:click="open = !open; if (!open) rejecting = false;">
                        <div class="min-w-0 text-left">
                            <div class="text-slate-800 font-semibold truncate">{{ $u->name ?? '-' }}</div>
                            <div class="text-xs text-slate-500">{{ $rows->count() }} bobot</div>
                        </div>
                        <div class="flex items-center gap-2 flex-shrink-0">
                            @if($pendingCount > 0)
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-amber-50 text-amber-700 border border-amber-100">{{ $pendingCount }} pending</span>
                            @endif
                            <i class="fa-solid fa-chevron-down text-slate-400 transition-transform" x-bind:class="open ? 'rotate-180' : ''"></i>
                        </div>
                    </button>

                    <div x-show="open" x-cloak class="px-6 pb-6">
                        <div class="border-t border-slate-100 pt-4">
                            <x-ui.table min-width="960px">
                                <x-slot name="head">
                                    <tr>
                                        <th class="px-6 py-4 text-left whitespace-nowrap">Periode</th>
                                        <th class="px-6 py-4 text-left whitespace-nowrap">Kriteria</th>
                                        <th class="px-6 py-4 text-left whitespace-nowrap">Profesi Dinilai</th>
                                        <th class="px-6 py-4 text-left whitespace-nowrap">Tipe Penilai</th>
                                        <th class="px-6 py-4 text-right whitespace-nowrap">Bobot</th>
                                        <th class="px-6 py-4 text-left whitespace-nowrap">Status</th>
                                    </tr>
                                </x-slot>

                                @forelse($rows as $row)
                                    @php($status = $row->status?->value ?? (string) $row->status ?? 'draft')
                                    <tr class="hover:bg-slate-50">
                                        <td class="px-6 py-4">{{ $row->period?->name ?? '-' }}</td>
                                        <td class="px-6 py-4">{{ $row->criteria?->name ?? '-' }}</td>
                                        <td class="px-6 py-4">{{ $row->assesseeProfession?->name ?? '-' }}</td>
                                        <td class="px-6 py-4">{{ $row->assessor_label }}</td>
                                        <td class="px-6 py-4 text-right">{{ number_format((float) ($row->weight ?? 0), 2) }}</td>
                                        <td class="px-6 py-4">
                                            @switch($status)
                                                @case('active')
                                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-100">Active</span>
                                                    @break
                                                @case('rejected')
                                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-rose-50 text-rose-700 border border-rose-100">Rejected</span>
                                                    @break
                                                @case('pending')
                                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-amber-50 text-amber-700 border border-amber-100">Pending</span>
                                                    @break
                                                @case('archived')
                                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-slate-50 text-slate-700 border border-slate-200">Archived</span>
                                                    @break
                                                @default
                                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-slate-50 text-slate-700 border border-slate-100">Draft</span>
                                            @endswitch
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="px-6 py-8 text-center text-slate-500">Tidak ada data.</td></tr>
                                @endforelse
                            </x-ui.table>

                            @if($pendingCount > 0)
                                <div class="mt-4 flex justify-end gap-2">
                                    <form method="POST" action="{{ route('kepala_poliklinik.rater_weights.approve_unit', $unitId) }}" onsubmit="return confirm('Setujui semua bobot pending pada unit ini?');">
                                        @csrf
                                        <input type="hidden" name="q" value="{{ $filters['q'] ?? '' }}" />
                                        <input type="hidden" name="assessment_period_id" value="{{ $filters['assessment_period_id'] ?? '' }}" />
                                        <input type="hidden" name="performance_criteria_id" value="{{ $filters['performance_criteria_id'] ?? '' }}" />
                                        <input type="hidden" name="assessee_profession_id" value="{{ $filters['assessee_profession_id'] ?? '' }}" />
                                        <x-ui.button type="submit" variant="approve" class="h-10 px-4 text-sm">Setuju</x-ui.button>
                                    </form>
                                    <x-ui.button type="button" variant="reject" class="h-10 px-4 text-sm" x-on:click="rejecting = !rejecting">Tolak</x-ui.button>
                                </div>

                                <div x-show="rejecting" x-cloak class="mt-4 bg-slate-50 border border-slate-200 rounded-2xl p-4">
                                    <form method="POST" action="{{ route('kepala_poliklinik.rater_weights.reject_unit', $unitId) }}" class="space-y-4" onsubmit="return confirm('Tolak semua bobot pending pada unit ini?');">
                                        @csrf
                                        <input type="hidden" name="q" value="{{ $filters['q'] ?? '' }}" />
                                        <input type="hidden" name="assessment_period_id" value="{{ $filters['assessment_period_id'] ?? '' }}" />
                                        <input type="hidden" name="performance_criteria_id" value="{{ $filters['performance_criteria_id'] ?? '' }}" />
                                        <input type="hidden" name="assessee_profession_id" value="{{ $filters['assessee_profession_id'] ?? '' }}" />
                                        <div>
                                            <label class="block text-sm font-medium text-slate-700 mb-1">Catatan (wajib)</label>
                                            <textarea name="comment" rows="4" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-slate-900 placeholder:text-slate-400 shadow-sm focus:outline-none focus:border-slate-400 focus:ring-2 focus:ring-slate-200" placeholder="Tuliskan apa yang perlu diubah..." required></textarea>
                                            <div class="mt-1 text-xs text-slate-500">Catatan ini akan terlihat oleh Kepala Unit.</div>
                                        </div>
                                        <div class="flex justify-end gap-2">
                                            <x-ui.button type="button" variant="outline" x-on:click="rejecting = false">Batal</x-ui.button>
                                            <x-ui.button type="submit" variant="reject">Kirim Penolakan</x-ui.button>
                                        </div>
                                    </form>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="bg-white rounded-2xl shadow-sm p-8 border border-slate-100 text-center text-slate-500">Tidak ada data.</div>
            @endforelse
        </div>

        {{-- FOOTER PAGINATION --}}
        <div class="pt-2 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div class="text-sm text-slate-600">
                Menampilkan
                <span class="font-medium text-slate-800">{{ $units->firstItem() ?? 0 }}</span>
                -
                <span class="font-medium text-slate-800">{{ $units->lastItem() ?? 0 }}</span>
                dari
                <span class="font-medium text-slate-800">{{ $units->total() }}</span>
                unit
            </div>
            <div>{{ $units->withQueryString()->links() }}</div>
        </div>
    </div>
</x-app-layout>
