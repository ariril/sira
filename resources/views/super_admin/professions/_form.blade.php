@php /** @var \App\Models\Profession $profession */ @endphp

<div class="grid grid-cols-1 md:grid-cols-2 gap-5">
    <div class="space-y-4">
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Nama Profession *</label>
            <x-ui.input name="name" :value="old('name', $profession->name)" required />
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Kode</label>
                <x-ui.input name="code" :value="old('code', $profession->code)" />
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Status</label>
                <label class="inline-flex items-center gap-2 h-12 px-3 rounded-xl border border-slate-300 bg-white">
                    <input type="checkbox"
                           name="is_active"
                           value="1"
                           class="h-4 w-4 rounded border-slate-300 text-blue-600"
                        @checked(old('is_active', (int)($profession->is_active ?? 1)))>
                    <span class="text-sm text-slate-700">Aktif</span>
                </label>
            </div>
        </div>
    </div>

    <div class="space-y-4">
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Deskripsi</label>
            <x-ui.textarea name="description" rows="6"
                           :value="old('description', $profession->description)"/>
        </div>
    </div>

    {{-- BAR TOMBOL --}}
    <div class="md:col-span-2 flex items-center justify-between pt-2">
        <x-ui.button as="a" href="{{ route('super_admin.professions.index') }}" variant="outline">
            <i class="fa-solid fa-arrow-left"></i> Kembali
        </x-ui.button>

        <x-ui.button type="submit" variant="{{ $profession->exists ? 'success' : 'primary' }}">
            <i class="fa-solid fa-floppy-disk"></i>
            {{ $profession->exists ? 'Update' : 'Create' }}
        </x-ui.button>
    </div>
</div>
