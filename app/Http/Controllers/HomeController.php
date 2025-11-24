<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Schema;

use App\Models\SiteSetting;             // site_settings
use App\Models\AssessmentPeriod;        // assessment_periods
use App\Models\Announcement;            // announcements
use App\Models\Faq;                     // faqs
use App\Models\AdditionalContribution;  // additional_contributions
use App\Models\PerformanceAssessment;   // performance_assessments
use App\Models\User;                    // users

use Carbon\Carbon;

class HomeController extends Controller
{
    public function index(): View|RedirectResponse
    {
        if (Auth::check()) {
            $role = session('active_role') ?: Auth::user()->getActiveRoleSlug();

            return match ($role) {
                'super_admin'        => redirect()->route('super_admin.dashboard'),
                'kepala_unit'        => redirect()->route('kepala_unit.dashboard'),
                'kepala_poliklinik'  => redirect()->route('kepala_poliklinik.dashboard'),
                'admin_rs'           => redirect()->route('admin_rs.dashboard'),
                default              => redirect()->route('pegawai_medis.dashboard'),
            };
        }

        // ========== PUBLIC LANDING (GUEST) ==========
        Carbon::setLocale('id');

        // 1) Site/Profile (toleran saat tabel belum ada di lingkungan test)
        $site = Cache::remember('site.profile', 300, function () {
            if (!Schema::hasTable('site_settings')) {
                return null;
            }
            return SiteSetting::query()->first();
        });

        // 2) Periode aktif
        if (Schema::hasTable('assessment_periods')) {
            // Sinkronkan status otomatis berdasarkan tanggal sekarang saat halaman diakses
            AssessmentPeriod::syncByNow();
        }
        $periodeAktif = Cache::remember('periode.aktif', 300, function () {
            if (!Schema::hasTable('assessment_periods')) {
                return null;
            }
            return AssessmentPeriod::query()
                ->where('status', 'active')
                ->orderByDesc('id')
                ->first();
        });

        // 3) Quick stats
        $totalPegawai = Cache::remember('stat.total_pegawai', 300, function () {
            return Schema::hasTable('users') ? User::count() : 0;
        });

        // 3a) Rata-rata capaian (avg performance_assessments.total_wsm_score pada periode aktif)
        $capaianKinerja = Cache::remember('stat.capaian_kinerja', 300, function () use ($periodeAktif) {
            if (!$periodeAktif) return null;

            return PerformanceAssessment::query()
                ->where('assessment_period_id', $periodeAktif->id)
                ->avg('total_wsm_score'); // decimal|null
        });

        // 3b) Pengganti "Logbook Terisi": jumlah additional_contributions pada periode aktif
        $logbookTerisi = Cache::remember('stat.logbook_terisi', 300, function () use ($periodeAktif) {
            if (!Schema::hasTable('additional_contributions')) {
                return 0;
            }
            $q = AdditionalContribution::query();
            if ($periodeAktif) $q->where('assessment_period_id', $periodeAktif->id);
            return $q->count();
        });

        // Catatan: Sistem tidak mencatat jadwal tenaga medis, jadi tidak ada statistik "Jadwal Besok" di beranda.

        $stats = [
            [
                'icon'  => 'fa-users',
                'value' => number_format($totalPegawai, 0, ',', '.'),
                'label' => 'Total Pegawai',
            ],
            [
                'icon'  => 'fa-chart-line',
                'value' => $capaianKinerja !== null
                    ? number_format((float)$capaianKinerja, 1, ',', '.')
                    : '—',
                'label' => 'Capaian Kinerja',
            ],
            [
                'icon'  => 'fa-calendar-days',
                'value' => $periodeAktif
                    ? ($periodeAktif->name
                        ?: ($this->formatBulanTahun($periodeAktif->start_date ?: $periodeAktif->end_date)))
                    : '—',
                'label' => 'Periode Aktif',
            ],
            [
                'icon'  => 'fa-circle-plus',
                'value' => number_format($logbookTerisi, 0, ',', '.'),
                'label' => 'Kontribusi Tambahan',
            ],
        ];

        // 4) Announcements terbaru (published_at / expired_at)
        $announcements = Cache::remember('pengumuman.terbaru', 300, function () {
            if (!Schema::hasTable('announcements')) {
                return collect();
            }
            return Announcement::query()
                ->where(function ($q) {
                    $q->whereNull('expired_at')
                        ->orWhere('expired_at', '>=', now());
                })
                ->orderByDesc('published_at')
                ->limit(6)
                ->get();
        });

        // 5) FAQ aktif
        $faqs = Cache::remember('faq.aktif', 300, function () {
            if (!Schema::hasTable('faqs')) {
                return collect();
            }
            return Faq::query()
                ->where('is_active', 1)
                ->orderBy('order')
                ->limit(10)
                ->get(['id','question','answer','category']);
        });

        // 6) Quick links (tanpa akses data remunerasi publik)
        $links = [
            ['icon' => 'fa-bullhorn',   'title' => 'Pengumuman',    'desc' => 'Lihat informasi terbaru',   'href' => route('announcements.index')],
            ['icon' => 'fa-comment',    'title' => 'Berikan Ulasan','desc' => 'Sampaikan masukan Anda',     'href' => route('reviews.create')],
            ['icon' => 'fa-circle-question','title' => 'FAQ',        'desc' => 'Pertanyaan yang sering diajukan', 'href' => route('faqs.index')],
            ['icon' => 'fa-phone',      'title' => 'Kontak',        'desc' => 'Alamat & nomor telepon',    'href' => route('contact')],
        ];

        return view('welcome', compact(
            'site',
            'periodeAktif',
            'stats',
            'announcements',
            'faqs',
            'links'
        ));
    }

    private function formatBulanTahun($date): string
    {
        return $date ? Carbon::parse($date)->translatedFormat('F Y') : '—';
    }
}
