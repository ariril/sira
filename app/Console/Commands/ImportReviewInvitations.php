<?php

namespace App\Console\Commands;

use App\Models\ReviewInvitation;
use App\Models\ReviewInvitationStaff;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportReviewInvitations extends Command
{
    protected $signature = 'reviews:import-invitations {file : Path to .xlsx file} {--expires-days=7 : Invitation expiry in days}';

    protected $description = 'Import patient review invitations from Excel (.xlsx) and generate invitation links.';

    public function handle(): int
    {
        $file = (string) $this->argument('file');
        $expiresDays = (int) $this->option('expires-days');
        $expiresDays = $expiresDays > 0 ? $expiresDays : 7;

        if (!is_file($file)) {
            $this->error("File not found: {$file}");
            return 1;
        }

        $sheet = IOFactory::load($file)->getActiveSheet();
        $highestRow = (int) $sheet->getHighestDataRow();

        $this->info('Expected columns:');
        $this->line('A=no_rm, B=patient_name, C=patient_phone, D=clinic, E=employee_numbers (comma separated NIP)');

        $created = 0;
        $skipped = 0;

        for ($row = 2; $row <= $highestRow; $row++) {
            $noRm = trim((string) $sheet->getCell("A{$row}")->getValue());
            $patientName = trim((string) $sheet->getCell("B{$row}")->getValue());
            $phone = trim((string) $sheet->getCell("C{$row}")->getValue());
            $employeeNumbersRaw = (string) $sheet->getCell("E{$row}")->getValue();

            if ($patientName === '' && $noRm === '' && trim($employeeNumbersRaw) === '') {
                continue;
            }

            $employeeNumbers = $this->parseEmployeeNumbers($employeeNumbersRaw);
            if (empty($employeeNumbers)) {
                $this->warn("Row {$row}: skipped (no employee_numbers)");
                $skipped++;
                continue;
            }

            $staff = User::query()->whereIn('employee_number', $employeeNumbers)->get(['id', 'employee_number']);
            if ($staff->isEmpty()) {
                $this->warn("Row {$row}: skipped (no staff found for given employee_numbers)");
                $skipped++;
                continue;
            }

            [$rawToken, $invUrl] = DB::transaction(function () use ($noRm, $patientName, $phone, $staff, $expiresDays) {
                $now = Carbon::now();

                $rawToken = $this->generateUniqueToken();

                $invitation = ReviewInvitation::create([
                    'patient_name' => $patientName !== '' ? $patientName : 'Pasien',
                    'phone' => $phone !== '' ? $phone : null,
                    'no_rm' => $noRm !== '' ? $noRm : null,
                    'token' => $rawToken,
                    'expires_at' => $now->copy()->addDays($expiresDays),
                    'status' => 'pending',
                ]);

                $mapRows = $staff->map(fn (User $u) => [
                    'invitation_id' => $invitation->id,
                    'user_id' => $u->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all();

                ReviewInvitationStaff::insert($mapRows);

                $url = url('/reviews/invite/' . $rawToken);
                return [$rawToken, $url];
            });

            $this->line("Row {$row}: created invitation => {$invUrl}");
            $created++;
        }

        $this->info("Done. created={$created}, skipped={$skipped}");
        return 0;
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
