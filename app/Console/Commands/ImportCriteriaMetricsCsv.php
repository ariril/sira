<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\CriteriaMetric;

class ImportCriteriaMetricsCsv extends Command
{
    protected $signature = 'metrics:import-csv {path}';
    protected $description = 'Import criteria_metrics from a CSV file (employee_number,assessment_period_id,performance_criteria_id,value_numeric)';

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
        while (($row = fgetcsv($fh)) !== false) {
            $emp = (string)($row[$map['employee_number']] ?? '');
            $periodId = (int)($row[$map['assessment_period_id']] ?? 0);
            $critId = (int)($row[$map['performance_criteria_id']] ?? 0);
            $value = (float)($row[$map['value_numeric']] ?? 0);
            if ($emp === '' || $periodId <= 0 || $critId <= 0) { $skipped++; continue; }
            $user = User::where('employee_number', $emp)->first();
            if (!$user) { $skipped++; continue; }
            CriteriaMetric::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'assessment_period_id' => $periodId,
                    'performance_criteria_id' => $critId,
                ],
                [
                    'value_numeric' => $value,
                    'source_type' => 'import',
                    'source_table' => 'csv',
                ]
            );
            $count++;
        }
        fclose($fh);
        $this->info("Imported {$count} metrics, skipped {$skipped}");
        return 0;
    }
}
