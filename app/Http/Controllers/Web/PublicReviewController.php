<?php

namespace App\Http\Controllers\Web;

use App\Enums\ReviewStatus;
use App\Http\Controllers\Controller;
use App\Models\ReviewInvitation;
use App\Models\ReviewInvitationStaff;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class PublicReviewController extends Controller
{
    public function create(Request $request): View
    {
        // Open review (manual NO RM input) is disabled. Patients must use invitation links.
        return view('pages.reviews.create');
    }

    public function show(string $token): View
    {
        $tokenHash = hash('sha256', $token);
        $invitation = ReviewInvitation::query()
            ->with(['review.unit'])
            ->where('token_hash', $tokenHash)
            ->first();

        if (!$invitation) {
            return view('pages.reviews.invite', ['state' => 'invalid']);
        }

        $now = Carbon::now();
        if ($invitation->status === 'active' && $invitation->expires_at && $now->greaterThan($invitation->expires_at)) {
            $invitation->update(['status' => 'expired']);
            $invitation->refresh();
        }

        $state = match ($invitation->status) {
            'used' => 'used',
            'expired' => 'expired',
            'revoked' => 'revoked',
            default => 'active',
        };

        $staffIds = ReviewInvitationStaff::query()
            ->where('invitation_id', $invitation->id)
            ->pluck('medical_staff_id')
            ->all();

        $staff = User::query()
            ->whereIn('id', $staffIds)
            ->with(['unit:id,name', 'profession:id,name'])
            ->orderBy('name')
            ->get()
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'unit_name' => $u->unit?->name,
                'profession' => $u->profession?->name,
            ])
            ->values();

        // Prefill existing ratings/comments (if any)
        $existing = DB::table('review_details')
            ->where('review_id', $invitation->review_id)
            ->whereIn('medical_staff_id', $staffIds)
            ->get(['medical_staff_id', 'rating', 'comment'])
            ->keyBy('medical_staff_id');

        return view('pages.reviews.invite', [
            'state' => $state,
            'token' => $token,
            'invitation' => $invitation,
            'review' => $invitation->review,
            'staff' => $staff,
            'existing' => $existing,
        ]);
    }

    public function store(Request $request, string $token): RedirectResponse
    {
        $tokenHash = hash('sha256', $token);

        $result = DB::transaction(function () use ($request, $tokenHash) {
            /** @var ReviewInvitation|null $invitation */
            $invitation = ReviewInvitation::query()
                ->where('token_hash', $tokenHash)
                ->lockForUpdate()
                ->first();

            if (!$invitation) {
                return ['ok' => false, 'state' => 'invalid'];
            }

            $now = Carbon::now();
            if ($invitation->status === 'active' && $invitation->expires_at && $now->greaterThan($invitation->expires_at)) {
                $invitation->update(['status' => 'expired']);
                return ['ok' => false, 'state' => 'expired'];
            }
            if ($invitation->status === 'used' || $invitation->used_at) {
                return ['ok' => false, 'state' => 'used'];
            }
            if ($invitation->status !== 'active') {
                return ['ok' => false, 'state' => $invitation->status];
            }

            $allowedStaffIds = ReviewInvitationStaff::query()
                ->where('invitation_id', $invitation->id)
                ->pluck('medical_staff_id')
                ->map(fn ($v) => (int) $v)
                ->all();

            $validator = Validator::make($request->all(), [
                'details' => ['required', 'array'],
                'details.*.rating' => ['nullable', 'integer', 'min:1', 'max:5'],
                'details.*.comment' => ['nullable', 'string', 'max:2000'],
            ], [
                'details.required' => 'Form ulasan tidak valid.',
            ]);

            $validator->after(function ($validator) use ($request, $allowedStaffIds) {
                $details = $request->input('details', []);
                if (!is_array($details)) {
                    $validator->errors()->add('details', 'Form ulasan tidak valid.');
                    return;
                }

                // Require rating for all invited staff and disallow non-invited staff.
                $postedStaffIds = collect(array_keys($details))
                    ->map(fn ($id) => (int) $id)
                    ->filter(fn ($id) => $id > 0)
                    ->values();

                $extra = $postedStaffIds->diff($allowedStaffIds);
                if ($extra->isNotEmpty()) {
                    $validator->errors()->add('details', 'Form ulasan tidak valid.');
                }

                foreach ($allowedStaffIds as $staffId) {
                    $rating = data_get($details, $staffId . '.rating');
                    if ($rating === null || $rating === '' || !is_numeric($rating)) {
                        $validator->errors()->add("details.$staffId.rating", 'Rating wajib diisi.');
                    }
                }
            });

            $validator->validateWithBag('reviewForm');

            $detailsInput = (array) $request->input('details', []);
            $rows = collect($allowedStaffIds)->map(function (int $staffId) use ($detailsInput, $now, $invitation) {
                $rating = (int) data_get($detailsInput, $staffId . '.rating');
                $comment = (string) (data_get($detailsInput, $staffId . '.comment') ?? '');
                $comment = trim($comment) !== '' ? $comment : null;

                return [
                    'review_id' => $invitation->review_id,
                    'medical_staff_id' => $staffId,
                    'rating' => $rating,
                    'comment' => $comment,
                    'updated_at' => $now,
                    'created_at' => $now,
                ];
            });

            // Upsert details (import may have created empty rows)
            DB::table('review_details')->upsert(
                $rows->all(),
                ['review_id', 'medical_staff_id'],
                ['rating', 'comment', 'updated_at']
            );

            $avgRating = (int) round(max(1, $rows->avg('rating') ?: 0));

            // Update review metadata (locked fields are NOT taken from request)
            DB::table('reviews')
                ->where('id', $invitation->review_id)
                ->update([
                    'overall_rating' => $avgRating,
                    'status' => ReviewStatus::PENDING->value,
                    'client_ip' => $request->ip(),
                    'user_agent' => substr((string) $request->header('User-Agent'), 0, 255),
                    'updated_at' => $now,
                ]);

            $invitation->update([
                'status' => 'used',
                'used_at' => $now,
                'updated_at' => $now,
            ]);

            return ['ok' => true, 'state' => 'used'];
        });

        if (!($result['ok'] ?? false)) {
            return redirect()->route('reviews.invite.show', ['token' => $token])
                ->with('invite_state', $result['state'] ?? 'invalid');
        }

        return redirect()->route('reviews.invite.show', ['token' => $token])
            ->with('status', 'Terima kasih, ulasan Anda telah direkam.')
            ->with('invite_state', 'used');
    }
}

