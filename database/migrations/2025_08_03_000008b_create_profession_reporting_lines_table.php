<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profession_reporting_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessee_profession_id')->constrained('professions')->cascadeOnDelete();
            $table->foreignId('assessor_profession_id')->constrained('professions')->cascadeOnDelete();
            $table->enum('relation_type', ['supervisor', 'peer', 'subordinate'])->default('supervisor');
            $table->unsignedInteger('level')->nullable();
            $table ->boolean('is_required')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique([
                'assessee_profession_id',
                'assessor_profession_id',
                'relation_type',
                'level',
            ], 'uniq_prof_reporting_line');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profession_reporting_lines');
    }
};
