<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ReviewInvitation;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;

class ReviewInvitationRedirectController extends Controller
{
    public function __invoke(string $token): RedirectResponse
    {
        $tokenHash = hash('sha256', $token);

        /** @var ReviewInvitation|null $invitation */
        $invitation = ReviewInvitation::query()
            ->where('token_hash', $tokenHash)
            ->first();

        if (!$invitation) {
            abort(404);
        }

        if ($invitation->clicked_at === null) {
            $now = Carbon::now();

            $invitation->forceFill([
                'clicked_at' => $now,
                'status' => in_array((string) $invitation->status, ['created', 'sent'], true) ? 'clicked' : $invitation->status,
            ])->save();
        }

        return redirect()->route('reviews.invite.show', ['token' => $token]);
    }
}
