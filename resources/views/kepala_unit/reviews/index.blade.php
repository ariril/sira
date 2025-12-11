@php
    use App\Enums\ReviewStatus;
@endphp

<x-app-layout title="Approval Ulasan Pasien">
    <x-slot name="header">
        <h1 class="text-2xl font-semibold">Approval Ulasan Pasien</h1>
        <p class="text-sm text-slate-500">Validasi ulasan publik sebelum masuk ke komponen remunerasi unit Anda.</p>
    </x-slot>

    <div class="container-px py-6 space-y-6">
        @if ($errors->has('review'))
            <div class="bg-rose-50 border border-rose-100 text-rose-700 rounded-2xl px-4 py-3 text-sm">
                {{ $errors->first('review') }}
            </div>
        @endif

        <form method="GET" class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
            <div class="grid gap-5 md:grid-cols-12">
                <div class="md:col-span-5">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Cari</label>
                    <x-ui.input name="q" placeholder="Nomor RM / nama pasien / komentar"
                        addonLeft="fa-magnifying-glass" :value="$filters['q'] ?? ''"
                        class="focus:border-amber-500 focus:ring-amber-500" />
                </div>
                <div class="md:col-span-4">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Status</label>
                    @php
                        $statusSelectOptions = ['all' => '(Semua)'] + $statusOptions;
                    @endphp
                    <x-ui.select name="status" :options="$statusSelectOptions" :value="$filters['status'] ?? 'all'"
                        class="focus:border-amber-500 focus:ring-amber-500" />
                </div>
                <div class="md:col-span-3">
                    <label class="block text-sm font-semibold text-slate-700 mb-1">
                        Tampil
                        <span class="inline-block ml-1 text-amber-600 cursor-help"
                            title="Atur jumlah data per halaman.">!</span>
                    </label>
                    @php
                        $perPageSelect = [];
                        foreach ($perPageOptions as $opt) {
                            $perPageSelect[$opt] = $opt . ' / halaman';
                        }
                    @endphp
                    <x-ui.select name="per_page" :options="$perPageSelect" :value="$filters['per_page'] ?? 12"
                        class="focus:border-amber-500 focus:ring-amber-500" />
                </div>
            </div>
            <div class="mt-4 flex justify-end gap-3">
                <a href="{{ route('kepala_unit.reviews.index') }}"
                    class="inline-flex items-center gap-2 h-12 px-6 rounded-xl text-base font-medium text-slate-600 bg-white border border-slate-200 hover:bg-slate-50">
                    <i class="fa-solid fa-rotate-left"></i> Reset
                </a>
                <button type="submit"
                    class="inline-flex items-center gap-2 h-12 px-6 rounded-xl text-base font-medium text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 shadow-sm">
                    <i class="fa-solid fa-filter"></i> Terapkan
                </button>
            </div>
        </form>

        @php
            $statusBadges = [
                ReviewStatus::PENDING->value => 'bg-amber-50 text-amber-700 border-amber-100',
                ReviewStatus::APPROVED->value => 'bg-emerald-50 text-emerald-700 border-emerald-100',
                ReviewStatus::REJECTED->value => 'bg-rose-50 text-rose-700 border-rose-100',
            ];
            $pendingCount = $statusCounts[ReviewStatus::PENDING->value] ?? 0;
        @endphp
        <div
            class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 flex flex-wrap items-center justify-between gap-4">
            <div class="flex flex-wrap gap-3">
                @foreach ($statusBadges as $key => $class)
                    <span class="px-4 py-1.5 rounded-full text-sm border {{ $class }}">
                        {{ ucfirst($key) }}: <span class="font-semibold">{{ $statusCounts[$key] ?? 0 }}</span>
                    </span>
                @endforeach
            </div>
            <form method="POST" action="{{ route('kepala_unit.reviews.approve-all') }}"
                onsubmit="return confirm('Setujui seluruh ulasan pending?');" class="flex-shrink-0">
                @csrf
                <x-ui.button 
    type="submit" 
    variant="success" 
    class="h-12 px-6 text-base"
    title="{{ $pendingCount === 0 ? 'Tidak ada ulasan pending' : 'Setujui semua ulasan pending' }}"
    :disabled="$pendingCount === 0"
>
    <i class="fa-solid fa-circle-check"></i> Approve Semua
</x-ui.button>
            </form>
        </div>

        <div class="space-y-5">
            @forelse ($items as $review)
                @php
                    $statusEnum =
                        $review->status instanceof ReviewStatus
                            ? $review->status
                            : ReviewStatus::tryFrom((string) $review->status);
                    $isPending = $statusEnum === ReviewStatus::PENDING;
                    $badgeClass = match ($statusEnum) {
                        ReviewStatus::APPROVED => 'bg-emerald-100 text-emerald-800',
                        ReviewStatus::REJECTED => 'bg-rose-100 text-rose-700',
                        default => 'bg-amber-100 text-amber-700',
                    };
                    $createdAt = $review->created_at?->timezone('Asia/Jakarta');
                    $averageRating = $review->average_rating ?? 0;
                @endphp
                <div class="bg-white rounded-3xl border border-slate-100 shadow-sm p-6 space-y-5">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <p class="text-[11px] font-semibold tracking-[0.2em] text-slate-400">NOMOR RM</p>
                            <p class="text-2xl font-semibold text-slate-900">{{ $review->registration_ref }}</p>
                            <p class="text-sm text-slate-500">Dibuat
                                {{ $createdAt?->format('d M Y H:i') ?? '-' }}</p>
                        </div>
                        <div class="text-right space-y-2">
                            <span
                                class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold {{ $badgeClass }}">
                                <span class="w-2 h-2 rounded-full bg-current"></span>
                                {{ $statusEnum?->label() ?? ucfirst((string) $review->status) }}
                            </span>
                            <p class="text-sm text-slate-500">
                                Rating rata-rata: <span
                                    class="font-semibold text-slate-800">{{ number_format($averageRating, 1) }} / 5</span>
                            </p>
                        </div>
                    </div>

                    <div class="bg-slate-50 border border-slate-100 rounded-2xl p-4 space-y-2">
                        <p class="text-slate-700 leading-relaxed">{{ $review->comment }}</p>
                        <div class="text-sm text-slate-500 flex flex-wrap gap-4">
                            <span>Pasien: <span
                                    class="font-medium text-slate-800">{{ $review->patient_name ?? 'Anonim' }}</span></span>
                            @if ($review->contact)
                                <span>Kontak: {{ $review->contact }}</span>
                            @endif
                        </div>
                    </div>

                    <div class="space-y-3">
                        @foreach ($review->details as $detail)
                            @php
                                $roleLabel =
                                    $detail->role instanceof \App\Enums\MedicalStaffReviewRole
                                        ? $detail->role->value
                                        : (string) $detail->role;
                            @endphp
                            <div
                                class="flex flex-wrap items-start justify-between gap-4 rounded-2xl border border-slate-100 p-4">
                                <div>
                                    <div class="flex items-center gap-2 font-semibold text-slate-900">
                                        {{ $detail->medicalStaff->name ?? '-' }}
                                        <span
                                            class="text-xs uppercase tracking-wide text-slate-400">{{ $roleLabel }}</span>
                                    </div>
                                    <div class="text-sm text-slate-500">
                                        {{ $detail->medicalStaff->profession->name ?? 'Profesi tidak tersedia' }}</div>
                                    @if ($detail->comment)
                                        <div class="text-sm text-slate-600 mt-2 leading-relaxed">{{ $detail->comment }}
                                        </div>
                                    @endif
                                </div>
                                <div class="text-right">
                                    <div class="inline-flex items-center gap-1 text-amber-500 font-semibold text-lg">
                                        {{ $detail->rating }}
                                        <i class="fa-solid fa-star text-base"></i>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    @if ($review->decision_note)
                        @php
                            $decidedAt = $review->decided_at?->timezone('Asia/Jakarta');
                        @endphp
                        <div
                            class="bg-slate-50 border border-slate-100 rounded-2xl p-4 text-sm text-slate-600 space-y-1">
                            <div class="font-semibold text-slate-700">Catatan Keputusan</div>
                            <div>{{ $review->decision_note }}</div>
                            <div class="text-xs text-slate-500">
                                Oleh {{ $review->decidedBy->name ?? 'Kepala Unit' }} pada
                                {{ $decidedAt?->format('d M Y H:i') ?? '-' }}
                            </div>
                        </div>
                    @endif

                    @if ($isPending)
                        <div class="border-t border-slate-100 pt-4" x-data="{ showReject: false }">
                            <div class="flex flex-wrap items-start gap-4">
                                <form method="POST" action="{{ route('kepala_unit.reviews.approve', $review) }}">
                                    @csrf
                                    <x-ui.button type="submit" variant="success" class="h-11 px-6 text-sm">
                                        <i class="fa-solid fa-circle-check"></i> Approve
                                    </x-ui.button>
                                </form>

                                <template x-if="!showReject">
                                    <x-ui.button type="button" variant="outline" class="h-11 px-6 text-sm"
                                        @click="showReject = true">
                                        <i class="fa-solid fa-circle-xmark"></i> Reject
                                    </x-ui.button>
                                </template>

                                <div class="flex-1 w-full" x-show="showReject" x-cloak>
                                    <form method="POST" action="{{ route('kepala_unit.reviews.reject', $review) }}"
                                        class="bg-rose-50 border border-rose-100 rounded-2xl p-4 space-y-3">
                                        @csrf
                                        <label class="block text-sm font-medium text-rose-900">Catatan Penolakan</label>
                                        <textarea name="note" rows="3"
                                            class="w-full rounded-xl border-rose-200 focus:ring-rose-400 focus:border-rose-400 text-sm"
                                            placeholder="Alasan penolakan..." required></textarea>
                                        <div class="flex gap-2 justify-end text-sm">
                                            <button type="button" class="text-slate-500 hover:underline"
                                                @click="showReject = false">Batal</button>
                                            <button type="submit"
                                                class="inline-flex items-center justify-center h-10 px-4 rounded-xl font-semibold text-white bg-rose-600 hover:bg-rose-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-300">
                                                Kirim
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            @empty
                <div
                    class="bg-white rounded-2xl border border-dashed border-slate-200 p-12 text-center text-slate-500">
                    Belum ada ulasan yang perlu ditinjau.
                </div>
            @endforelse
        </div>

        <div class="pt-2 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div class="text-sm text-slate-600">
                Menampilkan <span class="font-medium text-slate-800">{{ $items->firstItem() ?? 0 }}</span>
                - <span class="font-medium text-slate-800">{{ $items->lastItem() ?? 0 }}</span>
                dari <span class="font-medium text-slate-800">{{ $items->total() }}</span> ulasan
            </div>
            <div>{{ $items->links() }}</div>
        </div>
    </div>
</x-app-layout>
