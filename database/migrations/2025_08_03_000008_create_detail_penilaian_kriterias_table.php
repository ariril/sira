<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('detail_penilaian_kriterias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('penilaian_kinerja_id')->constrained('penilaian_kinerjas')->onDelete('cascade');
            $table->foreignId('kriteria_kinerja_id')->constrained('kriteria_kinerjas')->onDelete('cascade');
            $table->decimal('nilai', 10, 2);
            $table->timestamps();

            $table->unique(['penilaian_kinerja_id', 'kriteria_kinerja_id'], 'penilaian_kinerja_kriteria_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detail_penilaian_kriterias');
    }
};
