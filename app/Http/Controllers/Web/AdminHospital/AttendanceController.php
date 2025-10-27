<?php

namespace App\Http\Controllers\Web\AdminHospital;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Unit;
use App\Enums\AttendanceStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    // Rekap Absensi (list + filter)
    public function index(Request $request): View
    {
        $filters = [
            'q'         => trim((string)$request->get('q', '')),
            'unit_id'   => $request->get('unit_id'),
            'status'    => $request->get('status'),
            'date_from' => $request->get('date_from'),
            'date_to'   => $request->get('date_to'),
        ];

        $query = Attendance::query()->with(['user:id,name,employee_number,unit_id','user.unit:id,name']);

        if ($filters['q'] !== '') {
            $q = $filters['q'];
            $query->whereHas('user', function($w) use ($q) {
                $w->where('name','like',"%$q%")
                  ->orWhere('employee_number','like',"%$q%");
            });
        }
        if ($filters['unit_id']) {
            $unitId = (int) $filters['unit_id'];
            $query->whereHas('user', fn($w) => $w->where('unit_id',$unitId));
        }
        if ($filters['status']) {
            $query->where('attendance_status', $filters['status']);
        }
        if ($filters['date_from']) {
            $query->whereDate('attendance_date','>=',$filters['date_from']);
        }
        if ($filters['date_to']) {
            $query->whereDate('attendance_date','<=',$filters['date_to']);
        }

        $items = $query->orderByDesc('attendance_date')->orderBy('user_id')
            ->paginate(20)->withQueryString();

        $units = Unit::orderBy('name')->pluck('name','id');
        $statuses = collect(AttendanceStatus::cases())->mapWithKeys(fn($c)=>[(string)$c->value => (string)$c->value]);

        return view('admin_rs.attendances.index', compact('items','filters','units','statuses'));
    }

    // Detail + edit sederhana
    public function show(Attendance $attendance): View
    {
        return view('admin_rs.attendances.show', [
            'item' => $attendance->load('user.unit'),
            'statuses' => collect(AttendanceStatus::cases())->mapWithKeys(fn($c)=>[(string)$c->value => (string)$c->value]),
        ]);
    }

    public function update(Request $request, Attendance $attendance): RedirectResponse
    {
        $data = $request->validate([
            'attendance_status' => ['required','string'],
            'overtime_note'     => ['nullable','string','max:255'],
            'check_in'          => ['nullable','date_format:Y-m-d H:i'],
            'check_out'         => ['nullable','date_format:Y-m-d H:i'],
        ]);
        $attendance->update($data);
        return back()->with('status','Data absensi diperbarui.');
    }

    public function destroy(Attendance $attendance): RedirectResponse
    {
        $attendance->delete();
        return back()->with('status','Data absensi dihapus.');
    }
}
