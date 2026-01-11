<?php

namespace App\Http\Controllers\Web\AdminHospital;

use App\Http\Controllers\Controller;
use App\Models\AssessmentPeriod;
use App\Models\ReviewInvitation;
use App\Services\Reviews\ReviewInvitationEmailService;
use App\Support\AssessmentPeriodGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class ReviewInvitationController extends Controller
{
    public function index(Request $request): View
    {
        if (!Schema::hasTable('review_invitations')) {
            return view('admin_rs.review_invitations.index', [
                'items' => null,
                'period' => null,
                'periodWarning' => 'Tabel review_invitations belum tersedia.',
                'periodOptions' => [],
                'selectedPeriodId' => null,
            ]);
        }

        ['period' => $period, 'periodWarning' => $periodWarning, 'periodOptions' => $periodOptions, 'selectedPeriodId' => $selectedPeriodId] = $this->resolvePeriodContext($request);

        $query = ReviewInvitation::query()->with('unit');

        if ($period && Schema::hasColumn('review_invitations', 'assessment_period_id')) {
            $query->where('assessment_period_id', (int) $period->id);
        }

        $items = $query
            ->orderByDesc('id')
            ->paginate(12)
            ->withQueryString();

        return view('admin_rs.review_invitations.index', [
            'items' => $items,
            'period' => $period,
            'periodWarning' => $periodWarning,
            'periodOptions' => $periodOptions,
            'selectedPeriodId' => $selectedPeriodId,
        ]);
    }

    public function sendEmail(int $id, ReviewInvitationEmailService $service): RedirectResponse
    {
        /** @var ReviewInvitation $invitation */
        $invitation = ReviewInvitation::query()->findOrFail($id);

        $email = trim((string) ($invitation->email ?? ''));
        if ($email === '') {
            return back()->with('error', 'Email undangan belum diisi.');
        }

        if ($invitation->used_at !== null) {
            return back()->with('error', 'Undangan sudah digunakan.');
        }

        try {
            $service->sendSingle($invitation);
        } catch (\Throwable $e) {
            return back()->with('error', 'Gagal mengirim email undangan. Silakan cek konfigurasi email / log.');
        }

        return back()->with('success', 'Email undangan berhasil dikirim.');
    }

    public function sendEmailBulk(Request $request, ReviewInvitationEmailService $service): RedirectResponse
    {
        $validated = $request->validate([
            'period_id' => ['required', 'integer'],
        ]);

        $periodId = (int) $validated['period_id'];

        $result = null;
        $errorMsg = null;
        try {
            $result = $service->sendBulkByPeriod($periodId);
        } catch (\Throwable $e) {
            \Log::error('bulk_review_invitation_send_failed', [
                'period_id' => $periodId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $errorMsg = $e->getMessage();
        }

        if ($result) {
            $msg = 'Email bulk selesai. Dikirim: ' . ($result['sent'] ?? 0) . ', Gagal: ' . ($result['failed'] ?? 0) . ', Total: ' . ($result['attempted'] ?? 0) . '.';
            if ($errorMsg) {
                return back()->with('error', $msg . ' (Ada error: ' . $errorMsg . ')');
            }
            return back()->with('success', $msg);
        }

        return back()->with('error', 'Gagal mengirim email bulk. Silakan cek konfigurasi email / log.');
    }

    public function testEmail(Request $request, ReviewInvitationEmailService $service): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $to = (string) $validated['email'];
        $result = $service->sendTestEmail($to);

        session(['mail_test_result' => $result]);

        if (!($result['success'] ?? false)) {
            return back()->with('error', (string) ($result['error'] ?? 'Gagal mengirim email test.'));
        }

        $msgId = (string) (($result['message_id'] ?? '') ?: '-');
        return back()->with('success', 'Email test terkirim (accepted). Message-ID: ' . $msgId);
    }

    /**
     * When there is both an active (date-based) period and a latest LOCKED period, allow user to choose.
     * Default selection prefers LOCKED (so monthly monitoring can still target the locked month).
     *
     * @return array{period:?AssessmentPeriod,periodWarning:?string,periodOptions:array<int,string>,selectedPeriodId:?int}
     */
    private function resolvePeriodContext(Request $request): array
    {
        if (!Schema::hasTable('assessment_periods')) {
            return [
                'period' => null,
                'periodWarning' => 'Periode penilaian belum tersedia.',
                'periodOptions' => [],
                'selectedPeriodId' => null,
            ];
        }

        $periods = AssessmentPeriod::query()
            ->orderByDesc('start_date')
            ->get(['id', 'name', 'status', 'start_date']);

        if ($periods->isEmpty()) {
            return [
                'period' => null,
                'periodWarning' => 'Periode penilaian belum tersedia.',
                'periodOptions' => [],
                'selectedPeriodId' => null,
            ];
        }

        $active = AssessmentPeriodGuard::resolveActive();
        $defaultId = $active?->id ? (int) $active->id : (int) $periods->first()->id;

        $periodOptions = $periods
            ->mapWithKeys(fn (AssessmentPeriod $p) => [
                (int) $p->id => (string) ($p->name ?? '-'),
            ])
            ->all();

        $requestedId = $request->query('period_id');
        $requestedId = $requestedId !== null ? (int) $requestedId : null;

        $selectedPeriodId = ($requestedId && isset($periodOptions[$requestedId])) ? $requestedId : $defaultId;
        $selectedPeriod = $periods->firstWhere('id', $selectedPeriodId);

        return [
            'period' => $selectedPeriod,
            'periodWarning' => null,
            'periodOptions' => $periodOptions,
            'selectedPeriodId' => $selectedPeriodId,
        ];
    }
}
