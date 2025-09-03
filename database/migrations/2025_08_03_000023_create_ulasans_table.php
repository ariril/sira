<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('ulasans', function (Blueprint $t) {
            $t->id();
            $t->foreignId('kunjungan_id')->constrained('kunjungans')->cascadeOnDelete();

            $t->unsignedTinyInteger('overall_rating')->nullable(); // 1..5 (opsional)
            $t->text('komentar')->nullable();

            // identitas ringan (tanpa akun pasien)
            $t->string('nama_pasien')->nullable();
            $t->string('kontak')->nullable(); // email/telepon opsional

            // anti-spam/audit
            $t->string('client_ip', 45)->nullable();
            $t->string('user_agent', 255)->nullable();

            $t->timestamps();

            // jika ingin SATU ulasan per kunjungan, aktifkan unique ini:
            // $t->unique('kunjungan_id');
        });
    }

    public function down(): void {
        Schema::dropIfExists('ulasans');
    }
};
