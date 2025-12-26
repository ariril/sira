<?php

namespace App\Http\Controllers\Web\AdminHospital;

use App\Http\Controllers\Controller;
use App\Imports\ReviewInvitationImport;
use App\Services\ReviewInvitationService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\View\View;

class ReviewInvitationImportController extends Controller
{
    public function form(Request $request): View
    {
        return view('admin_rs.review_invitations.import', [
            'results' => session('review_invitation_import_results', []),
            'summary' => session('review_invitation_import_summary', null),
        ]);
    }

    public function process(Request $request, ReviewInvitationService $service): View
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt'],
        ]);

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
        ]);
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
