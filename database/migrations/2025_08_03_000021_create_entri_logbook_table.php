<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entri_logbook', function (Blueprint $table) {
            $table->id();

            // relasi pengguna yang membuat entri
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->date('tanggal')->index();
            $table->time('jam_mulai')->nullable();
            $table->time('jam_selesai')->nullable();
            $table->unsignedInteger('durasi_menit')->nullable();
            $table->text('aktivitas');

            // metadata
            $table->string('kategori', 50)->nullable()->index();
            $table->enum('status', ['draf','diajukan','disetujui','ditolak'])
                ->default('diajukan')
                ->index();

            $table->foreignId('penyetuju_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('disetujui_pada')->nullable();

            $table->json('lampiran_json')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entri_logbook');
    }
};
