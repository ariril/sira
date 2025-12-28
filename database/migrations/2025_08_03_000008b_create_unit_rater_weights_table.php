<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
            $table->decimal('weight', 5, 2)->nullable(); // percent
            $table->enum('status', ['draft', 'pending', 'active', 'rejected', 'archived'])->default('draft');
            $table->foreignId('proposed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->unique([
                'assessment_period_id',
                'unit_id',
                'performance_criteria_id',
                'assessee_profession_id',
                'assessor_type',
                'assessor_profession_id',
                'assessor_level',
            ], 'uniq_rater_weight');
        });

        // Best-effort conditional constraints (skip if driver doesn't support CHECK)
        $driver = Schema::getConnection()->getDriverName();
        if (in_array($driver, ['mysql', 'pgsql', 'sqlite'], true)) {
            try {
                // unit_rater_weights: assessor_level only for supervisor
                DB::statement("ALTER TABLE unit_rater_weights ADD CONSTRAINT chk_rw_level_supervisor CHECK ((assessor_type = 'supervisor' AND (assessor_level IS NULL OR assessor_level >= 1)) OR (assessor_type <> 'supervisor' AND assessor_level IS NULL))");
            } catch (\Throwable $e) {
                // ignore
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_rater_weights');
    }
};
