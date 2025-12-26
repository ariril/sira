@props(['item','types','normalizationBases' => [],'hasOtherCriteria' => false])

@php($fieldTips = [
    'type' => 'Kelompok kriteria sesuai master (mis. Kinerja Utama, Perilaku, Kompetensi). Pilih yang paling relevan.',
    'data_type' => 'numeric=nilai bebas, percentage=persentase 0-100, boolean=ya/tidak, datetime=tanggal/waktu, text=deskripsi bebas.',
    'input_method' => 'system=dihitung otomatis, manual=diinput petugas, import=unggahan massal, 360=diisi penilai 360°.',
    'aggregation' => 'sum=jumlah total, avg=rata-rata, count=hitung entri, latest=nilai terbaru, custom=rumus khusus.',
])

<div class="space-y-5" x-data="{
    normalizationBasis: '{{ old('normalization_basis', $item->normalization_basis) }}',
    basisLabel() {
        const map = {
            total_unit: 'total unit',
            max_unit: 'nilai maksimum unit',
            average_unit: 'rata-rata unit',
            custom_target: 'target khusus',
        };
        return map[this.normalizationBasis] || 'basis normalisasi';
    }
}">
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

    <div class="flex items-start gap-3" x-show="normalizationBasis !== null" x-cloak>
        <div class="flex items-center gap-3">
            <input type="hidden" name="is_360" value="0">
            <x-ui.input
                type="checkbox"
                name="is_360"
                value="1"
                :checked="old('is_360', (bool) $item->is_360)"
                class="h-5 w-5"
                x-bind:disabled="document.querySelector('[name=\"input_method\"]')?.value !== '360'"
            />
        </div>
        <div>
            <div class="text-sm font-medium text-slate-700">Kriteria 360</div>
            <div class="text-xs text-slate-500">Centang agar muncul di modul Penilaian 360.</div>
        </div>
    </div>

    <div class="grid md:grid-cols-2 gap-5">
        <div class="space-y-4">
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

        <div class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Basis Normalisasi</label>
                <x-ui.select name="normalization_basis" :options="$normalizationBases" :value="old('normalization_basis', $item->normalization_basis)" placeholder="Pilih basis" x-model="normalizationBasis" />
                <div class="text-xs text-slate-500 mt-1">Ubah basis ini ke semua kriteria bila diganti.</div>
            </div>

            <div class="space-y-3" x-show="normalizationBasis" x-cloak>
                <div x-show="normalizationBasis === 'custom_target'" x-cloak>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Target Khusus (basis Target)</label>
                    <x-ui.input type="number" step="0.01" min="0" name="custom_target_value" :value="old('custom_target_value', $item->custom_target_value)" placeholder="Mis. 100" />
                </div>

                <div class="p-4 rounded-xl border border-slate-200 bg-slate-50">
                    <div class="text-sm font-semibold text-slate-700">Rumus Normalisasi (baku)</div>
                    <div class="text-sm text-slate-800 mt-1">(nilai individu / <span x-text="basisLabel()"></span>) × 100</div>
                    <div class="text-xs text-slate-500 mt-1">Rumus ini baku dan tidak dapat diedit.</div>
                </div>
            </div>
        </div>
    </div>

    <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Deskripsi</label>
        <x-ui.textarea name="description" rows="4" :value="old('description', $item->description)" placeholder="Keterangan tambahan (opsional)" />
    </div>
</div>
<div class="mt-4">
    <label class="inline-flex items-center gap-2 text-sm font-medium text-slate-700">
        <input type="hidden" name="apply_basis_to_all" value="0">
        <x-ui.input type="checkbox" name="apply_basis_to_all" value="1" :checked="false" class="h-4 w-4" />
        <span>Terapkan basis normalisasi ini ke semua kriteria</span>
    </label>
    @if($hasOtherCriteria)
        <div class="text-xs text-amber-600 mt-1">Wajib dicentang bila mengubah kebijakan basis agar semua kriteria selaras.</div>
    @endif
</div>
