<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Jobs\RecalculatePeriodJob;
use App\Models\Review;
use App\Models\ReviewDetail;
use App\Models\ReviewInvitation;
use App\Models\AssessmentPeriod;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class PublicReviewController extends Controller
{
    private function expiredRedirect(): RedirectResponse
    {
        return redirect()
            ->route('reviews.unavailable')
            ->with('notice', 'Maaf, link ulasan ini sudah kedaluwarsa sehingga tidak dapat digunakan.');
    }

    public function show(Request $request, string $token): View|RedirectResponse
    {
        $tokenHash = hash('sha256', $token);

        /** @var ReviewInvitation|null $invitation */
        $invitation = ReviewInvitation::query()
            ->with([
                'unit:id,name',
                'assessmentPeriod:id,status',
                'staff.user:id,name,employee_number,profession_id,unit_id',
                'staff.user.unit:id,name',
                'staff.user.profession:id,name',
            ])
            ->where('token_hash', $tokenHash)
            ->first();

        if (!$invitation) {
            return $this->expiredRedirect();
        }

        $now = Carbon::now();

        if ($invitation->status === 'cancelled') {
            return $this->expiredRedirect();
        }

        if ($invitation->status === 'used' || $invitation->used_at) {
            return redirect()
                ->route('reviews.thanks')
                ->with('notice', 'Link ini sudah pernah digunakan. Jika Anda sudah mengirim ulasan, tidak perlu mengisi lagi.');
        }

        if ($invitation->status === 'expired' || ($invitation->expires_at && $now->greaterThan($invitation->expires_at))) {
            if ($invitation->status !== 'expired') {
                $invitation->forceFill(['status' => 'expired'])->save();
            }
            return $this->expiredRedirect();
        }

        // Business rule: reviews cannot be submitted after approval starts.
        if (Schema::hasColumn('review_invitations', 'assessment_period_id') && $invitation->assessment_period_id) {
            $periodStatus = (string) ($invitation->assessmentPeriod?->status ?? '');
            if ($periodStatus !== '' && !in_array($periodStatus, [AssessmentPeriod::STATUS_ACTIVE, AssessmentPeriod::STATUS_LOCKED], true)) {
                // Untuk pasien: tampilkan sebagai "kedaluwarsa" agar tidak menimbulkan bias.
                return $this->expiredRedirect();
            }
        }

        if ($invitation->clicked_at === null) {
            $invitation->forceFill([
                'clicked_at' => $now,
                'status' => $invitation->status === 'sent' || $invitation->status === 'created' ? 'clicked' : $invitation->status,
                'client_ip' => $request->ip(),
                'user_agent' => substr((string) $request->header('User-Agent'), 0, 255),
            ])->save();
        }

        $staff = $invitation->staff
            ->map(function ($row) {
                $u = $row->user;
                return [
                    'id' => $u?->id,
                    'name' => $u?->name,
                    'employee_number' => $u?->employee_number,
                    'role' => $row->role,
                    'unit_name' => $u?->unit?->name,
                    'profession' => $u?->profession?->name,
                ];
            })
            ->filter(fn ($r) => !empty($r['id']))
            ->values();

        return view('public.reviews.invite', [
            'token' => $token,
            'invitation' => $invitation,
            'staff' => $staff,
        ]);
    }

    public function store(Request $request, string $token): RedirectResponse
    {
        $tokenHash = hash('sha256', $token);

        $invitationId = ReviewInvitation::query()
            ->where('token_hash', $tokenHash)
            ->value('id');

        if (!$invitationId) {
            return $this->expiredRedirect();
        }

        $allowedStaffIds = \App\Models\ReviewInvitationStaff::query()
            ->where('invitation_id', (int) $invitationId)
            ->pluck('user_id')
            ->map(fn ($v) => (int) $v)
            ->values();

        $validator = Validator::make($request->all(), [
            'overall_rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:2000'],
            'details' => ['required', 'array', 'min:1'],
            'details.*.user_id' => ['required', 'integer'],
            'details.*.rating' => ['required', 'integer', 'min:1', 'max:5'],
            'details.*.comment' => ['nullable', 'string', 'max:2000'],
        ], [
            'details.required' => 'Mohon isi rating untuk semua staf.',
            'details.array' => 'Form ulasan tidak valid.',
            'details.*.user_id.required' => 'Form ulasan tidak valid.',
            'details.*.rating.required' => 'Rating wajib diisi.',
            'details.*.rating.min' => 'Rating wajib diisi.',
        ]);

        $validator->after(function ($validator) use ($allowedStaffIds, $request) {
            if ($allowedStaffIds->isEmpty()) {
                $validator->errors()->add('details', 'Daftar staf tidak valid.');
                return;
            }

            $details = collect($request->input('details', []))
                ->filter(fn ($row) => is_array($row));

            $postedIds = $details
                ->pluck('user_id')
                ->map(fn ($v) => (int) $v)
                ->filter(fn (int $v) => $v > 0)
                ->values();

            if ($postedIds->count() !== $postedIds->unique()->count()) {
                $validator->errors()->add('details', 'Form ulasan tidak valid.');
                return;
            }

            $extra = $postedIds->diff($allowedStaffIds);
            if ($extra->isNotEmpty()) {
                $validator->errors()->add('details', 'Form ulasan tidak valid.');
                return;
            }

            $missing = $allowedStaffIds->diff($postedIds);
            if ($missing->isNotEmpty()) {
                $validator->errors()->add('details', 'Mohon isi rating untuk semua staf.');
            }
        });

        $validated = $validator->validateWithBag('reviewForm');

        $result = DB::transaction(function () use ($request, $invitationId, $validated) {
            /** @var ReviewInvitation $invitation */
            $invitation = ReviewInvitation::query()
                ->whereKey((int) $invitationId)
                ->lockForUpdate()
                ->firstOrFail();

            // Business rule: reviews cannot be submitted after approval starts.
            if (Schema::hasColumn('review_invitations', 'assessment_period_id') && $invitation->assessment_period_id) {
                $period = AssessmentPeriod::query()
                    ->whereKey((int) $invitation->assessment_period_id)
                    ->lockForUpdate()
                    ->first();

                $periodStatus = (string) ($period?->status ?? '');
                if ($periodStatus !== '' && !in_array($periodStatus, [AssessmentPeriod::STATUS_ACTIVE, AssessmentPeriod::STATUS_LOCKED], true)) {
                    return 'expired';
                }
            }

            $now = Carbon::now();

            if ($invitation->status === 'cancelled') {
                return 'expired';
            }

            if ($invitation->status === 'used' || $invitation->used_at) {
                return 'already_used';
            }

            if ($invitation->status === 'expired' || ($invitation->expires_at && $now->greaterThan($invitation->expires_at))) {
                if ($invitation->status !== 'expired') {
                    $invitation->forceFill(['status' => 'expired'])->save();
                }
                return 'expired';
            }

            $allowedStaff = $invitation->staff()->get(['user_id', 'role']);
            $allowedStaffIds = $allowedStaff->pluck('user_id')->map(fn ($v) => (int) $v)->values();
            $roleByUserId = $allowedStaff->mapWithKeys(fn ($r) => [(int) $r->user_id => $r->role])->all();

            $details = collect($validated['details'] ?? [])
                ->filter(fn ($row) => is_array($row))
                ->map(fn ($row) => [
                    'user_id' => (int) ($row['user_id'] ?? 0),
                    'rating' => $row['rating'] ?? null,
                    'comment' => $row['comment'] ?? null,
                ])
                ->filter(fn ($row) => $row['user_id'] > 0)
                ->values();

            $postedIds = $details->pluck('user_id');
            if ($postedIds->count() !== $postedIds->unique()->count()) {
                abort(422, 'Form ulasan tidak valid.');
            }

            $extra = $postedIds->diff($allowedStaffIds);
            if ($extra->isNotEmpty()) {
                abort(422, 'Form ulasan tidak valid.');
            }

            $detailsByUserId = $details->keyBy('user_id');

            $review = Review::query()->updateOrCreate(
                ['registration_ref' => $invitation->registration_ref],
                [
                    'unit_id' => $invitation->unit_id,
                    'patient_name' => $invitation->patient_name,
                    'contact' => $invitation->contact,
                    'status' => \App\Enums\ReviewStatus::PENDING,
                    'overall_rating' => $validated['overall_rating'] ?? null,
                    'comment' => $validated['comment'] ?? null,
                    'client_ip' => $request->ip(),
                    'user_agent' => substr((string) $request->header('User-Agent'), 0, 255),
                ]
            );

            $rows = $allowedStaffIds->map(function (int $userId) use ($detailsByUserId, $now, $review, $roleByUserId) {
                $input = $detailsByUserId->get($userId, []);

                $rating = $input['rating'] ?? null;
                $rating = ($rating === '' || $rating === null) ? null : (int) $rating;

                $comment = $input['comment'] ?? null;
                $comment = is_string($comment) ? trim($comment) : null;
                $comment = $comment !== '' ? $comment : null;

                return [
                    'review_id' => $review->id,
                    'medical_staff_id' => $userId,
                    'role' => $roleByUserId[$userId] ?? null,
                    'rating' => $rating,
                    'comment' => $comment,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            })->all();

            DB::table('review_details')->upsert(
                $rows,
                ['review_id', 'medical_staff_id'],
                ['role', 'rating', 'comment', 'updated_at']
            );

            $invitation->forceFill([
                'status' => 'used',
                'used_at' => $now,
            ])->save();

            // Keep performance assessments consistent with latest reviews.
            if (Schema::hasColumn('review_invitations', 'assessment_period_id') && $invitation->assessment_period_id) {
                RecalculatePeriodJob::dispatch((int) $invitation->assessment_period_id)->afterCommit();
            }

            return 'saved';
        });

        if ($result === 'already_used') {
            return redirect()
                ->route('reviews.thanks')
                ->with('notice', 'Ulasan sudah pernah terkirim. Terima kasih!');
        }

        if ($result === 'expired') {
            return $this->expiredRedirect();
        }

        return redirect()->route('reviews.thanks');
    }

    public function unavailable(): View
    {
        return view('public.reviews.unavailable');
    }

    public function thanks(): View
    {
        return view('public.reviews.thanks');
    }
}

