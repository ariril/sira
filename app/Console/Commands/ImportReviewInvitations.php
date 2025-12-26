<?php

namespace App\Console\Commands;

use App\Models\ReviewInvitation;
use App\Models\ReviewInvitationStaff;
use App\Models\Unit;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportReviewInvitations extends Command
{
    protected $signature = 'reviews:import-invitations {file : Path to .xlsx file} {--expires-days=5 : Invitation expiry in days}';

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
        $this->line('A=registration_ref, B=patient_name, C=phone, D=unit, E=staff_numbers (semicolon separated employee_number)');

        $created = 0;
        $skipped = 0;

        for ($row = 2; $row <= $highestRow; $row++) {
            $registrationRef = trim((string) $sheet->getCell("A{$row}")->getValue());
            $patientName = trim((string) $sheet->getCell("B{$row}")->getValue());
            $phone = trim((string) $sheet->getCell("C{$row}")->getValue());
            $unitName = trim((string) $sheet->getCell("D{$row}")->getValue());
            $staffNumbersRaw = (string) $sheet->getCell("E{$row}")->getValue();

            if ($patientName === '' && $registrationRef === '' && trim($staffNumbersRaw) === '') {
                continue;
            }

            if ($registrationRef === '') {
                $this->warn("Row {$row}: skipped (missing registration_ref)");
                $skipped++;
                continue;
            }

            if ($unitName === '') {
                $this->warn("Row {$row}: skipped (missing unit)");
                $skipped++;
                continue;
            }

            $unit = Unit::query()->whereRaw('LOWER(name) = ?', [Str::lower($unitName)])->first();
            if (!$unit) {
                $this->warn("Row {$row}: skipped (unit not found: {$unitName})");
                $skipped++;
                continue;
            }

            $staffNumbers = $this->parseStaffNumbers($staffNumbersRaw);
            if (empty($staffNumbers)) {
                $this->warn("Row {$row}: skipped (no staff_numbers)");
                $skipped++;
                continue;
            }

            $staff = User::query()->whereIn('employee_number', $staffNumbers)->get(['id', 'employee_number']);
            $missing = collect($staffNumbers)->diff($staff->pluck('employee_number')->map(fn ($v) => (string) $v))->values();
            if ($missing->isNotEmpty()) {
                $this->warn("Row {$row}: skipped (staff not found: {$missing->implode(', ')})");
                $skipped++;
                continue;
            }

            $duplicate = ReviewInvitation::query()
                ->where('registration_ref', $registrationRef)
                ->whereIn('status', ['created', 'sent', 'opened'])
                ->where(function ($q) {
                    $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
                })
                ->exists();

            if ($duplicate) {
                $this->warn("Row {$row}: skipped (duplicate active invitation)");
                $skipped++;
                continue;
            }

            [$rawToken, $invUrl] = DB::transaction(function () use ($registrationRef, $patientName, $phone, $unit, $staff, $expiresDays) {
                $now = Carbon::now();

                [$rawToken, $tokenHash] = $this->generateToken();

                $invitation = ReviewInvitation::create([
                    'registration_ref' => $registrationRef,
                    'unit_id' => $unit->id,
                    'patient_name' => $patientName !== '' ? $patientName : null,
                    'contact' => $phone !== '' ? $phone : null,
                    'token_hash' => $tokenHash,
                    'expires_at' => $now->copy()->addDays($expiresDays),
                    'status' => 'sent',
                    'sent_at' => $now,
                ]);

                $mapRows = $staff->map(fn (User $u) => [
                    'invitation_id' => $invitation->id,
                    'user_id' => $u->id,
                    'role' => null,
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

    private function parseStaffNumbers(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }

        $parts = preg_split('/\s*;\s*/', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
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

    /**
     * @return array{0:string,1:string}
     */
    private function generateToken(): array
    {
        for ($i = 0; $i < 5; $i++) {
            $tokenPlain = Str::random(40);
            $tokenHash = hash('sha256', $tokenPlain);
            $exists = ReviewInvitation::query()->where('token_hash', $tokenHash)->exists();
            if (!$exists) {
                return [$tokenPlain, $tokenHash];
            }
        }

        throw new \RuntimeException('Gagal menghasilkan token unik.');
    }
}
