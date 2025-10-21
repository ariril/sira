@php /** @var \App\Models\AboutPage $aboutPage */ @endphp

<div class="grid grid-cols-1 md:grid-cols-2 gap-5">
    <div class="space-y-4">
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Tipe *</label>
            <x-ui.select name="type" :options="$types" :value="old('type', $aboutPage->type?->value)" required />
        </div>

        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Judul</label>
            <x-ui.input name="title" :value="old('title', $aboutPage->title)" />
        </div>

        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Konten</label>
            <x-ui.textarea name="content" rows="10" :value="old('content', $aboutPage->content)" />
        </div>
    </div>

    <div class="space-y-4">
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Gambar Utama</label>
            <x-ui.input type="file" name="image" />
            @if($aboutPage->image_path)
                <div class="mt-2 text-xs text-slate-600">File sekarang: {{ $aboutPage->image_path }}</div>
            @endif
        </div>

        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Lampiran (opsional)</label>
            <x-ui.input type="file" name="attachments[]" multiple />
            @if($aboutPage->attachments)
                <div class="mt-2 text-xs text-slate-600">{{ count($aboutPage->attachments) }} file terunggah.</div>
            @endif
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Publish At</label>
                <x-ui.input type="datetime-local" name="published_at" :value="old('published_at', optional($aboutPage->published_at)->format('Y-m-d\\TH:i'))" />
            </div>
            <div class="pt-6">
                <label class="inline-flex items-center gap-2">
                    <input type="checkbox" name="is_active" value="1" class="h-4 w-4 rounded border-slate-300 text-emerald-600" {{ old('is_active', $aboutPage->is_active) ? 'checked' : '' }}>
                    <span class="text-sm text-slate-700">Aktif</span>
                </label>
            </div>
        </div>

        <div class="pt-2 flex items-center justify-between">
            <x-ui.button as="a" href="{{ route('super_admin.about-pages.index') }}" variant="outline">
                <i class="fa-solid fa-arrow-left"></i> Kembali
            </x-ui.button>

            <x-ui.button type="submit" variant="{{ $aboutPage->exists ? 'success' : 'primary' }}">
                <i class="fa-solid fa-floppy-disk"></i>
                {{ $aboutPage->exists ? 'Update' : 'Create' }}
            </x-ui.button>
        </div>
    </div>
</div>
