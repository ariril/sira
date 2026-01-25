@php
    /** @var \App\Models\AssessmentPeriod|null $period */
    $p = $period ?? null;
    $status = $p ? (string) ($p->status ?? '') : '';
    $isRevision = $p && $status === (\App\Models\AssessmentPeriod::STATUS_REVISION ?? 'revision');
    $isRejectedApproval = $p && method_exists($p, 'isRejectedApproval') && $p->isRejectedApproval();

    $rejectedBy = null;
    $rejectionAudit = null;
    $rejectionMeta = [];
    $rejectedStaffName = null;

    if ($p && $isRejectedApproval) {
        try {
            if (method_exists($p, 'rejectedBy')) {
                $rejectedBy = $p->rejectedBy;
            }

            if (\Illuminate\Support\Facades\Schema::hasTable('assessment_period_audit_logs')) {
                $row = \Illuminate\Support\Facades\DB::table('assessment_period_audit_logs')
                    ->where('assessment_period_id', (int) $p->id)
                    ->where('action', 'period_rejected')
                    ->orderByDesc('id')
                    ->first(['id', 'actor_id', 'reason', 'meta', 'created_at']);
                if ($row) {
                    $rejectionAudit = $row;
                    $rejectionMeta = [];
                    if (!empty($row->meta)) {
                        $decoded = json_decode((string) $row->meta, true);
                        if (is_array($decoded)) {
                            $rejectionMeta = $decoded;
                        }
                    }
                }
            }

            $rejectedStaffName = $rejectionMeta['rejected_staff_name'] ?? null;
            if (!filled($rejectedStaffName) && \Illuminate\Support\Facades\Schema::hasTable('assessment_approvals')) {
                $attempt = max(1, (int) ($p->approval_attempt ?? 0));
                if ($attempt === 0) {
                    $attempt = 1;
                }

                $fallback = \Illuminate\Support\Facades\DB::table('assessment_approvals as aa')
                    ->join('performance_assessments as pa', 'pa.id', '=', 'aa.performance_assessment_id')
                    ->leftJoin('users as u', 'u.id', '=', 'pa.user_id')
                    ->where('pa.assessment_period_id', (int) $p->id)
                    ->whereNull('aa.invalidated_at')
                    ->where('aa.attempt', $attempt)
                    ->where('aa.status', 'rejected')
                    ->orderByDesc('aa.acted_at')
                    ->orderByDesc('aa.id')
                    ->value('u.name');

                $rejectedStaffName = filled($fallback) ? (string) $fallback : null;
            }
        } catch (\Throwable $e) {
            // best-effort only
        }
    }

    $role = auth()->user()?->getActiveRoleSlug();
@endphp

@if($p && ($isRevision || $isRejectedApproval))
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-4">
        @if($isRejectedApproval)
            @php($modalId = 'period-reject-detail-' . (int) $p->id)
            <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-red-900">
                <div class="font-semibold">PERIODE DITOLAK</div>
                <div class="text-sm">
                    <span class="font-medium">Periode:</span> {{ $p->name ?? ('#' . $p->id) }}
                </div>
                <div class="mt-2 text-sm">
                    Semua input/perubahan dikunci sampai Admin RS membuka mode Revisi.
                </div>

                <div class="mt-2 flex items-center gap-3 text-sm">
                    <button type="button" class="underline font-medium" onclick="document.getElementById('{{ $modalId }}')?.showModal();">
                        Detail penolakan
                    </button>
                </div>
            </div>

            <dialog id="{{ $modalId }}" class="fixed inset-0 m-auto rounded-xl p-0 w-[calc(100%-2rem)] max-w-lg backdrop:bg-slate-900/40">
                <div class="bg-white rounded-xl overflow-hidden">
                    <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between gap-3">
                        <div>
                            <div class="text-sm font-semibold text-slate-900">Detail Penolakan Periode</div>
                            <div class="text-xs text-slate-500">{{ $p->name ?? ('#' . $p->id) }}</div>
                        </div>
                        <button type="button" class="h-9 px-3 rounded-lg text-sm border border-slate-200 text-slate-600 hover:bg-slate-50" onclick="document.getElementById('{{ $modalId }}')?.close();">
                            Tutup
                        </button>
                    </div>

                    <div class="px-5 py-4 space-y-3 text-sm text-slate-700">
                        <div>
                            <div class="text-xs text-slate-500">Alasan</div>
                            <div class="font-medium text-slate-900">{{ $p->rejected_reason ?? '-' }}</div>
                        </div>

                        <div>
                            <div class="text-xs text-slate-500">Pegawai yang ditolak</div>
                            <div class="font-medium text-slate-900">{{ $rejectedStaffName ?? '-' }}</div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <div class="text-xs text-slate-500">Level</div>
                                <div class="font-medium text-slate-900">{{ $p->rejected_level ? (int) $p->rejected_level : '-' }}</div>
                            </div>
                            <div>
                                <div class="text-xs text-slate-500">Waktu</div>
                                <div class="font-medium text-slate-900">{{ $p->rejected_at ? \Illuminate\Support\Carbon::parse($p->rejected_at)->format('d M Y H:i') : '-' }}</div>
                            </div>
                        </div>

                        <div>
                            <div class="text-xs text-slate-500">Ditolak oleh</div>
                            <div class="font-medium text-slate-900">{{ $rejectedBy->name ?? '-' }}</div>
                        </div>
                    </div>

                    @if($role === 'admin_rs')
                        <div class="px-5 py-4 border-t border-slate-100 flex justify-end">
                            <x-ui.button as="a" href="{{ route('admin_rs.assessment-periods.index') }}" variant="success" class="h-10 px-4 text-sm">
                                Buka Periode
                            </x-ui.button>
                        </div>
                    @endif
                </div>
            </dialog>
        @endif

        @if($isRevision)
            <div class="mb-4 rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-blue-900">
                <div class="font-semibold">PERIODE REVISI</div>
                <div class="text-sm">
                    <span class="font-medium">Periode:</span> {{ $p->name ?? ('#' . $p->id) }}
                    @if(!empty($p->revision_opened_reason))
                        <div><span class="font-medium">Catatan Revisi:</span> {{ $p->revision_opened_reason }}</div>
                    @endif
                </div>
                <div class="mt-2 text-sm">
                    Perubahan hanya boleh dilakukan pada modul yang diizinkan (mis. import absensi/metric, tugas tambahan, 360) sesuai role.
                </div>
            </div>
        @endif
    </div>
@endif
