<?php

namespace App\Services\Metrics\Imports;

class CsvReader
{
    /**
     * @return array{0:array<int,mixed>|null,1:array<int,array<int,mixed>>}
     */
    public function read(string $path): array
    {
        if (!is_readable($path)) {
            throw new \RuntimeException('File not readable: ' . $path);
        }

        $fh = fopen($path, 'r');
        if ($fh === false) {
            throw new \RuntimeException('Cannot open file');
        }

        $header = fgetcsv($fh);
        if (!$header) {
            fclose($fh);
            throw new \RuntimeException('Empty CSV');
        }

        $rows = [];
        while (($row = fgetcsv($fh)) !== false) {
            $rows[] = $row;
        }

        fclose($fh);

        return [$header, $rows];
    }
}
