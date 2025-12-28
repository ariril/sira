<?php

namespace App\Http\Controllers\Web\AdminHospital;

use App\Http\Controllers\Controller;
use App\Imports\ReviewInvitationImport;
use App\Models\AssessmentPeriod;
use App\Services\ReviewInvitationService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use App\Support\AssessmentPeriodGuard;

class ReviewInvitationImportController extends Controller
{
    public function form(Request $request): View
    {
        $period = $this->resolvePeriodOrAbort();
        return view('admin_rs.review_invitations.import', [
            'results' => session('review_invitation_import_results', []),
            'summary' => session('review_invitation_import_summary', null),
            'period' => $period,
        ]);
    }

    public function process(Request $request, ReviewInvitationService $service): View
    {
        $period = $this->resolvePeriodOrAbort();

        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt'],
        ]);

        $service->setTargetPeriodId($period?->id ? (int) $period->id : null);

        $import = new ReviewInvitationImport($service);
        $import->import($request->file('file')->getRealPath());

        $results = $import->results;

        $summary = [
            'success' => collect($results)->where('status', 'success')->count(),
            'failed' => collect($results)->where('status', 'failed')->count(),
            'skipped' => collect($results)->where('status', 'skipped')->count(),
        ];

        $successRows = collect($results)
            ->where('status', 'success')
            ->map(fn ($r) => Arr::only($r, ['registration_ref', 'patient_name', 'contact', 'link_undangan']))
            ->values()
            ->all();

        session([
            'review_invitation_import_results' => $results,
            'review_invitation_import_summary' => $summary,
            'review_invitation_import_success' => $successRows,
        ]);

        return view('admin_rs.review_invitations.import', [
            'results' => $results,
            'summary' => $summary,
            'period' => $period,
        ]);
    }

    private function resolvePeriodOrAbort(): ?AssessmentPeriod
    {
        if (!Schema::hasTable('assessment_periods')) {
            abort(403, 'Periode penilaian belum tersedia.');
        }

        // Prefer current date-based period, fallback to latest LOCKED.
        $active = AssessmentPeriodGuard::resolveActive();
        if ($active) {
            // Block if already approval/closed (defensive)
            if (in_array((string) $active->status, [AssessmentPeriod::STATUS_APPROVAL, AssessmentPeriod::STATUS_CLOSED], true)) {
                abort(403, 'Undangan review tidak dapat dibuat ketika periode sudah masuk tahap approval/closed.');
            }
            return $active;
        }

        $locked = AssessmentPeriod::query()
            ->where('status', AssessmentPeriod::STATUS_LOCKED)
            ->orderByDesc('start_date')
            ->first();

        if ($locked) {
            return $locked;
        }

        abort(403, 'Undangan review hanya dapat dibuat ketika ada periode yang sedang berjalan atau berstatus LOCKED.');
    }

    public function exportCsv(Request $request)
    {
        $rows = session('review_invitation_import_success', []);
        if (empty($rows) || !is_array($rows)) {
            abort(404);
        }

        $filename = 'review_invitation_links_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['registration_ref', 'patient_name', 'contact', 'link_undangan']);
            foreach ($rows as $r) {
                fputcsv($out, [
                    (string) ($r['registration_ref'] ?? ''),
                    (string) ($r['patient_name'] ?? ''),
                    (string) ($r['contact'] ?? ''),
                    (string) ($r['link_undangan'] ?? ''),
                ]);
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
