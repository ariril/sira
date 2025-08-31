<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Profesi;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // (opsional) inisialisasi DB – hindari dd() agar tidak memutus request
        try {
            DB::statement('CREATE DATABASE IF NOT EXISTS sira');
        } catch (\Throwable $e) {
            Log::error('Gagal membuat database: '.$e->getMessage());
        }

        // View composer: inject $profesis ke partial login-modal
        // -> resources/views/partials/login-modal.blade.php
        View::composer('partials.login-modal', function ($view) {
            $view->with(
                'profesis',
                Profesi::select('id','nama')->orderBy('nama')->get()
            );
        });
    }
}
