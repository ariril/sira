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
            'pegawai_medis' => User::where('role', 'pegawai_medis')->count(),
            'kepala_unit' => User::where('role', 'kepala_unit')->count(),
            'kepala_poliklinik' => User::where('role', 'kepala_poliklinik')->count(),
            // Backward-compatible count, but normalize to 'admin_rs'
            'admin_rs' => User::where('role', 'admin_rs')->orWhere('role', 'administrasi')->count(),
            'super_admin' => User::where('role', 'super_admin')->count(),
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

        // ====== USER TERBARU ======
        $recentUsers = User::query()
            ->select(['id', 'name', 'email', 'role', 'created_at'])
            ->latest('id')
            ->limit(8)
            ->get();

        return view('super_admin.dashboard', compact(
            'stats',
            'userDistribution',
            'sysSummary',
            'sysChecks',
            'siteConfig',
            'recentUsers'
        ));
    }
}
