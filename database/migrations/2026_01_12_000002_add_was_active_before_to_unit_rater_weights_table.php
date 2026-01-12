<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('unit_rater_weights', function (Blueprint $table) {
            $table->boolean('was_active_before')
                ->default(false)
                ->after('status');
        });

        // Backfill: baris yang diarsipkan pada umumnya adalah bobot yang pernah aktif
        // untuk periode tersebut.
        DB::table('unit_rater_weights')
            ->where('status', 'archived')
            ->update(['was_active_before' => 1]);
    }

    public function down(): void
    {
        Schema::table('unit_rater_weights', function (Blueprint $table) {
            $table->dropColumn('was_active_before');
        });
    }
};
