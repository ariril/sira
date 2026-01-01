<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_360_windows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_period_id')->constrained('assessment_periods');
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_active')->default(true);
            $table->foreignId('opened_by')->nullable()->constrained('users');
            $table->timestamps();
        });

        // Add generated column to enforce single active window per period
        // MySQL syntax via raw statements
        if (DB::getDriverName() === 'mysql') {
            Schema::table('assessment_360_windows', function (Blueprint $table) {
                $table->unsignedBigInteger('active_period_key')->nullable()->storedAs("IF(is_active, assessment_period_id, NULL)");
                $table->unique('active_period_key', 'uniq_active_period_window');
                $table->index('assessment_period_id');
            });
        } else {
            Schema::table('assessment_360_windows', function (Blueprint $table) {
                $table->index('assessment_period_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_360_windows');
    }
};
