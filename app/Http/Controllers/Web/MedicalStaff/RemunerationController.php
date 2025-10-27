<?php

namespace App\Http\Controllers\Web\MedicalStaff;

use App\Http\Controllers\Controller;
use App\Models\Remuneration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RemunerationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): View
    {
        $items = Remuneration::with('assessmentPeriod')
            ->where('user_id', Auth::id())
            ->orderByDesc('id')
            ->paginate(12)
            ->withQueryString();

        return view('pegawai_medis.remunerations.index', [
            'items' => $items,
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Remuneration $id): View
    {
        $remuneration = $id; // implicit model binding
        abort_unless($remuneration->user_id === Auth::id(), 403);
        $remuneration->load(['assessmentPeriod']);
        return view('pegawai_medis.remunerations.show', [
            'item' => $remuneration,
        ]);
    }
}
