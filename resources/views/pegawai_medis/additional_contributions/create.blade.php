<x-app-layout title="Tambah Kontribusi Tambahan">
    <x-slot name="header">
        <h1 class="text-2xl font-semibold text-slate-800">Tambah Kontribusi Tambahan</h1>
    </x-slot>
    <div class="container-px py-6 space-y-6">

    @unless($activePeriod ?? null)
        <div class="p-4 rounded-xl border text-sm bg-rose-50 border-rose-200 text-rose-800">
            <div class="font-semibold">Periode penilaian tidak aktif.</div>
            <div>Input kontribusi hanya dapat dilakukan ketika periode berstatus ACTIVE.</div>
        </div>
    @endunless
    @if($activePeriod ?? null)
        <form method="post" action="{{ route('pegawai_medis.additional_contributions.store') }}" enctype="multipart/form-data" class="card p-4 shadow-sm">
            @csrf
            <div class="mb-3">
                <label class="form-label">Judul</label>
                <input type="text" name="title" class="form-control" value="{{ old('title') }}" required maxlength="200">
            </div>
            <div class="mb-3">
                <label class="form-label">Deskripsi (opsional)</label>
                <textarea name="description" class="form-control" rows="4" maxlength="2000">{{ old('description') }}</textarea>
            </div>
            <div class="mb-3">
                <label class="form-label">Terkait Klaim Tugas (opsional)</label>
                <select name="claim_id" class="form-select">
                    <option value="">-- Tidak terkait --</option>
                    @foreach($claims as $c)
                        <option value="{{ $c->claim_id }}" @selected(old('claim_id')==$c->claim_id)>{{ $c->title }}</option>
                    @endforeach
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Lampiran (pdf/xls/xlsx/doc/docx/ppt/pptx/zip/jpg/png, maks 20MB)</label>
                <input type="file" name="file" class="form-control" accept=".pdf,.xls,.xlsx,.doc,.docx,.ppt,.pptx,.zip,.jpg,.jpeg,.png">
            </div>
            <div class="text-end">
                <button class="btn btn-primary">Kirim Kontribusi</button>
            </div>
        </form>
    @endif
    </div>
</x-app-layout>
