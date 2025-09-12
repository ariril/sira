<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('performance_criterias', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type', 10);                 // tipe_kriteria (contoh: Benefit, Cost)
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_criterias');
    }

};
