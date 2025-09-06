<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengumuman', function (Blueprint $table) {
            $table->id();

            $table->string('judul', 200);
            $table->string('slug', 220)->unique();

            $table->text('ringkasan')->nullable();
            $table->longText('konten');

            $table->enum('kategori', ['remunerasi','kinerja','panduan','lainnya'])
                ->default('lainnya');

            $table->enum('label', ['penting','info','update'])->nullable(); // badge tampilan
            $table->boolean('disorot')->default(false);

            $table->timestamp('dipublikasikan_pada')->nullable()->index();
            $table->timestamp('kedaluwarsa_pada')->nullable()->index();

            $table->json('lampiran_json')->nullable();

            $table->foreignId('penulis_id')->constrained('users')->cascadeOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengumuman');
    }
};
