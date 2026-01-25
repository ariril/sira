<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unit_rater_weights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_period_id')->constrained('assessment_periods')->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained('units')->cascadeOnDelete();
            $table->foreignId('performance_criteria_id')->constrained('performance_criterias')->cascadeOnDelete();
            $table->foreignId('assessee_profession_id')->constrained('professions')->cascadeOnDelete();
            $table->enum('assessor_type', ['self', 'supervisor', 'peer', 'subordinate']);
            $table->foreignId('assessor_profession_id')->nullable()->constrained('professions')->nullOnDelete();
            $table->unsignedInteger('assessor_level')->nullable();
            $table->decimal('weight', 5, 2); // percent (stored decimal, entered as integer)
            $table->enum('status', ['draft', 'pending', 'active', 'rejected', 'archived'])->default('draft');
            $table->boolean('was_active_before')->default(false);
            $table->foreignId('proposed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('decided_note')->nullable();
            $table->timestamps();

            $table->unique([
                'assessment_period_id',
                'unit_id',
                'performance_criteria_id',
                'assessee_profession_id',
                'assessor_type',
                'assessor_profession_id',
                'assessor_level',
                'status',
            ], 'uniq_rater_weight');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_rater_weights');
    }
};
