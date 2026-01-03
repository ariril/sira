<?php

namespace App\Http\Controllers\Web\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Unit;
use App\Models\Profession;
use App\Models\AssessmentPeriod;
use App\Models\SiteSetting;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        // ====== RINGKASAN USER ======
        $stats = [
            'total_user' => User::count(),
            'total_unit' => Unit::count(),
            'total_profesi' => Profession::count(),
            'unverified' => User::whereNull('email_verified_at')->count(),
        ];

        $userDistribution = [
            'pegawai_medis'     => User::role('pegawai_medis')->count(),
            'kepala_unit'       => User::role('kepala_unit')->count(),
            'kepala_poliklinik' => User::role('kepala_poliklinik')->count(),
            'admin_rs'          => User::role('admin_rs')->count(),
            'super_admin'       => User::role('super_admin')->count(),
        ];

        // ====== RINGKASAN SISTEM ======
        $sysSummary = [
            'app_name' => Config::get('app.name'),
            'env' => Config::get('app.env'),
            'debug' => Config::get('app.debug'),
            'php' => PHP_VERSION,
            'laravel' => App::version(),
            'timezone' => Config::get('app.timezone'),
            'queue' => Config::get('queue.default'),
            'cache' => Config::get('cache.default'),
        ];

        // ====== HEALTH CHECKS ======
        // App key
        $appKeyOk = filled(Config::get('app.key'));

        // DB connection
        $dbOk = false;
        try {
            DB::select('select 1 as ok');
            $dbOk = true;
        } catch (\Throwable $e) {
            $dbOk = false;
        }

        // Cache check
        $cacheOk = false;
        try {
            Cache::put('health_check', 'ok', 10);
            $cacheOk = Cache::get('health_check') === 'ok';
        } catch (\Throwable $e) {
            $cacheOk = false;
        }

        // Storage writable
        $storageOk = is_writable(storage_path('app'));

        $sysChecks = [
            'app_key' => $appKeyOk,
            'database' => $dbOk,
            'cache' => $cacheOk,
            'storage_writable' => $storageOk,
        ];

        // ====== SITE CONFIG ======
        $site = SiteSetting::query()->latest('updated_at')->first();
        $siteConfig = [
            'exists' => (bool) $site,
            'name' => $site->name ?? null,
            'email' => $site->email ?? null,
            'address' => $site->address ?? null,
            'logo' => $site->logo_path ?? null,
        ];
        $siteConfig['missing'] = collect([
            'name' => $siteConfig['name'],
            'email' => $siteConfig['email'],
            'address' => $siteConfig['address'],
            'logo' => $siteConfig['logo'],
        ])->filter(fn ($v) => blank($v))->keys()->values()->all();

        // ====== NOTIFICATIONS (actionable only) ======
        $notifications = [];

        if (!$sysChecks['app_key']) {
            $notifications[] = [
                'type' => 'error',
                'text' => 'APP_KEY belum terpasang. Aplikasi tidak aman untuk dijalankan.',
                'href' => null,
            ];
        }
        if (!$sysChecks['database']) {
            $notifications[] = [
                'type' => 'error',
                'text' => 'Koneksi database bermasalah. Periksa konfigurasi DB.',
                'href' => null,
            ];
        }
        if (!$sysChecks['cache']) {
            $notifications[] = [
                'type' => 'warning',
                'text' => 'Cache tidak berjalan normal. Beberapa fitur bisa melambat.',
                'href' => null,
            ];
        }
        if (!$sysChecks['storage_writable']) {
            $notifications[] = [
                'type' => 'warning',
                'text' => 'Storage tidak writable. Upload / log bisa gagal.',
                'href' => null,
            ];
        }

        if (!$siteConfig['exists']) {
            $notifications[] = [
                'type' => 'warning',
                'text' => 'Konfigurasi situs belum diisi. Lengkapi profil RS sebelum digunakan.',
                'href' => null,
            ];
        } elseif (!empty($siteConfig['missing'])) {
            $notifications[] = [
                'type' => 'warning',
                'text' => 'Konfigurasi situs belum lengkap: ' . implode(', ', $siteConfig['missing']) . '.',
                'href' => null,
            ];
        }

        // ====== USER TERBARU ======
        $recentUsers = User::query()
            ->select(['id','name','email','last_role','created_at'])
            ->latest('id')
            ->with('roles')
            ->limit(8)->get()
            ->map(function($u){
                $u->display_role = $u->getActiveRoleSlug();
                // Backward-compatible field name used by some views
                $u->role_label = $u->display_role;
                return $u;
            });

        return view('super_admin.dashboard', compact(
            'stats',
            'sysChecks',
            'siteConfig',
            'recentUsers',
            'notifications'
        ));
    }
}
