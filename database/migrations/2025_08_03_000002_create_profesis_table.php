<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('profesis', function (Blueprint $table) {
            $table->id();
            $table->string('nama')->unique();   // contoh: Dokter, Perawat
            $table->string('kode')->unique();   // contoh: DOK, PRW
            $table->text('deskripsi')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('profesi_id');
        });
        Schema::dropIfExists('profesis');
    }
};
