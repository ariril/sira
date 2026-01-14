<x-app-layout title="Periode Penilaian (Admin RS)">
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold text-slate-800">Periode Penilaian</h1>
            <x-ui.button as="a" href="{{ route('admin_rs.assessment-periods.create') }}" variant="success" class="h-12 px-6 text-base">
                <i class="fa-solid fa-plus mr-2"></i> Tambah Periode
            </x-ui.button>
        </div>
    </x-slot>

    <div class="container-px py-6 space-y-6">
        {{-- FILTERS --}}
        <form method="GET" class="bg-white rounded-2xl shadow-sm p-6 border border-slate-100">
            <div class="grid gap-5 md:grid-cols-12">
                <div class="md:col-span-6">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Cari</label>
                    <x-ui.input name="q" placeholder="Nama periode" addonLeft="fa-magnifying-glass" value="{{ $filters['q'] ?? '' }}" />
                </div>

                <div class="md:col-span-4">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Status</label>
                    <x-ui.select name="status" :options="\App\Models\AssessmentPeriod::statusOptions()" :value="$filters['status'] ?? null" placeholder="(Semua)" />
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Tampil</label>
                    <x-ui.select name="per_page"
                                 :options="collect($perPageOptions)->mapWithKeys(fn($n) => [$n => $n.' / halaman'])->all()"
                                 :value="(int)request('per_page', $perPage)" />
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <a href="{{ route('admin_rs.assessment-periods.index') }}"
                   class="inline-flex items-center gap-2 h-12 px-6 rounded-xl text-[15px] font-medium text-slate-600 bg-white border border-slate-200 hover:bg-slate-50">
                    <i class="fa-solid fa-rotate-left"></i>
                    Reset
                </a>

                <button type="submit"
                        class="inline-flex items-center gap-2 h-12 px-6 rounded-xl text-[15px] font-medium text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 shadow-sm">
                    <i class="fa-solid fa-filter"></i>
                    Terapkan
                </button>
            </div>
        </form>

        {{-- TABLE --}}
        <x-ui.table min-width="900px">
            <x-slot name="head">
                <tr>
                    <th class="px-6 py-4 text-left whitespace-nowrap">Nama</th>
                    <th class="px-6 py-4 text-left whitespace-nowrap">Tanggal</th>
                    <th class="px-6 py-4 text-left whitespace-nowrap">Status</th>
                    <th class="px-6 py-4 text-right whitespace-nowrap">Aksi</th>
                </tr>
            </x-slot>
            @forelse($items as $it)
                <tr class="hover:bg-slate-50">
                    <td class="px-6 py-4 font-medium text-slate-800">{{ $it->name }}</td>
                    <td class="px-6 py-4 text-slate-600">
                        {{ optional($it->start_date)->format('d M Y') }} - {{ optional($it->end_date)->format('d M Y') }}
                    </td>
                    <td class="px-6 py-4">
                        @if(method_exists($it, 'isCurrentlyActive') && $it->isCurrentlyActive())
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-100">Aktif</span>
                        @else
                            @switch($it->status)
                            @case(\App\Models\AssessmentPeriod::STATUS_LOCKED)
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-amber-50 text-amber-700 border border-amber-100">Dikunci</span>
                                @break
                            @case(\App\Models\AssessmentPeriod::STATUS_APPROVAL)
                                @if(!empty($it->rejected_at))
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-rose-50 text-rose-700 border border-rose-100">Persetujuan (DITOLAK)</span>
                                @else
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-blue-50 text-blue-700 border border-blue-100">Persetujuan</span>
                                @endif
                                @break
                            @case(\App\Models\AssessmentPeriod::STATUS_REVISION)
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-purple-50 text-purple-700 border border-purple-100">Revisi</span>
                                @break
                            @case(\App\Models\AssessmentPeriod::STATUS_CLOSED)
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-slate-200 text-slate-700 border border-slate-300">Ditutup</span>
                                @break
                            @default
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-slate-100 text-slate-700 border border-slate-200">Draft</span>
                            @endswitch
                        @endif
                    </td>
                    <td class="px-6 py-4 text-right">
                        <div class="inline-flex gap-2">
                            @php($today = \Carbon\Carbon::today())
                            {{-- Delete tidak boleh untuk active/locked/approval/closed --}}
                            @php($nonDeletable = \App\Models\AssessmentPeriod::NON_DELETABLE_STATUSES)

                            {{-- Edit tetap tersedia sesuai implementasi sebelumnya (kecuali locked/closed) --}}
                            @if(!in_array($it->status, [\App\Models\AssessmentPeriod::STATUS_LOCKED, \App\Models\AssessmentPeriod::STATUS_CLOSED], true))
                                <x-ui.icon-button as="a" href="{{ route('admin_rs.assessment-periods.edit', $it) }}" icon="fa-pen-to-square" />
                            @endif

                            {{-- Tombol delete hanya saat masih draft dan tidak ada data terkait (akan divalidasi lagi di server) --}}
                            @if(!in_array($it->status, $nonDeletable, true))
                                <form method="POST" action="{{ route('admin_rs.assessment-periods.destroy', $it) }}" onsubmit="return confirm('Hapus periode ini?')">
                                    @csrf
                                    @method('DELETE')
                                    <x-ui.icon-button icon="fa-trash" variant="danger" />
                                </form>
                            @endif

                            @if($it->status === \App\Models\AssessmentPeriod::STATUS_LOCKED)
                                <form method="POST" action="{{ route('admin_rs.assessment_periods.start_approval', $it) }}">
                                    @csrf
                                    <x-ui.button type="submit" variant="success" class="h-9 px-3 text-xs">Mulai Persetujuan</x-ui.button>
                                </form>
                            @endif

                            @if($it->status === \App\Models\AssessmentPeriod::STATUS_APPROVAL && !empty($it->rejected_at))
                                <form method="POST" action="{{ route('admin_rs.assessment_periods.open_revision', $it) }}" class="flex items-center gap-2" onsubmit="return confirm('Buka mode revisi untuk periode ini? Approval akan diulang dari Level 1 setelah resubmit.');">
                                    @csrf
                                    <input type="text" name="reason" required maxlength="800"
                                           class="h-9 px-3 rounded-lg border border-slate-300 text-xs w-52"
                                           placeholder="Alasan Open Revision" />
                                    <x-ui.button type="submit" variant="warning" class="h-9 px-3 text-xs">Open Revision</x-ui.button>
                                </form>
                            @endif

                            @if($it->status === \App\Models\AssessmentPeriod::STATUS_REVISION)
                                <form method="POST" action="{{ route('admin_rs.assessment_periods.resubmit_from_revision', $it) }}" class="flex items-center gap-2" onsubmit="return confirm('Ajukan ulang periode ini ke tahap persetujuan? Proses approval akan mulai ulang dari Level 1.');">
                                    @csrf
                                    <input type="text" name="note" maxlength="800"
                                           class="h-9 px-3 rounded-lg border border-slate-300 text-xs w-52"
                                           placeholder="Catatan (opsional)" />
                                    <x-ui.button type="submit" variant="success" class="h-9 px-3 text-xs">Resubmit</x-ui.button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-6 py-8 text-center text-slate-500">Tidak ada data.</td></tr>
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

    {{-- Warning confirmation modal for pre-approval validation --}}
    @php($approvalWarning = session('approval_warning'))
    @if(!empty($approvalWarning) && !empty($approvalWarning['period_id']) && !empty($approvalWarning['messages']))
        <x-modal name="confirm-period-approval" :show="true" focusable>
            <div class="p-6">
                <h2 class="text-lg font-semibold text-slate-800">Konfirmasi Tahap Persetujuan</h2>
                <p class="mt-2 text-sm text-amber-700">Ada data yang belum tersedia untuk periode ini:</p>

                <ul class="mt-3 space-y-2 text-sm text-slate-700 list-disc list-inside">
                    @foreach(($approvalWarning['messages'] ?? []) as $msg)
                        <li>{{ $msg }}</li>
                    @endforeach
                </ul>

                <div class="mt-6 flex justify-end gap-3">
                    <button type="button"
                            class="inline-flex items-center h-11 px-5 rounded-xl text-[15px] font-medium text-slate-700 bg-white border border-slate-300 hover:bg-slate-50"
                            x-on:click="$dispatch('close-modal', 'confirm-period-approval')">
                        Batal
                    </button>

                    <form method="POST" action="{{ route('admin_rs.assessment_periods.start_approval', (int) $approvalWarning['period_id']) }}">
                        @csrf
                        <input type="hidden" name="force" value="1" />
                        <x-ui.button type="submit" variant="success" class="h-11 px-5">Lanjutkan</x-ui.button>
                    </form>
                </div>
            </div>
        </x-modal>
    @endif
</x-app-layout>
