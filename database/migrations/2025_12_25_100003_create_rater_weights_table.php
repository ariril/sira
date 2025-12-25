<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rater_weights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_period_id')->constrained('assessment_periods')->cascadeOnDelete();
            $table->foreignId('assessee_profession_id')->constrained('professions')->cascadeOnDelete();
            $table->enum('assessor_type', ['self', 'supervisor', 'peer', 'subordinate', 'patient', 'other']);
            $table->decimal('weight', 5, 2); // percent
            $table->enum('status', ['draft', 'pending', 'active', 'rejected', 'archived'])->default('draft');
            $table->foreignId('proposed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->unique(['assessment_period_id', 'assessee_profession_id', 'assessor_type'], 'uniq_rater_weight');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rater_weights');
    }
};
