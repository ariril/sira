@php /** @var \App\Models\Announcement $announcement */ @endphp

<div class="grid grid-cols-1 md:grid-cols-2 gap-5">
    <div class="space-y-4">
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Judul *</label>
            <x-ui.input name="title" :value="old('title', $announcement->title)" required />
        </div>

        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Slug</label>
            <x-ui.input name="slug" :value="old('slug', $announcement->slug)" placeholder="otomatis dari judul jika kosong"/>
        </div>

        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Ringkasan</label>
            <x-ui.textarea name="summary" rows="3" :value="old('summary', $announcement->summary)" />
        </div>

        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Konten</label>
            <x-ui.textarea name="content" rows="8" :value="old('content', $announcement->content)" placeholder="Markdown/HTML sederhana" />
        </div>
    </div>

    <div class="space-y-4">
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Kategori</label>
                <x-ui.select name="category"
                             :options="collect(\App\Enums\AnnouncementCategory::cases())->mapWithKeys(fn($c)=>[$c->value=>\Illuminate\Support\Str::headline($c->value)])->all()"
                             :value="old('category', $announcement->category?->value)"
                             placeholder="Pilih kategori" />
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Label</label>
                <x-ui.select name="label"
                             :options="collect(\App\Enums\AnnouncementLabel::cases())->mapWithKeys(fn($c)=>[$c->value=>\Illuminate\Support\Str::headline($c->value)])->all()"
                             :value="old('label', $announcement->label?->value)"
                             placeholder="Pilih label" />
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Waktu Publikasi</label>
                <x-ui.input type="datetime-local" name="published_at" :value="old('published_at', optional($announcement->published_at)->format('Y-m-d\TH:i'))" />
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Waktu Berakhir</label>
                <x-ui.input type="datetime-local" name="expired_at" :value="old('expired_at', optional($announcement->expired_at)->format('Y-m-d\TH:i'))" />
            </div>
        </div>

        <div class="flex items-center gap-2">
            <input type="checkbox" name="is_featured" value="1" class="h-4 w-4 rounded border-slate-300 text-indigo-600" {{ old('is_featured', $announcement->is_featured) ? 'checked' : '' }}>
            <span class="text-sm text-slate-700">Tandai sebagai Sorotan</span>
        </div>

        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Lampiran (opsional)</label>
            <x-ui.input name="attachments[]" type="file" multiple />
            @if($announcement->attachments)
                <div class="mt-2 text-xs text-slate-600">{{ count($announcement->attachments) }} file terunggah.</div>
            @endif
        </div>

        <div class="pt-2 flex items-center justify-between">
            <x-ui.button as="a" href="{{ route('super_admin.announcements.index') }}" variant="outline">
                <i class="fa-solid fa-arrow-left"></i> Kembali
            </x-ui.button>

            <x-ui.button type="submit" variant="{{ $announcement->exists ? 'success' : 'primary' }}">
                <i class="fa-solid fa-floppy-disk"></i>
                {{ $announcement->exists ? 'Perbarui' : 'Simpan' }}
            </x-ui.button>
        </div>
    </div>
</div>
