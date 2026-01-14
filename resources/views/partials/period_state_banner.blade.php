@php
    /** @var \App\Models\AssessmentPeriod|null $period */
    $p = $period ?? null;
    $status = $p ? (string) ($p->status ?? '') : '';
    $isRevision = $p && $status === (\App\Models\AssessmentPeriod::STATUS_REVISION ?? 'revision');
    $isRejectedApproval = $p && method_exists($p, 'isRejectedApproval') && $p->isRejectedApproval();

    $role = auth()->user()?->getActiveRoleSlug();
@endphp

@if($p && ($isRevision || $isRejectedApproval))
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        @if($isRejectedApproval)
            <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-red-900">
                <div class="font-semibold">PERIODE DITOLAK</div>
                <div class="text-sm">
                    <span class="font-medium">Periode:</span> {{ $p->name ?? ('#' . $p->id) }}
                    @if(!empty($p->rejected_reason))
                        <div><span class="font-medium">Alasan:</span> {{ $p->rejected_reason }}</div>
                    @endif
                </div>
                <div class="mt-2 text-sm">
                    Semua input/perubahan dikunci sampai Admin RS membuka mode Revisi.
                    @if($role === 'admin_rs')
                        <a class="underline font-medium" href="{{ route('admin_rs.assessment-periods.index') }}">Buka Periode</a>
                    @endif
                </div>
            </div>
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
