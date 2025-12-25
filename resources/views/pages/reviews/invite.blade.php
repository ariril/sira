@extends('layouts.app')

@section('content')
<div class="container py-5">
    @if(session('status'))
        <div class="alert alert-success">
            {{ session('status') }}
        </div>
    @endif

    @php
        $state = session('invite_state', $state ?? 'active');
    @endphp

    @if($state === 'invalid')
        <div class="card">
            <div class="card-body">
                <h4 class="mb-2">Tautan undangan tidak valid</h4>
                <p class="mb-0">Pastikan Anda membuka tautan undangan yang benar.</p>
            </div>
        </div>
    @else
        @if(in_array($state, ['expired','used','revoked'], true))
            <div class="card mb-4">
                <div class="card-body">
                    <h4 class="mb-2">Ulasan tidak dapat dilanjutkan</h4>
                    <p class="mb-0">
                        @if($state === 'expired')
                            Tautan undangan ini sudah kedaluwarsa.
                        @elseif($state === 'used')
                            Tautan undangan ini sudah digunakan.
                        @else
                            Tautan undangan ini sudah tidak aktif.
                        @endif
                    </p>
                </div>
            </div>
        @endif

        <div class="card">
            <div class="card-body">
                <h4 class="mb-3">Form Ulasan Pelayanan</h4>

                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label class="form-label">No. Rawat Medis</label>
                        <input type="text" class="form-control" value="{{ $invitation->no_rm ?? '-' }}" readonly>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Poliklinik/Unit</label>
                        <input type="text" class="form-control" value="-" readonly>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Nama Pasien</label>
                        <input type="text" class="form-control" value="{{ $invitation->patient_name ?? '-' }}" readonly>
                    </div>
                </div>

                <form method="POST" action="{{ route('reviews.invite.store', ['token' => $token]) }}">
                    @csrf

                    @foreach($staff as $s)
                        @php
                            $existingRow = $existing[$s['id']] ?? null;
                            $initialRating = old('details.' . $s['id'] . '.rating', $existingRow->rating ?? null);
                            $initialComment = old('details.' . $s['id'] . '.comment', $existingRow->comment ?? null);
                        @endphp

                        <div class="border rounded p-3 mb-3" x-data="{ rating: {{ $initialRating ? (int) $initialRating : 0 }} }">
                            <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                                <div>
                                    <div class="fw-semibold">{{ $s['name'] }}</div>
                                    <div class="text-muted small">
                                        {{ $s['profession'] ?? 'Pegawai Medis' }}
                                        @if(!empty($s['unit_name']))
                                            • {{ $s['unit_name'] }}
                                        @endif
                                    </div>
                                </div>

                                <div>
                                    <input type="hidden" name="details[{{ $s['id'] }}][rating]" :value="rating">

                                    <div class="d-flex align-items-center gap-1">
                                        @for($i = 1; $i <= 5; $i++)
                                            <button
                                                type="button"
                                                class="btn btn-sm"
                                                :class="rating >= {{ $i }} ? 'btn-warning' : 'btn-outline-secondary'"
                                                @click="rating = {{ $i }}"
                                                @disabled(in_array($state, ['expired','used','revoked'], true))
                                            >
                                                ★
                                            </button>
                                        @endfor
                                    </div>

                                    @error('details.' . $s['id'] . '.rating', 'reviewForm')
                                        <div class="text-danger small mt-1">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="mt-3">
                                <label class="form-label">Komentar (opsional)</label>
                                <textarea
                                    class="form-control"
                                    name="details[{{ $s['id'] }}][comment]"
                                    rows="2"
                                    @disabled(in_array($state, ['expired','used','revoked'], true))
                                >{{ $initialComment }}</textarea>
                                @error('details.' . $s['id'] . '.comment', 'reviewForm')
                                    <div class="text-danger small mt-1">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    @endforeach

                    @error('details', 'reviewForm')
                        <div class="text-danger small mb-3">{{ $message }}</div>
                    @enderror

                    <button type="submit" class="btn btn-primary" @disabled(in_array($state, ['expired','used','revoked'], true))>
                        Kirim Ulasan
                    </button>
                </form>
            </div>
        </div>
    @endif
</div>
@endsection
