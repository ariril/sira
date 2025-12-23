<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('multi_rater_scores')) {
            Schema::create('multi_rater_scores', function (Blueprint $table) {
                $table->id();
                $table->foreignId('period_id')->constrained('assessment_periods');
                $table->foreignId('rater_user_id')->constrained('users');
                $table->foreignId('target_user_id')->constrained('users');
                $table->foreignId('performance_criteria_id')->nullable()->constrained('performance_criterias');
                $table->unsignedTinyInteger('score'); // 1–100
                $table->timestamps();

                $table->unique(
                    ['period_id', 'rater_user_id', 'target_user_id', 'performance_criteria_id'],
                    'uniq_mr_period_rater_target_criteria'
                );
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('multi_rater_scores');
    }
};