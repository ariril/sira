<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('ulasan_details', function (Blueprint $t) {
            $t->id();
            $t->foreignId('ulasan_id')->constrained('ulasans')->cascadeOnDelete();
            $t->foreignId('tenaga_medis_id')->constrained('users')->cascadeOnDelete();

            $t->enum('peran', ['dokter','perawat','lainnya'])->nullable(); // opsional
            $t->unsignedTinyInteger('rating');                              // 1..5
            $t->text('komentar')->nullable();

            $t->timestamps();

            $t->unique(['ulasan_id','tenaga_medis_id']); // cegah duplikasi target pada 1 ulasan
            $t->index(['tenaga_medis_id','created_at']); // rekap cepat per tenaga medis
        });
    }

    public function down(): void {
        Schema::dropIfExists('ulasan_details');
    }
};
