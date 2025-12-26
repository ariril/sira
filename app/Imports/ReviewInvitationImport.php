<?php

namespace App\Imports;

use App\Services\ReviewInvitationService;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ReviewInvitationImport
{
    /** @var array<int, array> */
    public array $results = [];

    public function __construct(private readonly ReviewInvitationService $service) {}

    /**
     * Parse an Excel/CSV file and fill $results.
     */
    public function import(string $filePath): void
    {
        $sheet = IOFactory::load($filePath)->getActiveSheet();

        $highestRow = (int) $sheet->getHighestDataRow();
        $highestCol = (string) $sheet->getHighestDataColumn();

        $headings = [];
        for ($col = 'A'; $col <= $highestCol; $col++) {
            $headings[$col] = Str::lower(trim((string) $sheet->getCell("{$col}1")->getValue()));
        }

        $required = ['registration_ref', 'patient_name', 'phone', 'unit', 'staff_numbers'];
        $map = [];
        foreach ($required as $key) {
            $col = array_search($key, $headings, true);
            if ($col === false) {
                throw new \RuntimeException('Header tidak sesuai. Wajib ada kolom: registration_ref, patient_name, phone, unit, staff_numbers.');
            }
            $map[$key] = $col;
        }

        for ($row = 2; $row <= $highestRow; $row++) {
            $rowArr = [];
            foreach ($map as $key => $col) {
                $rowArr[$key] = $sheet->getCell("{$col}{$row}")->getValue();
            }

            $isEmpty = collect($rowArr)
                ->map(fn ($v) => trim((string) ($v ?? '')))
                ->filter(fn ($v) => $v !== '')
                ->isEmpty();

            if ($isEmpty) {
                continue;
            }

            $this->results[] = $this->service->importRow($rowArr, $row);
        }
    }
}
