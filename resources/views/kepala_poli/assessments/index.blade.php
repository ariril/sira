<x-app-layout title="Review Penilaian (Level 3)">
    <x-slot name="header">
        <h1 class="text-2xl font-semibold">Review Penilaian - Kepala Poliklinik</h1>
    </x-slot>

    <div class="container-px py-6 space-y-6">
        {{-- FILTERS --}}
        <div class="bg-white rounded-2xl shadow-sm p-6 border border-slate-100">
            <form method="GET">
                <div class="grid gap-5 md:grid-cols-12">
                    <div class="md:col-span-6">
                        <label class="block text-sm font-medium text-slate-600 mb-1">Cari</label>
                        <x-ui.input name="q" placeholder="Nama pegawai / periode" addonLeft="fa-magnifying-glass"
                                    :value="$q" class="focus:border-emerald-500 focus:ring-emerald-500" />
                    </div>

                    <div class="md:col-span-3">
                        <label class="block text-sm font-medium text-slate-600 mb-1">Status</label>
                        <x-ui.select name="status"
                                     :options="['pending'=>'Pending','approved'=>'Approved','rejected'=>'Rejected']"
                                     :value="request('status', $status)"
                                     placeholder="(Semua)"
                                     class="focus:border-emerald-500 focus:ring-emerald-500" />
                    </div>

                    <div class="md:col-span-3">
                        <label class="block text-sm font-medium text-slate-600 mb-1">Tampil</label>
                        <x-ui.select
                            name="per_page"
                            :options="collect($perPageOptions)->mapWithKeys(fn($n) => [$n => $n.' / halaman'])->all()"
                            :value="(int)request('per_page', $perPage)"
                            class="focus:border-emerald-500 focus:ring-emerald-500"
                        />
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <a href="{{ route('kepala_poliklinik.assessments.pending') }}"
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
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
            <table class="min-w-full">
                <thead class="bg-slate-50 text-slate-600 text-xs uppercase tracking-wide">
                <tr>
                        <th class="px-6 py-4 text-left">Periode</th>
                        <th class="px-6 py-4 text-left">Pegawai</th>
                        <th class="px-6 py-4 text-left">Skor</th>
                        <th class="px-6 py-4 text-left">Level</th>
                        <th class="px-6 py-4 text-left">Status</th>
                        <th class="px-6 py-4 text-right">Aksi</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm">
                @forelse($items as $it)
                    <tr class="hover:bg-slate-50">
                        <td class="px-6 py-4">{{ $it->period_name ?? '-' }}</td>
                        <td class="px-6 py-4">{{ $it->user_name ?? '-' }}</td>
                        <td class="px-6 py-4">{{ $it->total_wsm_score ?? '-' }}</td>
                        <td class="px-6 py-4">{{ $it->level ?? '-' }}</td>
                        <td class="px-6 py-4">
                            @php($st = $it->status ?? 'pending')
                            @if($st==='approved')
                                <span class="px-2 py-1 rounded text-xs bg-emerald-100 text-emerald-700">{{ ucfirst($st) }}</span>
                            @elseif($st==='rejected')
                                <span class="px-2 py-1 rounded text-xs bg-rose-100 text-rose-700">{{ ucfirst($st) }}</span>
                            @else
                                <span class="px-2 py-1 rounded text-xs bg-amber-100 text-amber-700">{{ ucfirst($st) }}</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-right">
                            @php($st = $it->status ?? 'pending')
                            <div class="inline-flex gap-2">
                                @if($st !== 'approved')
                                    <form method="POST" action="{{ route('kepala_poliklinik.assessments.approve', $it->id) }}">
                                        @csrf
                                        <x-ui.button type="submit" variant="success" class="h-9 px-3 text-xs">Approve</x-ui.button>
                                    </form>
                                @endif

                                <form method="POST" action="{{ route('kepala_poliklinik.assessments.reject', $it->id) }}" onsubmit="return confirm('Tolak penilaian ini?')">
                                    @csrf
                                    <input type="hidden" name="note" value="Ditolak oleh Kepala Poliklinik">
                                    <x-ui.button type="submit" variant="outline" class="h-9 px-3 text-xs">
                                        Reject
                                    </x-ui.button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-6 py-8 text-center text-slate-500">Belum ada data.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

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

            <div>
                {{ $items->links() }}
            </div>
        </div>

    </div>
</x-app-layout>
