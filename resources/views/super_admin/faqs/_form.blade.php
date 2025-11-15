@php /** @var \App\Models\Faq $faq */ @endphp

<div class="grid grid-cols-1 md:grid-cols-2 gap-5">
    <div class="space-y-4">
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Pertanyaan *</label>
            <x-ui.input name="question" :value="old('question', $faq->question)" required />
        </div>

        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Jawaban *</label>
            <x-ui.textarea name="answer" rows="8" :value="old('answer', $faq->answer)" />
        </div>
    </div>

    <div class="space-y-4">
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Urutan</label>
                <x-ui.input type="number" min="0" name="order" :value="old('order', $faq->order)" />
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Kategori</label>
                <x-ui.input name="category" :value="old('category', $faq->category)" placeholder="opsional" />
            </div>
        </div>

        <label class="inline-flex items-center gap-2">
            <input type="checkbox" name="is_active" value="1" class="h-4 w-4 rounded border-slate-300 text-emerald-600" {{ old('is_active', $faq->is_active) ? 'checked' : '' }}>
            <span class="text-sm text-slate-700">Aktif</span>
        </label>

        <div class="pt-2 flex items-center justify-between">
            <x-ui.button as="a" href="{{ route('super_admin.faqs.index') }}" variant="outline">
                <i class="fa-solid fa-arrow-left"></i> Kembali
            </x-ui.button>

            <x-ui.button type="submit" variant="{{ $faq->exists ? 'success' : 'primary' }}">
                <i class="fa-solid fa-floppy-disk"></i>
                {{ $faq->exists ? 'Perbarui' : 'Simpan' }}
            </x-ui.button>
        </div>
    </div>
</div>
