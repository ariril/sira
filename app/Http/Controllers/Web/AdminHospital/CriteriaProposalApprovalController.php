<?php

namespace App\Http\Controllers\Web\AdminHospital;

use App\Http\Controllers\Controller;
use App\Models\CriteriaProposal;
use App\Models\PerformanceCriteria;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class CriteriaProposalApprovalController extends Controller
{
    public function index(): View
    {
        $this->authorizeAccess();
        $items = CriteriaProposal::query()
            ->whereIn('status', ['proposed'])
            ->orderBy('id')
            ->get();
        return view('admin_rs.criteria_proposals.index', [ 'items' => $items ]);
    }

    public function approve(Request $request, CriteriaProposal $proposal): RedirectResponse
    {
        $this->authorizeAccess();
        if ($proposal->status->value !== 'proposed') return back()->withErrors(['status' => 'Status usulan tidak valid.']);
        // Buat PerformanceCriteria aktif
        $pc = PerformanceCriteria::create([
            'name' => $proposal->name,
            'type' => 'benefit', // asumsi default; bisa diubah kemudian oleh admin
            'description' => $proposal->description,
            'is_active' => true,
            'suggested_weight' => $proposal->suggested_weight,
        ]);
        $proposal->update([
            'status' => 'published',
            'approved_at' => now(),
            'approved_by' => Auth::id(),
        ]);
        return back()->with('status','Usulan disetujui & dipublikasikan sebagai kriteria.');
    }

    public function reject(Request $request, CriteriaProposal $proposal): RedirectResponse
    {
        $this->authorizeAccess();
        if ($proposal->status->value !== 'proposed') return back()->withErrors(['status' => 'Status usulan tidak valid.']);
        $proposal->update([
            'status' => 'rejected',
        ]);
        return back()->with('status','Usulan ditolak.');
    }

    private function authorizeAccess(): void
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'admin_rs') abort(403);
    }
}
