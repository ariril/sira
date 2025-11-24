<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Carbon\Carbon;

class PublicReviewController extends Controller
{
    public function create(Request $request): View
    {
        $units = DB::table('units')->orderBy('name')->get(['id','name']);
        // Pivot-based filter: semua user dengan role pegawai_medis
        $staff = \App\Models\User::query()
            ->role('pegawai_medis')
            ->orderBy('name')
            ->get(['id','name','unit_id']);

        return view('pages.reviews.create', compact('units','staff'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->all();

        Validator::make($data, [
            'type'      => 'required|in:individual,overall',
            'unit_id'   => 'required_if:type,overall|nullable|exists:units,id',
            'staff_id'  => 'required_if:type,individual|nullable|exists:users,id',
            'rating'    => 'required|integer|min:1|max:5',
            'comment'   => 'required|string|min:5|max:2000',
            'patient_name' => 'nullable|string|max:200',
            'contact'      => 'nullable|string|max:100',
        ], [
            'unit_id.required_if'  => 'Silakan pilih unit untuk ulasan keseluruhan.',
            'staff_id.required_if' => 'Silakan pilih pegawai untuk ulasan per orang.',
        ])->validate();

        $now = Carbon::now();
        $reviewId = DB::table('reviews')->insertGetId([
            'unit_id'       => $data['type']==='overall' ? $request->integer('unit_id') : null,
            'overall_rating'=> (int)$data['rating'],
            'comment'       => (string)$data['comment'],
            'patient_name'  => $request->get('patient_name'),
            'contact'       => $request->get('contact'),
            'client_ip'     => $request->ip(),
            'user_agent'    => substr((string)$request->header('User-Agent'),0,255),
            'created_at'    => $now,
            'updated_at'    => $now,
        ]);

        // Helper untuk mapping role per staf (dokter/perawat)
        $roleFor = function ($professionName) {
            $name = strtolower((string)$professionName);
            if (str_contains($name, 'dokter')) return 'dokter';
            if (str_contains($name, 'perawat')) return 'perawat';
            return 'nakes';
        };

        if ($data['type'] === 'individual') {
            $staff = DB::table('users as u')
                ->leftJoin('professions as p', 'p.id', '=', 'u.profession_id')
                ->where('u.id', $request->integer('staff_id'))
                ->first(['u.id','p.name as profesi']);

            if ($staff) {
                DB::table('review_details')->insert([
                    'review_id'        => $reviewId,
                    'medical_staff_id' => $staff->id,
                    'role'             => $roleFor($staff->profesi),
                    'rating'           => (int)$data['rating'],
                    'comment'          => (string)$data['comment'],
                    'created_at'       => $now,
                    'updated_at'       => $now,
                ]);
            }
        } else {
            // overall: distribusikan ke semua pegawai medis pada unit terpilih
            $unitId = $request->integer('unit_id');
            $staffs = DB::table('users as u')
                ->leftJoin('professions as p', 'p.id', '=', 'u.profession_id')
                ->where('u.unit_id', $unitId)
                ->whereExists(function($q){
                    $q->select(DB::raw(1))
                      ->from('role_user as ru')
                      ->join('roles as r','r.id','=','ru.role_id')
                      ->whereColumn('ru.user_id','u.id')
                      ->where('r.slug','pegawai_medis');
                })
                ->get(['u.id','p.name as profesi']);

            foreach ($staffs as $s) {
                DB::table('review_details')->insert([
                    'review_id'        => $reviewId,
                    'medical_staff_id' => $s->id,
                    'role'             => $roleFor($s->profesi),
                    'rating'           => (int)$data['rating'],
                    'comment'          => (string)$data['comment'],
                    'created_at'       => $now,
                    'updated_at'       => $now,
                ]);
            }
        }

        return back()->with('status','Terima kasih, ulasan Anda telah direkam.');
    }
}

