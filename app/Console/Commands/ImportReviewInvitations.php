<?php

namespace App\Console\Commands;

use App\Enums\MedicalStaffReviewRole;
use App\Enums\ReviewStatus;
use App\Models\Review;
use App\Models\ReviewDetail;
use App\Models\ReviewInvitation;
use App\Models\ReviewInvitationStaff;
use App\Models\Unit;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportReviewInvitations extends Command
{
    protected $signature = 'reviews:import-invitations {file : Path to .xlsx file} {--expires-days=7 : Invitation expiry in days}';

    protected $description = 'Import review invitations from Excel (.xlsx) and generate one-time invitation links.';

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
        $this->line('A=registration_ref, B=patient_name, C=contact, D=unit_name, E=medical_staff_ids (comma separated user IDs)');

        $created = 0;
        $skipped = 0;

        for ($row = 2; $row <= $highestRow; $row++) {
            $registrationRef = trim((string) $sheet->getCell("A{$row}")->getValue());
            if ($registrationRef === '') {
                continue;
            }

            $patientName = trim((string) $sheet->getCell("B{$row}")->getValue());
            $contact = trim((string) $sheet->getCell("C{$row}")->getValue());
            $unitName = trim((string) $sheet->getCell("D{$row}")->getValue());
            $staffIdsRaw = (string) $sheet->getCell("E{$row}")->getValue();

            $staffIds = collect(preg_split('/\s*,\s*/', $staffIdsRaw, -1, PREG_SPLIT_NO_EMPTY) ?: [])
                ->map(fn ($v) => (int) preg_replace('/\D+/', '', (string) $v))
                ->filter(fn ($v) => $v > 0)
                ->unique()
                ->values()
                ->all();

            if (empty($staffIds)) {
                $this->warn("Row {$row}: skipped (no staff IDs)");
                $skipped++;
                continue;
            }

            $unit = Unit::query()
                ->where('type', 'poliklinik')
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($unitName)])
                ->first();

            if (!$unit) {
                $this->warn("Row {$row}: skipped (unit not found: {$unitName})");
                $skipped++;
                continue;
            }

            if (Review::query()->where('registration_ref', $registrationRef)->exists()) {
                $this->warn("Row {$row}: skipped (registration_ref already exists: {$registrationRef})");
                $skipped++;
                continue;
            }

            $staff = User::query()->whereIn('id', $staffIds)->with('profession:id,name')->get(['id', 'profession_id']);
            if ($staff->count() !== count($staffIds)) {
                $this->warn("Row {$row}: skipped (some staff IDs not found)");
                $skipped++;
                continue;
            }

            [$rawToken, $invUrl] = DB::transaction(function () use ($registrationRef, $patientName, $contact, $unit, $staff, $staffIds, $expiresDays) {
                $now = Carbon::now();

                $review = Review::create([
                    'registration_ref' => $registrationRef,
                    'unit_id' => $unit->id,
                    'overall_rating' => null,
                    'comment' => null,
                    'patient_name' => $patientName !== '' ? $patientName : null,
                    'contact' => $contact !== '' ? $contact : null,
                    'client_ip' => null,
                    'user_agent' => null,
                    'status' => ReviewStatus::PENDING,
                ]);

                $details = $staff->map(function (User $u) use ($review) {
                    $role = MedicalStaffReviewRole::guessFromProfession($u->profession?->name)->value;
                    return [
                        'review_id' => $review->id,
                        'medical_staff_id' => $u->id,
                        'role' => $role,
                        'rating' => null,
                        'comment' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                })->all();

                ReviewDetail::insert($details);

                $rawToken = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
                $tokenHash = hash('sha256', $rawToken);

                $invitation = ReviewInvitation::create([
                    'review_id' => $review->id,
                    'token_hash' => $tokenHash,
                    'status' => 'active',
                    'expires_at' => $now->copy()->addDays($expiresDays),
                ]);

                $mapRows = collect($staffIds)->map(fn (int $staffId) => [
                    'invitation_id' => $invitation->id,
                    'medical_staff_id' => $staffId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all();

                ReviewInvitationStaff::insert($mapRows);

                $url = url('/reviews/invite/' . $rawToken);
                return [$rawToken, $url];
            });

            $this->line("Row {$row}: created {$registrationRef} => {$invUrl}");
            $created++;
        }

        $this->info("Done. created={$created}, skipped={$skipped}");
        return 0;
    }
}
