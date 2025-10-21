<?php

namespace App\Http\Controllers\Web\SuperAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ReportController extends Controller
{
    /**
     * Simple system health overview for Super Admin.
     * Shows framework, PHP, env info and a few connectivity checks.
     */
    public function systemHealth()
    {
        $summary = [
            'app_name'      => Config::get('app.name'),
            'environment'   => App::environment(),
            'debug'         => (bool) Config::get('app.debug'),
            'timezone'      => Config::get('app.timezone'),
            'laravel'       => App::version(),
            'php'           => PHP_VERSION,
            'cache_driver'  => Config::get('cache.default'),
            'queue_driver'  => Config::get('queue.default'),
            'session_driver'=> Config::get('session.driver'),
        ];

        $checks = [];

        // APP KEY
        $checks[] = [
            'name' => 'App Key',
            'status' => (bool) Config::get('app.key') ? 'ok' : 'fail',
            'message' => Config::get('app.key') ? 'Set' : 'Not set',
        ];

        // PHP Version >= 8.2
        $phpOk = version_compare(PHP_VERSION, '8.2.0', '>=');
        $checks[] = [
            'name' => 'PHP Version >= 8.2',
            'status' => $phpOk ? 'ok' : 'warn',
            'message' => PHP_VERSION,
        ];

        // Database connection
        $dbMessage = 'Connected';
        $dbStatus = 'ok';
        try {
            DB::connection()->getPdo();
        } catch (\Throwable $e) {
            $dbStatus = 'fail';
            $dbMessage = $e->getMessage();
        }
        $checks[] = [
            'name' => 'Database Connection',
            'status' => $dbStatus,
            'message' => $dbMessage,
        ];

        // Cache read/write
        $cacheStatus = 'ok';
        $cacheMessage = 'OK';
        try {
            Cache::put('_health_test', 'ok', 60);
            $cacheStatus = Cache::get('_health_test') === 'ok' ? 'ok' : 'fail';
            Cache::forget('_health_test');
        } catch (\Throwable $e) {
            $cacheStatus = 'fail';
            $cacheMessage = $e->getMessage();
        }
        $checks[] = [
            'name' => 'Cache Read/Write',
            'status' => $cacheStatus,
            'message' => $cacheMessage,
        ];

        // Storage (public disk) write
        $storageStatus = 'ok';
        $storageMessage = 'OK';
        try {
            Storage::disk('public')->put('_health.txt', 'ok');
            $exists = Storage::disk('public')->exists('_health.txt');
            Storage::disk('public')->delete('_health.txt');
            $storageStatus = $exists ? 'ok' : 'fail';
            if (!$exists) {
                $storageMessage = 'File not found after write';
            }
        } catch (\Throwable $e) {
            $storageStatus = 'fail';
            $storageMessage = $e->getMessage();
        }
        $checks[] = [
            'name' => 'Storage public disk',
            'status' => $storageStatus,
            'message' => $storageMessage . (is_link(public_path('storage')) ? ' (storage link exists)' : ' (storage link missing)'),
        ];

        return view('super_admin.reports.system_health', [
            'summary' => $summary,
            'checks'  => $checks,
        ]);
    }
}
