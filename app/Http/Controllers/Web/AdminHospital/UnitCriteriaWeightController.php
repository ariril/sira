<?php

namespace App\Http\Controllers\Web\AdminHospital;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class UnitCriteriaWeightController extends Controller
{
    public function index(): View { return view('admin_rs.unit_criteria_weights.index'); }
    public function create(): View { return view('admin_rs.unit_criteria_weights.index'); }
    public function store(Request $request): RedirectResponse { return back()->with('status','Belum diimplementasikan'); }
    public function show(string $id): View { return view('admin_rs.unit_criteria_weights.index'); }
    public function edit(string $id): View { return view('admin_rs.unit_criteria_weights.index'); }
    public function update(Request $request, string $id): RedirectResponse { return back()->with('status','Belum diimplementasikan'); }
    public function destroy(string $id): RedirectResponse { return back()->with('status','Belum diimplementasikan'); }

    public function publishDraft(): RedirectResponse { return back()->with('status','Belum diimplementasikan'); }
}
