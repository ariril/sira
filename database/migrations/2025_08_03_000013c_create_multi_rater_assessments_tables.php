<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('multi_rater_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessee_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assessor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('assessor_type', ['self','supervisor','peer','subordinate']);
            $table->foreignId('assessment_period_id')->constrained('assessment_periods')->cascadeOnDelete();
            $table->enum('status', ['invited','in_progress','submitted','cancelled'])->default('invited');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->index(['assessee_id','assessment_period_id']);
            $table->unique(
                ['assessee_id', 'assessment_period_id', 'assessor_type', 'assessor_id'],
                'uniq_mra_once_per_assessor_type'
            );
        });

        Schema::create('multi_rater_assessment_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('multi_rater_assessment_id')->constrained('multi_rater_assessments')->cascadeOnDelete();
            $table->foreignId('performance_criteria_id')->constrained('performance_criterias')->cascadeOnDelete();
            $table->decimal('score', 5, 2); // 0-100
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->unique(['multi_rater_assessment_id','performance_criteria_id'], 'uniq_mra_detail_once');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('multi_rater_assessment_details');
        Schema::dropIfExists('multi_rater_assessments');
    }
};
