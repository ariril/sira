<?php

namespace App\Http\Controllers\Web\AdminHospital;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use App\Models\MultiRaterAssessment;

class MultiRaterController extends Controller
{
    public function index(Request $request)
    {
        $periodId = $request->integer('period_id');
        $stats = [];
        if ($periodId) {
            $stats = MultiRaterAssessment::selectRaw('status, COUNT(*) as total')
                ->where('assessment_period_id', $periodId)
                ->groupBy('status')
                ->pluck('total','status')
                ->toArray();
        }

        return view('admin_rs.multi_rater.index', [
            'periodId' => $periodId,
            'stats' => $stats,
        ]);
    }

    public function generate(Request $request)
    {
        $request->validate([
            'period_id' => 'required|integer|min:1',
            'reset' => 'sometimes|boolean',
        ]);

        Artisan::call('mra:generate', [
            'period_id' => (int) $request->input('period_id'),
            '--reset' => (bool) $request->boolean('reset'),
        ]);

        return redirect()
            ->route('admin_rs.multi_rater.index', ['period_id' => $request->input('period_id')])
            ->with('status', trim(Artisan::output()) ?: 'Undangan 360 berhasil diproses.');
    }
}
