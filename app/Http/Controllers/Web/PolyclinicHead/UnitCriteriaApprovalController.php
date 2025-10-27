<?php

namespace App\Http\Controllers\Web\PolyclinicHead;

use App\Http\Controllers\Controller;
use App\Models\UnitCriteriaWeight as Weight;
use App\Enums\UnitCriteriaWeightStatus as UCWStatus;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UnitCriteriaApprovalController extends Controller
{
    public function index(Request $request): View
    {
        $me = Auth::user();

        // Scope unit untuk Kepala Poliklinik: unit anak dari unit-nya, atau fallback semua unit bertipe poliklinik
        $scopeUnitIds = collect();
        if (Schema::hasTable('units')) {
            if ($me->unit_id) {
                $scopeUnitIds = DB::table('units')->where('parent_id', $me->unit_id)->pluck('id');
            }
            if ($scopeUnitIds->isEmpty()) {
                $scopeUnitIds = DB::table('units')->where('type','poliklinik')->pluck('id');
            }
        }

        $filters = [
            'q'      => trim((string)$request->get('q','')),
            'status' => $request->get('status','pending'),
        ];

        $query = Weight::with(['unit:id,name','performanceCriteria:id,name,type'])
            ->when($scopeUnitIds->isNotEmpty(), fn($w) => $w->whereIn('unit_id', $scopeUnitIds));

        if ($filters['q'] !== '') {
            $q = $filters['q'];
            $query->where(function($w) use ($q) {
                $w->whereHas('unit', fn($u)=>$u->where('name','like',"%$q%"))
                  ->orWhereHas('performanceCriteria', fn($pc)=>$pc->where('name','like',"%$q%"));
            });
        }
        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        $items = $query->orderByDesc('id')->paginate(20)->withQueryString();

        return view('kepala_poli.unit_criteria_weights.index', compact('items','filters'));
    }

    public function approve(Request $request, Weight $weight)
    {
        // simple scope guard: pastikan unit dalam lingkup
        $me = Auth::user();
        $allowed = true;
        if (Schema::hasTable('units') && $me?->unit_id) {
            $allowed = DB::table('units')->where('parent_id', $me->unit_id)->where('id', $weight->unit_id)->exists();
        }
        if (!$allowed) return back()->withErrors(['msg'=>'Unit tidak dalam lingkup Anda.']);

    $weight->status = UCWStatus::ACTIVE;
        $weight->polyclinic_head_id = $me->id;
        $weight->save();
        return back()->with('status','Bobot kriteria disetujui.');
    }

    public function reject(Request $request, Weight $weight)
    {
        $data = $request->validate(['reason' => ['required','string','max:255']]);

        $me = Auth::user();
        $allowed = true;
        if (Schema::hasTable('units') && $me?->unit_id) {
            $allowed = DB::table('units')->where('parent_id', $me->unit_id)->where('id', $weight->unit_id)->exists();
        }
        if (!$allowed) return back()->withErrors(['msg'=>'Unit tidak dalam lingkup Anda.']);

    $weight->status = UCWStatus::REJECTED;
        $weight->polyclinic_head_id = $me->id;
        // Simpan alasan di policy_note agar tercatat
        $weight->policy_note = trim('Rejected: '.$data['reason']);
        $weight->save();
        return back()->with('status','Bobot kriteria ditolak.');
    }
}
