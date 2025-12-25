<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('performance_criterias', function (Blueprint $table) {
            if (!Schema::hasColumn('performance_criterias', 'is_360')) {
                $table->boolean('is_360')->default(false)->after('input_method');
            }
        });

        // Backfill: any existing 360 input_method is considered 360 criteria.
        if (Schema::hasColumn('performance_criterias', 'input_method') && Schema::hasColumn('performance_criterias', 'is_360')) {
            DB::table('performance_criterias')
                ->where('input_method', '360')
                ->update(['is_360' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('performance_criterias', function (Blueprint $table) {
            if (Schema::hasColumn('performance_criterias', 'is_360')) {
                $table->dropColumn('is_360');
            }
        });
    }
};
