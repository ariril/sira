<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jadwal_dokter', function (Blueprint $table) {
            $table->id();

            $table->foreignId('dokter_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->date('tanggal');
            $table->time('jam_mulai');
            $table->time('jam_selesai');

            $table->foreignId('unit_kerja_id')
                ->constrained('unit_kerja')
                ->cascadeOnDelete();

            $table->timestamps();

            $table->unique(
                ['dokter_id', 'tanggal', 'jam_mulai', 'jam_selesai', 'unit_kerja_id'],
                'uniq_jadwal_dokter_slot'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jadwal_dokter');
    }
};
