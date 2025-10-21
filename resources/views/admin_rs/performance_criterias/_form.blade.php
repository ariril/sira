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

        <div class="flex items-center gap-3">
            <input type="hidden" name="is_active" value="0">
            <x-ui.input type="checkbox" name="is_active" value="1" :checked="old('is_active', $item->is_active)" class="h-5 w-5" />
            <div>
                <div class="text-sm font-medium text-slate-700">Aktif</div>
                <div class="text-xs text-slate-500">Nonaktifkan jika tidak dipakai sementara.</div>
            </div>
        </div>
    </div>

    <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Deskripsi</label>
        <x-ui.input as="textarea" name="description" rows="4" :value="old('description', $item->description)" placeholder="Keterangan tambahan (opsional)" />
        @error('description')<div class="text-rose-600 text-xs mt-1">{{ $message }}</div>@enderror
    </div>
</div>
