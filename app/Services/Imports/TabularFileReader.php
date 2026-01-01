<?php

namespace App\Services\Imports;

use PhpOffice\PhpSpreadsheet\IOFactory;

class TabularFileReader
{
    /**
     * Read a tabular file (csv/txt/xls/xlsx) and return [header, rows].
     *
     * @return array{0:array<int,mixed>|null,1:array<int,array<int,mixed>>}
     */
    public function read(string $path, string $ext): array
    {
        $ext = strtolower($ext);

        if (in_array($ext, ['csv', 'txt'], true)) {
            $handle = fopen($path, 'r');
            if ($handle === false) {
                throw new \RuntimeException('Gagal membuka file.');
            }

            $header = fgetcsv($handle);
            $rows = [];
            while (($row = fgetcsv($handle)) !== false) {
                $rows[] = $row;
            }
            fclose($handle);

            return [$header, $rows];
        }

        try {
            $reader = IOFactory::createReaderForFile($path);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($path);
            $sheet = $spreadsheet->getSheet(0);
            $rows = $sheet->toArray(null, true, true, false);
            $header = array_shift($rows);

            return [$header, $rows];
        } catch (\Throwable $e) {
            throw new \RuntimeException('Gagal membaca file Excel: ' . $e->getMessage());
        }
    }
}
