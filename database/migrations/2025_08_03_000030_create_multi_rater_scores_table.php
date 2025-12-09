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
                $table->unsignedBigInteger('period_id')->index();
                $table->unsignedBigInteger('rater_user_id')->index();
                $table->unsignedBigInteger('target_user_id')->index();
                $table->unsignedBigInteger('performance_criteria_id')->nullable()->index();
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