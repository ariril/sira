<?php

namespace App\Services\Reviews\Imports;

use App\Models\AssessmentPeriod;
use App\Models\CriteriaMetric;
use App\Models\MetricImportBatch;
use App\Models\PerformanceCriteria;
use App\Models\User;
use App\Services\Imports\EmployeeNumberNormalizer;
use App\Services\Imports\TabularFileReader;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use App\Support\AssessmentPeriodAudit;

class MetricPatientImportService
{
    public function __construct(
        private readonly EmployeeNumberNormalizer $employeeNumberNormalizer,
        private readonly TabularFileReader $tabularFileReader,
    ) {
    }

    /**
     * Import metrics from a staff-based template.
     * Expected header (case-insensitive): employee_number, name, unit, type, value
     */
    public function import(
        UploadedFile $file,
        PerformanceCriteria $criteria,
        AssessmentPeriod $period,
        ?int $importedBy,
        bool $replaceExisting = false,
        int $expiresDays = 7,
    ): array {
        $periodStatus = (string) ($period->status ?? '');
        if (!in_array($periodStatus, [AssessmentPeriod::STATUS_LOCKED, AssessmentPeriod::STATUS_REVISION], true)) {
            $status = strtoupper($periodStatus !== '' ? $periodStatus : '-');
            throw new \RuntimeException("Import metrics hanya dapat dilakukan ketika periode LOCKED atau REVISION. Status periode saat ini: {$status}.");
        }

        if (method_exists($period, 'isRejectedApproval') && $period->isRejectedApproval()) {
            throw new \RuntimeException('Import metrics tidak dapat dilakukan: periode sedang DITOLAK (approval rejected).');
        }

        if (($criteria->input_method ?? null) !== 'import') {
            throw new \RuntimeException('Import hanya boleh untuk kriteria dengan input_method=import.');
        }

        if (($criteria->source ?? null) !== null && ($criteria->source ?? null) !== 'metric_import') {
            throw new \RuntimeException('Import ini hanya untuk kriteria dengan source=metric_import.');
        }

        $supportsSoftHistory = Schema::hasColumn('imported_criteria_values', 'is_active')
            && Schema::hasColumn('metric_import_batches', 'is_superseded');

        $prevActiveBatchId = null;
        if ($supportsSoftHistory) {
            $prevActiveBatchId = (int) (MetricImportBatch::query()
                ->where('assessment_period_id', (int) $period->id)
                ->where('is_superseded', false)
                ->orderByDesc('id')
                ->value('id') ?? 0);
            if ($prevActiveBatchId > 0) {
                // Free unique(active_period_key) for the new batch.
                MetricImportBatch::query()
                    ->where('id', $prevActiveBatchId)
                    ->update(['is_superseded' => true]);

                AssessmentPeriodAudit::log(
                    (int) $period->id,
                    $importedBy,
                    'metric_import_superseded',
                    'Supersede previous batch',
                    ['previous_batch_id' => $prevActiveBatchId]
                );
            }
        }

        $batch = MetricImportBatch::create(array_filter([
            'file_name' => $file->getClientOriginalName(),
            'assessment_period_id' => $period->id,
            'imported_by' => $importedBy,
            'status' => 'pending',
            'previous_batch_id' => ($supportsSoftHistory && $prevActiveBatchId > 0) ? $prevActiveBatchId : null,
        ], fn($v) => $v !== null));

        try {
            return DB::transaction(function () use ($file, $criteria, $period, $batch, $replaceExisting, $importedBy, $prevActiveBatchId, $supportsSoftHistory) {
                $supportsSoftHistory = Schema::hasColumn('imported_criteria_values', 'is_active');

                if (!$replaceExisting) {
                    $qExists = CriteriaMetric::query()
                        ->where('assessment_period_id', $period->id)
                        ->where('performance_criteria_id', $criteria->id);
                    if ($supportsSoftHistory) {
                        $qExists->where('is_active', true);
                    }
                    if ($qExists->exists()) {
                        throw new \RuntimeException('Data untuk periode dan kriteria ini sudah ada. Centang "Timpa" untuk mengganti.');
                    }
                }

                if ($replaceExisting && $supportsSoftHistory) {
                    CriteriaMetric::query()
                        ->where('assessment_period_id', $period->id)
                        ->where('performance_criteria_id', $criteria->id)
                        ->where('is_active', true)
                        ->update([
                            'is_active' => false,
                            'superseded_at' => now(),
                            'superseded_by_batch_id' => (int) $batch->id,
                            'updated_at' => now(),
                        ]);
                }

                if ($replaceExisting && !$supportsSoftHistory) {
                    CriteriaMetric::query()
                        ->where('assessment_period_id', $period->id)
                        ->where('performance_criteria_id', $criteria->id)
                        ->delete();
                }

                [$header, $rows] = $this->tabularFileReader->read($file->getRealPath(), $file->getClientOriginalExtension());
                if (!$header) {
                    throw new \RuntimeException('File kosong atau header tidak ditemukan.');
                }

                $map = $this->mapHeader($header);
                foreach (['employee_number', 'value'] as $k) {
                    if (($map[$k] ?? false) === false) {
                        throw new \RuntimeException('Header tidak sesuai. Wajib ada kolom: NIP (employee_number) dan Nilai (value).');
                    }
                }

                $now = now();

                $skippedRows = 0;
                $skippedBlankRows = 0;
                $skippedEmptyEmployeeNumberRows = 0;
                $skippedEmptyValueRows = 0;
                $missingStaffRefs = 0;
                $invalidValueRows = 0;

                $sampleLimit = 8;
                $sampleBlankRows = [];
                $sampleEmptyEmployeeNumberRows = [];
                $sampleEmptyValueRows = [];
                $sampleInvalidValueRows = [];
                $sampleMissingStaffRows = [];

                /** @var array<int,array<string,mixed>> $valueRowsByUserId */
                $valueRowsByUserId = [];

                foreach ($rows as $idx => $row) {
                    $rowNo = $idx + 2; // data starts at row 2

                    $employeeNumberRaw = $this->cellToString($row[$map['employee_number']] ?? null);
                    $valueRaw = $this->cellToString($row[$map['value']] ?? null);
                    $nameRaw = ($map['name'] !== false) ? $this->cellToString($row[$map['name']] ?? null) : '';

                    if ($employeeNumberRaw === '' && $valueRaw === '' && $nameRaw === '') {
                        $skippedRows++;
                        $skippedBlankRows++;
                        if (count($sampleBlankRows) < $sampleLimit) {
                            $sampleBlankRows[] = $rowNo;
                        }
                        continue;
                    }

                    $employeeNumber = $this->employeeNumberNormalizer->normalize($employeeNumberRaw);
                    if ($employeeNumber === '') {
                        $skippedRows++;
                        $skippedEmptyEmployeeNumberRows++;
                        if (count($sampleEmptyEmployeeNumberRows) < $sampleLimit) {
                            $sampleEmptyEmployeeNumberRows[] = $rowNo;
                        }
                        continue;
                    }

                    if (trim($valueRaw) === '') {
                        $skippedRows++;
                        $skippedEmptyValueRows++;
                        if (count($sampleEmptyValueRows) < $sampleLimit) {
                            $sampleEmptyValueRows[] = $rowNo;
                        }
                        continue;
                    }

                    $valueNormalized = str_replace(',', '.', trim($valueRaw));
                    if (!is_numeric($valueNormalized)) {
                        $invalidValueRows++;
                        if (count($sampleInvalidValueRows) < $sampleLimit) {
                            $sampleInvalidValueRows[] = ['row' => $rowNo, 'value' => $valueRaw];
                        }
                        continue;
                    }

                    $user = User::query()
                        ->where('employee_number', $employeeNumber)
                        ->first(['id']);

                    if (!$user) {
                        $missingStaffRefs++;
                        if (count($sampleMissingStaffRows) < $sampleLimit) {
                            $sampleMissingStaffRows[] = ['row' => $rowNo, 'employee_number' => $employeeNumber];
                        }
                        continue;
                    }

                    $rowPayload = [
                        'import_batch_id' => $batch->id,
                        'user_id' => (int) $user->id,
                        'assessment_period_id' => $period->id,
                        'performance_criteria_id' => $criteria->id,
                        'value_numeric' => (float) $valueNormalized,
                        'value_datetime' => null,
                        'value_text' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];

                    if ($supportsSoftHistory) {
                        $rowPayload['is_active'] = true;
                    }

                    $valueRowsByUserId[$user->id] = $rowPayload;
                }

                $valueRows = array_values($valueRowsByUserId);
                if ($valueRows) {
                    if (Schema::hasColumn('imported_criteria_values', 'is_active')) {
                        // Insert new active rows; old active rows (if any) were deactivated above.
                        DB::table('imported_criteria_values')->insert($valueRows);
                    } else {
                        DB::table('imported_criteria_values')->upsert(
                            $valueRows,
                            ['user_id', 'assessment_period_id', 'performance_criteria_id'],
                            ['import_batch_id', 'value_numeric', 'value_datetime', 'value_text', 'updated_at']
                        );
                    }
                }

                $batch->update(['status' => 'processed']);

                AssessmentPeriodAudit::log(
                    (int) $period->id,
                    $importedBy,
                    'metric_import_completed',
                    $replaceExisting ? 'Replace existing metrics' : null,
                    [
                        'batch_id' => (int) $batch->id,
                        'criteria_id' => (int) $criteria->id,
                        'criteria_name' => (string) ($criteria->name ?? ''),
                        'file_name' => (string) ($file->getClientOriginalName() ?? ''),
                        'replace_existing' => (bool) $replaceExisting,
                        'soft_history' => (bool) $supportsSoftHistory,
                        'previous_batch_id' => ($supportsSoftHistory && $prevActiveBatchId && $prevActiveBatchId > 0) ? (int) $prevActiveBatchId : null,
                    ]
                );

                return [
                    'batch_id' => $batch->id,
                    'total_rows' => count($rows),
                    'imported_rows' => count($valueRows),
                    'skipped_rows' => $skippedRows,
                    'skipped_blank_rows' => $skippedBlankRows,
                    'skipped_empty_employee_number_rows' => $skippedEmptyEmployeeNumberRows,
                    'skipped_empty_value_rows' => $skippedEmptyValueRows,
                    'invalid_value_rows' => $invalidValueRows,
                    'missing_staff_refs' => $missingStaffRefs,
                    'affected_staff' => count($valueRowsByUserId),

                    'samples' => [
                        'blank_rows' => $sampleBlankRows,
                        'empty_employee_number_rows' => $sampleEmptyEmployeeNumberRows,
                        'empty_value_rows' => $sampleEmptyValueRows,
                        'invalid_value_rows' => $sampleInvalidValueRows,
                        'missing_staff_rows' => $sampleMissingStaffRows,
                    ],
                ];
            });
        } catch (\Throwable $e) {
            $batch->update(['status' => 'failed']);

            AssessmentPeriodAudit::log(
                (int) $period->id,
                $importedBy,
                'metric_import_failed',
                $e->getMessage(),
                [
                    'batch_id' => (int) $batch->id,
                    'criteria_id' => (int) $criteria->id,
                    'criteria_name' => (string) ($criteria->name ?? ''),
                    'file_name' => (string) ($file->getClientOriginalName() ?? ''),
                ]
            );

            throw $e;
        }
    }

    /**
     * @return array<string,int|false>
     */
    private function mapHeader(array $header): array
    {
        $normalized = [];
        foreach ($header as $i => $h) {
            $key = Str::of((string) $h)->trim()->lower()->toString();
            $key = preg_replace('/^\xEF\xBB\xBF/', '', $key) ?? $key;
            $normalized[$i] = $key;
        }

        $map = [];
        foreach ($normalized as $i => $key) {
            if ($key === '') {
                continue;
            }
            $map[$key] = $i;
        }

        $pick = function (array $candidates) use ($map): int|false {
            foreach ($candidates as $c) {
                if (array_key_exists($c, $map)) {
                    return (int) $map[$c];
                }
            }
            return false;
        };

        return [
            // Support Indonesian/English headers
            'employee_number' => $pick(['nip', 'employee_number', 'employee no', 'employee_no', 'no pegawai', 'nomor pegawai']),
            'name' => $pick(['nama', 'name']),
            'unit' => $pick(['unit']),
            'criteria' => $pick(['kriteria', 'criteria']),
            'type' => $pick(['tipe', 'type']),
            'value' => $pick(['nilai', 'value']),
        ];
    }

    private function cellToString(mixed $cell): string
    {
        if ($cell === null) {
            return '';
        }

        if (is_bool($cell)) {
            return $cell ? '1' : '0';
        }

        if (is_int($cell) || is_float($cell)) {
            // Keep as is; avoid scientific notation where possible
            $s = (string) $cell;
            return $s;
        }

        return trim((string) $cell);
    }
}
