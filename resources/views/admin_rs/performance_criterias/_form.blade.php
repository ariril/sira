@props(['item','types'])

<div class="space-y-5">
    <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Nama Kriteria</label>
        <x-ui.input name="name" :value="old('name', $item->name)" placeholder="Mis. Disiplin, Kehadiran" required />
        @error('name')<div class="text-rose-600 text-xs mt-1">{{ $message }}</div>@enderror
    </div>

    <div class="grid md:grid-cols-2 gap-5">
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Tipe</label>
            <x-ui.select name="type" :options="$types" :value="old('type', $item->type?->value)" placeholder="Pilih tipe" />
            @error('type')<div class="text-rose-600 text-xs mt-1">{{ $message }}</div>@enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Data Type</label>
            <x-ui.select name="data_type" :options="['numeric'=>'Numeric','percentage'=>'Percentage','boolean'=>'Boolean','datetime'=>'Datetime','text'=>'Text']" :value="old('data_type', $item->data_type)" placeholder="Pilih tipe data" />
            @error('data_type')<div class="text-rose-600 text-xs mt-1">{{ $message }}</div>@enderror
        </div>
    </div>

    <div class="grid md:grid-cols-2 gap-5">
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Input Method</label>
            <x-ui.select name="input_method" :options="['system'=>'System','manual'=>'Manual','import'=>'Import','360'=>'360']" :value="old('input_method', $item->input_method)" placeholder="Pilih metode" />
            @error('input_method')<div class="text-rose-600 text-xs mt-1">{{ $message }}</div>@enderror
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
            <label class="block text-sm font-medium text-slate-700 mb-1">Aggregation</label>
            <x-ui.select name="aggregation_method" :options="['sum'=>'Sum','avg'=>'Average','count'=>'Count','latest'=>'Latest','custom'=>'Custom']" :value="old('aggregation_method', $item->aggregation_method)" placeholder="Pilih agregasi" />
            @error('aggregation_method')<div class="text-rose-600 text-xs mt-1">{{ $message }}</div>@enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Bobot Saran (Global)</label>
            <x-ui.input type="number" step="0.01" min="0" max="100" name="suggested_weight" :value="old('suggested_weight', $item->suggested_weight)" placeholder="Contoh: 10" />
            <div class="text-xs text-slate-500 mt-1">Digunakan sebagai saran saat Kepala Unit membuat bobot per unit. Tidak otomatis terapkan.</div>
            @error('suggested_weight')<div class="text-rose-600 text-xs mt-1">{{ $message }}</div>@enderror
        </div>
    </div>

    <div class="grid md:grid-cols-2 gap-5">
        <div class="flex items-center gap-3">
            <input type="hidden" name="is_360_based" value="0">
            <x-ui.input type="checkbox" name="is_360_based" value="1" :checked="old('is_360_based', $item->is_360_based)" class="h-5 w-5" />
            <div>
                <div class="text-sm font-medium text-slate-700">Penilaian 360°</div>
                <div class="text-xs text-slate-500">Jika dicentang, set bobot rater 360 di bawah.</div>
            </div>
        </div>
    </div>

    <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Deskripsi</label>
        <x-ui.textarea name="description" rows="4" :value="old('description', $item->description)" placeholder="Keterangan tambahan (opsional)" />
        @error('description')<div class="text-rose-600 text-xs mt-1">{{ $message }}</div>@enderror
    </div>
</div>

@if(old('is_360_based', $item->is_360_based))
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
