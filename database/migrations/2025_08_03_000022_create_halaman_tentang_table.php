<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('halaman_tentang', function (Blueprint $table) {
            $table->id();

            $table->enum('tipe', ['visi','misi','struktur','profil_rs','tugas_fungsi'])
                ->unique('halaman_tentang_tipe_unique');

            $table->string('judul', 200)->nullable();
            $table->longText('konten')->nullable();
            $table->string('path_gambar')->nullable();
            $table->json('lampiran_json')->nullable();

            $table->timestamp('diterbitkan_pada')->nullable();
            $table->boolean('aktif')->default(true);

            $table->timestamps();

            $table->index('diterbitkan_pada', 'halaman_tentang_diterbitkan_index');
            $table->index('aktif', 'halaman_tentang_aktif_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('halaman_tentang');
    }
};
