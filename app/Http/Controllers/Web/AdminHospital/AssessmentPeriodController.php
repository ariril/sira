<?php

namespace App\Http\Controllers\Web\AdminHospital;

use App\Http\Controllers\Controller;
use App\Models\AssessmentPeriod;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AssessmentPeriodController extends Controller
{
    protected function perPageOptions(): array { return [5, 10, 12, 20, 30, 50]; }

    public function index(Request $request): View
    {
        $perPageOptions = $this->perPageOptions();
        $data = $request->validate([
            'q'        => ['nullable','string','max:100'],
            'active'   => ['nullable','in:yes,no'],
            'locked'   => ['nullable','in:yes,no'],
            'per_page' => ['nullable','integer','in:' . implode(',', $perPageOptions)],
        ]);

        $q       = $data['q'] ?? null;
        $active  = $data['active'] ?? null;
        $locked  = $data['locked'] ?? null;
        $perPage = (int)($data['per_page'] ?? 12);

        $items = AssessmentPeriod::query()
            ->when($q, function($w) use($q){
                $w->where('name','like',"%{$q}%");
            })
            ->when($active === 'yes', fn($w) => $w->where('is_active', true))
            ->when($active === 'no',  fn($w) => $w->where('is_active', false))
            ->when($locked === 'yes', fn($w) => $w->whereNotNull('locked_at'))
            ->when($locked === 'no',  fn($w) => $w->whereNull('locked_at'))
            ->orderByDesc('start_date')
            ->paginate($perPage)
            ->withQueryString();

        return view('admin_rs.assessment_periods.index', [
            'items'          => $items,
            'perPage'        => $perPage,
            'perPageOptions' => $perPageOptions,
            'filters'        => [
                'q'      => $q,
                'active' => $active,
                'locked' => $locked,
            ],
        ]);
    }

    public function create(): View
    {
        return view('admin_rs.assessment_periods.create', [
            'item' => new AssessmentPeriod([
                'is_active' => false,
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);
        $data['is_active'] = (bool)($data['is_active'] ?? false);
        $period = AssessmentPeriod::create($data);
        return redirect()->route('admin_rs.assessment-periods.index')->with('status','Periode dibuat.');
    }

    public function edit(AssessmentPeriod $period): View
    {
        return view('admin_rs.assessment_periods.edit', [
            'item' => $period,
        ]);
    }

    public function update(Request $request, AssessmentPeriod $period): RedirectResponse
    {
        $data = $this->validateData($request, isUpdate: true, current: $period);
        $data['is_active'] = (bool)($data['is_active'] ?? false);
        $period->update($data);
        return redirect()->route('admin_rs.assessment-periods.index')->with('status','Periode diperbarui.');
    }

    public function destroy(AssessmentPeriod $period): RedirectResponse
    {
        if ($period->performanceAssessments()->exists() || $period->remunerations()->exists() || $period->additionalContributions()->exists()) {
            return back()->withErrors(['delete' => 'Tidak dapat menghapus: periode sudah memiliki data terkait.']);
        }
        $period->delete();
        return back()->with('status','Periode dihapus.');
    }

    public function activate(AssessmentPeriod $period): RedirectResponse
    {
        // Matikan periode lain jika kolom is_active ada
        if (Schema::hasColumn('assessment_periods','is_active')) {
            AssessmentPeriod::where('id','!=',$period->id)->update(['is_active' => false]);
            $period->is_active = true;
        }
        // Ubah status jika kolom status ada
        if (Schema::hasColumn('assessment_periods','status')) {
            $period->status = 'active';
        }
        // Unlock jika ada locked_at
        if (Schema::hasColumn('assessment_periods','locked_at')) {
            $period->locked_at = null;
        }
        $period->save();
        return back()->with('status','Periode diaktifkan.');
    }

    public function lock(AssessmentPeriod $period): RedirectResponse
    {
        if (Schema::hasColumn('assessment_periods','locked_at')) {
            $period->locked_at = now();
        }
        if (Schema::hasColumn('assessment_periods','status')) {
            $period->status = 'locked';
        }
        if (Schema::hasColumn('assessment_periods','is_active')) {
            $period->is_active = false;
        }
        $period->save();
        return back()->with('status','Periode dikunci.');
    }

    protected function validateData(Request $request, bool $isUpdate = false, ?AssessmentPeriod $current = null): array
    {
        $uniqueName = Rule::unique('assessment_periods','name');
        if ($isUpdate && $current) { $uniqueName = $uniqueName->ignore($current->id); }
        return $request->validate([
            'name'       => ['required','string','max:255',$uniqueName],
            'start_date' => ['required','date'],
            'end_date'   => ['required','date','after_or_equal:start_date'],
            'cycle'      => ['nullable','string','max:50'],
            'status'     => ['nullable','string','max:50'],
            'is_active'  => ['nullable','boolean'],
        ]);
    }
}
