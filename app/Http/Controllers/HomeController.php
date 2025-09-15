<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\View\View;

// Models (pastikan ada dengan $table yang sesuai)
use App\Models\SiteSetting;
use App\Models\AssessmentPeriod;
use App\Models\Announcement;
use App\Models\Faq;
use App\Models\LogbookEntry;
use App\Models\DoctorSchedule;
use App\Models\User;

use Carbon\Carbon;

class HomeController extends Controller
{
    /**
     * Jika login: redirect ke dashboard sesuai role.
     * Jika guest: tampilkan landing publik dinamis.
     */
    public function index(): View|RedirectResponse
    {
        if (Auth::check()) {
            $role = Auth::user()->role;

            return match ($role) {
                'super_admin'  => redirect()->route('super_admin.dashboard'),
                'kepala_unit'  => redirect()->route('kepala_unit.dashboard'),
                'administrasi' => redirect()->route('administrasi.dashboard'),
                default        => redirect()->route('pegawai_medis.dashboard'),
            };
        }

        // ========== PUBLIC LANDING (GUEST) ==========
        Carbon::setLocale('id');

        // Site/Profile
        $site = Cache::remember('site.profile', 300, fn () => SiteSetting::query()->first());

        // Periode aktif
        $periodeAktif = Cache::remember('periode.aktif', 300, function () {
            return AssessmentPeriod::query()
                ->where('is_active', 1)
                ->orderByDesc('id')
                ->first();
        });

        // Quick stats
        $totalPegawai = Cache::remember('stat.total_pegawai', 300, fn () => User::count());

        // Rata-rata capaian (contoh: skor_total_wsm pada periode aktif)
        $capaianKinerja = Cache::remember('stat.capaian_kinerja', 300, function () use ($periodeAktif) {
            if (!$periodeAktif) return null;
            return DB::table('penilaian_kinerja')
                ->where('periode_penilaian_id', $periodeAktif->id)
                ->avg('skor_total_wsm'); // bisa null
        });

        // Total entri logbook (bisa difilter status jika perlu)
        $logbookTerisi = Cache::remember('stat.logbook_terisi', 300, fn () => LogbookEntry::query()->count());

        // Jadwal dokter besok (opsional untuk info tambahan di hero)
        $jadwalDokterBesok = Cache::remember('stat.jadwal_dokter_besok', 300, function () {
            $besok = Carbon::tomorrow()->toDateString();
            return DoctorSchedule::whereDate('tanggal', $besok)->count();
        });

        $stats = [
            [
                'icon'  => 'fa-users',
                'value' => number_format($totalPegawai, 0, ',', '.'),
                'label' => 'Total Pegawai',
            ],
            [
                'icon'  => 'fa-chart-line',
                'value' => $capaianKinerja !== null ? number_format($capaianKinerja, 1, ',', '.') . '%' : '—',
                'label' => 'Capaian Kinerja',
            ],
            [
                'icon'  => 'fa-calendar-check',
                'value' => $periodeAktif
                    ? ($this->formatBulanTahun($periodeAktif->tanggal_mulai) . ' – ' . $this->formatBulanTahun($periodeAktif->tanggal_akhir))
                    : '—',
                'label' => 'Periode Aktif',
            ],
            [
                'icon'  => 'fa-file-lines',
                'value' => number_format($logbookTerisi, 0, ',', '.'),
                'label' => 'Logbook Terisi',
            ],
        ];

        // Announcement terbaru (termasuk yang belum kedaluwarsa)
        $announcements = Cache::remember('pengumuman.terbaru', 300, function () {
            return Announcement::query()
                ->where(function ($q) {
                    $q->whereNull('kedaluwarsa_pada')
                        ->orWhere('kedaluwarsa_pada', '>=', now());
                })
                ->orderByDesc('dipublikasikan_pada')
                ->limit(6)
                ->get();
        });

        // FAQ aktif
        $faqs = Cache::remember('faq.aktif', 300, function () {
            return Faq::query()
                ->where('aktif', 1)
                ->orderBy('urutan')
                ->limit(10)
                ->get(['id', 'pertanyaan', 'jawaban', 'kategori']);
        });

        // Quick links (statis untuk sekarang; bisa ambil dari DB jika sudah ada tabelnya)
        $links = [
            ['icon' => 'fa-table',          'title' => 'Data Remuneration', 'desc' => 'Lihat data remunerasi pegawai', 'href' => route('data')],
            ['icon' => 'fa-clipboard-list', 'title' => 'Logbook Harian',  'desc' => 'Isi logbook kerja harian',     'href' => route('login') . '?redirect=logbook'],
            ['icon' => 'fa-chart-bar',      'title' => 'Laporan SKP',     'desc' => 'Lihat laporan kinerja SKP',     'href' => '#'],
            ['icon' => 'fa-download',       'title' => 'Unduh Formulir',  'desc' => 'Download formulir terbaru',      'href' => '#'],
        ];

        return view('welcome', compact(
            'site',
            'periodeAktif',
            'stats',
            'announcements',
            'faqs',
            'links',
            'jadwalDokterBesok'
        ));
    }

    private function formatBulanTahun($date): string
    {
        return $date ? Carbon::parse($date)->translatedFormat('F Y') : '—';
    }
}
