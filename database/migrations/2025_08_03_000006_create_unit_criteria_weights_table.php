<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unit_criteria_weights', function (Blueprint $table) {
            $table->id();

            $table->foreignId('unit_id')
                ->constrained('units')
                ->onDelete('cascade');

            $table->foreignId('performance_criteria_id')
                ->constrained('performance_criterias')
                ->onDelete('cascade');

            $table->decimal('weight', 5, 2);

            // Revisi: Approval & policy
            $table->foreignId('assessment_period_id')
                ->nullable()
                ->constrained('assessment_periods')
                ->nullOnDelete();

            $table->enum('status', ['draft','pending','active','rejected','archived'])
                ->default('draft');

            $table->string('policy_doc_path')->nullable();
            $table->text('policy_note')->nullable();

            $table->foreignId('unit_head_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->text('unit_head_note')->nullable();

            $table->foreignId('polyclinic_head_id')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->timestamps();

            // Index
            $table->unique(
                ['unit_id','performance_criteria_id','assessment_period_id','status'],
                'uniq_unit_crit_period_status'
            );
            $table->index(['assessment_period_id','status'], 'idx_ucw_period_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_criteria_weights');
    }
};
