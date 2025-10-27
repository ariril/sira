<x-app-layout title="Remunerasi Saya">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <h1 class="text-2xl font-semibold mb-4">Remunerasi Saya</h1>

        <div class="bg-white rounded-xl border overflow-hidden">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-slate-600">
                    <tr>
                        <th class="text-left px-4 py-3">Periode</th>
                        <th class="text-left px-4 py-3">Jumlah</th>
                        <th class="text-left px-4 py-3">Status Pembayaran</th>
                        <th class="text-left px-4 py-3">Dipublikasikan</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($items as $item)
                        <tr class="border-t">
                            <td class="px-4 py-3">{{ $item->assessmentPeriod->name ?? '-' }}</td>
                            <td class="px-4 py-3">{{ $item->amount !== null ? 'Rp '.number_format($item->amount,0,',','.') : '-' }}</td>
                            <td class="px-4 py-3">{{ $item->payment_status?->value ?? '-' }}</td>
                            <td class="px-4 py-3">{{ optional($item->published_at)->format('d M Y H:i') ?? '-' }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2 justify-end">
                                    <a href="{{ route('pegawai_medis.remunerations.show', $item->id) }}" class="px-3 py-1.5 border rounded-md text-slate-700 hover:bg-slate-50">Detail</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-6 text-center text-slate-500">Belum ada data remunerasi.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $items->links() }}</div>
    </div>
</x-app-layout>
