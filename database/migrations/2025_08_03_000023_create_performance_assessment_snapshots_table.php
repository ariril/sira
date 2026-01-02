<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('performance_assessment_snapshots', function (Blueprint $table) {
            $table->id();

            $table->foreignId('assessment_period_id')
                ->constrained('assessment_periods')
                ->onDelete('cascade');

            $table->foreignId('user_id')
                ->constrained('users')
                ->onDelete('cascade');

            // Snapshot payload containing a compatible shape with PerformanceScoreService::calculate.
            $table->json('payload');

            $table->timestamp('snapshotted_at')->nullable();
            $table->timestamps();

            $table->unique(['assessment_period_id', 'user_id'], 'perf_assessment_snapshot_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_assessment_snapshots');
    }
};
