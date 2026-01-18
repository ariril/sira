<?php

namespace App\Http\Controllers\Web\AdminHospital;

use App\Http\Controllers\Controller;
use App\Models\AssessmentPeriod;
use App\Models\AssessmentApproval;
use App\Models\PerformanceAssessment;
use App\Models\User;
use App\Enums\AssessmentApprovalStatus;
use App\Enums\AssessmentValidationStatus;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use App\Services\AssessmentPeriods\PeriodPerformanceAssessmentService;
use App\Services\AssessmentPeriods\AssessmentPeriodRevisionService;
use App\Support\AssessmentPeriodGuard;

class AssessmentPeriodController extends Controller
{
    protected function perPageOptions(): array { return [5, 10, 12, 20, 30, 50]; }

    public function index(Request $request): View
    {
        // Sinkronkan lifecycle periode (auto active/locked + auto close)
        try {
            if (class_exists(\App\Services\AssessmentPeriods\AssessmentPeriodLifecycleService::class)) {
                app(\App\Services\AssessmentPeriods\AssessmentPeriodLifecycleService::class)->sync();
            } else {
                AssessmentPeriod::syncByNow();
            }
        } catch (\Throwable $e) {
            AssessmentPeriod::syncByNow();
        }

        $perPageOptions = $this->perPageOptions();
        $data = $request->validate([
            'q'        => ['nullable','string','max:100'],
            'status'   => ['nullable', Rule::in(AssessmentPeriod::STATUSES)],
            'per_page' => ['nullable','integer','in:' . implode(',', $perPageOptions)],
        ]);

        $q       = $data['q'] ?? null;
        $status  = $data['status'] ?? null;
        $perPage = (int)($data['per_page'] ?? 12);

        $items = AssessmentPeriod::query()
            ->when($q, function($w) use($q){
                $w->where('name','like',"%{$q}%");
            })
            ->when($status, fn($w) => $w->where('status', $status))
            ->orderByDesc('start_date')
            ->paginate($perPage)
            ->withQueryString();

        $approvalStats = [];
        $periodIds = $items->pluck('id');
        if ($periodIds->isNotEmpty()) {
            $pendingValue = AssessmentApprovalStatus::PENDING->value;
            $rows = DB::table('assessment_approvals as aa')
                ->join('performance_assessments as pa', 'pa.id', '=', 'aa.performance_assessment_id')
                ->whereIn('pa.assessment_period_id', $periodIds)
                ->selectRaw('pa.assessment_period_id as pid, SUM(CASE WHEN aa.status = ? THEN 1 ELSE 0 END) as pending_count, COUNT(*) as total_count', [$pendingValue])
                ->groupBy('pa.assessment_period_id')
                ->get();
            foreach ($rows as $r) {
                $approvalStats[$r->pid] = [
                    'pending' => (int) $r->pending_count,
                    'total' => (int) $r->total_count,
                ];
            }
        }

        return view('admin_rs.assessment_periods.index', [
            'items'          => $items,
            'perPage'        => $perPage,
            'perPageOptions' => $perPageOptions,
            'filters'        => [
                'q'      => $q,
                'status' => $status,
            ],
            'approvalStats'  => $approvalStats,
        ]);
    }

    public function create(): View
    {
        return view('admin_rs.assessment_periods.create', [
            'item' => new AssessmentPeriod(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);
        // pastikan tidak ada injeksi status / is_active dari form
        unset($data['status']);
        $period = AssessmentPeriod::create($data);
        return redirect()->route('admin_rs.assessment-periods.index')->with('status','Periode dibuat.');
    }

    public function edit(AssessmentPeriod $period): View
    {
        AssessmentPeriodGuard::requireDraftEditable($period, 'Edit');
        return view('admin_rs.assessment_periods.edit', [
            'item' => $period,
        ]);
    }

    public function update(Request $request, AssessmentPeriod $period): RedirectResponse
    {
        AssessmentPeriodGuard::requireDraftEditable($period, 'Edit');
        $data = $this->validateData($request, isUpdate: true, current: $period);
        unset($data['status']);
        $period->update($data);
        return redirect()->route('admin_rs.assessment-periods.index')->with('status','Periode diperbarui.');
    }

    public function destroy(AssessmentPeriod $period): RedirectResponse
    {
        // Guard: non-deletable statuses
        try {
            AssessmentPeriodGuard::requireDeletable($period, 'Hapus');
        } catch (\Throwable $e) {
            return back()->withErrors(['delete' => $e->getMessage()]);
        }

        if ($period->performanceAssessments()->exists() || $period->remunerations()->exists()) {
            return back()->withErrors(['delete' => 'Tidak dapat menghapus: periode sudah memiliki data terkait.']);
        }
        $period->delete();
        return back()->with('status','Periode dihapus.');
    }

    public function activate(AssessmentPeriod $period): RedirectResponse
    {
        abort(404);
    }

    public function lock(AssessmentPeriod $period, PeriodPerformanceAssessmentService $perfSvc): RedirectResponse
    {
        // Manual lock is not allowed (requirement: auto-lock only)
        return back()->withErrors([
            'status' => 'Periode tidak dapat dikunci secara manual. Sistem akan mengunci periode secara otomatis setelah melewati tanggal akhir periode.',
        ]);
    }

    public function startApproval(AssessmentPeriod $period, PeriodPerformanceAssessmentService $perfSvc): RedirectResponse
    {
        if ((string) $period->status !== AssessmentPeriod::STATUS_LOCKED) {
            return back()->withErrors(['status' => 'Tahap persetujuan hanya dapat dimulai ketika periode berstatus Dikunci.']);
        }

        // Pre-approval validation (warning, not error): require minimal 1 data for key modules.
        $missingWarnings = $this->buildPreApprovalWarnings($period);
        $force = (bool) request()->boolean('force');
        if (!$force && !empty($missingWarnings)) {
            return back()->with('approval_warning', [
                'period_id' => (int) $period->id,
                'messages' => array_values($missingWarnings),
            ]);
        }

        DB::transaction(function () use ($period, $perfSvc) {
            $period->refresh();

            $supportsAttempt = Schema::hasColumn('assessment_periods', 'approval_attempt');
            $attempt = $supportsAttempt ? max(1, (int) ($period->approval_attempt ?? 0)) : 1;

            $updates = [
                'status' => AssessmentPeriod::STATUS_APPROVAL,
            ];
            if ($supportsAttempt) {
                $updates['approval_attempt'] = $attempt;
            }
            if (Schema::hasColumn('assessment_periods', 'rejected_at')) {
                $updates['rejected_level'] = null;
                $updates['rejected_by_id'] = null;
                $updates['rejected_at'] = null;
                $updates['rejected_reason'] = null;
            }
            if (Schema::hasColumn('assessment_periods', 'closed_at')) {
                $updates['closed_at'] = null;
            }
            if (Schema::hasColumn('assessment_periods', 'closed_by_id')) {
                $updates['closed_by_id'] = null;
            }
            $period->update($updates);

            // Safety: ensure Penilaian Saya exists (and latest computed) before sending to approval.
            $perfSvc->initializeForPeriod($period);

            $this->createApprovalsForPeriod($period, $attempt);
        });

        return back()->with('status','Periode masuk tahap persetujuan. Semua penilaian dikirim ke alur approval.');
    }

    public function openRevision(Request $request, AssessmentPeriod $period, AssessmentPeriodRevisionService $svc): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:800'],
        ]);

        try {
            $svc->openRevision($period, $request->user(), (string) $data['reason']);
            return back()->with('status', 'Periode masuk mode revisi (terbatas).');
        } catch (\Throwable $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }
    }

    public function resubmitFromRevision(Request $request, AssessmentPeriod $period, AssessmentPeriodRevisionService $svc): RedirectResponse
    {
        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:800'],
        ]);

        try {
            $svc->resubmitFromRevision($period, $request->user(), $data['note'] ?? null);
            return back()->with('status', 'Periode diajukan ulang ke tahap persetujuan. Approval dimulai ulang dari Level 1.');
        } catch (\Throwable $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }
    }

    public function close(AssessmentPeriod $period): RedirectResponse
    {
        // Manual close is not allowed (requirement: auto-close only)
        return back()->withErrors([
            'status' => 'Periode tidak dapat ditutup secara manual. Sistem akan menutup periode secara otomatis setelah seluruh pegawai medis mencapai approval level terakhir dan berstatus approved.',
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function buildPreApprovalWarnings(AssessmentPeriod $period): array
    {
        $warnings = [];

        // attendance_import_batches
        if (Schema::hasTable('attendance_import_batches')) {
            $hasAttendance = DB::table('attendance_import_batches')
                ->where('assessment_period_id', $period->id)
                ->exists();
            if (!$hasAttendance) {
                $warnings[] = 'Belum ada import kehadiran pada periode ini. Apakah Anda yakin ingin melanjutkan ke proses approval?';
            }
        }

        // review_invitations (backward compatible: if no assessment_period_id column, fallback to created_at range)
        if (Schema::hasTable('review_invitations')) {
            $reviewQuery = DB::table('review_invitations');
            if (Schema::hasColumn('review_invitations', 'assessment_period_id')) {
                $reviewQuery->where('assessment_period_id', $period->id);
            } else {
                // Best-effort: approximate by created_at within period date range
                $start = Carbon::parse($period->start_date)->startOfDay();
                $end = Carbon::parse($period->end_date)->endOfDay();
                $reviewQuery->whereBetween('created_at', [$start, $end]);
            }

            $hasReviewInvites = $reviewQuery->exists();
            if (!$hasReviewInvites) {
                $warnings[] = 'Belum ada undangan review pada periode ini. Apakah Anda yakin ingin melanjutkan ke proses approval?';
            }
        }

        // metric_import_batches
        if (Schema::hasTable('metric_import_batches')) {
            $hasMetrics = DB::table('metric_import_batches')
                ->where('assessment_period_id', $period->id)
                ->exists();
            if (!$hasMetrics) {
                $warnings[] = 'Belum ada import data metrics pada periode ini. Apakah Anda yakin ingin melanjutkan ke proses approval?';
            }
        }

        return $warnings;
    }

    protected function validateData(Request $request, bool $isUpdate = false, ?AssessmentPeriod $current = null): array
    {
        $uniqueName = Rule::unique('assessment_periods','name');
        if ($isUpdate && $current) { $uniqueName = $uniqueName->ignore($current->id); }

        $rules = [
            'name'       => ['required','string','max:255',$uniqueName],
            'start_date' => ['required','date'],
            'end_date'   => ['required','date','after_or_equal:start_date'],
        ];

        $validator = Validator::make($request->all(), $rules);

        // Cek tidak boleh overlap dengan periode lain
        $validator->after(function ($v) use ($request, $isUpdate, $current) {
            try {
                $start = Carbon::parse($request->input('start_date'))->toDateString();
                $end   = Carbon::parse($request->input('end_date'))->toDateString();
            } catch (\Throwable $e) {
                return; // format tanggal salah akan tertangkap di rules di atas
            }

            $query = AssessmentPeriod::query()
                ->whereDate('start_date', '<=', $end)
                ->whereDate('end_date', '>=', $start);
            if ($isUpdate && $current) {
                $query->where('id', '!=', $current->id);
            }
            if ($query->exists()) {
                $v->errors()->add('start_date', 'Tanggal periode bersinggungan dengan periode lain. Harap pilih rentang yang berbeda.');
            }
        });

        return $validator->validate();
    }

    private function createApprovalsForPeriod(AssessmentPeriod $period, int $attempt): void
    {
        $assessments = PerformanceAssessment::query()
            ->where('assessment_period_id', $period->id)
            ->get(['id','user_id']);

        if ($assessments->isEmpty()) {
            return;
        }

        $adminApprover = User::query()->role(User::ROLE_ADMINISTRASI)->orderBy('id')->value('id');
        $pending = AssessmentApprovalStatus::PENDING->value;

        $attempt = max(1, (int) $attempt);

        foreach ($assessments as $assessment) {
            AssessmentApproval::firstOrCreate(
                [
                    'performance_assessment_id' => $assessment->id,
                    'level' => 1,
                    'attempt' => $attempt,
                ],
                [
                    'approver_id' => $adminApprover,
                    'status' => $pending,
                    'note' => null,
                    'acted_at' => null,
                ]
            );
            // Reset global status to pending when entering approval phase.
            $assessment->update([
                'validation_status' => AssessmentValidationStatus::PENDING->value,
            ]);
        }
    }
}
