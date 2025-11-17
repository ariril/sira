<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('criteria_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assessment_period_id')->constrained('assessment_periods')->cascadeOnDelete();
            $table->foreignId('performance_criteria_id')->constrained('performance_criterias')->cascadeOnDelete();

            $table->decimal('value_numeric', 15, 4)->nullable();
            $table->dateTime('value_datetime')->nullable();
            $table->text('value_text')->nullable();

            $table->enum('source_type', ['system','manual','import'])->default('system');
            $table->string('source_table', 100)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();

            $table->timestamps();

            $table->unique(['user_id','assessment_period_id','performance_criteria_id'], 'uniq_metric_final_per_period');
            $table->index(['assessment_period_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('criteria_metrics');
    }
};
