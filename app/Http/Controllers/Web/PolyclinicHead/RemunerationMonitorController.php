<?php

namespace App\Http\Controllers\Web\PolyclinicHead;

use App\Http\Controllers\Controller;
use App\Models\Remuneration;
use Illuminate\Contracts\Pagination\LengthAwarePaginator as LengthAwarePaginatorContract;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class RemunerationMonitorController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): View
    {
        $this->authorizeAccess();

        // Filters
        $perPageOptions = [5, 10, 12, 20, 30, 50];
        $data = $request->validate([
            'q'         => ['nullable','string','max:100'],
            'period_id' => ['nullable','integer'],
            'unit_id'   => ['nullable','integer'],
            'per_page'  => ['nullable','integer','in:' . implode(',', $perPageOptions)],
        ]);
        $q        = (string)($data['q'] ?? '');
        $periodId = $data['period_id'] ?? null;
        $unitId   = $data['unit_id'] ?? null;
        $perPage  = (int) ($data['per_page'] ?? 10);

        // Scope units under Kepala Poliklinik
        $me = Auth::user();
        $scopeUnitIds = collect();
        if (Schema::hasTable('units')) {
            if ($me && $me->unit_id) {
                $scopeUnitIds = DB::table('units')->where('parent_id', $me->unit_id)->pluck('id');
            }
            if ($scopeUnitIds->isEmpty()) {
                $scopeUnitIds = DB::table('units')->where('type', 'poliklinik')->pluck('id');
            }
        }

        // Fetch options
        $periods = collect();
        $units   = collect();
        if (Schema::hasTable('assessment_periods')) {
            $periods = DB::table('assessment_periods')
                ->orderByDesc(DB::raw("status = 'active'"))
                ->orderByDesc('id')->get();
        }
        if (Schema::hasTable('units')) {
            $units = DB::table('units')
                ->when($scopeUnitIds->isNotEmpty(), fn($q) => $q->whereIn('id', $scopeUnitIds))
                ->orderBy('name')->get();
        }

        // Build listing
        if (Schema::hasTable('remunerations') && Schema::hasTable('users') && Schema::hasTable('assessment_periods')) {
            $builder = DB::table('remunerations as r')
                ->join('users as u', 'u.id', '=', 'r.user_id')
                ->leftJoin('assessment_periods as ap', 'ap.id', '=', 'r.assessment_period_id')
                ->leftJoin('units as un', 'un.id', '=', 'u.unit_id')
                ->selectRaw('r.id, r.amount, r.published_at, r.payment_status, ap.name as period_name, u.name as user_name, un.name as unit_name, u.unit_id')
                ->orderByDesc('r.id');

            if ($scopeUnitIds->isNotEmpty()) {
                $builder->whereIn('u.unit_id', $scopeUnitIds);
            }
            if (!empty($q)) {
                $builder->where(function($w) use ($q) {
                    $w->where('u.name', 'like', "%$q%")
                      ->orWhere('ap.name', 'like', "%$q%")
                      ->orWhere('un.name', 'like', "%$q%");
                });
            }
            if (!empty($periodId)) {
                $builder->where('r.assessment_period_id', (int) $periodId);
            }
            if (!empty($unitId)) {
                $builder->where('u.unit_id', (int) $unitId);
            }

            $items = $builder->paginate($perPage)->withQueryString();
        } else {
            $items = new LengthAwarePaginator(
                collect(),
                0,
                $perPage,
                (int) $request->integer('page', 1),
                [
                    'path' => $request->url(),
                    'query' => $request->query(),
                ]
            );
        }

        return view('kepala_poli.remunerations.index', [
            'items'   => $items,
            'periods' => $periods,
            'units'   => $units,
            'periodId'=> $periodId,
            'unitId'  => $unitId,
            'q'       => $q,
            'perPage' => $perPage,
            'perPageOptions' => $perPageOptions,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Remuneration $remuneration): View
    {
        $this->authorizeAccess();
        // Scope guard: remuneration's user must be in allowed units
        $me = Auth::user();
        $scopeUnitIds = collect();
        if (Schema::hasTable('units')) {
            if ($me && $me->unit_id) {
                $scopeUnitIds = DB::table('units')->where('parent_id', $me->unit_id)->pluck('id');
            }
            if ($scopeUnitIds->isEmpty()) {
                $scopeUnitIds = DB::table('units')->where('type', 'poliklinik')->pluck('id');
            }
        }
        $userUnitId = DB::table('users')->where('id', $remuneration->user_id)->value('unit_id');
        if ($scopeUnitIds->isNotEmpty() && !$scopeUnitIds->contains($userUnitId)) abort(403);

        $remuneration->load(['user.unit','assessmentPeriod']);
        return view('kepala_poli.remunerations.show', [
            'item' => $remuneration,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id) {}

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id) {}

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id) {}

    private function authorizeAccess(): void
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'kepala_poliklinik') abort(403);
    }
}
