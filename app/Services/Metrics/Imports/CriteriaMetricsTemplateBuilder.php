<?php

namespace App\Services\Metrics\Imports;

use App\Models\AssessmentPeriod;
use App\Models\PerformanceCriteria;
use App\Models\User;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
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

        $type = trim((string) ($criteria->aggregation_method ?? ''));
        if ($type === '') {
            $type = 'sum';
        }

        // Header row (must be first row for TabularFileReader)
        // Bahasa Indonesia
        $headers = ['NIP', 'Nama', 'Unit', 'Kriteria', 'Tipe', 'Nilai'];

        foreach ($headers as $idx => $head) {
            $col = Coordinate::stringFromColumnIndex($idx + 1);
            $ws->setCellValue($col . '1', $head);
        }

        // Fill rows with all pegawai medis
        $users = User::query()
            ->role('pegawai_medis')
            ->with('unit:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'employee_number', 'unit_id']);

        $row = 2;
        foreach ($users as $u) {
            $ws->setCellValueExplicit('A' . $row, (string) ($u->employee_number ?? ''), DataType::TYPE_STRING);
            $ws->setCellValue('B' . $row, (string) ($u->name ?? ''));
            $ws->setCellValue('C' . $row, (string) (($u->unit->name ?? '') ?: ''));
            $ws->setCellValue('D' . $row, (string) ($criteria->name ?? ''));
            $ws->setCellValue('E' . $row, $type);
            // F (nilai) left blank for input
            $row++;
        }

        // Styling
        $ws->getStyle('A1:F1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => '0F172A']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F1F5F9']],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E2E8F0']],
            ],
        ]);
        $ws->getRowDimension(1)->setRowHeight(20);

        // Keep NIP as text
        $ws->getStyle('A:A')->getNumberFormat()->setFormatCode('@');

        // Highlight Nilai column area for input
        $lastDataRow = max(2, $row - 1);
        $ws->getStyle('F2:F' . $lastDataRow)->applyFromArray([
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'FEF9C3'],
            ],
        ]);

        // Set column widths
        $ws->getColumnDimension('A')->setWidth(22);
        $ws->getColumnDimension('B')->setWidth(32);
        $ws->getColumnDimension('C')->setWidth(24);
        $ws->getColumnDimension('D')->setWidth(30);
        $ws->getColumnDimension('E')->setWidth(10);
        $ws->getColumnDimension('F')->setWidth(14);

        // Freeze header
        $ws->freezePane('A2');

        // NOTE: Do not set AutoFilter to avoid dropdown icons in header.

        $tmpPath = tempnam(sys_get_temp_dir(), 'metric_tpl_');
        $writer = new Xlsx($sheet);
        $writer->save($tmpPath);

        $fileName = 'template-import-metrics-' . $criteria->id . '-' . $period->id . '.xlsx';
        return ['tmpPath' => $tmpPath, 'fileName' => $fileName];
    }
}
