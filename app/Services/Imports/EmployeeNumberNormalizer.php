<?php

namespace App\Services\Imports;

class EmployeeNumberNormalizer
{
    public function looksLikeScientificNotation(string $val): bool
    {
        $val = trim($val);
        if ($val === '') {
            return false;
        }
        return (bool) preg_match('/\d(?:\.\d+)?e[+-]?\d+/i', $val);
    }

    public function normalize(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }

        // Keep as-is when it's already a digit string (preserves leading zero)
        if (ctype_digit($raw)) {
            return $raw;
        }

        // Convert scientific notation to plain integer string (best effort)
        if ($this->looksLikeScientificNotation($raw)) {
            $expanded = $this->expandScientificToPlainInteger($raw);
            if ($expanded !== null && $expanded !== '') {
                return $expanded;
            }
        }

        return $raw;
    }

    /**
     * Expand strings like "1.97909012020803E+16" into "19790901202080300".
     * Returns null if format is not supported.
     */
    public function expandScientificToPlainInteger(string $val): ?string
    {
        $val = trim($val);
        if (!preg_match('/^([+-])?(\d+)(?:\.(\d+))?[eE]([+-]?\d+)$/', $val, $m)) {
            return null;
        }

        $sign = $m[1] ?? '';
        $intPart = $m[2] ?? '';
        $fracPart = $m[3] ?? '';
        $exp = (int) ($m[4] ?? 0);

        if ($exp < 0) {
            return null;
        }

        $digits = $intPart . $fracPart;
        $fracLen = strlen($fracPart);
        $zerosToAdd = $exp - $fracLen;

        if ($zerosToAdd < 0) {
            return null;
        }

        $digits .= str_repeat('0', $zerosToAdd);
        $digits = ltrim($digits, '+');
        if ($sign === '-') {
            $digits = '-' . $digits;
        }

        return $digits;
    }
}
