<x-app-layout title="Tugas Tambahan Tersedia">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">
        <h1 class="text-2xl font-semibold">Tugas Tambahan Tersedia</h1>

        @unless($activePeriod ?? null)
            <div class="rounded-xl border border-rose-200 bg-rose-50 text-rose-800 px-4 py-3 text-sm">
                <div class="font-semibold">Tidak ada periode yang aktif saat ini.</div>
                <div>Klaim tugas hanya dapat dilakukan ketika periode berstatus ACTIVE.</div>
            </div>
        @endunless

        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-4">
            <x-ui.table min-width="1000px">
                <x-slot name="head">
                    <tr>
                        <th class="text-left px-4 py-3 whitespace-nowrap">Judul</th>
                        <th class="text-left px-4 py-3 whitespace-nowrap">Periode</th>
                        <th class="text-left px-4 py-3 whitespace-nowrap">Due</th>
                        <th class="text-left px-4 py-3 whitespace-nowrap">Poin</th>
                        <th class="text-left px-4 py-3 whitespace-nowrap">Bonus</th>
                        <th class="text-left px-4 py-3 whitespace-nowrap">Aktif / Kuota</th>
                        <th class="px-4 py-3 text-right whitespace-nowrap">Aksi</th>
                    </tr>
                </x-slot>
                @forelse($tasks as $t)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3">{{ $t->title }}</td>
                        <td class="px-4 py-3">{{ $t->period_name ?? '-' }}</td>
                        <td class="px-4 py-3">{{ $t->due_date }}</td>
                        <td class="px-4 py-3">{{ $t->points ?? '-' }}</td>
                        <td class="px-4 py-3">{{ $t->bonus_amount ? 'Rp '.number_format($t->bonus_amount,0,',','.') : '-' }}</td>
                        <td class="px-4 py-3">{{ $t->active_claims }} / {{ $t->max_claims ?? '∞' }}</td>
                        <td class="px-4 py-3 text-right">
                            @if($activePeriod ?? null)
                                <form method="POST" action="{{ route('pegawai_medis.additional_tasks.claim', $t->id) }}">
                                    @csrf
                                    <x-ui.button variant="orange" class="h-9 px-3 text-xs">Klaim</x-ui.button>
                                </form>
                            @else
                                <span class="text-xs text-slate-500">Periode tidak aktif</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-6 text-center text-slate-500">Tidak ada tugas tersedia saat ini.</td>
                    </tr>
                @endforelse
            </x-ui.table>
        </div>
    </div>
</x-app-layout>
