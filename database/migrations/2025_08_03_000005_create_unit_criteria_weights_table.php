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
                ->onDelete('cascade'); // id_unit -> unit_id

            $table->foreignId('performance_criteria_id')
                ->constrained('performance_criterias')
                ->onDelete('cascade'); // id_kriteria -> performance_criteria_id

            $table->decimal('weight', 5, 2); // bobot

            $table->timestamps();

            $table->unique(['unit_id', 'performance_criteria_id'], 'uniq_unit_criteria');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_criteria_weights');
    }

};
