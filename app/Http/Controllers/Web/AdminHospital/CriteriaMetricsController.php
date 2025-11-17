<?php

namespace App\Http\Controllers\Web\AdminHospital;

use App\Http\Controllers\Controller;
use App\Models\CriteriaMetric;
use App\Models\PerformanceCriteria;
use App\Models\AssessmentPeriod;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class CriteriaMetricsController extends Controller
{
    public function index(Request $request): View
    {
        $periodId = (int)$request->query('period_id');
        $items = CriteriaMetric::query()
            ->when($periodId, fn($w) => $w->where('assessment_period_id', $periodId))
            ->with(['user:id,name,employee_number','criteria:id,name','period:id,name'])
            ->orderByDesc('id')->paginate(15)->withQueryString();

        $periods = AssessmentPeriod::orderByDesc('start_date')->pluck('name','id');
        $criterias = PerformanceCriteria::where('is_active', true)->orderBy('name')->pluck('name','id');
        return view('admin_rs.metrics.index', compact('items','periods','criterias','periodId'));
    }

    public function create(): View
    {
        $periods = AssessmentPeriod::orderByDesc('start_date')->pluck('name','id');
        $criterias = PerformanceCriteria::where('is_active', true)->orderBy('name')->get(['id','name','input_method']);
        $users = User::orderBy('name')->get(['id','name','employee_number']);
        return view('admin_rs.metrics.create', compact('periods','criterias','users'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'assessment_period_id' => 'required|exists:assessment_periods,id',
            'performance_criteria_id' => 'required|exists:performance_criterias,id',
            'value_numeric' => 'nullable|numeric',
            'value_datetime' => 'nullable|date',
            'value_text' => 'nullable|string',
            'source_type' => 'nullable|in:system,manual,import',
        ]);
        CriteriaMetric::updateOrCreate(
            [
                'user_id' => $data['user_id'],
                'assessment_period_id' => $data['assessment_period_id'],
                'performance_criteria_id' => $data['performance_criteria_id'],
            ],
            [
                'value_numeric' => $data['value_numeric'] ?? null,
                'value_datetime'=> $data['value_datetime'] ?? null,
                'value_text'    => $data['value_text'] ?? null,
                'source_type'   => $data['source_type'] ?? 'manual',
                'source_table'  => 'form',
            ]
        );

        return redirect()->route('admin_rs.metrics.index')->with('status','Metric disimpan');
    }

    public function uploadCsv(Request $request): RedirectResponse
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt|max:5120']);
        $path = $request->file('file')->store('metric_uploads');

        $fh = fopen(Storage::path($path), 'r');
        $header = fgetcsv($fh);
        if (!$header) return back()->withErrors(['file' => 'CSV kosong']);
        $map = array_flip($header);
        foreach (['employee_number','assessment_period_id','performance_criteria_id','value_numeric'] as $k) {
            if (!isset($map[$k])) return back()->withErrors(['file' => "Kolom $k wajib ada"]);
        }
        $ok = 0; $skip = 0;
        while (($row = fgetcsv($fh)) !== false) {
            $emp = (string)($row[$map['employee_number']] ?? '');
            $periodId = (int)($row[$map['assessment_period_id']] ?? 0);
            $critId = (int)($row[$map['performance_criteria_id']] ?? 0);
            $value = $row[$map['value_numeric']] ?? null;
            $user = $emp !== '' ? User::where('employee_number',$emp)->first() : null;
            if (!$user || $periodId <= 0 || $critId <= 0) { $skip++; continue; }
            CriteriaMetric::updateOrCreate(
                ['user_id'=>$user->id,'assessment_period_id'=>$periodId,'performance_criteria_id'=>$critId],
                ['value_numeric'=>is_numeric($value)?(float)$value:null,'source_type'=>'import','source_table'=>'csv']
            );
            $ok++;
        }
        fclose($fh);
        return back()->with('status',"Import selesai: {$ok} baris, dilewati {$skip}.");
    }
}
