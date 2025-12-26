<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('criteria_rater_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('performance_criteria_id')->constrained('performance_criterias')->cascadeOnDelete();
            $table->enum('assessor_type', ['self', 'supervisor', 'peer', 'subordinate']);
            $table->timestamps();

            $table->unique(['performance_criteria_id', 'assessor_type'], 'uniq_criteria_rater_rule');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('criteria_rater_rules');
    }
};
