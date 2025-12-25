<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\CriteriaMetric;
use App\Models\MetricImportBatch;
use App\Models\AssessmentPeriod;
use App\Models\PerformanceCriteria;

class ImportCriteriaMetricsCsv extends Command
{
    protected $signature = 'metrics:import-csv {path}';
    protected $description = 'Import imported_criteria_values from a CSV file (employee_number,assessment_period_id,performance_criteria_id,value_numeric)';

    public function handle()
    {
        $path = $this->argument('path');
        if (!is_readable($path)) { $this->error('File not readable: '.$path); return 1; }
        $fh = fopen($path, 'r');
        if (!$fh) { $this->error('Cannot open file'); return 1; }
        $header = fgetcsv($fh);
        if (!$header) { $this->error('Empty CSV'); return 1; }
        $map = array_flip($header);
        $required = ['employee_number','assessment_period_id','performance_criteria_id','value_numeric'];
        foreach ($required as $k) if (!isset($map[$k])) { $this->error("Missing column $k"); return 1; }

        $count = 0; $skipped = 0;
        $batchesByPeriodId = [];
        while (($row = fgetcsv($fh)) !== false) {
            $emp = (string)($row[$map['employee_number']] ?? '');
            $periodId = (int)($row[$map['assessment_period_id']] ?? 0);
            $critId = (int)($row[$map['performance_criteria_id']] ?? 0);
            $value = (float)($row[$map['value_numeric']] ?? 0);
            if ($emp === '' || $periodId <= 0 || $critId <= 0) { $skipped++; continue; }
            $user = User::where('employee_number', $emp)->first();
            if (!$user) { $skipped++; continue; }

            $period = AssessmentPeriod::query()->find($periodId);
            if (!$period) { $skipped++; continue; }

            $criteria = PerformanceCriteria::query()->find($critId);
            if (!$criteria || ($criteria->input_method ?? null) !== 'import') { $skipped++; continue; }

            if (!isset($batchesByPeriodId[$periodId])) {
                $batchesByPeriodId[$periodId] = MetricImportBatch::create([
                    'file_name' => basename((string) $path),
                    'assessment_period_id' => $periodId,
                    'imported_by' => null,
                    'status' => 'processed',
                ]);
            }
            /** @var MetricImportBatch $batch */
            $batch = $batchesByPeriodId[$periodId];

            CriteriaMetric::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'assessment_period_id' => $periodId,
                    'performance_criteria_id' => $critId,
                ],
                [
                    'import_batch_id' => $batch->id,
                    'value_numeric' => $value,
                    'value_datetime' => null,
                    'value_text' => null,
                ]
            );
            $count++;
        }
        fclose($fh);
        $this->info("Imported {$count} metrics, skipped {$skipped}");
        return 0;
    }
}
