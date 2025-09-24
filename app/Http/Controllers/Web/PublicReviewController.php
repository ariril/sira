<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\{Review, ReviewDetail, User, Unit};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PublicReviewController extends Controller
{
    /**
     * Tampilkan form ulasan (publik).
     * Terima query: ?ticket=ABC123&unit=7
     * Catatan: di DB, "registration_ref" dan "unit_id".
     */
    public function create(Request $request)
    {
        return view('pages.ulasan-form', [
            'ticket_code'  => $request->get('ticket'),           // dipertahankan utk UI lama
            'unit_kerja_id'=> $request->integer('unit'),         // dipertahankan utk UI lama
        ]);
    }

    /**
     * Simpan 1 ulasan.
     * Payload disesuaikan ke skema baru:
     *  - registration_ref (required, unique)
     *  - unit_id (nullable, exists:units,id)
     *  - overall_rating (nullable, 1..5)
     *  - comment (nullable)
     *  - patient_name, contact (nullable)
     *  - items (opsional):
     *      items[]: [{profession_id, rating, comment?}, ...]
     *      Jika TIDAK dikirim, sistem akan membuat stub review_details
     *      untuk setiap profesi yang ada di unit tsb (distinct dari users).
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'registration_ref'            => ['required', 'string', 'max:50', 'unique:reviews,registration_ref'],
            'unit_id'                     => ['nullable', 'exists:units,id'],
            'overall_rating'              => ['nullable', 'integer', 'between:1,5'],
            'comment'                     => ['nullable', 'string', 'max:2000'],
            'patient_name'                => ['nullable', 'string', 'max:255'],
            'contact'                     => ['nullable', 'string', 'max:255'],

            'items'                       => ['nullable', 'array', 'min:1'],
            'items.*.profession_id'       => ['required_with:items', 'exists:professions,id'],
            'items.*.rating'              => ['required_with:items', 'integer', 'between:1,5'],
            'items.*.comment'             => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($request, $data) {
            // Buat review utama
            $review = Review::create([
                'registration_ref' => $data['registration_ref'],
                'unit_id'          => $data['unit_id'] ?? null,
                'overall_rating'   => $data['overall_rating'] ?? null,
                'comment'          => $data['comment'] ?? null,
                'patient_name'     => $data['patient_name'] ?? null,
                'contact'          => $data['contact'] ?? null,
                'client_ip'        => $request->ip(),
                'user_agent'       => substr((string) $request->userAgent(), 0, 255),
            ]);

            // 1) Jika item rating per-profesi sudah dikirim => langsung simpan
            if (!empty($data['items'])) {
                $payload = collect($data['items'])->map(function ($it) use ($review) {
                    return [
                        'review_id'     => $review->id,
                        'profession_id' => $it['profession_id'],
                        'rating'        => (int) $it['rating'],
                        'comment'       => $it['comment'] ?? null,
                        'created_at'    => now(),
                        'updated_at'    => now(),
                    ];
                })->all();

                ReviewDetail::insert($payload);
                return;
            }

            // 2) Jika items tidak dikirim => generate stub per profesi di unit
            if ($review->unit_id) {
                $professionIds = User::query()
                    ->where('unit_id', $review->unit_id)
                    ->whereNotNull('profession_id')
                    ->distinct()
                    ->pluck('profession_id')
                    ->filter()
                    ->values();

                if ($professionIds->isNotEmpty()) {
                    $payload = $professionIds->map(fn ($pid) => [
                        'review_id'     => $review->id,
                        'profession_id' => $pid,
                        'rating'        => 0,      // boleh 0/null sesuai preferensi UI
                        'comment'       => null,
                        'created_at'    => now(),
                        'updated_at'    => now(),
                    ])->all();

                    ReviewDetail::insert($payload);
                }
            }
        });

        return redirect()->route('home')->with('status', 'Terima kasih atas ulasan Anda!');
    }
}
