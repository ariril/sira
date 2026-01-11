<?php

namespace App\Http\Controllers\Web\AdminHospital;

use App\Http\Controllers\Controller;
use App\Models\AssessmentPeriod;
use App\Services\Reviews\Imports\ReviewInvitationImport;
use App\Services\Reviews\ReviewInvitationService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use App\Support\AssessmentPeriodGuard;

class ReviewInvitationImportController extends Controller
{
    public function form(Request $request): View
    {
        ['period' => $period, 'periodWarning' => $periodWarning, 'periodOptions' => $periodOptions, 'selectedPeriodId' => $selectedPeriodId] = $this->resolvePeriodContext($request);

        $errorMessage = session()->pull('review_invitation_import_error');
        $results = session()->pull('review_invitation_import_results', []);
        $summary = session()->pull('review_invitation_import_summary');

        return view('admin_rs.review_invitations.import', [
            'results' => $results,
            'summary' => $summary,
            'errorMessage' => $errorMessage,
            'period' => $period,
            'periodWarning' => $periodWarning,
            'periodOptions' => $periodOptions,
            'selectedPeriodId' => $selectedPeriodId,
        ]);
    }

    public function process(Request $request, ReviewInvitationService $service): View|RedirectResponse
    {
        ['period' => $period, 'periodWarning' => $periodWarning, 'periodOptions' => $periodOptions, 'selectedPeriodId' => $selectedPeriodId] = $this->resolvePeriodContext($request, true);

        if (!$period) {
            return view('admin_rs.review_invitations.import', [
                'results' => session('review_invitation_import_results', []),
                'summary' => session('review_invitation_import_summary', null),
                'period' => null,
                'periodWarning' => $periodWarning ?: 'Undangan review belum dapat dibuat saat ini.',
                'periodOptions' => $periodOptions,
                'selectedPeriodId' => $selectedPeriodId,
            ]);
        }

        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt'],
        ]);

        $service->setTargetPeriodId($period?->id ? (int) $period->id : null);

        $import = new ReviewInvitationImport($service);

        try {
            $import->import($request->file('file')->getRealPath());
        } catch (\RuntimeException $e) {
            \Log::warning('review_invitation_import_header_error', [
                'error' => $e->getMessage(),
            ]);

            session(['review_invitation_import_error' => $e->getMessage()]);

            return redirect()
                ->route('admin_rs.review_invitations.import.form', ['period_id' => $selectedPeriodId])
                ->withInput();
        } catch (\Throwable $e) {
            \Log::error('review_invitation_import_failed', [
                'error' => $e->getMessage(),
            ]);

            $msg = str_contains($e->getMessage(), "Unknown column 'email'")
                ? 'Kolom email belum tersedia di database. Jalankan migrate:fresh (dev) agar schema terbaru terpasang.'
                : 'Gagal memproses file import. Silakan cek format file dan coba lagi.';

            session(['review_invitation_import_error' => $msg]);
            return redirect()->route('admin_rs.review_invitations.import.form', ['period_id' => $selectedPeriodId]);
        }

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
            // persist for export
            'review_invitation_import_success' => $successRows,
            // clear any previous error
            'review_invitation_import_error' => null,
        ]);

        // show result only right after upload (flash)
        session()->flash('review_invitation_import_results', $results);
        session()->flash('review_invitation_import_summary', $summary);

        return redirect()->route('admin_rs.review_invitations.import.form', ['period_id' => $selectedPeriodId]);
    }

    /**
     * When there is both an active (date-based) period and a latest LOCKED period, allow user to choose.
     * Default selection prefers LOCKED (so monthly imports can still target the locked month).
     *
     * @return array{period:?AssessmentPeriod,periodWarning:?string,periodOptions:array<int,string>,selectedPeriodId:?int}
     */
    private function resolvePeriodContext(Request $request, bool $isPost = false): array
    {
        if (!Schema::hasTable('assessment_periods')) {
            return [
                'period' => null,
                'periodWarning' => 'Periode penilaian belum tersedia.',
                'periodOptions' => [],
                'selectedPeriodId' => null,
            ];
        }

        $active = AssessmentPeriodGuard::resolveActive();
        if ($active && in_array((string) $active->status, [AssessmentPeriod::STATUS_APPROVAL, AssessmentPeriod::STATUS_CLOSED], true)) {
            $active = null;
        }

        $locked = AssessmentPeriod::query()
            ->where('status', AssessmentPeriod::STATUS_LOCKED)
            ->orderByDesc('start_date')
            ->first();

        $periodOptions = [];
        if ($active) {
            $periodOptions[(int) $active->id] = (string) ($active->name ?? '-') . ' (Aktif)';
        }
        if ($locked) {
            $periodOptions[(int) $locked->id] = (string) ($locked->name ?? '-') . ' (Dikunci)';
        }

        $requestedId = $isPost ? $request->input('period_id') : $request->query('period_id');
        $requestedId = $requestedId !== null ? (int) $requestedId : null;

        $defaultId = $locked?->id ? (int) $locked->id : ($active?->id ? (int) $active->id : null);
        $selectedPeriodId = ($requestedId && isset($periodOptions[$requestedId])) ? $requestedId : $defaultId;

        $selectedPeriod = null;
        if ($selectedPeriodId && $active && (int) $active->id === $selectedPeriodId) {
            $selectedPeriod = $active;
        }
        if ($selectedPeriodId && !$selectedPeriod && $locked && (int) $locked->id === $selectedPeriodId) {
            $selectedPeriod = $locked;
        }

        if (!$selectedPeriod) {
            return [
                'period' => null,
                'periodWarning' => 'Undangan review hanya dapat dibuat ketika ada periode yang sedang berjalan atau berstatus LOCKED.',
                'periodOptions' => $periodOptions,
                'selectedPeriodId' => $selectedPeriodId,
            ];
        }

        return [
            'period' => $selectedPeriod,
            'periodWarning' => null,
            'periodOptions' => $periodOptions,
            'selectedPeriodId' => $selectedPeriodId,
        ];
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
