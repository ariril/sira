@php /** @var \App\Models\Unit $unit */ @endphp

<div class="grid grid-cols-1 md:grid-cols-2 gap-5">
    <div class="space-y-4">
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Nama Unit *</label>
            <x-ui.input name="name" :value="old('name', $unit->name)" required />
        </div>

        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Kode</label>
            <x-ui.input name="code" :value="old('code', $unit->code)" />
        </div>

        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Tipe *</label>
            <x-ui.select name="type"
                         :options="$types"
                         :value="old('type', $unit->type instanceof \App\Enums\UnitType ? $unit->type->value : $unit->type)"
                         required
                         placeholder="Pilih tipe" />
        </div>
    </div>

    <div class="space-y-4">
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Lokasi</label>
            <x-ui.input name="location" :value="old('location', $unit->location)" />
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Telepon</label>
                <x-ui.input name="phone" :value="old('phone', $unit->phone)" />
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Email</label>
                <x-ui.input type="email" name="email" :value="old('email', $unit->email)" />
            </div>
        </div>

        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Rasio Remunerasi (%)</label>
            <x-ui.input type="number" step="0.01" min="0" max="100"
                        name="remuneration_ratio"
                        :value="old('remuneration_ratio', $unit->remuneration_ratio)" />
        </div>

        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Status</label>
            <label class="inline-flex items-center gap-2 h-12 px-3 rounded-xl border border-slate-300 bg-white">
                <input type="checkbox"
                       name="is_active"
                       value="1"
                       class="h-4 w-4 rounded border-slate-300 text-blue-600"
                       @checked(old('is_active', $unit->is_active ?? 1))>
                <span class="text-sm text-slate-700">Aktif</span>
            </label>
        </div>
    </div>

    <div class="md:col-span-2 flex items-center justify-between pt-2">
        <x-ui.button as="a" href="{{ route('super_admin.units.index') }}" variant="outline">
            <i class="fa-solid fa-arrow-left"></i> Kembali
        </x-ui.button>

        <x-ui.button type="submit" variant="{{ $unit->exists ? 'success' : 'primary' }}">
            <i class="fa-solid fa-floppy-disk"></i> {{ $submitText }}
        </x-ui.button>
    </div>
</div>
