<?php

namespace App\Http\Controllers\Web\UnitHead;

use App\Http\Controllers\Controller;
use App\Models\CriteriaProposal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class CriteriaProposalController extends Controller
{
    public function index(): View
    {
        $this->authorizeAccess();
        $me = Auth::user();
        $items = CriteriaProposal::query()
            ->where('unit_head_id', $me->id)
            ->orderByDesc('id')
            ->get();
        return view('kepala_unit.criteria_proposals.index', [
            'items' => $items,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAccess();
        $me = Auth::user();
        $data = $request->validate([
            'name' => ['required','string','max:150'],
            'description' => ['nullable','string'],
            'suggested_weight' => ['nullable','numeric','min:0','max:100'],
        ]);
        CriteriaProposal::create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'suggested_weight' => $data['suggested_weight'] ?? null,
            'status' => 'proposed',
            'unit_head_id' => $me->id,
        ]);
        return back()->with('status','Usulan kriteria dikirim ke Admin RS.');
    }

    private function authorizeAccess(): void
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'kepala_unit') abort(403);
    }
}
