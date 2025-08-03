<?php

namespace App\Providers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        try {
            DB::statement('CREATE DATABASE IF NOT EXISTS sira');
        } catch (\Exception $e) {
            dd("Gagal membuat database: " . $e->getMessage());
        }
    }
}
