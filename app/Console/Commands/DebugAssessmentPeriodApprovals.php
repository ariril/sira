<?php

namespace App\Console\Commands;

use App\Models\AssessmentPeriod;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DebugAssessmentPeriodApprovals extends Command
{
    protected $signature = 'debug:period-approvals {period_id : assessment_periods.id} {--attempt= : Force attempt (default: period.approval_attempt)} {--limit=10 : Sample rows limit for anomaly lists}';

    protected $description = 'Debug helper: summarize assessment_approvals attempts (active vs invalidated) + basic anomalies.';

    public function handle(): int
    {
        $periodId = (int) ($this->argument('period_id') ?? 0);
        if ($periodId <= 0) {
            $this->error('period_id wajib diisi. Contoh: php artisan debug:period-approvals 12');
            return self::FAILURE;
        }

        if (!Schema::hasTable('assessment_periods')) {
            $this->error('Table assessment_periods tidak ditemukan.');
            return self::FAILURE;
        }

        if (!Schema::hasTable('performance_assessments') || !Schema::hasTable('assessment_approvals')) {
            $this->error('Table performance_assessments/assessment_approvals tidak ditemukan.');
            return self::FAILURE;
        }

        $period = AssessmentPeriod::query()->find($periodId);
        if (!$period) {
            $this->error('Periode tidak ditemukan.');
            return self::FAILURE;
        }

        $supportsAttempt = Schema::hasColumn('assessment_periods', 'approval_attempt') && Schema::hasColumn('assessment_approvals', 'attempt');
        $attempt = (int) ($this->option('attempt') ?: 0);
        if ($attempt <= 0) {
            $attempt = $supportsAttempt ? (int) ($period->approval_attempt ?? 1) : 1;
        }

        $limit = (int) ($this->option('limit') ?: 10);
        $limit = max(1, min(50, $limit));

        $this->info('period');
        $this->line(json_encode([
            'id' => (int) $period->id,
            'name' => (string) ($period->name ?? ''),
            'status' => (string) ($period->status ?? ''),
            'approval_attempt' => $supportsAttempt ? (int) ($period->approval_attempt ?? 1) : null,
            'rejected_at' => Schema::hasColumn('assessment_periods', 'rejected_at') ? ($period->rejected_at ? (string) $period->rejected_at : null) : null,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $attempts = DB::table('assessment_approvals as aa')
            ->join('performance_assessments as pa', 'pa.id', '=', 'aa.performance_assessment_id')
            ->where('pa.assessment_period_id', $periodId)
            ->selectRaw('aa.attempt as attempt')
            ->selectRaw('COUNT(*) as total_cnt')
            ->selectRaw('SUM(CASE WHEN aa.invalidated_at IS NULL THEN 1 ELSE 0 END) as active_cnt')
            ->selectRaw('SUM(CASE WHEN aa.invalidated_at IS NOT NULL THEN 1 ELSE 0 END) as invalidated_cnt')
            ->groupBy('aa.attempt')
            ->orderBy('aa.attempt')
            ->get();

        $this->info('approvals by attempt');
        $this->table(['attempt', 'total', 'active', 'invalidated'], $attempts->map(fn ($r) => [
            'attempt' => (int) ($r->attempt ?? 0),
            'total' => (int) ($r->total_cnt ?? 0),
            'active' => (int) ($r->active_cnt ?? 0),
            'invalidated' => (int) ($r->invalidated_cnt ?? 0),
        ])->all());

        $this->info('attempt breakdown (active only)');
        $breakdown = DB::table('assessment_approvals as aa')
            ->join('performance_assessments as pa', 'pa.id', '=', 'aa.performance_assessment_id')
            ->where('pa.assessment_period_id', $periodId)
            ->where('aa.attempt', $attempt)
            ->whereNull('aa.invalidated_at')
            ->selectRaw('aa.level, aa.status, COUNT(*) as cnt')
            ->groupBy('aa.level', 'aa.status')
            ->orderBy('aa.level')
            ->orderBy('aa.status')
            ->get();

        $this->table(['level', 'status', 'cnt'], $breakdown->map(fn ($r) => [
            'level' => (int) ($r->level ?? 0),
            'status' => (string) ($r->status ?? ''),
            'cnt' => (int) ($r->cnt ?? 0),
        ])->all());

        // Anomaly 1: assessments missing Level 1 approval for chosen attempt.
        $missingLvl1Count = (int) DB::table('performance_assessments as pa')
            ->where('pa.assessment_period_id', $periodId)
            ->whereNotExists(function ($q) use ($attempt) {
                $q->select(DB::raw(1))
                    ->from('assessment_approvals as aa')
                    ->whereColumn('aa.performance_assessment_id', 'pa.id')
                    ->where('aa.level', 1)
                    ->where('aa.attempt', $attempt)
                    ->whereNull('aa.invalidated_at');
            })
            ->count();

        $this->info('anomalies');
        $this->line('missing level-1 approvals for attempt ' . $attempt . ': ' . $missingLvl1Count);

        // Anomaly 2: approvals from non-current attempt that are still active.
        if ($supportsAttempt) {
            $currentAttempt = (int) ($period->approval_attempt ?? 1);

            $nonCurrentActiveCount = (int) DB::table('assessment_approvals as aa')
                ->join('performance_assessments as pa', 'pa.id', '=', 'aa.performance_assessment_id')
                ->where('pa.assessment_period_id', $periodId)
                ->whereNull('aa.invalidated_at')
                ->where('aa.attempt', '!=', $currentAttempt)
                ->count();

            $this->line('active approvals but attempt != current(' . $currentAttempt . '): ' . $nonCurrentActiveCount);

            if ($nonCurrentActiveCount > 0) {
                $samples = DB::table('assessment_approvals as aa')
                    ->join('performance_assessments as pa', 'pa.id', '=', 'aa.performance_assessment_id')
                    ->where('pa.assessment_period_id', $periodId)
                    ->whereNull('aa.invalidated_at')
                    ->where('aa.attempt', '!=', $currentAttempt)
                    ->orderByDesc('aa.id')
                    ->limit($limit)
                    ->get(['aa.id', 'aa.performance_assessment_id', 'aa.level', 'aa.attempt', 'aa.status', 'aa.acted_at']);

                $this->warn('sample non-current active approvals (should be 0):');
                $this->table(['id', 'pa_id', 'level', 'attempt', 'status', 'acted_at'], $samples->map(fn ($r) => [
                    'id' => (int) ($r->id ?? 0),
                    'pa_id' => (int) ($r->performance_assessment_id ?? 0),
                    'level' => (int) ($r->level ?? 0),
                    'attempt' => (int) ($r->attempt ?? 0),
                    'status' => (string) ($r->status ?? ''),
                    'acted_at' => $r->acted_at ? (string) $r->acted_at : null,
                ])->all());
            }
        }

        return self::SUCCESS;
    }
}
