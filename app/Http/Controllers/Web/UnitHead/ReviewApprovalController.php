<?php

namespace App\Http\Controllers\Web\UnitHead;

use App\Enums\ReviewStatus;
use App\Http\Controllers\Controller;
use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ReviewApprovalController extends Controller
{
    public function index(Request $request): View
    {
        $unitId = Auth::user()?->unit_id;
        abort_unless($unitId, 403, 'Unit belum dikonfigurasi untuk akun ini.');

        $perPage = (int) $request->integer('per_page', 12);
        $perPage = max(6, min($perPage, 50));
        $statusOptions = [
            'all' => 'Semua Status',
            ReviewStatus::PENDING->value  => 'Pending',
            ReviewStatus::APPROVED->value => 'Approved',
            ReviewStatus::REJECTED->value => 'Rejected',
        ];
        $statusFilter = $request->get('status', ReviewStatus::PENDING->value);
        if (!array_key_exists($statusFilter, $statusOptions)) {
            $statusFilter = ReviewStatus::PENDING->value;
        }
        $search = trim((string) $request->get('q'));

        $query = Review::query()
            ->with(['unit:id,name', 'details.medicalStaff:id,name,profession_id', 'details.medicalStaff.profession:id,name', 'decidedBy:id,name'])
            ->where('unit_id', $unitId)
            ->orderByDesc('created_at');

        if ($statusFilter && $statusFilter !== 'all') {
            $query->where('status', $statusFilter);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $pattern = '%' . $search . '%';
                $q->where('registration_ref', 'like', $pattern)
                    ->orWhere('patient_name', 'like', $pattern)
                    ->orWhere('comment', 'like', $pattern);
            });
        }

        $items = $query->paginate($perPage)->withQueryString();

        $statusCounts = Review::query()
            ->select('status', DB::raw('COUNT(*) as total'))
            ->where('unit_id', $unitId)
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('kepala_unit.reviews.index', [
            'items' => $items,
            'statusCounts' => $statusCounts,
            'filters' => [
                'status' => $statusFilter,
                'q' => $search,
                'per_page' => $perPage,
            ],
            'statusOptions' => $statusOptions,
            'perPageOptions' => [12, 24, 36],
        ]);
    }

    public function approve(Request $request, Review $review): RedirectResponse
    {
        $this->ensureReviewBelongsToUnit($review);
        if ($review->status === ReviewStatus::APPROVED) {
            return back()->with('status', 'Ulasan sudah disetujui.');
        }

        if ($review->status === ReviewStatus::REJECTED) {
            return back()->withErrors([
                'review' => 'Ulasan yang ditolak tidak dapat disetujui tanpa perubahan dari pasien.',
            ]);
        }

        $review->forceFill([
            'status'       => ReviewStatus::APPROVED,
            'decision_note'=> $request->input('note'),
            'decided_by'   => Auth::id(),
            'decided_at'   => now(),
        ])->save();

        return back()->with('status', 'Ulasan pasien disetujui.');
    }

    public function reject(Request $request, Review $review): RedirectResponse
    {
        $this->ensureReviewBelongsToUnit($review);
        if ($review->status === ReviewStatus::REJECTED) {
            return back()->withErrors(['review' => 'Ulasan sudah berada pada status ditolak.']);
        }

        if ($review->status === ReviewStatus::APPROVED) {
            return back()->withErrors(['review' => 'Tidak dapat menolak ulasan yang sudah disetujui.']);
        }

        $data = $request->validate([
            'note' => 'required|string|min:5|max:500',
        ]);

        $review->forceFill([
            'status'        => ReviewStatus::REJECTED,
            'decision_note' => $data['note'],
            'decided_by'    => Auth::id(),
            'decided_at'    => now(),
        ])->save();

        return back()->with('status', 'Ulasan pasien ditolak.');
    }

    public function approveAll(Request $request): RedirectResponse
    {
        $unitId = Auth::user()?->unit_id;
        abort_unless($unitId, 403, 'Unit belum dikonfigurasi untuk akun ini.');

        $pendingQuery = Review::query()
            ->where('unit_id', $unitId)
            ->where('status', ReviewStatus::PENDING);

        $pendingCount = (clone $pendingQuery)->count();

        if ($pendingCount === 0) {
            return back()->with('status', 'Tidak ada ulasan pending untuk disetujui.');
        }

        DB::transaction(function () use ($pendingQuery) {
            $pendingQuery->update([
                'status'      => ReviewStatus::APPROVED,
                'decided_by'  => Auth::id(),
                'decided_at'  => now(),
            ]);
        });

        return back()->with('status', $pendingCount . ' ulasan pending disetujui.');
    }

    private function ensureReviewBelongsToUnit(Review $review): void
    {
        $unitId = Auth::user()?->unit_id;
        abort_if(!$unitId || $review->unit_id !== $unitId, 403, 'Ulasan tidak berada pada unit Anda.');
    }
}
