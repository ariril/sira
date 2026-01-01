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
use App\Services\Reviews\Imports\StaffNumbersParser;
use App\Services\Imports\TabularFileReader;

class ImportReviewInvitations extends Command
{
    protected $signature = 'reviews:import-invitations {file : Path to .xlsx file} {--expires-days=5 : Invitation expiry in days}';

    protected $description = 'Import patient review invitations from Excel (.xlsx) and generate invitation links.';

    public function __construct(
        private readonly TabularFileReader $tabularFileReader,
        private readonly StaffNumbersParser $staffNumbersParser,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $file = (string) $this->argument('file');
        $expiresDays = (int) $this->option('expires-days');
        $expiresDays = $expiresDays > 0 ? $expiresDays : 7;

        if (!is_file($file)) {
            $this->error("File not found: {$file}");
            return 1;
        }

        $ext = strtolower((string) pathinfo($file, PATHINFO_EXTENSION));
        [$header, $rows] = $this->tabularFileReader->read($file, $ext);
        if (!$header) {
            $this->error('File kosong atau header tidak ditemukan.');
            return 1;
        }

        $this->info('Expected columns:');
        $this->line('A=registration_ref, B=patient_name, C=phone, D=unit, E=staff_numbers (semicolon separated employee_number)');

        $created = 0;
        $skipped = 0;

        foreach ($rows as $i => $row) {
            $rowNo = $i + 2; // preserve original Excel row numbers (data starts at row 2)
            $registrationRef = trim((string) ($row[0] ?? ''));
            $patientName = trim((string) ($row[1] ?? ''));
            $phone = trim((string) ($row[2] ?? ''));
            $unitName = trim((string) ($row[3] ?? ''));
            $staffNumbersRaw = (string) ($row[4] ?? '');

            if ($patientName === '' && $registrationRef === '' && trim($staffNumbersRaw) === '') {
                continue;
            }

            if ($registrationRef === '') {
                $this->warn("Row {$rowNo}: skipped (missing registration_ref)");
                $skipped++;
                continue;
            }

            if ($unitName === '') {
                $this->warn("Row {$rowNo}: skipped (missing unit)");
                $skipped++;
                continue;
            }

            $unit = Unit::query()->whereRaw('LOWER(name) = ?', [Str::lower($unitName)])->first();
            if (!$unit) {
                $this->warn("Row {$rowNo}: skipped (unit not found: {$unitName})");
                $skipped++;
                continue;
            }

            $staffNumbers = $this->staffNumbersParser->parse($staffNumbersRaw);
            if (empty($staffNumbers)) {
                $this->warn("Row {$rowNo}: skipped (no staff_numbers)");
                $skipped++;
                continue;
            }

            $staff = User::query()->whereIn('employee_number', $staffNumbers)->get(['id', 'employee_number']);
            $missing = collect($staffNumbers)->diff($staff->pluck('employee_number')->map(fn ($v) => (string) $v))->values();
            if ($missing->isNotEmpty()) {
                $this->warn("Row {$rowNo}: skipped (staff not found: {$missing->implode(', ')})");
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
                $this->warn("Row {$rowNo}: skipped (duplicate active invitation)");
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

            $this->line("Row {$rowNo}: created invitation => {$invUrl}");
            $created++;
        }

        $this->info("Done. created={$created}, skipped={$skipped}");
        return 0;
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
