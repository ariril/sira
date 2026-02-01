<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('performance_assessment_details', function (Blueprint $table) {
            $table->id();

            $table->foreignId('performance_assessment_id')
                ->constrained('performance_assessments')
                ->onDelete('cascade');

            $table->foreignId('performance_criteria_id')
                ->constrained('performance_criterias')
                ->onDelete('cascade');

            $table->decimal('score', 10, 2); // nilai
            $table->json('meta')->nullable();

            $table->timestamps();

            $table->unique(
                ['performance_assessment_id', 'performance_criteria_id'],
                'performance_assessment_criteria_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_assessment_details');
    }
};
