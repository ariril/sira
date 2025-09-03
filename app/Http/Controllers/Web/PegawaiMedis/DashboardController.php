<?php

namespace App\Http\Controllers\Web\PegawaiMedis;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $userId = Auth::id();

        $me = [
            'avg_rating_30d' => null,
            'total_review_30d' => 0,
            'recent_reviews' => collect(),
            'remunerasi_terakhir' => null,
            'nilai_kinerja_terakhir' => null,
            'jadwal_mendatang' => collect(),
        ];

        if (Schema::hasTable('ulasan_items')) {
            $from = now()->subDays(30);
            $me['avg_rating_30d'] = DB::table('ulasan_items')
                ->where('tenaga_medis_id',$userId)
                ->where('created_at','>=',$from)
                ->avg('rating');

            $me['total_review_30d'] = DB::table('ulasan_items')
                ->where('tenaga_medis_id',$userId)
                ->where('created_at','>=',$from)
                ->count();

            $me['recent_reviews'] = DB::table('ulasan_items as ui')
                ->join('ulasans as ul','ul.id','=','ui.ulasan_id')
                ->select('ui.rating','ui.komentar','ul.created_at')
                ->where('ui.tenaga_medis_id',$userId)
                ->orderByDesc('ul.created_at')->limit(8)->get();
        }

        if (Schema::hasTable('remunerasis')) {
            $me['remunerasi_terakhir'] = DB::table('remunerasis')
                ->where('user_id',$userId)
                ->orderByDesc('id')->first();
        }

        if (Schema::hasTable('penilaian_kinerjas')) {
            $me['nilai_kinerja_terakhir'] = DB::table('penilaian_kinerjas')
                ->where('user_id', $userId)
                ->orderByDesc('id')
                ->value('skor_total_wsm');
        }

        if (Schema::hasTable('jadwal_dokters')) {
            $me['jadwal_mendatang'] = DB::table('jadwal_dokters')
                ->where('user_id',$userId)
                ->where('tanggal','>=', now()->toDateString())
                ->orderBy('tanggal')->orderBy('jam_mulai')
                ->limit(10)->get();
        }

        return view('pegawai_medis.dashboard', compact('me'));
    }
}
