<?php

namespace App\Http\Controllers\Web\PolyclinicHead;

use App\Enums\RaterWeightStatus;
use App\Http\Controllers\Controller;
use App\Models\AssessmentPeriod;
use App\Models\Profession;
use App\Models\RaterWeight;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RaterWeightApprovalController extends Controller
{
    private const ASSESSOR_TYPES = [
        'self' => 'Diri sendiri',
        'supervisor' => 'Atasan',
        'peer' => 'Rekan',
        'subordinate' => 'Bawahan',
    ];

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'assessment_period_id' => ['nullable', 'integer'],
            'assessee_profession_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in(['pending', 'active', 'rejected', 'archived', 'all'])],
        ]);

        $status = $filters['status'] ?? 'pending';

        $periods = AssessmentPeriod::orderByDesc('start_date')->get();
        $professions = Profession::orderBy('name')->get(['id', 'name']);

        $query = RaterWeight::query()
            ->with(['period:id,name', 'assesseeProfession:id,name', 'proposedBy:id,name', 'decidedBy:id,name'])
            ->when(!empty($filters['assessment_period_id'] ?? null), fn($q) => $q->where('assessment_period_id', (int) $filters['assessment_period_id']))
            ->when(!empty($filters['assessee_profession_id'] ?? null), fn($q) => $q->where('assessee_profession_id', (int) $filters['assessee_profession_id']));

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $pendingCount = (clone $query)->where('status', RaterWeightStatus::PENDING)->count();

        $items = $query->orderByDesc('id')->paginate(20)->withQueryString();

        return view('kepala_poli.rater_weights.index', [
            'items' => $items,
            'periods' => $periods,
            'professions' => $professions,
            'assessorTypes' => self::ASSESSOR_TYPES,
            'filters' => [
                ...$filters,
                'status' => $status,
            ],
            'pendingCount' => $pendingCount,
        ]);
    }

    public function approve(Request $request, RaterWeight $raterWeight): RedirectResponse
    {
        abort_unless($raterWeight->status === RaterWeightStatus::PENDING, 403);

        DB::transaction(function () use ($raterWeight) {
            // Archive existing active weights for the same key.
            RaterWeight::query()
                ->where('assessment_period_id', $raterWeight->assessment_period_id)
                ->where('assessee_profession_id', $raterWeight->assessee_profession_id)
                ->where('assessor_type', $raterWeight->assessor_type)
                ->where('status', RaterWeightStatus::ACTIVE)
                ->update([
                    'status' => RaterWeightStatus::ARCHIVED,
                    'decided_by' => auth()->id(),
                    'decided_at' => now(),
                ]);

            $raterWeight->status = RaterWeightStatus::ACTIVE;
            $raterWeight->decided_by = auth()->id();
            $raterWeight->decided_at = now();
            $raterWeight->save();
        });

        return back()->with('status', 'Bobot penilai 360 disetujui dan diaktifkan.');
    }

    public function reject(Request $request, RaterWeight $raterWeight): RedirectResponse
    {
        abort_unless($raterWeight->status === RaterWeightStatus::PENDING, 403);

        $raterWeight->status = RaterWeightStatus::REJECTED;
        $raterWeight->decided_by = auth()->id();
        $raterWeight->decided_at = now();
        $raterWeight->save();

        return back()->with('status', 'Bobot penilai 360 ditolak.');
    }
}
