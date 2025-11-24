<?php

namespace App\Http\Controllers\Web\AdminHospital;

use App\Http\Controllers\Controller;
use App\Models\AssessmentPeriod;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AssessmentPeriodController extends Controller
{
    protected function perPageOptions(): array { return [5, 10, 12, 20, 30, 50]; }

    public function index(Request $request): View
    {
        // Sinkronkan status & periode aktif berdasarkan tanggal sekarang
        AssessmentPeriod::syncByNow();

        $perPageOptions = $this->perPageOptions();
        $data = $request->validate([
            'q'        => ['nullable','string','max:100'],
            'status'   => ['nullable','in:draft,active,locked,closed'],
            'per_page' => ['nullable','integer','in:' . implode(',', $perPageOptions)],
        ]);

        $q       = $data['q'] ?? null;
        $status  = $data['status'] ?? null;
        $perPage = (int)($data['per_page'] ?? 12);

        $items = AssessmentPeriod::query()
            ->when($q, function($w) use($q){
                $w->where('name','like',"%{$q}%");
            })
            ->when($status, fn($w) => $w->where('status', $status))
            ->orderByDesc('start_date')
            ->paginate($perPage)
            ->withQueryString();

        return view('admin_rs.assessment_periods.index', [
            'items'          => $items,
            'perPage'        => $perPage,
            'perPageOptions' => $perPageOptions,
            'filters'        => [
                'q'      => $q,
                'status' => $status,
            ],
        ]);
    }

    public function create(): View
    {
        return view('admin_rs.assessment_periods.create', [
            'item' => new AssessmentPeriod(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);
        // pastikan tidak ada injeksi status / is_active dari form
        unset($data['status']);
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
        unset($data['status']);
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
        // Boleh aktifkan ulang selama periode belum ditutup dan belum lewat dari tanggal selesai
        $today = Carbon::today();
        if ($period->status === AssessmentPeriod::STATUS_CLOSED) {
            return back()->withErrors(['status' => 'Periode yang sudah ditutup tidak dapat diaktifkan.']);
        }
        if ($period->end_date && $today->gt($period->end_date)) {
            return back()->withErrors(['status' => 'Periode sudah berakhir sehingga tidak dapat diaktifkan.']);
        }
        $period->activate(auth()->id());
        return back()->with('status','Periode diaktifkan.');
    }

    public function lock(AssessmentPeriod $period): RedirectResponse
    {
        // Hanya dari status Active ke Locked
        if ($period->status !== AssessmentPeriod::STATUS_ACTIVE) {
            return back()->withErrors(['status' => 'Periode harus dalam status Aktif sebelum dapat dikunci.']);
        }
        $period->lock(auth()->id());
        return back()->with('status','Periode dikunci.');
    }

    public function close(AssessmentPeriod $period): RedirectResponse
    {
        // Tutup hanya dari Locked dan setelah tanggal selesai
        $today = Carbon::today();
        if ($period->status !== AssessmentPeriod::STATUS_LOCKED) {
            return back()->withErrors(['status' => 'Tutup hanya bisa dilakukan setelah periode dikunci.']);
        }
        if ($period->end_date && $today->lt($period->end_date)) {
            return back()->withErrors(['status' => 'Periode belum berakhir. Tutup setelah tanggal selesai.']);
        }
        $period->close(auth()->id());
        return back()->with('status','Periode ditutup.');
    }

    protected function validateData(Request $request, bool $isUpdate = false, ?AssessmentPeriod $current = null): array
    {
        $uniqueName = Rule::unique('assessment_periods','name');
        if ($isUpdate && $current) { $uniqueName = $uniqueName->ignore($current->id); }

        $rules = [
            'name'       => ['required','string','max:255',$uniqueName],
            'start_date' => ['required','date'],
            'end_date'   => ['required','date','after_or_equal:start_date'],
        ];

        $validator = Validator::make($request->all(), $rules);

        // Cek tidak boleh overlap dengan periode lain
        $validator->after(function ($v) use ($request, $isUpdate, $current) {
            try {
                $start = Carbon::parse($request->input('start_date'))->toDateString();
                $end   = Carbon::parse($request->input('end_date'))->toDateString();
            } catch (\Throwable $e) {
                return; // format tanggal salah akan tertangkap di rules di atas
            }

            $query = AssessmentPeriod::query()
                ->whereDate('start_date', '<=', $end)
                ->whereDate('end_date', '>=', $start);
            if ($isUpdate && $current) {
                $query->where('id', '!=', $current->id);
            }
            if ($query->exists()) {
                $v->errors()->add('start_date', 'Tanggal periode bersinggungan dengan periode lain. Harap pilih rentang yang berbeda.');
            }
        });

        return $validator->validate();
    }
}
