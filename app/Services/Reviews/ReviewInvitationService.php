<?php

namespace App\Services\Reviews;

use App\Models\ReviewInvitation;
use App\Models\ReviewInvitationStaff;
use App\Models\Unit;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ReviewInvitationService
{
    private ?int $targetPeriodId = null;

    public function setTargetPeriodId(?int $periodId): void
    {
        $this->targetPeriodId = $periodId;
    }

    /**
     * @return array{row:int,status:string,message:string,registration_ref:?string,patient_name:?string,contact:?string,unit:?string,link_undangan:?string}
     */
    public function importRow(array $row, int $rowNumber): array
    {
        $registrationRef = trim((string) ($row['registration_ref'] ?? ''));
        $patientName = trim((string) ($row['patient_name'] ?? ''));
        $phone = trim((string) ($row['phone'] ?? ''));
        $unitName = trim((string) ($row['unit'] ?? ''));
        $staffNumbersRaw = trim((string) ($row['staff_numbers'] ?? ''));

        $patientName = $patientName !== '' ? $patientName : null;
        $contact = $phone !== '' ? $phone : null;

        if ($registrationRef === '') {
            return $this->fail($rowNumber, 'registration_ref wajib diisi.', $registrationRef, $patientName, $contact, $unitName);
        }

        if (mb_strlen($registrationRef) > 50) {
            return $this->fail($rowNumber, 'registration_ref maksimal 50 karakter.', $registrationRef, $patientName, $contact, $unitName);
        }

        if ($unitName === '') {
            return $this->fail($rowNumber, 'unit wajib diisi.', $registrationRef, $patientName, $contact, $unitName);
        }

        $unit = Unit::query()
            ->whereRaw('LOWER(name) = ?', [Str::lower($unitName)])
            ->first();

        if (!$unit) {
            return $this->fail($rowNumber, 'unit tidak ditemukan: ' . $unitName, $registrationRef, $patientName, $contact, $unitName);
        }

        $staffNumbers = collect(preg_split('/\s*;\s*/', $staffNumbersRaw, -1, PREG_SPLIT_NO_EMPTY) ?: [])
            ->map(fn ($v) => trim((string) $v))
            ->filter(fn ($v) => $v !== '')
            ->unique()
            ->values();

        if ($staffNumbers->isEmpty()) {
            return $this->fail($rowNumber, 'staff_numbers wajib diisi (pisahkan dengan ";").', $registrationRef, $patientName, $contact, $unitName);
        }

        $staff = User::query()
            ->whereIn('employee_number', $staffNumbers->all())
            ->get(['id', 'employee_number']);

        $found = $staff->pluck('employee_number')->map(fn ($v) => (string) $v)->values();
        $missing = $staffNumbers->diff($found)->values();

        if ($missing->isNotEmpty()) {
            return $this->fail(
                $rowNumber,
                'staff_numbers tidak ditemukan: ' . $missing->implode(', '),
                $registrationRef,
                $patientName,
                $contact,
                $unitName
            );
        }

        $now = Carbon::now();

        $duplicate = ReviewInvitation::query()
            ->where('registration_ref', $registrationRef)
            ->whereIn('status', ['created', 'sent', 'opened'])
            ->where(function ($q) use ($now) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', $now);
            })
            ->exists();

        if ($duplicate) {
            return $this->skip($rowNumber, 'Duplikat: invitation aktif sudah ada (created/sent/opened).', $registrationRef, $patientName, $contact, $unitName);
        }

        [$tokenPlain, $tokenHash] = $this->generateToken();

        $periodId = $this->targetPeriodId;

        $invitation = DB::transaction(function () use ($now, $registrationRef, $patientName, $contact, $unit, $tokenPlain, $tokenHash, $staff, $periodId) {
            $payload = [
                'registration_ref' => $registrationRef,
                'unit_id' => $unit->id,
                'patient_name' => $patientName,
                'contact' => $contact,
                'token_hash' => $tokenHash,
                'status' => 'sent',
                'expires_at' => $now->copy()->addDays(7),
                'sent_at' => $now,
            ];

            if (Schema::hasColumn('review_invitations', 'token_plain')) {
                $payload['token_plain'] = $tokenPlain;
            }

            if ($periodId && Schema::hasColumn('review_invitations', 'assessment_period_id')) {
                $payload['assessment_period_id'] = $periodId;
            }

            $inv = ReviewInvitation::create($payload);

            $mapRows = $staff->map(fn (User $u) => [
                'invitation_id' => $inv->id,
                'user_id' => $u->id,
                'role' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all();

            ReviewInvitationStaff::insert($mapRows);

            return $inv;
        });

        $link = url('/reviews/invite/' . $tokenPlain);

        return [
            'row' => $rowNumber,
            'status' => 'success',
            'message' => 'OK',
            'registration_ref' => $registrationRef,
            'patient_name' => $patientName,
            'contact' => $contact,
            'unit' => $unit->name,
            'link_undangan' => $link,
        ];
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

    private function fail(int $rowNumber, string $message, ?string $registrationRef, ?string $patientName, ?string $contact, ?string $unit): array
    {
        return [
            'row' => $rowNumber,
            'status' => 'failed',
            'message' => $message,
            'registration_ref' => $registrationRef ?: null,
            'patient_name' => $patientName,
            'contact' => $contact,
            'unit' => $unit ?: null,
            'link_undangan' => null,
        ];
    }

    private function skip(int $rowNumber, string $message, ?string $registrationRef, ?string $patientName, ?string $contact, ?string $unit): array
    {
        return [
            'row' => $rowNumber,
            'status' => 'skipped',
            'message' => $message,
            'registration_ref' => $registrationRef ?: null,
            'patient_name' => $patientName,
            'contact' => $contact,
            'unit' => $unit ?: null,
            'link_undangan' => null,
        ];
    }
}
