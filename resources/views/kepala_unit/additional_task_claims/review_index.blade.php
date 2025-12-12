@php
    use Illuminate\Support\Arr;
    use Illuminate\Support\Carbon;
    use Illuminate\Support\Facades\Storage;
@endphp

<x-app-layout title="Review Klaim Tugas Tambahan">
    <x-slot name="header">
        <div class="flex flex-col gap-2">
            <p class="text-xs uppercase tracking-wide text-slate-500">Dashboard Kepala Unit</p>
            <h1 class="text-3xl font-semibold text-slate-900">Review Klaim Tugas Tambahan</h1>
            <p class="text-sm text-slate-500">Validasi atau setujui klaim yang sudah dikirim pegawai medis sebelum jatuh tempo penugasan.</p>
        </div>
    </x-slot>

    <div class="container-px py-6 space-y-6">
        @php
            $statusFlash = Arr::first(Arr::wrap(session()->pull('status')));
        @endphp
        @if ($statusFlash)
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 text-emerald-800 px-4 py-3 text-sm">
                {{ $statusFlash }}</div>
        @endif

        <div class="bg-white rounded-2xl border border-slate-100 p-6 shadow-sm flex flex-col gap-3 text-sm text-slate-600">
            <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <div class="h-11 w-11 rounded-2xl bg-sky-100 text-sky-600 flex items-center justify-center text-lg">
                        <i class="fa-solid fa-clipboard-check"></i>
                    </div>
                    <div>
                        <p class="text-base text-slate-900 font-semibold">{{ number_format($claims->total()) }} klaim menunggu peninjauan</p>
                        <p class="text-xs text-slate-500">Gunakan tombol aksi sesuai status saat ini. Klaim yang ditolak sebelum tenggat akan membuka kembali tugas untuk pegawai.</p>
                    </div>
                </div>
                <span class="px-3 py-1 rounded-full text-xs font-semibold bg-sky-50 text-sky-700 border border-sky-100">Kepala Unit</span>
            </div>
        </div>

        <div class="space-y-5">
            @forelse ($claims as $claim)
                @php
                    $claimedAt = $claim->claimed_at
                        ? Carbon::parse($claim->claimed_at)->timezone('Asia/Jakarta')->format('d M Y H:i')
                        : '-';
                    $dueDate = $claim->due_date ? Carbon::parse($claim->due_date)->format('d M Y') : '-';
                    $resultUrl = $claim->result_file_path ? Storage::url($claim->result_file_path) : null;
                    $policyUrl = $claim->policy_doc_path ? Storage::url($claim->policy_doc_path) : null;
                    $statusColor =
                        [
                            'submitted' => 'bg-amber-100 text-amber-700',
                            'validated' => 'bg-sky-100 text-sky-700',
                            'approved' => 'bg-emerald-100 text-emerald-700',
                            'rejected' => 'bg-rose-100 text-rose-700',
                        ][$claim->status] ?? 'bg-slate-100 text-slate-700';
                    $statusLabel =
                        [
                            'submitted' => 'Menunggu Validasi',
                            'validated' => 'Menunggu Persetujuan',
                            'approved' => 'Disetujui',
                            'rejected' => 'Ditolak',
                        ][$claim->status] ?? ucfirst($claim->status);
                @endphp

                <div class="bg-white border border-slate-100 rounded-3xl shadow-sm p-6 space-y-6">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div class="space-y-1">
                            <p class="text-xs uppercase tracking-wide text-slate-400">{{ $claim->period_name ?? 'Periode belum ditentukan' }}</p>
                            <h2 class="text-2xl font-semibold text-slate-900 leading-tight">{{ $claim->task_title }}</h2>
                            <p class="text-sm text-slate-500">Diajukan oleh <span class="font-medium text-slate-700">{{ $claim->user_name }}</span> pada {{ $claimedAt }}</p>
                        </div>
                        <div class="flex flex-col items-start sm:items-end gap-2">
                            <span
                                class="px-3 py-1.5 rounded-full text-xs font-semibold {{ $statusColor }}">{{ $statusLabel }}</span>
                            <div class="text-xs text-slate-500">Jatuh tempo: <span
                                    class="font-medium text-slate-700">{{ $dueDate }}</span></div>
                        </div>
                    </div>

                    <div class="grid gap-4 md:grid-cols-3">
                        <div class="rounded-2xl border border-slate-100 bg-gradient-to-br from-slate-50 to-slate-100 p-4 space-y-1">
                            <p class="text-xs tracking-wide text-slate-400 uppercase">Skor</p>
                            @php
                                $pointsDisplay = is_null($claim->points) ? '-' : number_format((float) $claim->points, 0, ',', '.');
                            @endphp
                            <p class="text-2xl font-semibold text-slate-900">{{ $pointsDisplay === '-' ? '-' : $pointsDisplay . ' poin' }}</p>
                            <p class="text-xs text-slate-500">Bobot penilaian yang akan diterima pegawai.</p>
                        </div>
                        <div class="rounded-2xl border border-slate-100 bg-gradient-to-br from-emerald-50 via-white to-slate-50 p-4 space-y-1">
                            <p class="text-xs tracking-wide text-slate-400 uppercase">Bonus</p>
                            <p class="text-2xl font-semibold text-slate-900">
                                {{ $claim->bonus_amount ? 'Rp ' . number_format((float) $claim->bonus_amount, 0, ',', '.') : '-' }}
                            </p>
                            <p class="text-xs text-slate-500">Nominal tambahan jika tugas disetujui.</p>
                        </div>
                        <div class="rounded-2xl border border-slate-100 bg-gradient-to-br from-sky-50 to-white p-4 space-y-2">
                            <p class="text-xs tracking-wide text-slate-400 uppercase">Dokumen Kebijakan</p>
                            @if ($policyUrl)
                                <a href="{{ $policyUrl }}" target="_blank"
                                    class="inline-flex items-center gap-2 px-3 py-2 rounded-xl border border-slate-200 bg-white text-sm text-indigo-700 font-medium hover:bg-indigo-50">
                                    <i class="fa-solid fa-file-lines"></i> Lihat acuan tugas
                                </a>
                            @else
                                <p class="text-sm text-slate-500">Tidak ada dokumen terlampir.</p>
                            @endif
                        </div>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="rounded-2xl border border-slate-100 p-4">
                            <div class="flex items-center gap-2 mb-2">
                                <div
                                    class="h-8 w-8 rounded-2xl bg-indigo-100 text-indigo-600 flex items-center justify-center">
                                    <i class="fa-solid fa-file-lines"></i>
                                </div>
                                <h3 class="text-sm font-semibold text-slate-900">Catatan Hasil Pegawai</h3>
                            </div>
                            <p class="text-sm text-slate-600 whitespace-pre-line">
                                {{ $claim->result_note ?: 'Pegawai tidak menambahkan catatan hasil.' }}</p>
                        </div>
                        <div class="rounded-2xl border border-slate-100 p-4">
                            <div class="flex items-center gap-2 mb-2">
                                <div
                                    class="h-8 w-8 rounded-2xl bg-sky-100 text-sky-600 flex items-center justify-center">
                                    <i class="fa-solid fa-paperclip"></i>
                                </div>
                                <h3 class="text-sm font-semibold text-slate-900">Lampiran Bukti</h3>
                            </div>
                            @if ($resultUrl)
                                <a href="{{ $resultUrl }}" target="_blank"
                                    class="inline-flex items-center gap-2 px-3 py-2 rounded-xl border border-emerald-100 bg-emerald-50 text-sm font-medium text-emerald-800 hover:bg-emerald-100">
                                    <i class="fa-solid fa-file-lines"></i> Lampiran hasil
                                </a>
                                <p class="text-xs text-slate-500 mt-2">Buka tautan untuk memeriksa bukti pekerjaan sebelum mengambil keputusan.</p>
                            @else
                                <p class="text-sm text-slate-500">Belum ada berkas yang diunggah.</p>
                            @endif
                        </div>
                    </div>

                    <div class="flex flex-col gap-4">

                        {{-- === SECTION VALIDASI (STATUS: submitted) === --}}
                        @if ($claim->status === 'submitted')
                            <form method="POST"
                                action="{{ route('kepala_unit.additional_task_claims.review_update', $claim->id) }}"
                                class="flex flex-wrap gap-3">
                                @csrf
                                <input type="hidden" name="action" value="validate">

                                <button type="submit"
                                    class="inline-flex items-center gap-2 px-5 h-12 rounded-2xl text-sm font-semibold bg-gradient-to-r from-sky-400 to-blue-600 text-white shadow-sm hover:brightness-110">
                                    <i class="fa-solid fa-circle-check"></i> Tandai Valid
                                </button>

                                <p class="text-xs text-slate-500 self-center">
                                    Validasi memastikan bukti lengkap sebelum persetujuan akhir.
                                </p>
                            </form>
                        @endif

                        {{-- === SECTION APPROVE (STATUS: validated) === --}}
                        @if ($claim->status === 'validated')
                            <form method="POST"
                                action="{{ route('kepala_unit.additional_task_claims.review_update', $claim->id) }}"
                                class="flex flex-wrap gap-3">
                                @csrf
                                <input type="hidden" name="action" value="approve">

                                <button type="submit"
                                    class="inline-flex items-center gap-2 px-5 h-12 rounded-2xl text-sm font-semibold bg-gradient-to-r from-emerald-500 to-teal-600 text-white shadow-sm hover:brightness-110">
                                    <i class="fa-solid fa-badge-check"></i> Setujui &amp; Selesaikan
                                </button>

                                <p class="text-xs text-slate-500 self-center">
                                    Klaim yang disetujui akan menghitung skor dan bonus secara otomatis.
                                </p>
                            </form>
                        @endif

                        {{-- === SECTION TOLAK (SELALU ADA) === --}}
                        <form method="POST"
                            action="{{ route('kepala_unit.additional_task_claims.review_update', $claim->id) }}"
                            class="rounded-2xl border border-rose-100 bg-rose-50 p-4 space-y-3">
                            @csrf
                            <input type="hidden" name="action" value="reject">

                            <div class="flex items-center gap-2">
                                <div
                                    class="h-8 w-8 rounded-2xl bg-white text-rose-500 flex items-center justify-center border border-rose-100">
                                    <i class="fa-solid fa-message"></i>
                                </div>
                                <div>
                                    <p class="text-sm font-semibold text-rose-600">Tolak &amp; kirim catatan perbaikan
                                    </p>
                                    <p class="text-xs text-rose-500">
                                        Klaim akan kembali ke status draft dan tugas dibuka jika masih dalam periode.
                                    </p>
                                </div>
                            </div>

                            <textarea name="comment" rows="3"
                                class="w-full rounded-2xl border border-rose-200 bg-white px-3 py-2 text-sm text-slate-700
                   focus:outline-none focus:ring-2 focus:ring-rose-200"
                                placeholder="Tuliskan catatan singkat agar pegawai tahu apa yang perlu diperbaiki..."></textarea>

                            <div class="flex justify-end">
                                <button type="submit"
                                    class="inline-flex items-center gap-2 px-5 h-11 rounded-2xl text-sm font-semibold
                       bg-rose-600 text-white hover:bg-rose-700">
                                    <i class="fa-solid fa-xmark"></i> Tolak Klaim
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            @empty
                <div
                    class="flex flex-col items-center justify-center rounded-3xl border border-dashed border-slate-200 bg-white py-16 text-center">
                    <div
                        class="h-16 w-16 rounded-full bg-slate-100 text-slate-400 flex items-center justify-center text-2xl mb-4">
                        <i class="fa-regular fa-circle-check"></i>
                    </div>
                    <p class="text-lg font-semibold text-slate-900">Semua klaim sudah ditangani</p>
                    <p class="text-sm text-slate-500">Tidak ada klaim yang menunggu validasi atau persetujuan.</p>
                </div>
            @endforelse
        </div>

        <div
            class="pt-4 border-t border-slate-100 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between text-sm text-slate-600">
            <div>
                Menampilkan <span class="font-semibold text-slate-900">{{ $claims->firstItem() ?? 0 }}</span>
                - <span class="font-semibold text-slate-900">{{ $claims->lastItem() ?? 0 }}</span>
                dari <span class="font-semibold text-slate-900">{{ $claims->total() }}</span> klaim
            </div>
            <div>
                {{ $claims->links() }}
            </div>
        </div>
    </div>
</x-app-layout>