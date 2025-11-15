<x-app-layout title="Remunerasi Saya">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <h1 class="text-2xl font-semibold mb-4">Remunerasi Saya</h1>

        <x-section title="Daftar Remunerasi" class="overflow-hidden">
            <x-ui.table min-width="980px">
                <x-slot name="head">
                    <tr>
                        <th class="text-left px-4 py-3 whitespace-nowrap">Periode</th>
                        <th class="text-left px-4 py-3 whitespace-nowrap">Jumlah</th>
                        <th class="text-left px-4 py-3 whitespace-nowrap">Status Pembayaran</th>
                        <th class="text-left px-4 py-3 whitespace-nowrap">Dipublikasikan</th>
                        <th class="px-4 py-3 text-right whitespace-nowrap">Aksi</th>
                    </tr>
                </x-slot>
                @forelse($items as $item)
                    <tr>
                        <td class="px-4 py-3">{{ $item->assessmentPeriod->name ?? '-' }}</td>
                        <td class="px-4 py-3">{{ $item->amount !== null ? 'Rp '.number_format($item->amount,0,',','.') : '-' }}</td>
                        <td class="px-4 py-3">{{ $item->payment_status?->value ?? '-' }}</td>
                        <td class="px-4 py-3">{{ optional($item->published_at)->format('d M Y H:i') ?? '-' }}</td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2 justify-end">
                                <a href="{{ route('pegawai_medis.remunerations.show', $item->id) }}" class="px-3 py-1.5 rounded-md ring-1 ring-slate-200 text-slate-700 hover:bg-slate-50">Detail</a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-6 text-center text-slate-500">Belum ada data remunerasi.</td>
                    </tr>
                @endforelse
            </x-ui.table>
        </x-section>

        <div class="mt-4">{{ $items->links() }}</div>
    </div>
</x-app-layout>
