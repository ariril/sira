<?php

namespace App\Http\Controllers\Web\AdminHospital;

use App\Http\Controllers\Controller;
use App\Models\CriteriaMetric;
use App\Models\PerformanceCriteria;
use App\Models\AssessmentPeriod;
use App\Services\MetricPatientImportService;
use App\Services\PeriodPerformanceAssessmentService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use App\Support\AssessmentPeriodGuard;

class CriteriaMetricsController extends Controller
{
    public function index(Request $request): View
    {
        $periodId = (int) $request->query('period_id');
        $criteriaId = (int) $request->query('criteria_id');
        $q = trim((string) $request->query('q', ''));

        $perPageOptions = [10, 25, 50, 100];
        $perPage = (int) $request->query('per_page', 10);
        if (!in_array($perPage, $perPageOptions, true)) {
            $perPage = 10;
        }

        $activePeriod = AssessmentPeriodGuard::resolveActive();
        $latestLockedPeriod = AssessmentPeriodGuard::resolveLatestLocked();

        $items = CriteriaMetric::query()
            ->when($periodId, fn($w) => $w->where('assessment_period_id', $periodId))
            ->when($criteriaId, fn($w) => $w->where('performance_criteria_id', $criteriaId))
            ->when($q !== '', function ($w) use ($q) {
                $w->where(function ($sub) use ($q) {
                    $sub->whereHas('user', function ($uq) use ($q) {
                        $uq->where('name', 'like', "%{$q}%")
                            ->orWhere('employee_number', 'like', "%{$q}%");
                    })
                    ->orWhereHas('period', fn($pq) => $pq->where('name', 'like', "%{$q}%"))
                    ->orWhereHas('criteria', fn($cq) => $cq->where('name', 'like', "%{$q}%"));
                });
            })
            ->with(['user:id,name,employee_number','criteria:id,name','period:id,name'])
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        $periods = AssessmentPeriod::orderByDesc('start_date')->pluck('name','id');
        $importPeriods = AssessmentPeriod::query()
            ->where('status', AssessmentPeriod::STATUS_LOCKED)
            ->orderByDesc('start_date')
            ->pluck('name', 'id');

        // Manual Metrics hanya untuk kriteria input_method=import (metric_import).
        $criteriaQuery = PerformanceCriteria::query()
            ->where('is_active', true)
            ->where('input_method', 'import');

        if (Schema::hasColumn('performance_criterias', 'source')) {
            $criteriaQuery->where('source', 'metric_import');
        }

        $criterias = $criteriaQuery
            ->orderBy('name')
            ->get(['id', 'name', 'data_type']);

        $criteriaOptions = $criterias->pluck('name', 'id');

        // Template/Import metrics options (metric_import source).
        $patientImportCriteriaOptions = $criterias->pluck('name', 'id');

        return view('admin_rs.metrics.index', compact(
            'items',
            'periods',
            'importPeriods',
            'criterias',
            'criteriaOptions',
            'patientImportCriteriaOptions',
            'periodId',
            'criteriaId',
            'q',
            'perPage',
            'perPageOptions',
            'activePeriod',
            'latestLockedPeriod'
        ));
    }

    public function create(): View
    {
        abort(404);
    }

    public function store(Request $request): RedirectResponse
    {
        abort(404);
    }

    public function uploadCsv(Request $request, PeriodPerformanceAssessmentService $perfSvc): RedirectResponse
    {
        $validated = $request->validate([
            'file' => 'required|file|mimes:csv,txt,xls,xlsx|max:5120',
            'performance_criteria_id' => 'required|exists:performance_criterias,id',
            'period_id' => 'required|exists:assessment_periods,id',
            'replace_existing' => 'nullable|boolean',
        ]);

        $criteria = PerformanceCriteria::findOrFail($validated['performance_criteria_id']);

        if (($criteria->input_method ?? null) !== 'import') {
            return back()->withErrors(['performance_criteria_id' => 'Import hanya boleh untuk kriteria dengan input_method=import.']);
        }

        if (Schema::hasColumn('performance_criterias', 'source') && ($criteria->source ?? null) !== 'metric_import') {
            return back()->withErrors(['performance_criteria_id' => 'Import ini hanya untuk kriteria dengan source=metric_import.']);
        }

        $targetPeriod = AssessmentPeriod::query()->findOrFail((int) $validated['period_id']);
        AssessmentPeriodGuard::requireLocked($targetPeriod, 'Import Metrics');

        try {
            $svc = app(MetricPatientImportService::class);
            $result = $svc->import(
                file: $request->file('file'),
                criteria: $criteria,
                period: $targetPeriod,
                importedBy: $request->user()?->id,
                replaceExisting: (bool) ($validated['replace_existing'] ?? false),
            );
        } catch (\Throwable $e) {
            return back()->withErrors(['file' => 'Import gagal: '.$e->getMessage()]);
        }

        $msg = sprintf(
            'Import selesai: invitations=%d, staff_terdampak=%d, skipped=%d, staff_tidak_ditemukan=%d. (batch_id=%d)',
            (int) ($result['created_invitations'] ?? 0),
            (int) ($result['affected_staff'] ?? 0),
            (int) ($result['skipped_rows'] ?? 0),
            (int) ($result['missing_staff_refs'] ?? 0),
            (int) ($result['batch_id'] ?? 0),
        );

        // Recalculate Penilaian Saya for the target period (e.g., locked period uploads).
        $perfSvc->recalculateForPeriodId((int) $targetPeriod->id);

        return back()->with('status', $msg);
    }

    public function downloadTemplate(Request $request)
    {
        $data = $request->validate([
            'performance_criteria_id' => 'required|exists:performance_criterias,id',
            'period_id' => 'required|exists:assessment_periods,id',
        ]);

        $criteria = PerformanceCriteria::findOrFail($data['performance_criteria_id']);

        if (($criteria->input_method ?? null) !== 'import') {
            return back()->withErrors(['performance_criteria_id' => 'Template hanya tersedia untuk kriteria input_method=import.']);
        }

        if (Schema::hasColumn('performance_criterias', 'source') && ($criteria->source ?? null) !== 'metric_import') {
            return back()->withErrors(['performance_criteria_id' => 'Template ini hanya tersedia untuk kriteria dengan source=metric_import.']);
        }
        $period = AssessmentPeriod::findOrFail((int) $data['period_id']);
        AssessmentPeriodGuard::requireLocked($period, 'Generate Template Metrics');

        $sheet = new Spreadsheet();
        $sheet->getProperties()
            ->setCreator('SIRA')
            ->setTitle('Template Import Metrics - '.$criteria->name);
        $ws = $sheet->getActiveSheet();
        $ws->setTitle('Template Import');

        $headers = ['no_rm', 'patient_name', 'patient_phone', 'clinic', 'employee_numbers'];
        foreach ($headers as $idx => $head) {
            $col = Coordinate::stringFromColumnIndex($idx + 1);
            $ws->setCellValue($col.'1', $head);
        }

        // Example row
        $ws->setCellValueExplicit('A2', 'RM00123', DataType::TYPE_STRING);
        $ws->setCellValue('B2', 'Contoh Pasien');
        $ws->setCellValueExplicit('C2', '081234567890', DataType::TYPE_STRING);
        $ws->setCellValue('D2', 'Poli Umum');
        $ws->setCellValueExplicit('E2', '197909102008032001,197511132008031001', DataType::TYPE_STRING);

        // Keep these columns as text
        $ws->getStyle('A2:A2')->getNumberFormat()->setFormatCode('@');
        $ws->getStyle('C2:C2')->getNumberFormat()->setFormatCode('@');
        $ws->getStyle('E2:E2')->getNumberFormat()->setFormatCode('@');

        $tmp = tempnam(sys_get_temp_dir(), 'metric_tpl_');
        $writer = new Xlsx($sheet);
        $writer->save($tmp);

        $filename = 'template-import-metrics-'.$criteria->id.'-'.$period->id.'.xlsx';
        return response()->download($tmp, $filename)->deleteFileAfterSend(true);
    }
}
