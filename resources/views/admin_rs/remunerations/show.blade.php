<x-app-layout title="Detail Remunerasi">
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold text-slate-800">Detail Remunerasi</h1>
            <div class="flex items-center gap-2">
                @if(empty($item->published_at))
                <form method="POST" action="{{ route('admin_rs.remunerations.publish', $item) }}">
                    @csrf
                    <x-ui.button type="submit" variant="success" class="h-10 px-4 text-sm">Publish</x-ui.button>
                </form>
                @endif
                <x-ui.button as="a" href="{{ route('admin_rs.remunerations.index') }}" class="h-10 px-4 text-sm">Kembali</x-ui.button>
            </div>
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
                    <dt class="text-slate-500">Periode</dt>
                    <dd class="text-slate-800 font-medium">{{ $item->assessmentPeriod->name ?? '-' }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">Jumlah</dt>
                    <dd class="text-slate-800 font-medium">{{ number_format((float)($item->amount ?? 0), 2) }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">Status</dt>
                    <dd>
                        @if(!empty($item->published_at))
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-100">Published</span>
                        @else
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-amber-50 text-amber-700 border border-amber-100">Draft</span>
                        @endif
                    </dd>
                </div>
                <div class="md:col-span-2">
                    <dt class="text-slate-500 mb-1">Catatan Perhitungan</dt>
                    <dd>
                        <pre class="bg-slate-50 border border-slate-200 rounded-xl p-4 text-[13px] overflow-auto">{{ json_encode($item->calculation_details, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                    </dd>
                </div>
            </dl>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
            <form method="POST" action="{{ route('admin_rs.remunerations.update', $item) }}" class="space-y-4">
                @csrf
                @method('PUT')
                <div class="grid md:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Tanggal Pembayaran</label>
                        <x-ui.input type="date" name="payment_date" :value="$item->payment_date?->format('Y-m-d')" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Status Pembayaran</label>
                        <x-ui.select name="payment_status" :options="['pending'=>'Pending','paid'=>'Paid','failed'=>'Failed']" :value="(string)$item->payment_status" placeholder="(Pilih)" />
                    </div>
                </div>
                <div class="flex justify-end">
                    <x-ui.button type="submit" class="h-10 px-5">Simpan</x-ui.button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
