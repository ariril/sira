<?php

namespace App\Services\Attendances\Imports;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class AttendanceImportTemplateBuilder
{
    /**
     * @return array{tmpPath:string,fileName:string,headers:array<int,string>}
     */
    public function build(): array
    {
        $headers = [
            'PIN', 'NIP', 'Nama', 'Jabatan', 'Ruangan', 'Periode Mulai', 'Periode Selesai', 'Tanggal', 'Nama Shift',
            'Jam Masuk', 'Scan Masuk', 'Datang Terlambat', 'Jam Keluar', 'Scan Keluar', 'Pulang Awal',
            'Durasi Kerja', 'Istirahat Durasi', 'Istirahat Lebih', 'Lembur Akhir', 'Libur Umum', 'Libur Rutin',
            'Shift Lembur', 'Keterangan',
        ];

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Header
        foreach ($headers as $col => $label) {
            $cell = Coordinate::stringFromColumnIndex($col + 1) . '1';
            $sheet->setCellValue($cell, $label);
        }

        // Sample row: keep NIP as text
        $sample = [
            '001',
            '197909102008032001',
            'Contoh Nama',
            'Dokter',
            'Poli Umum',
            '01-12-2025',
            '31-12-2025',
            'Monday 01-12-2025',
            'Pagi',
            '08:00',
            '08:05',
            '00:05',
            '16:00',
            '16:03',
            '00:00',
            '08:00',
            '01:00',
            '00:00',
            '',
            '0',
            '0',
            '0',
            'Hadir',
        ];

        foreach ($sample as $col => $value) {
            $row = 2;
            $colIndex = $col + 1;
            $cell = Coordinate::stringFromColumnIndex($colIndex) . (string) $row;
            if ($headers[$col] === 'NIP') {
                $sheet->setCellValueExplicit($cell, (string) $value, DataType::TYPE_STRING);
                $sheet->getStyle($cell)->getNumberFormat()->setFormatCode('@');
            } else {
                $sheet->setCellValue($cell, $value);
            }
        }

        $fileName = 'template_import_absensi.xlsx';
        $tmpPath = storage_path('app/tmp/' . uniqid('att_tpl_', true) . '.xlsx');
        if (!is_dir(dirname($tmpPath))) {
            @mkdir(dirname($tmpPath), 0777, true);
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($tmpPath);

        return ['tmpPath' => $tmpPath, 'fileName' => $fileName, 'headers' => $headers];
    }

    /**
     * @param array<int,string> $headers
     * @return array{fileName:string,csv:string}
     */
    public function buildCsvFallback(array $headers): array
    {
        $fileName = 'template_import_absensi.csv';
        $lines = [];
        $lines[] = implode(',', $headers);
        $lines[] = '001,197909102008032001,Contoh Nama,Dokter,Poli Umum,01-12-2025,31-12-2025,Monday 01-12-2025,Pagi,08:00,08:05,00:05,16:00,16:03,00:00,08:00,01:00,00:00,,0,0,0,Hadir';
        $csv = implode("\n", $lines) . "\n";

        return ['fileName' => $fileName, 'csv' => $csv];
    }
}
