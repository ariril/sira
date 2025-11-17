<x-app-layout title="Tambah Metric Manual">
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold text-slate-800">Tambah Metric Manual</h1>
            <x-ui.button as="a" href="{{ route('admin_rs.metrics.index') }}" variant="outline" class="h-12 px-6 text-base">Kembali</x-ui.button>
        </div>
    </x-slot>

    <div class="container-px py-6 space-y-6">
        <div class="bg-white rounded-2xl shadow-sm p-6 border border-slate-100">
            <form method="POST" action="{{ route('admin_rs.metrics.store') }}" class="space-y-6">
                @csrf
                <div class="grid md:grid-cols-3 gap-5">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Pegawai</label>
                        <x-ui.select name="user_id" :options="$users->pluck('name','id')" placeholder="Pilih pegawai" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Periode</label>
                        <x-ui.select name="assessment_period_id" :options="$periods" placeholder="Pilih periode" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Kriteria</label>
                        <x-ui.select name="performance_criteria_id" :options="$criterias->pluck('name','id')" placeholder="Pilih kriteria" />
                    </div>
                </div>
                <div class="grid md:grid-cols-3 gap-5">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Nilai Numeric (0-100)</label>
                        <x-ui.input type="number" step="0.01" name="value_numeric" placeholder="cth 85" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Tanggal/Waktu (opsional)</label>
                        <x-ui.input type="datetime-local" name="value_datetime" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Catatan (opsional)</label>
                        <x-ui.input name="value_text" />
                    </div>
                </div>
                <div class="flex justify-end gap-3">
                    <x-ui.button variant="outline" as="a" href="{{ route('admin_rs.metrics.index') }}">Batal</x-ui.button>
                    <x-ui.button type="submit" variant="success">Simpan</x-ui.button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
