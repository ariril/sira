<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_360_windows', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('assessment_period_id');
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('opened_by')->nullable();
            $table->timestamps();
        });

        // Add generated column to enforce single active window per period
        // MySQL syntax via raw statements
        Schema::table('assessment_360_windows', function (Blueprint $table) {
            $table->unsignedBigInteger('active_period_key')->nullable()->storedAs("IF(is_active, assessment_period_id, NULL)");
        });
        Schema::table('assessment_360_windows', function (Blueprint $table) {
            $table->unique('active_period_key', 'uniq_active_period_window');
            $table->index('assessment_period_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_360_windows');
    }
};
