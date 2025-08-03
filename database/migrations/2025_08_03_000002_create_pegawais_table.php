<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pegawais', function (Blueprint $table) {
            $table->id();
            $table->string('nip')->unique()->nullable();
            $table->string('nama_pegawai');
            $table->date('tanggal_mulai_kerja');
            $table->string('jenis_kelamin', 10);
            $table->string('kewarganegaraan', 50);
            $table->string('nomor_identitas')->unique();
            $table->text('alamat')->nullable();
            $table->string('nomor_telepon', 20)->nullable();
            $table->string('email')->unique();
            $table->string('pendidikan_terakhir', 50)->nullable();
            $table->string('jabatan');
            $table->foreignId('id_unit')->constrained('unit_kerjas')->onDelete('cascade');
            $table->string('password');
            $table->enum('role', ['pegawai_medis', 'kepala_unit', 'administrasi', 'super_admin'])->default('pegawai_medis');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pegawais');
    }
};
