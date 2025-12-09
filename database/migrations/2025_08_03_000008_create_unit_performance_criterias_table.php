<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unit_performance_criterias', function (Blueprint $table) {
            $table->id();

            $table->foreignId('unit_id')
                ->constrained('units')
                ->onDelete('cascade');

            // Optional link to global criteria when this unit-level criteria derives from it
            $table->foreignId('performance_criteria_id')
                ->nullable()
                ->constrained('performance_criterias')
                ->nullOnDelete();

            $table->string('name');
            $table->enum('type', ['benefit','cost']);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            // Avoid duplicates per unit
            $table->unique(['unit_id','name'], 'uniq_upc_unit_name');
            $table->unique(['unit_id','performance_criteria_id'], 'uniq_upc_unit_global');
            $table->index(['unit_id','is_active'], 'idx_upc_unit_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_performance_criterias');
    }
};
