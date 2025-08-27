<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('nip')->unique()->nullable();
            $table->string('nama');
            $table->date('tanggal_mulai_kerja');
            $table->string('jenis_kelamin', 10);
            $table->string('kewarganegaraan', 50);
            $table->string('nomor_identitas')->unique();
            $table->text('alamat')->nullable();
            $table->string('nomor_telepon', 20)->nullable();
            $table->string('email')->unique();
            $table->string('pendidikan_terakhir', 50)->nullable();
            $table->string('jabatan');

            // Relasi ke unit kerja
            $table->foreignId('unit_kerja_id')
                ->constrained('unit_kerjas')
                ->cascadeOnDelete();

            // Relasi ke profesi medis
            $table->foreignId('profesi_id')
                ->nullable()
                ->constrained('profesis')
                ->nullOnDelete();

            $table->string('password');

            // Role untuk otorisasi
            $table->enum('role', ['pegawai_medis', 'kepala_unit', 'administrasi', 'super_admin'])
                ->default('pegawai_medis');

            $table->index('nama');
            $table->timestamps();
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
