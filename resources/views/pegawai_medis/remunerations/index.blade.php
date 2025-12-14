<x-app-layout title="Remunerasi Saya">
    <div class="container-px py-6 space-y-6">
        <h1 class="text-2xl font-semibold text-slate-800">Remunerasi Saya</h1>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
            <x-ui.table min-width="980px">
                <x-slot name="head">
                    <tr>
                        <th class="px-6 py-4 text-left whitespace-nowrap">Periode</th>
                        <th class="px-6 py-4 text-left whitespace-nowrap">Jumlah</th>
                        <th class="px-6 py-4 text-left whitespace-nowrap">Status Pembayaran</th>
                        <th class="px-6 py-4 text-left whitespace-nowrap">Dipublikasikan</th>
                        <th class="px-6 py-4 text-right whitespace-nowrap">Aksi</th>
                    </tr>
                </x-slot>
                @forelse($items as $item)
                    <tr class="hover:bg-slate-50">
                        <td class="px-6 py-4">{{ $item->assessmentPeriod->name ?? '-' }}</td>
                        <td class="px-6 py-4">{{ $item->amount !== null ? 'Rp '.number_format($item->amount,0,',','.') : '-' }}</td>
                        <td class="px-6 py-4">{{ $item->payment_status?->value ?? '-' }}</td>
                        <td class="px-6 py-4">{{ optional($item->published_at)->format('d M Y H:i') ?? '-' }}</td>
                        <td class="px-6 py-4 text-right">
                            <div class="inline-flex items-center gap-2 justify-end">
                                <a href="{{ route('pegawai_medis.remunerations.show', $item->id) }}" class="px-3 py-1.5 rounded-md ring-1 ring-slate-200 text-slate-700 hover:bg-slate-50">Detail</a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-8 text-center text-slate-500">Belum ada data remunerasi.</td>
                    </tr>
                @endforelse
            </x-ui.table>
        </div>

        <div class="pt-2 flex justify-end">{{ $items->withQueryString()->links() }}</div>

        {{-- Status Proses Penilaian & Remunerasi (informasi, tidak mencampur dengan tabel remunerasi) --}}
        @if(($progress ?? collect())->isNotEmpty())
            <x-section title="Status Proses Penilaian & Remunerasi" class="mt-4">
                <div class="space-y-2">
                    @foreach($progress as $p)
                        <div class="flex items-center justify-between rounded-xl ring-1 ring-slate-100 p-3">
                            <div>
                                <div class="font-medium">Periode {{ $p['period_name'] ?? '-' }}</div>
                                <div class="text-sm text-slate-600">Level disetujui: {{ $p['highestApproved'] }} • Status saat ini: 
                                    @if($p['currentStatus'] === 'approved')
                                        <span class="px-2 py-0.5 rounded text-xs bg-emerald-100 text-emerald-700">Approved</span>
                                    @elseif($p['currentStatus'] === 'rejected')
                                        <span class="px-2 py-0.5 rounded text-xs bg-rose-100 text-rose-700">Rejected</span>
                                    @else
                                        <span class="px-2 py-0.5 rounded text-xs bg-amber-100 text-amber-700">Pending</span>
                                    @endif
                                </div>
                            </div>
                            <a href="{{ route('pegawai_medis.assessments.show', $p['assessment_id']) }}" class="px-3 py-1.5 rounded-md ring-1 ring-slate-200 text-slate-700 hover:bg-slate-50">Detail Penilaian</a>
                        </div>
                    @endforeach
                </div>
            </x-section>
        @endif
    </div>
</x-app-layout>
