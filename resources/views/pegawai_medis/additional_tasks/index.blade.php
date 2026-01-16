<x-app-layout title="Tugas Tambahan">
    <x-slot name="header">
        <h1 class="text-2xl font-semibold text-slate-800">Tugas Tambahan (Poin)</h1>
    </x-slot>

    <div class="container-px py-6 space-y-6">
        @unless($activePeriod)
            <div class="rounded-xl border border-amber-200 bg-amber-50 text-amber-800 px-4 py-3 text-sm">
                <div class="font-semibold">Periode penilaian tidak aktif.</div>
                <div>Tugas tanpa periode masih bisa tampil; tugas berperiode membutuhkan periode aktif.</div>
            </div>
        @endunless

        @php
            $statusLabels = [
                'submitted' => 'Menunggu Review',
                'approved' => 'Disetujui',
                'rejected' => 'Ditolak',
            ];
            $statusClasses = [
                'submitted' => 'bg-amber-100 text-amber-800',
                'approved' => 'bg-emerald-100 text-emerald-800',
                'rejected' => 'bg-rose-100 text-rose-800',
            ];
        @endphp

        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-slate-500">Daftar</p>
                    <h2 class="text-xl font-semibold text-slate-800">Tugas Open</h2>
                </div>
            </div>

            <div class="space-y-4">
                @forelse($tasks as $task)
                    @php
                        $claim = $myClaimsByTaskId->get($task->id);
                        $dueTime = $task->due_time ?: '23:59:59';
                        $dueDate = $task->due_date ? \Illuminate\Support\Carbon::parse($task->due_date)->toDateString() : null;
                        $due = $dueDate ? \Illuminate\Support\Carbon::parse($dueDate.' '.$dueTime, config('app.timezone'))->format('d M Y H:i') : '-';
                        $claimsUsed = (int) ($task->claims_used ?? 0);
                        $maxClaims = $task->max_claims;
                        $quotaText = empty($maxClaims) ? $claimsUsed.' / ∞' : $claimsUsed.' / '.(int)$maxClaims;
                    @endphp

                    <div class="rounded-2xl border border-slate-100 p-5" x-data="{ open: false }">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <div class="text-lg font-semibold text-slate-900">{{ $task->title }}</div>
                                <div class="mt-1 text-sm text-slate-500">
                                    <span>Periode: <span class="font-medium text-slate-700">{{ $task->period?->name ?? '-' }}</span></span>
                                    <span class="mx-2">•</span>
                                    <span>Jatuh tempo: <span class="font-medium text-slate-700">{{ $due }}</span></span>
                                    <span class="mx-2">•</span>
                                    <span>Kuota: <span class="font-medium text-slate-700">{{ $quotaText }}</span></span>
                                </div>
                                @if($task->description)
                                    <div class="mt-2 text-sm text-slate-600">{{ $task->description }}</div>
                                @endif
                            </div>

                            <div class="text-right">
                                <div class="text-sm text-slate-500">Poin</div>
                                <div class="text-xl font-semibold text-slate-900">{{ rtrim(rtrim(number_format((float)$task->points, 2, ',', '.'),'0'),',') }}</div>

                                <div class="mt-2">
                                    @if($claim)
                                        <span class="px-3 py-1 rounded-full text-xs font-medium {{ $statusClasses[$claim->status] ?? 'bg-slate-200 text-slate-700' }}">
                                            {{ $statusLabels[$claim->status] ?? strtoupper($claim->status) }}
                                        </span>
                                    @else
                                        <span class="px-3 py-1 rounded-full text-xs font-medium bg-slate-100 text-slate-700">Belum submit</span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        @if($claim)
                            <div class="mt-4 text-sm text-slate-600">
                                <div>Submit: <span class="font-medium text-slate-800">{{ optional($claim->submitted_at?->timezone(config('app.timezone')))->format('d M Y H:i') ?? '-' }}</span></div>
                                <div>Review: <span class="font-medium text-slate-800">{{ optional($claim->reviewed_at?->timezone(config('app.timezone')))->format('d M Y H:i') ?? '-' }}</span></div>
                                @if($claim->review_comment)
                                    <div class="mt-2 p-3 rounded-xl bg-slate-50 border border-slate-100">
                                        <div class="text-xs uppercase tracking-wide text-slate-500">Catatan Reviewer</div>
                                        <div class="mt-1">{{ $claim->review_comment }}</div>
                                    </div>
                                @endif
                            </div>
                        @else
                            <div class="mt-4 flex items-center justify-end gap-3">
                                <button type="button" class="px-4 py-2 rounded-xl text-sm font-semibold text-sky-700 bg-sky-50 hover:bg-sky-100 ring-1 ring-sky-100" @click="open = !open">
                                    Submit hasil
                                </button>
                            </div>

                            <div x-cloak x-show="open" class="mt-4 rounded-2xl border border-slate-100 bg-slate-50/60 p-4">
                                <form method="POST" action="{{ route('pegawai_medis.additional_tasks.submit', $task->id) }}" enctype="multipart/form-data" class="space-y-3">
                                    @csrf
                                    <div>
                                        <label class="block text-sm font-medium text-slate-700">Catatan</label>
                                        <textarea name="note" rows="3" class="mt-1 w-full rounded-xl border-slate-200 focus:border-sky-400 focus:ring-sky-200" placeholder="Tuliskan ringkas hasil pekerjaan (opsional)">{{ old('note') }}</textarea>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-slate-700">File (opsional)</label>
                                        <input type="file" name="result_file" class="mt-1 block w-full text-sm text-slate-700" />
                                        <p class="mt-1 text-xs text-slate-500">Maks 10MB. Format: doc/docx/xls/xlsx/ppt/pptx/pdf.</p>
                                    </div>
                                    <div class="flex items-center justify-end gap-3">
                                        <button type="submit" class="px-4 py-2 rounded-xl text-white text-sm font-semibold bg-gradient-to-r from-sky-400 to-blue-600 shadow-sm hover:brightness-110 focus:ring-2 focus:ring-offset-1 focus:ring-sky-200">
                                            Kirim
                                        </button>
                                    </div>
                                </form>
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="text-sm text-slate-500">Belum ada tugas open untuk unit Anda.</div>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>