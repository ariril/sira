<?php

namespace App\Http\Controllers\Web\AdminHospital;

use App\Http\Controllers\Controller;
use App\Models\CriteriaMetric;
use App\Models\PerformanceCriteria;
use App\Models\AssessmentPeriod;
use App\Services\Reviews\Imports\MetricPatientImportService;
use App\Services\AssessmentPeriods\PeriodPerformanceAssessmentService;
use App\Services\Metrics\Imports\CriteriaMetricsTemplateBuilder;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use App\Support\AssessmentPeriodGuard;

class CriteriaMetricsController extends Controller
{
    public function __construct(
        private readonly MetricPatientImportService $metricPatientImportService,
        private readonly CriteriaMetricsTemplateBuilder $criteriaMetricsTemplateBuilder,
    ) {
    }

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
            $result = $this->metricPatientImportService->import(
                file: $request->file('file'),
                criteria: $criteria,
                period: $targetPeriod,
                importedBy: $request->user()?->id,
                replaceExisting: (bool) ($validated['replace_existing'] ?? false),
            );
        } catch (\Throwable $e) {
            return back()->withErrors(['file' => 'Import gagal: '.$e->getMessage()]);
        }

        if ((int) ($result['imported_rows'] ?? 0) === 0) {
            $samples = $result['samples'] ?? [];

            $fmtRows = function ($rows): string {
                if (!is_array($rows) || !$rows) return '-';
                $nums = [];
                foreach ($rows as $r) {
                    if (is_array($r)) {
                        $nums[] = (string) ($r['row'] ?? '');
                    } else {
                        $nums[] = (string) $r;
                    }
                }
                $nums = array_values(array_filter($nums, fn($v) => $v !== ''));
                return $nums ? implode(', ', $nums) : '-';
            };

            $msg = "Tidak ada data yang tersimpan dari file ini. "
                . "Penyebab paling sering: kolom 'Nilai' masih kosong atau NIP tidak sesuai." . "\n"
                . sprintf(
                    "Ringkasan: total=%d, tersimpan=%d, skipped=%d (kosong_nilai=%d, kosong_nip=%d, baris_kosong=%d), nilai_tidak_valid=%d, staff_tidak_ditemukan=%d.",
                    (int) ($result['total_rows'] ?? 0),
                    (int) ($result['imported_rows'] ?? 0),
                    (int) ($result['skipped_rows'] ?? 0),
                    (int) ($result['skipped_empty_value_rows'] ?? 0),
                    (int) ($result['skipped_empty_employee_number_rows'] ?? 0),
                    (int) ($result['skipped_blank_rows'] ?? 0),
                    (int) ($result['invalid_value_rows'] ?? 0),
                    (int) ($result['missing_staff_refs'] ?? 0),
                )
                . "\n"
                . "Contoh baris (nomor baris Excel): "
                . "nilai_kosong=" . $fmtRows($samples['empty_value_rows'] ?? []) . ", "
                . "nip_kosong=" . $fmtRows($samples['empty_employee_number_rows'] ?? []) . ", "
                . "nilai_tidak_valid=" . $fmtRows($samples['invalid_value_rows'] ?? []) . ", "
                . "staff_tidak_ditemukan=" . $fmtRows($samples['missing_staff_rows'] ?? []) . ".";

            return back()->withErrors(['file' => $msg]);
        }

        $msg = sprintf(
            'Import selesai: baris_tersimpan=%d, staff_terdampak=%d, skipped=%d, nilai_tidak_valid=%d, staff_tidak_ditemukan=%d. (batch_id=%d)',
            (int) ($result['imported_rows'] ?? 0),
            (int) ($result['affected_staff'] ?? 0),
            (int) ($result['skipped_rows'] ?? 0),
            (int) ($result['invalid_value_rows'] ?? 0),
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

        $built = $this->criteriaMetricsTemplateBuilder->build($criteria, $period);
        return response()->download($built['tmpPath'], $built['fileName'])->deleteFileAfterSend(true);
    }
}
