<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Models\ReviewDetail;
use App\Models\ReviewInvitation;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class PublicReviewController extends Controller
{
    public function show(Request $request, string $token): View
    {
        $tokenHash = hash('sha256', $token);

        /** @var ReviewInvitation|null $invitation */
        $invitation = ReviewInvitation::query()
            ->with([
                'unit:id,name',
                'staff.user:id,name,employee_number,profession_id,unit_id',
                'staff.user.unit:id,name',
                'staff.user.profession:id,name',
            ])
            ->where('token_hash', $tokenHash)
            ->first();

        if (!$invitation) {
            abort(404);
        }

        $now = Carbon::now();

        if ($invitation->status === 'cancelled') {
            abort(410, 'Link tidak aktif.');
        }

        if ($invitation->status === 'used' || $invitation->used_at) {
            abort(410, 'Link sudah digunakan.');
        }

        if ($invitation->status === 'expired' || ($invitation->expires_at && $now->greaterThan($invitation->expires_at))) {
            if ($invitation->status !== 'expired') {
                $invitation->forceFill(['status' => 'expired'])->save();
            }
            abort(410, 'Link kedaluwarsa.');
        }

        if ($invitation->opened_at === null) {
            $invitation->forceFill([
                'opened_at' => $now,
                'status' => $invitation->status === 'sent' || $invitation->status === 'created' ? 'opened' : $invitation->status,
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

        $validated = Validator::make($request->all(), [
            'overall_rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:2000'],
            'details' => ['nullable', 'array'],
            'details.*.user_id' => ['required_with:details', 'integer'],
            'details.*.rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'details.*.comment' => ['nullable', 'string', 'max:2000'],
        ])->validate();

        DB::transaction(function () use ($request, $tokenHash, $validated) {
            /** @var ReviewInvitation $invitation */
            $invitation = ReviewInvitation::query()
                ->where('token_hash', $tokenHash)
                ->lockForUpdate()
                ->firstOrFail();

            $now = Carbon::now();

            if ($invitation->status === 'cancelled') {
                abort(410, 'Link tidak aktif.');
            }

            if ($invitation->status === 'used' || $invitation->used_at) {
                abort(410, 'Link sudah digunakan.');
            }

            if ($invitation->status === 'expired' || ($invitation->expires_at && $now->greaterThan($invitation->expires_at))) {
                if ($invitation->status !== 'expired') {
                    $invitation->forceFill(['status' => 'expired'])->save();
                }
                abort(410, 'Link kedaluwarsa.');
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
        });

        return redirect()->route('reviews.thanks');
    }

    public function thanks(): View
    {
        return view('public.reviews.thanks');
    }
}

