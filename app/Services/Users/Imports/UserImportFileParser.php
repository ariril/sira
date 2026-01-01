<?php

namespace App\Services\Users\Imports;

class UserImportFileParser
{
    private function normalizeHeaderCell(string $cell): string
    {
        $cell = trim($cell);
        // Remove UTF-8 BOM if present
        $cell = preg_replace('/^\xEF\xBB\xBF/', '', $cell);
        return $cell;
    }

    /**
     * @return array{header: array<int,string>, rows: array<int,array{row_number:int,data:array<string,mixed>}>}|array{error:string}
     */
    public function parseImportFile(string $path, string $ext): array
    {
        $rows = [];
        $header = null;

        if (in_array($ext, ['csv', 'txt'], true)) {
            $handle = fopen($path, 'r');
            if (!$handle) {
                return ['error' => 'Tidak dapat membaca file.'];
            }

            $rowNumber = 0;
            while (($line = fgetcsv($handle, 0, ',')) !== false) {
                $rowNumber++;
                if ($header === null) {
                    $header = array_map(fn ($h) => $this->normalizeHeaderCell((string) $h), $line);
                    continue;
                }

                if (count(array_filter($line, fn ($v) => trim((string) $v) !== '')) === 0) {
                    continue;
                }

                $assoc = [];
                foreach ($header as $i => $key) {
                    $assoc[$key] = isset($line[$i]) ? trim((string) $line[$i]) : null;
                }
                $rows[] = ['row_number' => $rowNumber, 'data' => $assoc];
            }
            fclose($handle);

            if ($header === null) {
                return ['error' => 'File kosong atau header tidak ditemukan.'];
            }

            return ['header' => $header, 'rows' => $rows];
        }

        // Excel
        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
            $sheet = $spreadsheet->getSheet(0);
            $highestRow = $sheet->getHighestDataRow();
            $highestColumn = $sheet->getHighestDataColumn();

            $header = [];
            foreach ($sheet->rangeToArray('A1:' . $highestColumn . '1', null, true, true, false)[0] as $cellVal) {
                $header[] = $this->normalizeHeaderCell((string) $cellVal);
            }
            if (count(array_filter($header, fn ($v) => $v !== '')) === 0) {
                return ['error' => 'Header tidak ditemukan pada baris pertama.'];
            }

            for ($rowIndex = 2; $rowIndex <= $highestRow; $rowIndex++) {
                $rowVals = $sheet->rangeToArray('A' . $rowIndex . ':' . $highestColumn . $rowIndex, null, true, true, false)[0];
                if (count(array_filter($rowVals, fn ($v) => trim((string) $v) !== '')) === 0) {
                    continue;
                }

                $assoc = [];
                foreach ($header as $i => $key) {
                    $val = $rowVals[$i] ?? null;
                    if ($key === 'start_date' && is_numeric($val)) {
                        try {
                            $val = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($val)->format('Y-m-d');
                        } catch (\Throwable $e) {
                            // keep raw value; validator will catch
                        }
                    }
                    $assoc[$key] = is_string($val) ? trim($val) : $val;
                }
                $rows[] = ['row_number' => $rowIndex, 'data' => $assoc];
            }

            return ['header' => $header, 'rows' => $rows];
        } catch (\Throwable $e) {
            return ['error' => 'Gagal membaca Excel: ' . $e->getMessage()];
        }
    }
}
