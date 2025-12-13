@props(['item','types'])

@php($fieldTips = [
    'type' => 'Kelompok kriteria sesuai master (mis. Kinerja Utama, Perilaku, Kompetensi). Pilih yang paling relevan.',
    'data_type' => 'numeric=nilai bebas, percentage=persentase 0-100, boolean=ya/tidak, datetime=tanggal/waktu, text=deskripsi bebas.',
    'input_method' => 'system=dihitung otomatis, manual=diinput petugas, import=unggahan massal, 360=diisi penilai 360°.',
    'aggregation' => 'sum=jumlah total, avg=rata-rata, count=hitung entri, latest=nilai terbaru, custom=rumus khusus.',
])

<div class="space-y-5">
    <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Nama Kriteria</label>
        <x-ui.input name="name" :value="old('name', $item->name)" placeholder="Mis. Disiplin, Kehadiran" required />
    </div>

    <div class="grid md:grid-cols-2 gap-5">
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">
                <span class="inline-flex items-center gap-1">
                    <span>Tipe</span>
                    <span class="text-amber-500 font-bold cursor-help" title="{{ $fieldTips['type'] }}">!</span>
                </span>
            </label>
            <x-ui.select name="type" :options="$types" :value="old('type', $item->type?->value)" placeholder="Pilih tipe" />
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">
                <span class="inline-flex items-center gap-1">
                    <span>Data Type</span>
                    <span class="text-amber-500 font-bold cursor-help" title="{{ $fieldTips['data_type'] }}">!</span>
                </span>
            </label>
            <x-ui.select name="data_type" :options="['numeric'=>'Numeric','percentage'=>'Percentage','boolean'=>'Boolean','datetime'=>'Datetime','text'=>'Text']" :value="old('data_type', $item->data_type)" placeholder="Pilih tipe data" />
        </div>
    </div>

    <div class="grid md:grid-cols-2 gap-5">
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">
                <span class="inline-flex items-center gap-1">
                    <span>Input Method</span>
                    <span class="text-amber-500 font-bold cursor-help" title="{{ $fieldTips['input_method'] }}">!</span>
                </span>
            </label>
            <x-ui.select name="input_method" :options="['system'=>'System','manual'=>'Manual','import'=>'Import','360'=>'360']" :value="old('input_method', $item->input_method)" placeholder="Pilih metode" />
        </div>

        <div class="flex items-center gap-3">
            <input type="hidden" name="is_active" value="0">
            <x-ui.input type="checkbox" name="is_active" value="1" :checked="old('is_active', $item->is_active)" class="h-5 w-5" />
            <div>
                <div class="text-sm font-medium text-slate-700">Aktif</div>
                <div class="text-xs text-slate-500">Nonaktifkan jika tidak dipakai sementara.</div>
            </div>
        </div>
    </div>

    <div class="grid md:grid-cols-2 gap-5">
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">
                <span class="inline-flex items-center gap-1">
                    <span>Aggregation</span>
                    <span class="text-amber-500 font-bold cursor-help" title="{{ $fieldTips['aggregation'] }}">!</span>
                </span>
            </label>
            <x-ui.select name="aggregation_method" :options="['sum'=>'Sum','avg'=>'Average','count'=>'Count','latest'=>'Latest','custom'=>'Custom']" :value="old('aggregation_method', $item->aggregation_method)" placeholder="Pilih agregasi" />
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Bobot Saran (Global)</label>
            <x-ui.input type="number" step="0.01" min="0" max="100" name="suggested_weight" :value="old('suggested_weight', $item->suggested_weight)" placeholder="Contoh: 10" />
            <div class="text-xs text-slate-500 mt-1">Digunakan sebagai saran saat Kepala Unit membuat bobot per unit. Tidak otomatis terapkan.</div>
            
        </div>
    </div>

    <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Deskripsi</label>
        <x-ui.textarea name="description" rows="4" :value="old('description', $item->description)" placeholder="Keterangan tambahan (opsional)" />
    </div>
</div>
@if(old('input_method', $item->input_method) === '360')
<div class="mt-6 p-4 border border-amber-200 bg-amber-50 rounded-xl">
    <div class="font-semibold text-amber-900 mb-3">Bobot Penilai (360°)</div>
    <div class="grid md:grid-cols-3 gap-4">
        @php($weights = optional($item->raterWeights)->keyBy('assessor_type'))
        @foreach(['supervisor'=>'Atasan','peer'=>'Rekan','subordinate'=>'Bawahan','self'=>'Diri sendiri','patient'=>'Pasien','other'=>'Lainnya'] as $k=>$label)
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">{{ $label }}</label>
                <x-ui.input type="number" step="0.01" min="0" max="100" name="rater_weights[{{ $k }}]" :value="old('rater_weights.'.$k, $weights[$k]->weight ?? '')" placeholder="0-100" />
            </div>
        @endforeach
    </div>
    <div class="text-xs text-slate-600 mt-2">Total bobot sebaiknya 100.</div>
</div>
@endif
