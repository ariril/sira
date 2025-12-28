<div class="grid gap-4 md:grid-cols-12">
    <div class="md:col-span-4">
        <label class="block text-sm font-medium text-slate-600 mb-1">Periode</label>
        <x-ui.select name="assessment_period_id" :options="$periodOptions" :value="old('assessment_period_id', $item->assessment_period_id)" placeholder="Pilih periode" required />
    </div>

    <div class="md:col-span-4">
        <label class="block text-sm font-medium text-slate-600 mb-1">Profesi Dinilai</label>
        <x-ui.select name="assessee_profession_id" :options="$professionOptions" :value="old('assessee_profession_id', $item->assessee_profession_id)" placeholder="Pilih profesi" required />
    </div>

    <div class="md:col-span-4">
        <label class="block text-sm font-medium text-slate-600 mb-1">Tipe Penilai</label>
        <x-ui.select name="assessor_type" :options="$assessorTypes" :value="old('assessor_type', $item->assessor_type)" placeholder="Pilih tipe" required />
    </div>

    <div class="md:col-span-4">
        <label class="block text-sm font-medium text-slate-600 mb-1">Bobot (%)</label>
        <x-ui.input type="number" step="0.01" min="0" max="100" name="weight" :value="old('weight', $item->weight)" placeholder="0-100" required />
    </div>

    <div class="md:col-span-12">
        <label class="inline-flex items-start gap-2 text-sm text-slate-700">
            <input type="checkbox" name="apply_all_criteria" value="1" checked disabled class="mt-1 rounded border-slate-300" />
            <span>
                <span class="font-medium">Berlaku untuk semua kriteria</span>
                <span class="text-slate-500">(bobot penilai 360 saat ini otomatis berlaku untuk semua kriteria 360 pada periode tersebut)</span>
            </span>
        </label>
        <input type="hidden" name="apply_all_criteria" value="1" />
    </div>
</div>
