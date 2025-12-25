<?php

namespace App\Services;

use App\Models\AssessmentPeriod;
use App\Models\CriteriaMetric;
use App\Models\MetricImportBatch;
use App\Models\PerformanceCriteria;
use App\Models\ReviewInvitation;
use App\Models\ReviewInvitationStaff;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class MetricPatientImportService
{
    public function import(
        UploadedFile $file,
        PerformanceCriteria $criteria,
        AssessmentPeriod $period,
        ?int $importedBy,
        bool $replaceExisting = false,
        int $expiresDays = 7,
    ): array {
        if (($period->status ?? null) !== AssessmentPeriod::STATUS_LOCKED) {
            $status = strtoupper((string) ($period->status ?? '-'));
            throw new \RuntimeException("Import metrics hanya dapat dilakukan ketika periode LOCKED. Status periode saat ini: {$status}.");
        }

        if (($criteria->input_method ?? null) !== 'import') {
            throw new \RuntimeException('Import hanya boleh untuk kriteria dengan input_method=import.');
        }

        // This importer is designed specifically for "Jumlah Pasien Ditangani".
        if (($criteria->name ?? null) !== 'Jumlah Pasien Ditangani') {
            throw new \RuntimeException('Format file ini hanya untuk kriteria "Jumlah Pasien Ditangani".');
        }

        $batch = MetricImportBatch::create([
            'file_name' => $file->getClientOriginalName(),
            'assessment_period_id' => $period->id,
            'imported_by' => $importedBy,
            'status' => 'pending',
        ]);

        try {
            return DB::transaction(function () use ($file, $criteria, $period, $batch, $replaceExisting, $expiresDays) {
                if (!$replaceExisting) {
                    $exists = CriteriaMetric::query()
                        ->where('assessment_period_id', $period->id)
                        ->where('performance_criteria_id', $criteria->id)
                        ->exists();
                    if ($exists) {
                        throw new \RuntimeException('Data untuk periode dan kriteria ini sudah ada. Centang "Timpa" untuk mengganti.');
                    }
                }

                if ($replaceExisting) {
                    CriteriaMetric::query()
                        ->where('assessment_period_id', $period->id)
                        ->where('performance_criteria_id', $criteria->id)
                        ->delete();
                }

                [$header, $rows] = $this->readTabularFile($file->getRealPath(), $file->getClientOriginalExtension());
                if (!$header) {
                    throw new \RuntimeException('File kosong atau header tidak ditemukan.');
                }

                $map = $this->mapHeader($header);
                foreach (['no_rm', 'patient_name', 'patient_phone', 'clinic', 'employee_numbers'] as $k) {
                    if (($map[$k] ?? false) === false) {
                        throw new \RuntimeException('Header tidak sesuai. Wajib ada kolom: no_rm, patient_name, patient_phone, clinic, employee_numbers.');
                    }
                }

                $now = now();
                $expiresAt = $expiresDays > 0 ? $now->copy()->addDays($expiresDays) : null;

                $createdInvitations = 0;
                $skippedRows = 0;
                $missingStaffRefs = 0;
                $countsByUserId = [];

                foreach ($rows as $idx => $row) {
                    $noRm = $this->cellToString($row[$map['no_rm']] ?? null);
                    $patientName = $this->cellToString($row[$map['patient_name']] ?? null);
                    $phone = $this->cellToString($row[$map['patient_phone']] ?? null);
                    $employeeNumbersRaw = $this->cellToString($row[$map['employee_numbers']] ?? null);

                    if ($patientName === '' && $noRm === '' && $employeeNumbersRaw === '') {
                        $skippedRows++;
                        continue;
                    }

                    $employeeNumbers = $this->parseEmployeeNumbers($employeeNumbersRaw);
                    if (!$employeeNumbers) {
                        $skippedRows++;
                        continue;
                    }

                    $users = User::query()
                        ->whereIn('employee_number', $employeeNumbers)
                        ->get(['id', 'employee_number']);

                    if ($users->isEmpty()) {
                        $missingStaffRefs++;
                        continue;
                    }

                    $token = $this->generateUniqueToken();

                    $invitation = ReviewInvitation::create([
                        'patient_name' => $patientName !== '' ? $patientName : 'Pasien',
                        'phone' => $phone !== '' ? $phone : null,
                        'no_rm' => $noRm !== '' ? $noRm : null,
                        'token' => $token,
                        'expires_at' => $expiresAt,
                        'status' => 'pending',
                    ]);

                    $pivotRows = [];
                    foreach ($users as $u) {
                        $pivotRows[] = [
                            'invitation_id' => $invitation->id,
                            'user_id' => $u->id,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                        $countsByUserId[$u->id] = ($countsByUserId[$u->id] ?? 0) + 1;
                    }
                    ReviewInvitationStaff::insert($pivotRows);

                    $createdInvitations++;
                }

                // Upsert aggregated metric values
                $valueRows = [];
                foreach ($countsByUserId as $userId => $count) {
                    $valueRows[] = [
                        'import_batch_id' => $batch->id,
                        'user_id' => (int) $userId,
                        'assessment_period_id' => $period->id,
                        'performance_criteria_id' => $criteria->id,
                        'value_numeric' => (float) $count,
                        'value_datetime' => null,
                        'value_text' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if ($valueRows) {
                    DB::table('imported_criteria_values')->upsert(
                        $valueRows,
                        ['user_id', 'assessment_period_id', 'performance_criteria_id'],
                        ['import_batch_id', 'value_numeric', 'value_datetime', 'value_text', 'updated_at']
                    );
                }

                $batch->update(['status' => 'processed']);

                return [
                    'batch_id' => $batch->id,
                    'created_invitations' => $createdInvitations,
                    'skipped_rows' => $skippedRows,
                    'missing_staff_refs' => $missingStaffRefs,
                    'affected_staff' => count($countsByUserId),
                ];
            });
        } catch (\Throwable $e) {
            $batch->update(['status' => 'failed']);
            throw $e;
        }
    }

    private function readTabularFile(string $path, string $ext): array
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

        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($path);
        $sheet = $spreadsheet->getSheet(0);
        $rows = $sheet->toArray(null, true, true, false);
        $header = array_shift($rows);
        return [$header, $rows];
    }

    private function mapHeader(array $header): array
    {
        $norm = array_map(function ($h) {
            $h = strtolower((string) $h);
            $h = trim(preg_replace('/\s+/', ' ', $h));
            $h = str_replace(['-', ' '], ['_', '_'], $h);
            return $h;
        }, $header);

        $find = function (array $aliases) use ($norm) {
            foreach ($aliases as $a) {
                $i = array_search($a, $norm, true);
                if ($i !== false) {
                    return $i;
                }
            }
            return false;
        };

        return [
            'no_rm' => $find(['no_rm', 'nomr', 'no_rekam_medis', 'no_rm_pasien']),
            'patient_name' => $find(['patient_name', 'nama_pasien', 'nama']),
            'patient_phone' => $find(['patient_phone', 'patient_contact', 'phone', 'contact', 'no_hp', 'telp']),
            'clinic' => $find(['clinic', 'poliklinik', 'unit', 'ruangan']),
            'employee_numbers' => $find(['employee_numbers', 'nip', 'nips', 'employee_number', 'pegawai', 'employee_number_list']),
        ];
    }

    private function cellToString(mixed $val): string
    {
        if ($val === null) {
            return '';
        }
        if (is_bool($val)) {
            return $val ? '1' : '0';
        }
        return trim((string) $val);
    }

    private function parseEmployeeNumbers(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }

        $parts = preg_split('/\s*,\s*/', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $out = [];
        foreach ($parts as $p) {
            $p = $this->normalizeEmployeeNumber((string) $p);
            if ($p !== '') {
                $out[] = $p;
            }
        }

        return array_values(array_unique($out));
    }

    private function normalizeEmployeeNumber(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }

        if (ctype_digit($raw)) {
            return $raw;
        }

        // Scientific notation (Excel)
        if (preg_match('/\d(?:\.\d+)?e[+-]?\d+/i', $raw)) {
            $expanded = $this->expandScientificToPlainInteger($raw);
            if ($expanded !== null && $expanded !== '') {
                return $expanded;
            }
        }

        return $raw;
    }

    private function expandScientificToPlainInteger(string $val): ?string
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

    private function generateUniqueToken(): string
    {
        for ($i = 0; $i < 5; $i++) {
            $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
            $exists = ReviewInvitation::query()->where('token', $token)->exists();
            if (!$exists) {
                return $token;
            }
        }

        throw new \RuntimeException('Gagal menghasilkan token unik.');
    }
}
