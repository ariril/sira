<x-app-layout title="Detail Remunerasi">
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold text-slate-800">Detail Remunerasi</h1>
            <x-ui.button as="a" href="{{ route('kepala_poliklinik.remunerations.index') }}" class="h-10 px-4 text-sm">Kembali</x-ui.button>
        </div>
    </x-slot>

    <div class="container-px py-6 space-y-6">
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
            <dl class="grid md:grid-cols-2 gap-y-4 gap-x-8 text-sm">
                <div>
                    <dt class="text-slate-500">Nama Pegawai</dt>
                    <dd class="text-slate-800 font-medium">{{ $item->user->name ?? '-' }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">Unit</dt>
                    <dd class="text-slate-800 font-medium">{{ $item->user->unit->name ?? '-' }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">Periode</dt>
                    <dd class="text-slate-800 font-medium">{{ $item->assessmentPeriod->name ?? '-' }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">Jumlah</dt>
                    <dd class="text-slate-800 font-medium">{{ number_format((float)($item->amount ?? 0), 2) }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">Publish</dt>
                    <dd>
                        @if(!empty($item->published_at))
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-100">Published</span>
                        @else
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-amber-50 text-amber-700 border border-amber-100">Draft</span>
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-slate-500">Status Pembayaran</dt>
                    <dd class="text-slate-800 font-medium">{{ (string)$item->payment_status }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">Tanggal Pembayaran</dt>
                    <dd class="text-slate-800 font-medium">{{ $item->payment_date?->format('Y-m-d') ?? '-' }}</dd>
                </div>
                <div class="md:col-span-2">
                    <dt class="text-slate-500 mb-1">Catatan Perhitungan</dt>
                    <dd>
                        <pre class="bg-slate-50 border border-slate-200 rounded-xl p-4 text-[13px] overflow-auto">{{ json_encode($item->calculation_details, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                    </dd>
                </div>
            </dl>
        </div>
    </div>
</x-app-layout>
