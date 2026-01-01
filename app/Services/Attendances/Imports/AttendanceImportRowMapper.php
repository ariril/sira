<?php

namespace App\Services\Attendances\Imports;

use App\Services\Imports\EmployeeNumberNormalizer;

class AttendanceImportRowMapper
{
    public function __construct(
        private readonly EmployeeNumberNormalizer $employeeNumberNormalizer,
    ) {
    }

    public function mapHeader(array $header): ?array
    {
        // Normalize header: lowercase and collapse internal spaces
        $normalized = array_map(function ($h) {
            $h = strtolower((string) $h);
            $h = preg_replace('/\s+/', ' ', trim($h));
            return $h;
        }, $header);

        // Helper to find first match among aliases
        $find = function (array $aliases) use ($normalized) {
            foreach ($aliases as $a) {
                $idx = array_search($a, $normalized, true);
                if ($idx !== false) {
                    return $idx;
                }
            }
            return false;
        };

        // NOTE: Support both simple EN header and full ID header.
        // For ID format, prefer NIP for user lookup; PIN is accepted as fallback for older exports.
        $map = [
            // Identity
            'pin' => $find(['pin']),
            'employee_number' => $find(['employee_number', 'nip', 'nip/pin', 'pin']),
            'employee_name' => $find(['nama', 'name', 'pegawai']),

            // Period & date
            'period_start' => $find(['periode mulai']),
            'period_end' => $find(['periode selesai']),
            'attendance_date' => $find(['attendance_date', 'tanggal']),

            // Times: prefer Scan Masuk/Keluar for actual attendance
            'scheduled_in' => $find(['jam masuk']),
            'check_in' => $find(['check_in', 'scan masuk', 'scan masuk (hh:mm)']),
            'late' => $find(['datang terlambat', 'terlambat']),

            'scheduled_out' => $find(['jam keluar']),
            'check_out' => $find(['check_out', 'scan keluar', 'scan keluar (hh:mm)']),
            'early_leave' => $find(['pulang awal']),

            // Other fields
            'status' => $find(['status']),
            'shift_name' => $find(['nama shift', 'shift', 'nama shift (text)']),
            'work_duration' => $find(['durasi kerja']),
            'break_duration' => $find(['istirahat durasi']),
            'extra_break' => $find(['istirahat lebih']),
            'overtime_end' => $find(['lembur akhir']),
            'holiday_umum' => $find(['libur umum', 'libur - umum']),
            'holiday_rutin' => $find(['libur rutin', 'libur - rutin']),
            'overtime_shift' => $find(['shift lembur']),
            'note' => $find(['keterangan']),

            // Fields present in some exports but not used for import
            'position' => $find(['jabatan']),
            'room' => $find(['ruangan']),
            'overtime_note' => $find(['lembur', 'keterangan lembur']),
        ];

        if ($map['employee_number'] === false || $map['attendance_date'] === false) {
            return null;
        }

        return $map;
    }

    public function rowToAssoc(?array $map, array $row): ?array
    {
        if (!$map) {
            return null;
        }

        $get = fn ($key) => isset($map[$key]) && $map[$key] !== false ? ($row[$map[$key]] ?? null) : null;

        $rawEmployeeNumber = $get('employee_number');
        $normalizedEmployeeNumber = $this->normalizeEmployeeNumber($rawEmployeeNumber);

        return [
            'pin' => $this->cellToString($get('pin')),
            'employee_number_raw' => $this->cellToString($rawEmployeeNumber),
            'employee_number' => $normalizedEmployeeNumber,
            'employee_name' => $this->cellToString($get('employee_name')),

            'attendance_date' => $this->cellToString($get('attendance_date')),

            'check_in' => $this->cellToString($get('check_in')),
            'check_out' => $this->cellToString($get('check_out')),
            'status' => $this->cellToString($get('status')),
            'late' => $this->cellToString($get('late')),
            'note' => $this->cellToString($get('note')),
            'holiday_umum' => $this->cellToString($get('holiday_umum')),
            'holiday_rutin' => $this->cellToString($get('holiday_rutin')),

            // extras
            'shift_name' => $this->cellToString($get('shift_name')),
            'scheduled_in' => $this->cellToString($get('scheduled_in')),
            'scheduled_out' => $this->cellToString($get('scheduled_out')),
            'early_leave' => $this->cellToString($get('early_leave')),
            'work_duration' => $this->cellToString($get('work_duration')),
            'break_duration' => $this->cellToString($get('break_duration')),
            'extra_break' => $this->cellToString($get('extra_break')),
            'overtime_end' => $this->cellToString($get('overtime_end')),
            'overtime_shift' => $this->cellToString($get('overtime_shift')),
        ];
    }

    public function cellToString(mixed $val): string
    {
        if ($val === null) {
            return '';
        }
        if (is_bool($val)) {
            return $val ? '1' : '0';
        }
        return trim((string) $val);
    }

    public function looksLikeScientificNotation(string $val): bool
    {
        return $this->employeeNumberNormalizer->looksLikeScientificNotation($val);
    }

    public function looksLikeLongNumericIdentifier(string $val): bool
    {
        $val = trim($val);
        if ($val === '') {
            return false;
        }
        // Heuristic: long digits often come from Excel Number formatting.
        return ctype_digit($val) && strlen($val) >= 15;
    }

    public function normalizeEmployeeNumber(mixed $val): string
    {
        $raw = $this->cellToString($val);
        if ($raw === '') {
            return '';
        }

        return $this->employeeNumberNormalizer->normalize($raw);
    }

    /**
     * Expand strings like "1.97909012020803E+16" into "19790901202080300".
     * Returns null if format is not supported.
     */
    public function expandScientificToPlainInteger(string $val): ?string
    {
        return $this->employeeNumberNormalizer->expandScientificToPlainInteger($val);
    }
}
