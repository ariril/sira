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
            $table->string('nama')->nullable();
            $table->date('tanggal_mulai_kerja')->nullable();
            $table->string('jenis_kelamin', 10)->nullable();
            $table->string('kewarganegaraan', 50)->nullable();
            $table->string('nomor_identitas')->unique()->nullable();
            $table->text('alamat')->nullable();
            $table->string('nomor_telepon', 20)->nullable();
            $table->string('email')->unique()->nullable();
            $table->string('pendidikan_terakhir', 50)->nullable();
            $table->string('jabatan')->nullable();

            // Relasi ke unit kerja
            $table->foreignId('unit_kerja_id')
                ->nullable()
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
                ->default('pegawai_medis')->nullable();

            $table->index('nama');

            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();

            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->index();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
