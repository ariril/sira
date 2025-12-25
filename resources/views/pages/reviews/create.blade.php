@extends('layouts.public')

@section('title','Berikan Ulasan Pelayanan')

@php
    $unitOptions = $units->map(fn($u) => ['id' => (string) $u->id, 'name' => $u->name])->values();
    $staffPayload = $staffOptions->map(fn ($s) => [
        'id' => (string) $s['id'],
        'name' => $s['name'],
        'unit_id' => (string) $s['unit_id'],
        'unit_name' => $s['unit_name'],
        'profession' => $s['profession'],
    ])->values();
    $prefillState = [
        'registration_ref' => old('registration_ref'),
        'unit_id' => old('unit_id'),
        'patient_name' => old('patient_name'),
        'contact' => old('contact'),
        'comment' => old('comment'),
        'details' => collect(old('details', []))->map(function ($row) {
            return [
                'staff_id' => $row['staff_id'] ?? null,
                'rating' => $row['rating'] ?? null,
                'comment' => $row['comment'] ?? null,
            ];
        })->values(),
    ];
@endphp

@section('content')
<div class="max-w-4xl mx-auto px-4 py-10" x-data="publicReviewForm(@js($unitOptions), @js($staffPayload), @js($prefillState), @js(session('status')))" x-init="init()">
    @php($reviewErrors = $errors->reviewForm ?? $errors)

    @if ($reviewErrors->any())
        <div class="mb-6 rounded-2xl bg-rose-50 border border-rose-200 text-rose-900 px-4 py-3">
            <div class="font-medium mb-1">Terdapat kesalahan pada formulir:</div>
            <ul class="list-disc ml-5 space-y-0.5 text-sm">
                @foreach ($reviewErrors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Success modal --}}
    <div x-show="showSuccess" x-cloak class="fixed inset-0 z-40 flex items-center justify-center bg-slate-900/75 px-4">
        <div class="w-full max-w-md bg-white rounded-3xl shadow-2xl p-6 space-y-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-fuchsia-600">Terima kasih</p>
                <h2 class="text-2xl font-semibold text-slate-900 mt-1">Ulasan Anda sudah diterima</h2>
                <p class="text-sm text-slate-600 mt-1" x-text="successMessage || 'Masukan Anda sangat berarti untuk peningkatan layanan kami.'"></p>
            </div>
            <div class="flex flex-col sm:flex-row items-stretch gap-3 pt-2">
                <a href="{{ route('home') }}" class="flex-1 inline-flex items-center justify-center gap-2 h-12 px-5 rounded-2xl text-sm font-semibold text-slate-700 bg-slate-100 hover:bg-slate-200">
                    <i class="fa-solid fa-house"></i> Kembali ke Beranda
                </a>
                <a href="{{ route('reviews.create') }}" class="flex-1 inline-flex items-center justify-center gap-2 h-12 px-5 rounded-2xl text-sm font-semibold text-white bg-gradient-to-r from-indigo-500 to-fuchsia-500 shadow-lg shadow-fuchsia-100">
                    <i class="fa-solid fa-rotate-left"></i> Isi Ulasan Lagi
                </a>
            </div>
        </div>
    </div>

    <div x-show="showGate" x-cloak class="fixed inset-0 z-30 flex items-center justify-center bg-slate-900/70 px-4">
        <div class="w-full max-w-md bg-white rounded-3xl shadow-xl p-6 space-y-4">
            <div>
                <p class="text-xs font-medium uppercase tracking-wide text-fuchsia-600">Langkah awal</p>
                <h2 class="text-2xl font-semibold text-slate-900 mt-1">Masukkan Nomor Rawat Medis</h2>
                <p class="text-sm text-slate-600 mt-1">Nomor ini membantu kami memverifikasi kunjungan Anda. Satu nomor hanya dapat digunakan sekali.</p>
            </div>
            <div class="space-y-2">
                <label class="text-sm font-medium text-slate-700">Nomor Rawat Medis</label>
                <x-ui.input type="text" placeholder="Contoh: 055599" x-model.trim="registrationRef" />
                @php($registrationError = optional($reviewErrors)->first('registration_ref'))
                @if (!empty($registrationError))
                    <p class="text-xs text-rose-500 mt-1">{{ $registrationError }}</p>
                @endif
            </div>
            <div class="flex flex-col sm:flex-row items-stretch gap-3 pt-1">
                <button type="button" class="flex-1 inline-flex items-center justify-center gap-2 h-12 px-5 rounded-2xl text-sm font-semibold text-slate-600 bg-slate-100 hover:bg-slate-200" @click="registrationRef = ''">
                    <i class="fa-solid fa-eraser text-xs"></i> Bersihkan
                </button>
                <x-ui.button type="button" class="flex-1 h-12" @click="confirmRegistration()" x-bind:disabled="!registrationRefValid" x-bind:class="registrationRefValid ? '' : 'opacity-60 pointer-events-none'">Mulai Mengisi</x-ui.button>
            </div>
        </div>
    </div>

    <form method="POST" action="{{ route('reviews.store') }}" class="space-y-8" x-bind:class="(showGate || showSuccess) ? 'blur-sm pointer-events-none select-none' : ''">
        @csrf

        <div class="space-y-2">
            <h1 class="text-3xl font-semibold text-slate-900">Berikan Ulasan Pelayanan</h1>
            <p class="text-slate-600">Bagikan pengalaman Anda selama berkunjung ke poliklinik kami. Formulir ini dirancang agar nyaman diisi dari ponsel maupun laptop.</p>
        </div>

        <div class="bg-white rounded-3xl border border-slate-100 shadow-sm p-5 sm:p-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-sm text-slate-500">Nomor Rawat Medis</p>
                    <p class="text-xl font-semibold text-slate-900" x-text="registrationRef || 'Belum diisi'"></p>
                </div>
                <div class="flex gap-3">
                    <x-ui.button type="button" variant="outline" class="h-11 px-4 text-sm" @click="openGate()">
                        <i class="fa-solid fa-pen"></i> Ubah Nomor
                    </x-ui.button>
                </div>
            </div>
            <input type="hidden" name="registration_ref" x-bind:value="registrationRef" required>
        </div>

        <div class="bg-white rounded-3xl border border-slate-100 shadow-sm p-5 sm:p-6 space-y-6">
            <div>
                <label class="text-sm font-semibold text-slate-800">Pilih Poliklinik</label>
                <p class="text-xs text-slate-500">Ketik untuk mencari, lalu pilih poliklinik yang Anda kunjungi.</p>
                <div class="relative mt-3" @click.away="unitDropdown = false">
                    <x-ui.input type="text" placeholder="Contoh: Poli Anak" x-model="unitQuery" @focus="openUnitDropdown()" @input="openUnitDropdown()" class="pr-12" />
                    <input type="hidden" name="unit_id" x-bind:value="selectedUnitId">
                    <button type="button" class="absolute right-11 top-1/2 -translate-y-1/2 text-slate-400" x-show="selectedUnitId" @click="clearUnit()">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                    <i class="fa-solid fa-chevron-down pointer-events-none absolute right-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                    <div x-show="unitDropdown" x-cloak class="absolute z-20 mt-2 w-full rounded-2xl border border-slate-200 bg-white shadow-xl max-h-64 overflow-y-auto">
                        <template x-if="filteredUnits().length === 0">
                            <div class="px-4 py-3 text-sm text-slate-500">Poliklinik tidak ditemukan.</div>
                        </template>
                        <template x-for="unit in filteredUnits()" x-bind:key="unit.id">
                            <button type="button" class="w-full text-left px-4 py-3 text-sm hover:bg-fuchsia-50" @click="selectUnit(unit)">
                                <span class="font-medium" x-text="unit.name"></span>
                            </button>
                        </template>
                    </div>
                </div>
                <p class="text-xs text-rose-500 mt-1" x-show="!selectedUnitId">Poliklinik wajib dipilih sebelum menambah ulasan pegawai.</p>
                @php($unitError = optional($reviewErrors)->first('unit_id'))
                @if (!empty($unitError))
                    <p class="text-xs text-rose-500 mt-1">{{ $unitError }}</p>
                @endif
            </div>

            <div class="space-y-4" id="reviewEntries">
                <template x-for="(entry, index) in reviews" x-bind:key="entry.uuid">
                    <div class="rounded-2xl border border-slate-200 p-4 sm:p-5 bg-slate-50/60">
                        <div class="flex items-center justify-between mb-3">
                            <div>
                                <p class="text-xs font-medium text-slate-500">Pegawai ke-<span x-text="index + 1"></span></p>
                                <h3 class="text-lg font-semibold text-slate-900">Detail Ulasan</h3>
                            </div>
                            <button type="button" class="text-sm text-rose-500 font-medium" x-show="reviews.length > 1" @click="removeReview(index)">Hapus</button>
                        </div>
                        <div class="space-y-4">
                            <div class="space-y-2" @click.away="entry.dropdown = false">
                                <label class="text-sm font-medium text-slate-700">Pilih Pegawai</label>
                                <div class="relative">
                                    <x-ui.input type="text" placeholder="Cari nama atau profesi" x-model="entry.staffQuery" @focus="openStaffDropdown(entry)" @input="openStaffDropdown(entry)" x-bind:disabled="!selectedUnitId" class="pr-12" />
                                    <input type="hidden" x-bind:name="`details[${index}][staff_id]`" x-bind:value="entry.staffId">
                                    <button type="button" class="absolute right-11 top-1/2 -translate-y-1/2 text-slate-400" x-show="entry.staffId" @click="clearStaff(entry)">
                                        <i class="fa-solid fa-xmark"></i>
                                    </button>
                                    <i class="fa-solid fa-user-nurse pointer-events-none absolute right-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                                    <div x-show="entry.dropdown && selectedUnitId" x-cloak class="absolute z-10 mt-2 w-full rounded-2xl border border-slate-200 bg-white shadow-xl max-h-56 overflow-y-auto">
                                        <template x-if="staffOptionsFor(entry).length === 0">
                                            <div class="px-4 py-3 text-sm text-slate-500">Pegawai tidak ditemukan atau sudah dipilih.</div>
                                        </template>
                                        <template x-for="person in staffOptionsFor(entry)" x-bind:key="person.id">
                                            <button type="button" class="w-full text-left px-4 py-3 text-sm hover:bg-fuchsia-50" @click="selectStaff(entry, person)">
                                                <p class="font-medium" x-text="person.name"></p>
                                                <p class="text-xs text-slate-500" x-text="person.profession ? person.profession + ' · ' + person.unit_name : person.unit_name"></p>
                                            </button>
                                        </template>
                                    </div>
                                </div>
                                <p class="text-xs text-slate-500" x-show="!selectedUnitId">Pilih poliklinik terlebih dahulu.</p>
                            </div>

                            <div class="space-y-2">
                                <label class="text-sm font-medium text-slate-700">Rating Pelayanan</label>
                                <div class="flex items-center gap-1">
                                    <template x-for="star in [1,2,3,4,5]" x-bind:key="star">
                                        <button type="button" class="w-12 h-12 flex items-center justify-center rounded-2xl transition" @click="setRating(entry, star)" x-bind:class="entry.rating >= star ? 'text-amber-400' : 'text-slate-300'">
                                            <svg class="w-7 h-7" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                                <path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z" />
                                            </svg>
                                        </button>
                                    </template>
                                </div>
                                <input type="hidden" x-bind:name="`details[${index}][rating]`" x-bind:value="entry.rating">
                            </div>

                            <div>
                                <label class="text-sm font-medium text-slate-700">Catatan Singkat (opsional)</label>
                                <x-ui.textarea rows="2" x-bind:name="`details[${index}][comment]`" placeholder="Tulis apresiasi atau saran untuk pegawai ini" x-model="entry.comment" />
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            <div class="flex flex-wrap gap-3">
                <x-ui.button type="button" variant="outline" class="h-11 px-5 text-sm" @click="addReview()" x-bind:disabled="!canAddReview" x-bind:class="canAddReview ? '' : 'opacity-60 pointer-events-none'">
                    <i class="fa-solid fa-plus"></i> Tambah Review Pegawai
                </x-ui.button>
                <p class="text-xs text-slate-500" x-show="!canAddReview">Semua pegawai pada poliklinik ini sudah ditambahkan.</p>
            </div>
        </div>

        <div class="bg-white rounded-3xl border border-slate-100 shadow-sm p-5 sm:p-6 space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="text-sm font-medium text-slate-700">Nama (opsional)</label>
                    <x-ui.input name="patient_name" placeholder="Nama Anda" x-model="patientName" />
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-700">Kontak (opsional)</label>
                    <x-ui.input name="contact" placeholder="No. HP / Email" x-model="contactInfo" />
                </div>
            </div>
            <div>
                <label class="text-sm font-medium text-slate-700">Ulasan Umum</label>
                <x-ui.textarea name="comment" rows="4" placeholder="Ceritakan pengalaman Anda secara singkat" x-model="generalComment" required />
                <p class="text-xs text-slate-500 mt-1">Ulasan ini akan dibaca tim kami untuk peningkatan layanan.</p>
                @php($commentError = optional($reviewErrors)->first('comment'))
                @if (!empty($commentError))
                    <p class="text-xs text-rose-500 mt-1">{{ $commentError }}</p>
                @endif
            </div>
        </div>

        <div class="flex flex-wrap gap-3">
            <x-ui.button type="submit">
                <i class="fa-solid fa-paper-plane"></i> Kirim Ulasan
            </x-ui.button>
            <x-ui.button as="a" href="{{ route('home') }}" variant="outline">
                <i class="fa-solid fa-arrow-left"></i> Kembali ke Beranda
            </x-ui.button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
    function publicReviewForm(units, staff, oldState = {}, successMessage = '') {
        return {
            units,
            staff,
            registrationRef: oldState.registration_ref || '',
            unitQuery: '',
            selectedUnitId: oldState.unit_id ? String(oldState.unit_id) : '',
            patientName: oldState.patient_name || '',
            contactInfo: oldState.contact || '',
            generalComment: oldState.comment || '',
            showGate: true,
            showSuccess: !!successMessage,
            successMessage,
            unitDropdown: false,
            <div class="container py-5">
                <div class="row justify-content-center">
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-body">
                                <h3 class="mb-2">Form Ulasan</h3>
                                <p class="text-muted mb-0">
                                    Demi keamanan, form ulasan publik tidak dapat diakses menggunakan input Nomor Rawat Medis.
                                    Silakan gunakan tautan undangan yang diberikan oleh petugas.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
                }
