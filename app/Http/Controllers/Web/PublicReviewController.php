<?php

namespace App\Http\Controllers\Web;

use App\Enums\ReviewStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PublicReviewController extends Controller
{
    public function create(Request $request): View
    {
        $units = DB::table('units')
            ->where('type', 'poliklinik')
            ->orderBy('name')
            ->get(['id','name']);

        $staffOptions = User::query()
            ->role(User::ROLE_PEGAWAI_MEDIS)
            ->with(['unit:id,name', 'profession:id,name'])
            ->orderBy('name')
            ->get()
            ->map(function ($user) {
                return [
                    'id'         => $user->id,
                    'name'       => $user->name,
                    'unit_id'    => $user->unit_id,
                    'unit_name'  => $user->unit->name ?? null,
                    'profession' => $user->profession->name ?? null,
                ];
            });

        return view('pages.reviews.create', [
            'units'        => $units,
            'staffOptions' => $staffOptions,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $detailsInput = $request->input('details', []);
        $unitId = $request->integer('unit_id');

        $validator = Validator::make($request->all(), [
            'registration_ref' => 'required|string|max:50|unique:reviews,registration_ref',
            'unit_id'          => [
                'required',
                Rule::exists('units', 'id')->where(fn ($q) => $q->where('type', 'poliklinik')),
            ],
            'patient_name'     => 'nullable|string|max:200',
            'contact'          => 'nullable|string|max:100',
            'comment'          => 'required|string|min:5|max:2000',
            'details'          => 'required|array|min:1',
            'details.*.staff_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where(function ($query) {
                    $query->whereExists(function ($sub) {
                        $sub->select(DB::raw(1))
                            ->from('role_user as ru')
                            ->join('roles as r', 'r.id', '=', 'ru.role_id')
                            ->whereColumn('ru.user_id', 'users.id')
                            ->where('r.slug', User::ROLE_PEGAWAI_MEDIS);
                    });
                }),
            ],
            'details.*.rating'  => 'required|integer|min:1|max:5',
            'details.*.comment' => 'nullable|string|max:2000',
        ], [
            'registration_ref.required' => 'Nomor rawat medis wajib diisi.',
            'registration_ref.unique'   => 'Nomor rawat medis tersebut sudah pernah memberikan ulasan.',
            'unit_id.required'          => 'Silakan pilih poliklinik terlebih dahulu.',
            'details.required'          => 'Tambahkan minimal satu pegawai untuk diulas.',
            'details.*.staff_id.required' => 'Pilih pegawai pada setiap ulasan.',
            'details.*.rating.required'   => 'Tetapkan rating bintang untuk setiap pegawai.',
        ]);

        $validator->after(function ($validator) use ($detailsInput, $unitId) {
            if (!is_array($detailsInput) || empty($detailsInput)) {
                return;
            }

            $staffIds = collect($detailsInput)->pluck('staff_id')->filter()->all();
            if (count($staffIds) !== count(array_unique($staffIds))) {
                $validator->errors()->add('details', 'Setiap pegawai hanya boleh diulas satu kali.');
            }

            if ($unitId && !empty($staffIds)) {
                $mismatch = DB::table('users')
                    ->whereIn('id', $staffIds)
                    ->where('unit_id', '!=', $unitId)
                    ->count();

                if ($mismatch > 0) {
                    $validator->errors()->add('details', 'Pegawai yang dipilih harus berasal dari poliklinik yang sama.');
                }
            }
        });

        $validator->validateWithBag('reviewForm');

        $now = Carbon::now();

        $detailsCollection = collect($detailsInput)->map(fn ($row) => [
            'staff_id' => (int) ($row['staff_id'] ?? 0),
            'rating'   => (int) ($row['rating'] ?? 0),
            'comment'  => $row['comment'] ?? null,
        ]);

        $avgRating = (int) round(max(1, $detailsCollection->avg(fn ($row) => $row['rating']) ?: 0));

        $reviewId = DB::table('reviews')->insertGetId([
            'registration_ref' => $request->input('registration_ref'),
            'unit_id'          => $unitId,
            'overall_rating'   => $avgRating,
            'comment'          => (string) $request->input('comment'),
            'patient_name'     => $request->get('patient_name'),
            'contact'          => $request->get('contact'),
            'client_ip'        => $request->ip(),
            'user_agent'       => substr((string) $request->header('User-Agent'), 0, 255),
            'status'           => ReviewStatus::PENDING->value,
            'created_at'       => $now,
            'updated_at'       => $now,
        ]);

        // Helper untuk mapping role per staf (dokter/perawat)
        $roleFor = function ($professionName) {
            $name = strtolower((string)$professionName);
            if (str_contains($name, 'dokter')) return 'dokter';
            if (str_contains($name, 'perawat')) return 'perawat';
            return 'lainnya';
        };

        $staffMeta = DB::table('users as u')
            ->leftJoin('professions as p', 'p.id', '=', 'u.profession_id')
            ->whereIn('u.id', $detailsCollection->pluck('staff_id')->all())
            ->get(['u.id', 'p.name as profesi']);

        $detailPayload = $detailsCollection->map(function ($detail) use ($reviewId, $roleFor, $staffMeta, $request, $now) {
            $meta = $staffMeta->firstWhere('id', $detail['staff_id']);
            return [
                'review_id'        => $reviewId,
                'medical_staff_id' => $detail['staff_id'],
                'role'             => $roleFor($meta->profesi ?? null),
                'rating'           => $detail['rating'],
                'comment'          => $detail['comment'] ?: (string) $request->input('comment'),
                'created_at'       => $now,
                'updated_at'       => $now,
            ];
        })->all();

        if (!empty($detailPayload)) {
            DB::table('review_details')->insert($detailPayload);
        }

        return back()->with('status','Terima kasih, ulasan Anda telah direkam.');
    }
}

