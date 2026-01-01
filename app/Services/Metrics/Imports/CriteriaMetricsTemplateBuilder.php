<?php

namespace App\Services\Metrics\Imports;

use App\Models\AssessmentPeriod;
use App\Models\PerformanceCriteria;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class CriteriaMetricsTemplateBuilder
{
    /**
     * @return array{tmpPath:string,fileName:string}
     */
    public function build(PerformanceCriteria $criteria, AssessmentPeriod $period): array
    {
        $sheet = new Spreadsheet();
        $sheet->getProperties()
            ->setCreator('SIRA')
            ->setTitle('Template Import Metrics - ' . $criteria->name);

        $ws = $sheet->getActiveSheet();
        $ws->setTitle('Template Import');

        $headers = ['no_rm', 'patient_name', 'patient_phone', 'clinic', 'employee_numbers'];
        foreach ($headers as $idx => $head) {
            $col = Coordinate::stringFromColumnIndex($idx + 1);
            $ws->setCellValue($col . '1', $head);
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

        $tmpPath = tempnam(sys_get_temp_dir(), 'metric_tpl_');
        $writer = new Xlsx($sheet);
        $writer->save($tmpPath);

        $fileName = 'template-import-metrics-' . $criteria->id . '-' . $period->id . '.xlsx';
        return ['tmpPath' => $tmpPath, 'fileName' => $fileName];
    }
}
