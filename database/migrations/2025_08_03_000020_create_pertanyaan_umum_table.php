<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pertanyaan_umum', function (Blueprint $table) {
            $table->id();
            $table->string('pertanyaan');
            $table->longText('jawaban');
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->boolean('aktif')->default(true);
            $table->string('kategori', 50)->nullable();
            $table->timestamps();

            $table->index('urutan', 'pertanyaan_umum_urutan_index');
            $table->index('aktif', 'pertanyaan_umum_aktif_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pertanyaan_umum');
    }
};
