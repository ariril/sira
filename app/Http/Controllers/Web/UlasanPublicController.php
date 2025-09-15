<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Visit;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UlasanPublicController extends Controller
{
    /**
     * Tampilkan form ulasan (publik).
     * Terima query: ?ticket=ABC123&unit=7
     * Jika kamu punya daftar tenaga medis di form, render via Blade.
     */
    public function create(Request $request)
    {
        return view('pages.ulasan-form', [
            'ticket_code'  => $request->get('ticket'),
            'unit_kerja_id'=> $request->integer('unit'),
        ]);
    }

    /**
     * Simpan 1 ulasan dengan banyak item tenaga medis.
     * payload:
     *  - ticket_code (string, required)
     *  - unit_kerja_id (nullable, exists:unit_kerjas,id)
     *  - overall_rating (nullable, 1..5)
     *  - komentar (nullable)
     *  - nama_pasien, kontak (nullable)
     *  - items[]: [{tenaga_medis_id, peran?, rating, komentar?}, ...]
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'ticket_code'                   => ['required', 'string', 'max:100'],
            'unit_kerja_id'                 => ['nullable', 'exists:unit_kerjas,id'],
            'overall_rating'                => ['nullable', 'integer', 'between:1,5'],
            'komentar'                      => ['nullable', 'string', 'max:2000'],
            'nama_pasien'                   => ['nullable', 'string', 'max:100'],
            'kontak'                        => ['nullable', 'string', 'max:100'],

            'items'                         => ['required', 'array', 'min:1'],
            'items.*.tenaga_medis_id'       => ['required', 'exists:users,id'],
            'items.*.peran'                 => ['nullable', 'in:dokter,perawat,lainnya'],
            'items.*.rating'                => ['required', 'integer', 'between:1,5'],
            'items.*.komentar'              => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($request, $data) {
            // Buat / ambil kunjungan berdasarkan ticket_code
            $kunjungan = Visit::firstOrCreate(
                ['ticket_code' => $data['ticket_code']],
                [
                    'unit_kerja_id' => $data['unit_kerja_id'] ?? null,
                    'tanggal'       => now(),
                ]
            );

            // (opsional) cegah 2 ulasan untuk 1 ticket
            if (Review::where('kunjungan_id', $kunjungan->id)->exists()) {
                abort(422, 'Review untuk tiket ini sudah ada.');
            }

            $ulasan = Review::create([
                'kunjungan_id'   => $kunjungan->id,
                'overall_rating' => $data['overall_rating'] ?? null,
                'komentar'       => $data['komentar'] ?? null,
                'nama_pasien'    => $data['nama_pasien'] ?? null,
                'kontak'         => $data['kontak'] ?? null,
                'client_ip'      => $request->ip(),
                'user_agent'     => substr((string) $request->userAgent(), 0, 255),
            ]);

            // detail per tenaga medis
            $ulasan->items()->createMany($data['items']);
        });

        return redirect()->route('home')->with('status', 'Terima kasih atas ulasan Anda!');
    }
}
