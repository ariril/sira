<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kriteria_kinerjas', function (Blueprint $table) {
            $table->id();
            $table->string('nama_kriteria');
            $table->string('tipe_kriteria', 10); // Benefit, Cost
            $table->text('deskripsi_kriteria')->nullable();
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kriteria_kinerjas');
    }
};
