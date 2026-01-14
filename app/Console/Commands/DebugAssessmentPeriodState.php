<?php

namespace App\Console\Commands;

use App\Models\AssessmentPeriod;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DebugAssessmentPeriodState extends Command
{
    protected $signature = 'debug:period-state {period_id? : assessment_periods.id} {--latest : Auto-pick latest REVISION, else rejected APPROVAL, else ACTIVE, else latest by date} {--json : Output JSON payload}';

    protected $description = 'Debug helper: inspect assessment period status + rejected/revision metadata + approval attempt summary.';

    public function handle(): int
    {
        if (!Schema::hasTable('assessment_periods')) {
            $this->error('Table assessment_periods tidak ditemukan.');
            return self::FAILURE;
        }

        $periodId = (int) ($this->argument('period_id') ?? 0);

        if ($periodId <= 0 && $this->option('latest')) {
            $periodId = (int) (AssessmentPeriod::query()
                ->where('status', AssessmentPeriod::STATUS_REVISION)
                ->orderByDesc('start_date')
                ->value('id') ?? 0);

            if ($periodId <= 0 && Schema::hasColumn('assessment_periods', 'rejected_at')) {
                $periodId = (int) (AssessmentPeriod::query()
                    ->where('status', AssessmentPeriod::STATUS_APPROVAL)
                    ->whereNotNull('rejected_at')
                    ->orderByDesc('start_date')
                    ->value('id') ?? 0);
            }

            if ($periodId <= 0) {
                $periodId = (int) (AssessmentPeriod::query()
                    ->where('status', AssessmentPeriod::STATUS_ACTIVE)
                    ->orderByDesc('start_date')
                    ->value('id') ?? 0);
            }

            if ($periodId <= 0) {
                $periodId = (int) (AssessmentPeriod::query()->orderByDesc('start_date')->value('id') ?? 0);
            }
        }

        if ($periodId <= 0) {
            $periods = AssessmentPeriod::query()
                ->orderByDesc('start_date')
                ->limit(15)
                ->get(['id', 'name', 'status', 'start_date', 'end_date']);

            $this->info('Pilih period_id atau gunakan --latest');
            $this->line('Contoh: php artisan debug:period-state 12');
            $this->line('Contoh: php artisan debug:period-state --latest');
            $this->table(['id', 'name', 'status', 'start', 'end'], $periods->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'status' => $p->status,
                'start' => (string) $p->start_date,
                'end' => (string) $p->end_date,
            ])->all());
            return self::SUCCESS;
        }

        $period = AssessmentPeriod::query()->find($periodId);
        if (!$period) {
            $this->error('Periode tidak ditemukan.');
            return self::FAILURE;
        }

        $supportsRejected = Schema::hasColumn('assessment_periods', 'rejected_at');
        $supportsRevisionMeta = Schema::hasColumn('assessment_periods', 'revision_opened_at');
        $supportsAttempt = Schema::hasColumn('assessment_periods', 'approval_attempt');

        $approvalAttempt = $supportsAttempt ? (int) ($period->approval_attempt ?? 1) : 1;

        $payload = [
            'period' => [
                'id' => (int) $period->id,
                'name' => (string) ($period->name ?? ''),
                'status' => (string) ($period->status ?? ''),
                'start_date' => (string) ($period->start_date ?? ''),
                'end_date' => (string) ($period->end_date ?? ''),
                'is_currently_active' => method_exists($period, 'isCurrentlyActive') ? (bool) $period->isCurrentlyActive() : null,
                'locked_at' => (string) ($period->locked_at ?? ''),
                'closed_at' => (string) ($period->closed_at ?? ''),
                'approval_attempt' => $approvalAttempt,
                'is_rejected_approval' => method_exists($period, 'isRejectedApproval') ? (bool) $period->isRejectedApproval() : null,
                'is_revision' => (string) ($period->status ?? '') === AssessmentPeriod::STATUS_REVISION,
            ],
        ];

        if ($supportsRejected) {
            $payload['rejected'] = [
                'rejected_level' => $period->rejected_level ?? null,
                'rejected_by_id' => $period->rejected_by_id ?? null,
                'rejected_at' => $period->rejected_at ? (string) $period->rejected_at : null,
                'rejected_reason' => $period->rejected_reason ?? null,
            ];
        }

        if ($supportsRevisionMeta) {
            $payload['revision'] = [
                'revision_opened_by_id' => $period->revision_opened_by_id ?? null,
                'revision_opened_at' => $period->revision_opened_at ? (string) $period->revision_opened_at : null,
                'revision_opened_reason' => $period->revision_opened_reason ?? null,
            ];
        }

        $payload['counts'] = [
            'performance_assessments' => Schema::hasTable('performance_assessments')
                ? (int) DB::table('performance_assessments')->where('assessment_period_id', (int) $period->id)->count()
                : null,
        ];

        // Approval summary (best effort)
        if (Schema::hasTable('assessment_approvals') && Schema::hasTable('performance_assessments')) {
            $attemptRows = DB::table('assessment_approvals as aa')
                ->join('performance_assessments as pa', 'pa.id', '=', 'aa.performance_assessment_id')
                ->where('pa.assessment_period_id', (int) $period->id)
                ->selectRaw('aa.attempt as attempt')
                ->selectRaw('COUNT(*) as total_cnt')
                ->selectRaw('SUM(CASE WHEN aa.invalidated_at IS NULL THEN 1 ELSE 0 END) as active_cnt')
                ->selectRaw('SUM(CASE WHEN aa.invalidated_at IS NOT NULL THEN 1 ELSE 0 END) as invalidated_cnt')
                ->groupBy('aa.attempt')
                ->orderBy('aa.attempt')
                ->get();

            $currentAttemptRows = DB::table('assessment_approvals as aa')
                ->join('performance_assessments as pa', 'pa.id', '=', 'aa.performance_assessment_id')
                ->where('pa.assessment_period_id', (int) $period->id)
                ->where('aa.attempt', $approvalAttempt)
                ->whereNull('aa.invalidated_at')
                ->selectRaw('aa.level, aa.status, COUNT(*) as cnt')
                ->groupBy('aa.level', 'aa.status')
                ->orderBy('aa.level')
                ->orderBy('aa.status')
                ->get();

            $payload['approvals'] = [
                'attempts' => $attemptRows,
                'current_attempt' => $approvalAttempt,
                'current_attempt_breakdown' => $currentAttemptRows,
            ];
        }

        if ($this->option('json')) {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return self::SUCCESS;
        }

        $this->info('assessment_periods');
        $this->line(json_encode($payload['period'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        if (isset($payload['rejected'])) {
            $this->info('rejected metadata');
            $this->line(json_encode($payload['rejected'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        if (isset($payload['revision'])) {
            $this->info('revision metadata');
            $this->line(json_encode($payload['revision'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        $this->info('counts');
        $this->line(json_encode($payload['counts'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        if (isset($payload['approvals'])) {
            $this->info('approvals by attempt');
            $this->table(['attempt', 'total', 'active', 'invalidated'], collect($payload['approvals']['attempts'])->map(fn ($r) => [
                'attempt' => (int) ($r->attempt ?? 0),
                'total' => (int) ($r->total_cnt ?? 0),
                'active' => (int) ($r->active_cnt ?? 0),
                'invalidated' => (int) ($r->invalidated_cnt ?? 0),
            ])->all());

            $this->info('current attempt breakdown (active only)');
            $this->table(['level', 'status', 'cnt'], collect($payload['approvals']['current_attempt_breakdown'])->map(fn ($r) => [
                'level' => (int) ($r->level ?? 0),
                'status' => (string) ($r->status ?? ''),
                'cnt' => (int) ($r->cnt ?? 0),
            ])->all());
        }

        return self::SUCCESS;
    }
}
