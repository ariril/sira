<?php

namespace App\Services\Reviews\Imports;

use App\Services\Imports\EmployeeNumberNormalizer;

class StaffNumbersParser
{
    public function __construct(
        private readonly EmployeeNumberNormalizer $employeeNumberNormalizer,
    ) {
    }

    public function parse(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }

        $parts = preg_split('/\s*;\s*/', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $out = [];
        foreach ($parts as $p) {
            $p = $this->employeeNumberNormalizer->normalize((string) $p);
            if ($p !== '') {
                $out[] = $p;
            }
        }

        return array_values(array_unique($out));
    }
}
