@extends('layouts.public')

@section('title','Form Ulasan')

@php
    $ref = (string) ($invitation->registration_ref ?? '');
    $len = strlen($ref);
    $maskedRef = $len > 6
        ? substr($ref, 0, 3) . str_repeat('*', max(0, $len - 5)) . substr($ref, -2)
        : $ref;
@endphp

@section('content')
<div class="max-w-4xl mx-auto px-4 py-10">
    <div class="space-y-2 mb-6">
        <h1 class="text-3xl font-semibold text-slate-900">Ulasan Pelayanan</h1>
        <p class="text-slate-600">Mohon isi penilaian untuk staf yang tertera. Data kunjungan dan daftar staf bersifat terkunci.</p>
    </div>

    <div class="bg-white rounded-3xl border border-slate-100 shadow-sm p-5 sm:p-6 space-y-5">
        <div class="grid sm:grid-cols-3 gap-4">
            <div>
                <p class="text-sm text-slate-500">Registration Ref</p>
                <p class="text-lg font-semibold text-slate-900">{{ $maskedRef }}</p>
            </div>
            <div>
                <p class="text-sm text-slate-500">Poliklinik/Unit</p>
                <p class="text-lg font-semibold text-slate-900">{{ $invitation->unit?->name ?? '-' }}</p>
            </div>
            <div>
                <p class="text-sm text-slate-500">Nama Pasien</p>
                <p class="text-lg font-semibold text-slate-900">{{ $invitation->patient_name ?? '-' }}</p>
            </div>
        </div>

        <form method="POST" action="{{ route('reviews.invite.store', ['token' => $token]) }}" class="space-y-6">
            @csrf

            <div class="rounded-2xl border border-slate-100 bg-slate-50 p-4 space-y-3">
                <div class="font-semibold text-slate-800">Ulasan Umum (Opsional)</div>

                @php
                    $initialOverall = old('overall_rating');
                    $initialOverallInt = ($initialOverall !== null && $initialOverall !== '')
                        ? (int) $initialOverall
                        : 0;
                @endphp
                <div x-data="{ rating: @json($initialOverallInt) }" class="space-y-2">
                    <p class="text-sm text-slate-600">Rating keseluruhan</p>
                    <input type="hidden" name="overall_rating" :value="rating">
                    <div class="flex items-center gap-1">
                        @for($i = 1; $i <= 5; $i++)
                            <button type="button" class="h-10 w-10 rounded-xl text-lg font-semibold" :class="rating >= {{ $i }} ? 'bg-amber-400 text-white' : 'bg-white border border-slate-200 text-slate-400'" @click="rating={{ $i }}">★</button>
                        @endfor
                    </div>
                </div>

                <div>
                    <label class="text-sm font-medium text-slate-700">Komentar umum (opsional)</label>
                    <textarea name="comment" rows="3" class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm" placeholder="Tulis komentar Anda...">{{ old('comment') }}</textarea>
                </div>
            </div>

            <div class="space-y-4">
                <div class="font-semibold text-slate-800">Daftar Staf (Terkunci)</div>

                @foreach($staff as $idx => $s)
                    @php
                        $oldBase = 'details.' . $idx;
                        $initialRating = old($oldBase . '.rating');
                        $initialComment = old($oldBase . '.comment');
                        $initialRatingInt = ($initialRating !== null && $initialRating !== '')
                            ? (int) $initialRating
                            : 0;
                    @endphp

                    <div class="bg-white rounded-3xl border border-slate-100 shadow-sm p-5 space-y-4" x-data="{ rating: @json($initialRatingInt) }">
                        <div class="flex items-start justify-between gap-4 flex-wrap">
                            <div>
                                <div class="text-lg font-semibold text-slate-900">{{ $s['name'] ?? '-' }}</div>
                                <div class="text-sm text-slate-500">
                                    {{ $s['profession'] ?? 'Pegawai Medis' }}
                                    @if(!empty($s['role']))
                                        • {{ $s['role'] }}
                                    @endif
                                </div>
                            </div>
                        </div>

                        <input type="hidden" name="details[{{ $idx }}][user_id]" value="{{ $s['id'] }}">
                        <input type="hidden" name="details[{{ $idx }}][rating]" :value="rating">

                        <div class="space-y-2">
                            <p class="text-sm font-medium text-slate-700">Rating (opsional)</p>
                            <div class="flex items-center gap-1">
                                @for($i = 1; $i <= 5; $i++)
                                    <button type="button" class="h-10 w-10 rounded-xl text-lg font-semibold" :class="rating >= {{ $i }} ? 'bg-amber-400 text-white' : 'bg-white border border-slate-200 text-slate-400'" @click="rating={{ $i }}">★</button>
                                @endfor
                                <button type="button" class="ml-2 text-xs text-slate-500 underline" @click="rating=0">Reset</button>
                            </div>
                        </div>

                        <div>
                            <label class="text-sm font-medium text-slate-700">Komentar (opsional)</label>
                            <textarea name="details[{{ $idx }}][comment]" rows="2" class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm" placeholder="Tulis komentar untuk staf ini...">{{ $initialComment }}</textarea>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="pt-2">
                <x-ui.button type="submit" class="h-12 px-6">Kirim Ulasan</x-ui.button>
            </div>
        </form>
    </div>
</div>
@endsection
