@php /** @var \App\Models\Announcement $announcement */ @endphp

<div class="grid grid-cols-1 md:grid-cols-2 gap-5">
    {{-- WYSIWYG & Attachments styling specific to announcement form --}}
    {{-- Quill WYSIWYG editor assets & styles (replaces broken Trix) --}}
    <link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet" />
    <style>
        .file-box-announcement input[type=file]{background:#f5f7fa;border:1px dashed #cbd5e1;padding:.65rem;border-radius:.75rem;font-size:.8rem;color:#334155;width:100%;}
        .file-box-announcement input[type=file]:hover{background:#eef2f6;}
        #editor-container{min-height:14rem;background:#ffffff;border:1px solid #cbd5e1;border-radius:.75rem;}
        .ql-toolbar{border-radius:.75rem;border:1px solid #cbd5e1;background:#f8fafc;}
    </style>
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
            <label class="block text-xs font-medium text-slate-600 mb-1">Konten *</label>
            <input type="hidden" id="content_hidden" name="content" value="{{ old('content', $announcement->content) }}" required>
            <div id="toolbar-container" class="mb-2">
                <span class="ql-formats">
                    <button class="ql-bold"></button>
                    <button class="ql-italic"></button>
                    <button class="ql-underline"></button>
                    <button class="ql-link"></button>
                </span>
                <span class="ql-formats">
                    <button class="ql-list" value="ordered"></button>
                    <button class="ql-list" value="bullet"></button>
                </span>
                <span class="ql-formats">
                    <button class="ql-clean"></button>
                </span>
            </div>
            <div id="editor-container">{!! old('content', $announcement->content) !!}</div>
            <p class="mt-1 text-[11px] text-slate-500">Gunakan toolbar untuk format dasar (tebal, miring, daftar, tautan). Editor otomatis menyimpan konten.</p>
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
            <div class="file-box-announcement">
                <input name="attachments[]" type="file" multiple />
            </div>
            @if($announcement->attachments)
                <div class="mt-2 text-xs text-slate-600">{{ count($announcement->attachments) }} file terunggah.</div>
            @endif
            <p class="mt-1 text-[11px] text-slate-500">Anda dapat memilih beberapa file sekaligus (PDF, DOCX, XLSX, JPG, PNG).</p>
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
<script src="https://cdn.quilljs.com/1.3.7/quill.min.js"></script>
<script>
    (function(){
        const editorEl = document.getElementById('editor-container');
        if(!editorEl) return;
        const quill = new Quill('#editor-container', {
            theme: 'snow',
            modules: { toolbar: '#toolbar-container' }
        });
        const hiddenInput = document.getElementById('content_hidden');
        quill.on('text-change', function(){
            hiddenInput.value = editorEl.querySelector('.ql-editor').innerHTML.trim();
        });
        // Ensure initial value captured if existing content
        hiddenInput.value = editorEl.querySelector('.ql-editor').innerHTML.trim();
        // Form submit safety
        editorEl.closest('form')?.addEventListener('submit', function(){
            hiddenInput.value = editorEl.querySelector('.ql-editor').innerHTML.trim();
        });
    })();
</script>
