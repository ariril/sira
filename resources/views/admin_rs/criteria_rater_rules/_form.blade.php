@props([
    'item',
    'criteriaOptions',
    'assessorTypes',
])

<div class="grid gap-5 md:grid-cols-2">
    <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Kriteria 360</label>
        <x-ui.select
            name="performance_criteria_id"
            :options="$criteriaOptions"
            :value="old('performance_criteria_id', $item->performance_criteria_id)"
            placeholder="Pilih kriteria 360"
        />
        @error('performance_criteria_id')
            <div class="text-xs text-rose-600 mt-1">{{ $message }}</div>
        @enderror
    </div>

    <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Tipe Penilai</label>
        <x-ui.select
            name="assessor_type"
            :options="$assessorTypes"
            :value="old('assessor_type', $item->assessor_type)"
            placeholder="Pilih tipe penilai"
        />
        @error('assessor_type')
            <div class="text-xs text-rose-600 mt-1">{{ $message }}</div>
        @enderror
    </div>
</div>
