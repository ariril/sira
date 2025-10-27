<x-app-layout title="Detail Remunerasi">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div class="flex items-center justify-between mb-4">
            <h1 class="text-2xl font-semibold">Detail Remunerasi</h1>
            <a href="{{ route('pegawai_medis.remunerations.index') }}" class="px-3 py-2 rounded-lg border">Kembali</a>
        </div>

        <div class="grid sm:grid-cols-2 gap-4 mb-4">
            <div class="p-4 rounded-xl border bg-white">
                <div class="text-sm text-slate-500">Periode</div>
                <div class="text-lg font-semibold">{{ $item->assessmentPeriod->name ?? '-' }}</div>
            </div>
            <div class="p-4 rounded-xl border bg-white">
                <div class="text-sm text-slate-500">Jumlah</div>
                <div class="text-lg font-semibold">{{ $item->amount !== null ? 'Rp '.number_format($item->amount,0,',','.') : '-' }}</div>
            </div>
            <div class="p-4 rounded-xl border bg-white">
                <div class="text-sm text-slate-500">Dipublikasikan</div>
                <div class="text-lg font-semibold">{{ optional($item->published_at)->format('d M Y H:i') ?? '-' }}</div>
            </div>
            <div class="p-4 rounded-xl border bg-white">
                <div class="text-sm text-slate-500">Status Pembayaran</div>
                <div class="text-lg font-semibold">{{ $item->payment_status?->value ?? '-' }}</div>
            </div>
        </div>

        <div class="p-4 rounded-xl border bg-white">
            <h2 class="font-semibold mb-3">Rincian Perhitungan</h2>
            @php $details = $item->calculation_details ?? []; @endphp
            @if(empty($details))
                <div class="text-sm text-slate-500">Tidak ada rincian perhitungan.</div>
            @else
                <pre class="text-xs bg-slate-50 p-3 rounded-lg overflow-auto">{{ json_encode($details, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre>
            @endif
        </div>
    </div>
</x-app-layout>
