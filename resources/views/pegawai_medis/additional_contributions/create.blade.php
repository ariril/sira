@extends('layouts.app')
@section('content')
<div class="container py-6">
    <h1 class="mb-4">Tambah Kontribusi Tambahan</h1>

    @unless($activePeriod ?? null)
        <div class="alert alert-danger">
            <div class="fw-bold">Periode penilaian tidak aktif.</div>
            <div>Input kontribusi hanya dapat dilakukan ketika periode berstatus ACTIVE.</div>
        </div>
    @endunless

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul>
        </div>
    @endif
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
@endsection
