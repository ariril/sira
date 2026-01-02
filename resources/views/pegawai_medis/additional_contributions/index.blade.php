<x-app-layout title="Kontribusi Tambahan">
    <x-slot name="header">
        <h1 class="text-2xl font-semibold text-slate-800">Kontribusi Tambahan</h1>
    </x-slot>
    <div class="container-px py-6 space-y-6">
        @unless($activePeriod)
            <div class="rounded-xl border border-rose-200 bg-rose-50 text-rose-800 px-4 py-3 text-sm">
                <div class="font-semibold">Tidak ada periode yang aktif saat ini.</div>
                <div>Hubungi Admin RS untuk mengaktifkan periode penilaian terlebih dahulu.</div>
            </div>
        @endunless

        @php
            $statusLabels = [
                'active' => 'Dalam Proses',
                'submitted' => 'Menunggu Review',
                'validated' => 'Validasi Awal',
                'approved' => 'Disetujui',
                'completed' => 'Selesai',
                'cancelled' => 'Dibatalkan',
                'rejected' => 'Ditolak',
            ];
            $statusClasses = [
                'active' => 'bg-cyan-100 text-cyan-800',
                'submitted' => 'bg-amber-100 text-amber-800',
                'validated' => 'bg-blue-100 text-blue-800',
                'approved' => 'bg-emerald-100 text-emerald-800',
                'completed' => 'bg-emerald-100 text-emerald-800',
                'cancelled' => 'bg-slate-100 text-slate-700',
                'rejected' => 'bg-rose-100 text-rose-800',
            ];
            $statusHelp = [
                'active' => 'Klaim sedang berjalan dan tugas sedang dikerjakan.',
                'submitted' => 'Hasil sudah dikirim, menunggu validasi kepala unit.',
                'validated' => 'Hasil sudah divalidasi, menunggu persetujuan.',
                'approved' => 'Klaim disetujui; nilai/bonus akan dihitung.',
                'completed' => 'Tugas sudah selesai.',
                'cancelled' => 'Klaim dibatalkan.',
                'rejected' => 'Klaim ditolak; periksa catatan/revisi jika ada.',
            ];
        @endphp

        {{-- CARD: Tugas Tersedia --}}
        <div id="tugas-tersedia" class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-slate-500">Daftar Tugas</p>
                    <h2 class="text-xl font-semibold text-slate-800">Tugas Tersedia</h2>
                </div>
                <a href="#tugas-tersedia" class="text-sm text-slate-500 hover:underline">#</a>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                @forelse($availableTasks as $t)
                    <div class="rounded-2xl ring-1 ring-slate-100 p-5 bg-white h-full flex flex-col shadow-[0_10px_25px_-20px_rgba(15,23,42,0.35)]" x-data="{ showDetail: false }">
                        @php
                            $availableTime = $t->due_time ?? '23:59';
                            $availableDate = $t->due_date ? \Illuminate\Support\Carbon::parse($t->due_date)->toDateString() : null;
                            try {
                                $availableDue = $availableDate
                                    ? \Illuminate\Support\Carbon::parse($availableDate.' '.$availableTime, 'Asia/Jakarta')->format('d M Y H:i')
                                    : '-';
                            } catch (\Exception $e) {
                                $availableDue = $availableDate ? $availableDate.' '.$availableTime : '-';
                            }
                        @endphp
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="font-semibold text-slate-900 text-lg">{{ $t->title }}</div>
                            </div>
                            <div class="text-right text-base">
                                <div class="font-semibold">{{ $t->bonus_amount ? 'Rp '.number_format($t->bonus_amount,0,',','.') : '-' }}</div>
                                <div class="text-sm text-slate-500">Poin: {{ $t->points ?? '-' }}</div>
                            </div>
                        </div>
                        <div class="mt-3 flex items-center justify-between text-base text-slate-600">
                            <span>Klaim: {{ $t->claims_used }} / {{ $t->max_claims ?? '∞' }}</span>
                            <span class="text-sm text-slate-500">Status: {{ $t->available ? 'Slot tersedia' : 'Kuota penuh' }}</span>
                        </div>
                        <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between">
                            <div></div>
                            <div class="flex items-center gap-3">
                                @if($t->my_claim_status)
                                    <span class="px-3 py-1 rounded-full text-xs font-medium {{ $statusClasses[$t->my_claim_status] ?? 'bg-slate-200 text-slate-700' }}">
                                        {{ $statusLabels[$t->my_claim_status] ?? strtoupper($t->my_claim_status) }}
                                    </span>
                                @elseif(($activePeriod ?? null) && $t->available)
                                    <form method="POST" action="{{ route('pegawai_medis.additional_tasks.claim', $t->id) }}">
                                        @csrf
                                        <button class="px-4 py-2 rounded-xl text-white text-sm font-semibold bg-gradient-to-r from-sky-400 to-blue-600 shadow-sm hover:brightness-110 focus:ring-2 focus:ring-offset-1 focus:ring-sky-200">
                                            Klaim
                                        </button>
                                    </form>
                                @elseif(!($activePeriod ?? null))
                                    <span class="text-xs text-slate-500">Periode tidak aktif</span>
                                @else
                                    <span class="text-xs text-rose-600">Kuota habis</span>
                                @endif
                                <button type="button" class="px-4 py-2 rounded-xl text-sm font-semibold text-sky-700 bg-sky-50 hover:bg-sky-100 ring-1 ring-sky-100" @click="showDetail = !showDetail">
                                    Detail
                                </button>
                            </div>
                        </div>
                        <div x-cloak x-show="showDetail" class="mt-4 rounded-2xl border border-slate-100 bg-slate-50/60 p-4 text-sm text-slate-600 space-y-3">
                            <div>
                                <p class="text-xs uppercase tracking-wide text-slate-500">Deskripsi Lengkap</p>
                                <p class="mt-1 leading-relaxed">{{ $t->description ?? 'Tidak ada deskripsi.' }}</p>
                            </div>
                            <div class="flex flex-wrap gap-3 text-sm">
                                <span>Periode: <span class="font-medium text-slate-800">{{ $t->period_name ?? '-' }}</span></span>
                                <span>Jatuh tempo: <span class="font-medium text-slate-800">{{ $availableDue }}</span></span>
                            </div>
                            @php
                                $pt = (string)($t->default_penalty_type ?? 'none');
                                $pv = (float)($t->default_penalty_value ?? 0);
                                $pb = (string)($t->penalty_base ?? 'task_bonus');
                                if ($pt === 'none') {
                                    $policyPenalty = 'Tidak ada sanksi.';
                                } elseif ($pt === 'amount') {
                                    $policyPenalty = 'Potong Rp '.number_format($pv,0,',','.').'.';
                                } else {
                                    $baseLbl = $pb === 'remuneration' ? 'remunerasi' : 'bonus tugas';
                                    $policyPenalty = rtrim(rtrim(number_format($pv,2,',','.'),'0'),',').'% dari '.$baseLbl.'.';
                                }
                            @endphp
                            <div class="flex flex-wrap gap-3 text-sm">
                                <span>Batas pembatalan: <span class="font-medium text-slate-800">{{ (int)($t->cancel_window_hours ?? 24) }} jam</span></span>
                                <span>Aturan sanksi: <span class="font-medium text-slate-800">{{ $policyPenalty }}</span></span>
                            </div>
                            @if($t->supporting_file_url)
                                <a href="{{ $t->supporting_file_url }}" target="_blank" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-white border border-slate-200 text-slate-700 hover:bg-slate-100 text-sm">
                                    <i class="fa-solid fa-file-lines text-sky-500"></i> Dokumen Pendukung
                                </a>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="text-sm text-slate-500">Belum ada tugas yang tersedia.</div>
                @endforelse
            </div>
        </div>

        {{-- CARD: Progress Klaim --}}
        <div id="klaim-aktif" class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6" x-data="{ activeDetail: null }">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-slate-500">Klaim Berjalan</p>
                    <h2 class="text-xl font-semibold text-slate-800">Progress Klaim Saya</h2>
                </div>
                <a href="#klaim-aktif" class="text-sm text-slate-500 hover:underline">#</a>
            </div>
            <div class="overflow-x-auto">
                <x-ui.table min-width="780px">
                    <x-slot name="head">
                        <tr>
                            <th class="px-6 py-4 text-left whitespace-nowrap">Tugas</th>
                            <th class="px-6 py-4 text-left whitespace-nowrap">
                                Status
                                <span class="inline-block ml-1 text-amber-600 cursor-help" title="Status klaim: Dalam Proses → Menunggu Review → Validasi Awal → Disetujui/Ditolak.">!</span>
                            </th>
                            <th class="px-6 py-4 text-left whitespace-nowrap">Klaim Pada</th>
                            <th class="px-6 py-4 text-left whitespace-nowrap">Batas Batal</th>
                            <th class="px-6 py-4 text-right whitespace-nowrap">Aksi</th>
                        </tr>
                    </x-slot>
                    @forelse($currentClaims as $claim)
                        <tr class="align-top hover:bg-slate-50">
                            <td class="px-6 py-4 text-base">
                                <div class="font-semibold text-slate-900">{{ $claim->task?->title ?? '-' }}</div>
                            </td>
                            <td class="px-6 py-4 text-base">
                                <span class="px-3 py-1 rounded-full text-xs font-medium {{ $statusClasses[$claim->status] ?? 'bg-slate-200 text-slate-700' }} cursor-help" title="{{ $statusHelp[$claim->status] ?? '' }}">
                                    {{ $statusLabels[$claim->status] ?? strtoupper($claim->status) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-base font-medium">{{ optional($claim->claimed_at?->timezone('Asia/Jakarta'))->format('d M Y H:i') ?? '-' }}</td>
                            <td class="px-6 py-4 text-base font-medium">{{ optional($claim->cancel_deadline_at?->timezone('Asia/Jakarta'))->format('d M Y H:i') ?? '-' }}</td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-2 flex-wrap">
                                    @if(($activePeriod ?? null) && ($claim->task?->period?->status ?? null) === 'active' && $claim->status === 'active')
                                        <form method="POST" action="{{ route('pegawai_medis.additional_task_claims.cancel', $claim->id) }}" onsubmit="const r = prompt('Alasan pembatalan (opsional):'); if (r === null) return false; this.querySelector('[name=reason]').value = r; return confirm('Batalkan klaim ini?')">
                                            @csrf
                                            <input type="hidden" name="reason" value="">
                                            <button class="px-5 py-2.5 rounded-lg ring-1 ring-slate-200 text-slate-700 hover:bg-slate-50 text-sm font-medium">Batalkan</button>
                                        </form>
                                    @endif
                                    <button type="button"
                                        class="px-5 py-2.5 rounded-xl text-white text-sm font-semibold bg-gradient-to-r from-sky-400 to-blue-600 shadow-sm hover:brightness-110"
                                        @click="activeDetail = activeDetail === '{{ $claim->id }}' ? null : '{{ $claim->id }}'">
                                        {{ __('Detail') }}
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-8 text-center text-slate-500">Belum ada klaim aktif.</td>
                        </tr>
                    @endforelse
                </x-ui.table>
            </div>

            @foreach($currentClaims as $claim)
                @php
                    $detailTime = $claim->task?->due_time ?? '23:59';
                    $detailDate = $claim->task?->due_date ? \Illuminate\Support\Carbon::parse($claim->task->due_date)->toDateString() : null;
                    try {
                        $detailDue = $detailDate
                            ? \Illuminate\Support\Carbon::parse($detailDate.' '.$detailTime, 'Asia/Jakarta')->format('d M Y H:i')
                            : '-';
                    } catch (\Exception $e) {
                        $detailDue = $detailDate ? $detailDate.' '.$detailTime : '-';
                    }
                    $periodName = $claim->task?->period?->name ?? '-';
                    $instructionUrl = $claim->task?->policy_doc_path
                        ? asset('storage/'.ltrim($claim->task->policy_doc_path,'/'))
                        : null;
                    $resultUrl = $claim->result_file_path
                        ? asset('storage/'.ltrim($claim->result_file_path,'/'))
                        : null;
                @endphp
                <div x-cloak x-show="activeDetail === '{{ $claim->id }}'" class="mt-6 rounded-2xl border border-slate-100 shadow-sm bg-slate-50/70 p-6">
                    <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                        <div>
                            <p class="text-xs uppercase tracking-wide text-slate-500">Detail Klaim</p>
                            <h3 class="text-xl font-semibold text-slate-900">{{ $claim->task?->title ?? '-' }}</h3>
                            <p class="text-sm text-slate-500">Periode {{ $periodName }} &bull; Jatuh tempo {{ $detailDue }}</p>
                        </div>
                        <button type="button" class="text-sm text-slate-500 hover:text-slate-700" @click="activeDetail = null">
                            Tutup
                        </button>
                    </div>

                    <div class="mt-4 grid gap-4 md:grid-cols-4">
                        <div class="p-4 rounded-xl bg-white border border-slate-100">
                            <p class="text-xs text-slate-500">Status Saat Ini</p>
                            <p class="mt-2 inline-flex items-center px-3 py-1 rounded-full text-xs font-medium {{ $statusClasses[$claim->status] ?? 'bg-slate-200 text-slate-700' }}">
                                {{ $statusLabels[$claim->status] ?? strtoupper($claim->status) }}
                            </p>
                        </div>
                        <div class="p-4 rounded-xl bg-white border border-slate-100">
                            <p class="text-xs text-slate-500">Klaim Pada</p>
                            <p class="mt-2 text-sm font-medium text-slate-800">{{ optional($claim->claimed_at?->timezone('Asia/Jakarta'))->format('d M Y H:i') ?? '-' }}</p>
                            <p class="text-xs text-slate-500">Batas batal: {{ optional($claim->cancel_deadline_at?->timezone('Asia/Jakarta'))->format('d M Y H:i') ?? '-' }}</p>
                        </div>
                        <div class="p-4 rounded-xl bg-white border border-slate-100">
                            <p class="text-xs text-slate-500">Bonus / Poin</p>
                            <p class="mt-2 text-sm font-medium text-slate-800">{{ $claim->task?->bonus_amount ? 'Rp '.number_format($claim->task->bonus_amount,0,',','.') : '-' }}</p>
                            <p class="text-xs text-slate-500">Poin: {{ $claim->task?->points ?? '-' }}</p>
                        </div>
                        @php
                            $pt = (string)($claim->penalty_type ?? 'none');
                            $pv = (float)($claim->penalty_value ?? 0);
                            $pb = (string)($claim->penalty_base ?? 'task_bonus');
                            if ($pt === 'none') {
                                $snap = 'Tidak ada sanksi.';
                            } elseif ($pt === 'amount') {
                                $snap = 'Rp '.number_format($pv,0,',','.');
                            } else {
                                $baseLbl = $pb === 'remuneration' ? 'remunerasi' : 'bonus tugas';
                                $snap = rtrim(rtrim(number_format($pv,2,',','.'),'0'),',').'% dari '.$baseLbl;
                            }
                        @endphp
                        <div class="p-4 rounded-xl bg-white border border-slate-100">
                            <p class="text-xs text-slate-500">Penalty Snapshot</p>
                            <p class="mt-2 text-sm font-medium text-slate-800">{{ $snap }}</p>
                            <p class="text-xs text-slate-500">Batas batal: {{ optional($claim->cancel_deadline_at?->timezone('Asia/Jakarta'))->format('d M Y H:i') ?? '-' }}</p>
                        </div>
                    </div>

                    <div class="mt-6 grid gap-6 md:grid-cols-2">
                        <div class="bg-white rounded-2xl border border-dashed border-slate-200 p-5">
                            <p class="text-sm font-semibold text-slate-800 mb-2">Dokumen</p>
                            <div class="space-y-2 text-sm">
                                <div>
                                    <p class="text-xs text-slate-500">Instruksi</p>
                                    @if($instructionUrl)
                                        <a href="{{ $instructionUrl }}" target="_blank" class="inline-flex items-center gap-2 px-3 py-2 rounded-xl border border-slate-200 bg-white text-sky-800 hover:bg-sky-50 text-sm font-medium">
                                            <i class="fa-solid fa-file-word text-sky-500"></i>
                                            Dokumen Pendukung
                                        </a>
                                    @else
                                        <span class="text-slate-400">Tidak ada lampiran.</span>
                                    @endif
                                </div>
                                <div>
                                    <p class="text-xs text-slate-500">File Hasil</p>
                                    @if($resultUrl)
                                        <a href="{{ $resultUrl }}" target="_blank" class="inline-flex items-center gap-2 px-3 py-2 rounded-xl border border-emerald-100 bg-emerald-50 text-emerald-800 hover:bg-emerald-100 text-sm font-medium">
                                            <i class="fa-solid fa-file-lines text-emerald-500"></i>
                                            File Hasil
                                        </a>
                                    @else
                                        <span class="text-slate-400">Belum ada file.</span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="bg-white rounded-2xl border border-slate-100 p-5">
                            <p class="text-sm font-semibold text-slate-800">Unggah Hasil Tugas</p>
                            @if(!($activePeriod ?? null) || ($claim->task?->period?->status ?? null) !== 'active')
                                <p class="mt-2 text-xs text-slate-500">Unggah dinonaktifkan karena periode penilaian tidak berstatus ACTIVE.</p>
                            @elseif($claim->status !== 'active')
                                <p class="mt-2 text-xs text-slate-500">Unggah hanya tersedia ketika status masih "Dalam Proses".</p>
                            @else
                                <form method="POST" action="{{ route('pegawai_medis.additional_task_claims.submit', $claim->id) }}" enctype="multipart/form-data" class="mt-3 space-y-3" x-data="{ fileName: '' }">
                                    @csrf
                                    <label class="block rounded-2xl border-2 border-dashed border-slate-200 bg-slate-50 text-center py-6 cursor-pointer transition-colors hover:border-sky-300"
                                        :class="fileName ? 'border-sky-400 bg-gradient-to-r from-sky-100 to-blue-100 text-sky-900' : ''">
                                        <template x-if="!fileName">
                                            <div>
                                                <p class="text-sm font-medium text-slate-700">Klik untuk pilih atau tarik &amp; lepas</p>
                                                <p class="text-xs text-slate-500">.doc, .xls, .ppt, .pdf &bull; Maks. 10 MB</p>
                                            </div>
                                        </template>
                                        <template x-if="fileName">
                                            <div class="text-sm font-semibold">
                                                File siap diunggah:<br>
                                                <span x-text="fileName"></span>
                                            </div>
                                        </template>
                                        <input type="file" name="result_file" accept=".doc,.docx,.xls,.xlsx,.ppt,.pptx,.pdf" class="hidden" required @change="fileName = $event.target.files.length ? $event.target.files[0].name : ''">
                                    </label>
                                    <textarea name="note" rows="3" class="w-full border border-slate-200 rounded-xl p-3 text-[15px]" placeholder="Catatan untuk kepala unit (opsional)"></textarea>
                                    <button class="w-full h-11 rounded-xl text-white font-semibold bg-gradient-to-r from-sky-400 to-blue-600 hover:brightness-110">Kirim untuk Review</button>
                                </form>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- CARD: Riwayat Klaim --}}
        <div id="tugas-selesai" class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6" x-data="{ historyDetail: null }">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-slate-500">Riwayat</p>
                    <h2 class="text-xl font-semibold text-slate-800">Riwayat Klaim</h2>
                </div>
                <a href="#tugas-selesai" class="text-sm text-slate-500 hover:underline">#</a>
            </div>
            <div class="overflow-x-auto">
                <x-ui.table min-width="720px">
                    <x-slot name="head">
                        <tr>
                            <th class="px-6 py-4 text-left whitespace-nowrap">Tugas</th>
                            <th class="px-6 py-4 text-left whitespace-nowrap">
                                Status
                                <span class="inline-block ml-1 text-amber-600 cursor-help" title="Status riwayat: Disetujui/Selesai/Dibatalkan/Ditolak.">!</span>
                            </th>
                            <th class="px-6 py-4 text-left whitespace-nowrap">Selesai / Update</th>
                            <th class="px-6 py-4 text-right whitespace-nowrap">Aksi</th>
                        </tr>
                    </x-slot>
                    @forelse($historyClaims as $claim)
                        <tr class="hover:bg-slate-50">
                            <td class="px-6 py-4 text-base">
                                <div class="font-medium text-slate-900">{{ $claim->task?->title ?? '-' }}</div>
                            </td>
                            <td class="px-6 py-4 text-base">
                                <span class="px-3 py-1 rounded-full text-xs font-medium {{ $statusClasses[$claim->status] ?? 'bg-slate-200 text-slate-700' }} cursor-help" title="{{ $statusHelp[$claim->status] ?? '' }}">
                                    {{ $statusLabels[$claim->status] ?? strtoupper($claim->status) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-base">
                                @php
                                    $historyTime = ($claim->completed_at ?: $claim->updated_at)?->timezone('Asia/Jakarta');
                                @endphp
                                {{ $historyTime ? $historyTime->format('d M Y H:i') : '-' }}
                            </td>
                            <td class="px-6 py-4 text-right">
                                <button type="button" class="px-5 py-2.5 rounded-xl text-white text-sm font-semibold bg-gradient-to-r from-sky-400 to-blue-600 shadow-sm hover:brightness-110" @click="historyDetail = historyDetail === '{{ $claim->id }}' ? null : '{{ $claim->id }}'">
                                    Detail
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-6 py-8 text-center text-slate-500">Belum ada riwayat klaim.</td>
                        </tr>
                    @endforelse
                </x-ui.table>
            </div>

            @foreach($historyClaims as $claim)
                @php
                    $instructionUrl = $claim->task?->policy_doc_path ? asset('storage/'.ltrim($claim->task->policy_doc_path,'/')) : null;
                    $resultUrl = $claim->result_file_path ? asset('storage/'.ltrim($claim->result_file_path,'/')) : null;
                    $note = $claim->penalty_note ?: $claim->result_note;
                @endphp
                <div x-cloak x-show="historyDetail === '{{ $claim->id }}'" class="mt-6 rounded-2xl border border-slate-100 bg-slate-50/70 p-5 space-y-4">
                    <div class="flex items-start justify-between flex-wrap gap-3">
                        <div>
                            <p class="text-xs uppercase tracking-wide text-slate-500">Ringkasan Klaim</p>
                            <h3 class="text-xl font-semibold text-slate-900">{{ $claim->task?->title ?? '-' }}</h3>
                            <p class="text-sm text-slate-500">Periode {{ $claim->task?->period?->name ?? '-' }}</p>
                        </div>
                        <button type="button" class="text-sm text-slate-500 hover:text-slate-700" @click="historyDetail = null">Tutup</button>
                    </div>
                    <div class="grid gap-4 md:grid-cols-4">
                        <div class="p-4 rounded-xl bg-white border border-slate-100">
                            <p class="text-xs text-slate-500">Status Akhir</p>
                            <p class="mt-2 inline-flex px-3 py-1 rounded-full text-xs font-medium {{ $statusClasses[$claim->status] ?? 'bg-slate-200 text-slate-700' }}">
                                {{ $statusLabels[$claim->status] ?? strtoupper($claim->status) }}
                            </p>
                        </div>
                        <div class="p-4 rounded-xl bg-white border border-slate-100">
                            <p class="text-xs text-slate-500">Tanggal Perubahan</p>
                            <p class="mt-2 text-sm font-medium text-slate-800">{{ optional(($claim->completed_at ?: $claim->updated_at)?->timezone('Asia/Jakarta'))->format('d M Y H:i') ?? '-' }}</p>
                        </div>
                        <div class="p-4 rounded-xl bg-white border border-slate-100">
                            <p class="text-xs text-slate-500">Bonus / Poin</p>
                            <p class="mt-2 text-sm font-medium text-slate-800">{{ $claim->task?->bonus_amount ? 'Rp '.number_format($claim->task->bonus_amount,0,',','.') : '-' }}</p>
                            <p class="text-xs text-slate-500">Poin: {{ $claim->task?->points ?? '-' }}</p>
                        </div>
                        @php
                            $ptH = (string)($claim->penalty_type ?? 'none');
                            $pvH = (float)($claim->penalty_value ?? 0);
                            $pbH = (string)($claim->penalty_base ?? 'task_bonus');
                            if ($ptH === 'none') {
                                $snapH = 'Tidak ada sanksi.';
                            } elseif ($ptH === 'amount') {
                                $snapH = 'Rp '.number_format($pvH,0,',','.');
                            } else {
                                $baseLblH = $pbH === 'remuneration' ? 'remunerasi' : 'bonus tugas';
                                $snapH = rtrim(rtrim(number_format($pvH,2,',','.'),'0'),',').'% dari '.$baseLblH;
                            }
                        @endphp
                        <div class="p-4 rounded-xl bg-white border border-slate-100">
                            <p class="text-xs text-slate-500">Violation / Penalty</p>
                            <p class="mt-2 text-sm font-medium text-slate-800">{{ $claim->is_violation ? 'Violation' : 'Tidak violation' }}</p>
                            <p class="text-xs text-slate-500">Snapshot: {{ $snapH }}</p>
                            @if($claim->penalty_applied)
                                <p class="text-xs text-slate-500">Penalty applied: Rp {{ number_format((float)($claim->penalty_amount ?? 0),0,',','.') }}</p>
                            @endif
                        </div>
                    </div>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="p-4 rounded-xl bg-white border border-dashed border-slate-200">
                            <p class="text-sm font-semibold text-slate-800">Dokumen</p>
                            <div class="mt-2 space-y-1 text-sm">
                                <div>
                                    <span class="text-xs text-slate-500">Instruksi</span>
                                    @if($instructionUrl)
                                        <a href="{{ $instructionUrl }}" target="_blank" class="inline-flex items-center gap-2 px-3 py-2 rounded-xl border border-slate-200 bg-white text-sky-800 hover:bg-sky-50 text-sm font-medium ml-2">
                                            <i class="fa-solid fa-file-word text-sky-500"></i>
                                            Dokumen Pendukung
                                        </a>
                                    @else
                                        <span class="ml-2 text-slate-400">Tidak ada file.</span>
                                    @endif
                                </div>
                                <div>
                                    <span class="text-xs text-slate-500">File Hasil</span>
                                    @if($resultUrl)
                                        <a href="{{ $resultUrl }}" target="_blank" class="inline-flex items-center gap-2 px-3 py-2 rounded-xl border border-emerald-100 bg-emerald-50 text-emerald-800 hover:bg-emerald-100 text-sm font-medium ml-2">
                                            <i class="fa-solid fa-file-lines text-emerald-500"></i>
                                            File Hasil
                                        </a>
                                    @else
                                        <span class="ml-2 text-slate-400">Belum ada file.</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div class="p-4 rounded-xl bg-white border border-slate-100">
                            <p class="text-sm font-semibold text-slate-800">Catatan</p>
                            <p class="mt-2 text-sm text-slate-600">{{ $note ?? 'Tidak ada catatan.' }}</p>
                            @if($claim->status === 'rejected' && $claim->penalty_note)
                                <p class="text-xs text-rose-600 mt-1">Catatan penolakan dari Kepala Unit.</p>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- CARD: Tugas Tidak Terklaim --}}
        <div id="tugas-tidak-terklaim" class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-slate-500">Evaluasi</p>
                    <h2 class="text-xl font-semibold text-slate-800">Tugas yang Pernah Diberikan (Tidak Diklaim)</h2>
                </div>
                <a href="#tugas-tidak-terklaim" class="text-sm text-slate-500 hover:underline">#</a>
            </div>
            <div class="overflow-x-auto">
                <x-ui.table min-width="860px">
                    <x-slot name="head">
                        <tr>
                            <th class="px-6 py-4 text-left whitespace-nowrap">Tugas</th>
                            <th class="px-6 py-4 text-left whitespace-nowrap">Periode</th>
                            <th class="px-6 py-4 text-left whitespace-nowrap">Jatuh Tempo</th>
                            <th class="px-6 py-4 text-left whitespace-nowrap">Bonus / Poin</th>
                            <th class="px-6 py-4 text-right whitespace-nowrap">Dokumen</th>
                        </tr>
                    </x-slot>
                    @forelse($missedTasks as $t)
                        @php
                            $missedDue = $t->due_date
                                ? \Illuminate\Support\Carbon::parse($t->due_date)->format('d M Y')
                                : '-';
                            $supportingUrl = $t->policy_doc_path
                                ? asset('storage/'.ltrim($t->policy_doc_path,'/'))
                                : null;
                        @endphp
                        <tr class="hover:bg-slate-50">
                            <td class="px-6 py-4 text-base">
                                <div class="font-medium text-slate-900">{{ $t->title }}</div>
                            </td>
                            <td class="px-6 py-4 text-base">{{ $t->period?->name ?? '-' }}</td>
                            <td class="px-6 py-4 text-base">{{ $missedDue }}</td>
                            <td class="px-6 py-4 text-base">
                                <span class="font-medium text-slate-900">{{ $t->bonus_amount ? 'Rp '.number_format($t->bonus_amount,0,',','.') : '-' }}</span>
                                <span class="text-slate-500"> / Poin: {{ $t->points ?? '-' }}</span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                @if($supportingUrl)
                                    <a href="{{ $supportingUrl }}" target="_blank" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-white border border-slate-200 text-slate-700 hover:bg-slate-100 text-sm">
                                        <i class="fa-solid fa-file-lines text-sky-500"></i> Dokumen
                                    </a>
                                @else
                                    <span class="text-sm text-slate-400">-</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-8 text-center text-slate-500">Belum ada data tugas yang terlewat.</td>
                        </tr>
                    @endforelse
                </x-ui.table>
            </div>
        </div>
    </div>
</x-app-layout>
