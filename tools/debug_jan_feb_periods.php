<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

function findJanCreatedFebUsedInvitation(int $limit = 1500): array
{
    $rows = DB::table('review_invitations')
        ->whereNotNull('used_at')
        ->orderByDesc('used_at')
        ->limit($limit)
        ->get([
            'id',
            'registration_ref',
            'assessment_period_id',
            'status',
            'created_at',
            'sent_at',
            'used_at',
        ]);

    // 1) Strict: created in Jan, used in Feb
    foreach ($rows as $r) {
        $created = $r->created_at ? Carbon::parse((string) $r->created_at) : null;
        $used = $r->used_at ? Carbon::parse((string) $r->used_at) : null;
        if (!$created || !$used) {
            continue;
        }
        if ($created->month === 1 && $used->month === 2) {
            $full = DB::table('review_invitations as i')
                ->leftJoin('assessment_periods as p', 'p.id', '=', 'i.assessment_period_id')
                ->where('i.id', (int) $r->id)
                ->first([
                    'i.id',
                    'i.registration_ref',
                    'i.status',
                    'i.assessment_period_id',
                    'p.name as period_name',
                    'i.created_at',
                    'i.sent_at',
                    'i.used_at',
                ]);
            return ['match_type' => 'created_jan_used_feb', 'row' => $full];
        }
    }

    // 2) Common: sent in Jan, used in Feb (created_at could be different)
    foreach ($rows as $r) {
        $sent = $r->sent_at ? Carbon::parse((string) $r->sent_at) : null;
        $used = $r->used_at ? Carbon::parse((string) $r->used_at) : null;
        if (!$sent || !$used) {
            continue;
        }
        if ($sent->month === 1 && $used->month === 2) {
            $full = DB::table('review_invitations as i')
                ->leftJoin('assessment_periods as p', 'p.id', '=', 'i.assessment_period_id')
                ->where('i.id', (int) $r->id)
                ->first([
                    'i.id',
                    'i.registration_ref',
                    'i.status',
                    'i.assessment_period_id',
                    'p.name as period_name',
                    'i.created_at',
                    'i.sent_at',
                    'i.used_at',
                ]);
            return ['match_type' => 'sent_jan_used_feb', 'row' => $full];
        }
    }

    // 3) Fallback: any cross-month usage to prove period linkage behavior
    foreach ($rows as $r) {
        $created = $r->created_at ? Carbon::parse((string) $r->created_at) : null;
        $sent = $r->sent_at ? Carbon::parse((string) $r->sent_at) : null;
        $used = $r->used_at ? Carbon::parse((string) $r->used_at) : null;
        if (!$used) {
            continue;
        }

        $createdDiff = $created ? ($created->format('Y-m') !== $used->format('Y-m')) : false;
        $sentDiff = $sent ? ($sent->format('Y-m') !== $used->format('Y-m')) : false;

        if ($createdDiff || $sentDiff) {
            $full = DB::table('review_invitations as i')
                ->leftJoin('assessment_periods as p', 'p.id', '=', 'i.assessment_period_id')
                ->where('i.id', (int) $r->id)
                ->first([
                    'i.id',
                    'i.registration_ref',
                    'i.status',
                    'i.assessment_period_id',
                    'p.name as period_name',
                    'i.created_at',
                    'i.sent_at',
                    'i.used_at',
                ]);

            return ['match_type' => $createdDiff ? 'created_month_differs_from_used' : 'sent_month_differs_from_used', 'row' => $full];
        }
    }

    return ['match_type' => 'no_match', 'row' => null];
}

function findJanCreatedFebSubmittedTaskClaim(int $limit = 3000): array
{
    $rows = DB::table('additional_task_claims as c')
        ->join('additional_tasks as t', 't.id', '=', 'c.additional_task_id')
        ->whereNotNull('c.submitted_at')
        ->orderByDesc('c.submitted_at')
        ->limit($limit)
        ->get([
            'c.id as claim_id',
            'c.status as claim_status',
            'c.submitted_at',
            'c.reviewed_at',
            'c.awarded_points',
            't.id as task_id',
            't.title',
            't.assessment_period_id',
            't.created_at as task_created_at',
            't.due_date',
            't.due_time',
        ]);

    // 1) Strict: task created in Jan, submitted in Feb
    foreach ($rows as $r) {
        $taskCreated = $r->task_created_at ? Carbon::parse((string) $r->task_created_at) : null;
        $submitted = $r->submitted_at ? Carbon::parse((string) $r->submitted_at) : null;
        if (!$taskCreated || !$submitted) {
            continue;
        }
        if ($taskCreated->month === 1 && $submitted->month === 2) {
            $full = DB::table('additional_task_claims as c')
                ->join('additional_tasks as t', 't.id', '=', 'c.additional_task_id')
                ->leftJoin('assessment_periods as p', 'p.id', '=', 't.assessment_period_id')
                ->where('c.id', (int) $r->claim_id)
                ->first([
                    'c.id as claim_id',
                    'c.status as claim_status',
                    'c.submitted_at',
                    'c.reviewed_at',
                    'c.awarded_points',
                    't.id as task_id',
                    't.title',
                    't.assessment_period_id',
                    'p.name as period_name',
                    't.created_at as task_created_at',
                    't.due_date',
                    't.due_time',
                ]);
            return ['match_type' => 'task_created_jan_submitted_feb', 'row' => $full];
        }
    }

    // 2) Also relevant: due date in Feb, but task created earlier and submitted in Feb
    foreach ($rows as $r) {
        $due = $r->due_date ? Carbon::parse((string) $r->due_date) : null;
        $submitted = $r->submitted_at ? Carbon::parse((string) $r->submitted_at) : null;
        if (!$due || !$submitted) {
            continue;
        }
        if ($due->month === 2 && $submitted->month === 2) {
            $full = DB::table('additional_task_claims as c')
                ->join('additional_tasks as t', 't.id', '=', 'c.additional_task_id')
                ->leftJoin('assessment_periods as p', 'p.id', '=', 't.assessment_period_id')
                ->where('c.id', (int) $r->claim_id)
                ->first([
                    'c.id as claim_id',
                    'c.status as claim_status',
                    'c.submitted_at',
                    'c.reviewed_at',
                    'c.awarded_points',
                    't.id as task_id',
                    't.title',
                    't.assessment_period_id',
                    'p.name as period_name',
                    't.created_at as task_created_at',
                    't.due_date',
                    't.due_time',
                ]);
            return ['match_type' => 'due_feb_submitted_feb', 'row' => $full];
        }
    }

    // 3) Fallback: any cross-month submission to prove period linkage behavior
    foreach ($rows as $r) {
        $taskCreated = $r->task_created_at ? Carbon::parse((string) $r->task_created_at) : null;
        $submitted = $r->submitted_at ? Carbon::parse((string) $r->submitted_at) : null;
        if (!$taskCreated || !$submitted) {
            continue;
        }
        if ($taskCreated->format('Y-m') !== $submitted->format('Y-m')) {
            $full = DB::table('additional_task_claims as c')
                ->join('additional_tasks as t', 't.id', '=', 'c.additional_task_id')
                ->leftJoin('assessment_periods as p', 'p.id', '=', 't.assessment_period_id')
                ->where('c.id', (int) $r->claim_id)
                ->first([
                    'c.id as claim_id',
                    'c.status as claim_status',
                    'c.submitted_at',
                    'c.reviewed_at',
                    'c.awarded_points',
                    't.id as task_id',
                    't.title',
                    't.assessment_period_id',
                    'p.name as period_name',
                    't.created_at as task_created_at',
                    't.due_date',
                    't.due_time',
                ]);
            return ['match_type' => 'task_created_month_differs_from_submitted', 'row' => $full];
        }
    }

    return ['match_type' => 'no_match', 'row' => null];
}

$invMatch = findJanCreatedFebUsedInvitation();
$claimMatch = findJanCreatedFebSubmittedTaskClaim();
$inv = $invMatch['row'] ?? null;
$claim = $claimMatch['row'] ?? null;

$out = [
    'invitation_match_type' => (string) ($invMatch['match_type'] ?? 'unknown'),
    'invitation' => $inv ? [
        'id' => $inv->id,
        'registration_ref' => $inv->registration_ref,
        'status' => $inv->status,
        'assessment_period_id' => $inv->assessment_period_id,
        'period_name' => $inv->period_name,
        'created_at' => (string) $inv->created_at,
        'sent_at' => $inv->sent_at ? (string) $inv->sent_at : null,
        'used_at' => (string) $inv->used_at,
    ] : null,

    'additional_task_claim_match_type' => (string) ($claimMatch['match_type'] ?? 'unknown'),
    'additional_task_claim' => $claim ? [
        'claim_id' => $claim->claim_id,
        'claim_status' => $claim->claim_status,
        'is_approved' => ((string) $claim->claim_status) === 'approved',
        'submitted_at' => (string) $claim->submitted_at,
        'reviewed_at' => $claim->reviewed_at ? (string) $claim->reviewed_at : null,
        'awarded_points' => $claim->awarded_points !== null ? (string) $claim->awarded_points : null,
        'task_id' => $claim->task_id,
        'task_title' => $claim->title,
        'assessment_period_id' => $claim->assessment_period_id,
        'period_name' => $claim->period_name,
        'task_created_at' => (string) $claim->task_created_at,
        'due_date' => (string) $claim->due_date,
        'due_time' => (string) $claim->due_time,
    ] : null,
];

echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
