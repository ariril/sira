<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengaturan_situs', function (Blueprint $table) {
            $table->id();

            $table->string('nama', 150);
            $table->string('nama_singkat', 50)->nullable();
            $table->text('alamat')->nullable();
            $table->string('telepon', 30)->nullable();
            $table->string('email', 150)->nullable();

            $table->string('path_logo')->nullable();
            $table->string('path_favicon')->nullable(); // ikon tab browser

            $table->string('url_facebook')->nullable();
            $table->string('url_instagram')->nullable();
            $table->string('url_twitter')->nullable();
            $table->string('url_youtube')->nullable();

            $table->string('teks_footer')->nullable();

            // ganti 'users' menjadi nama tabel pengguna Anda jika juga di-Indonesiakan
            $table->foreignId('diperbarui_oleh')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->timestamps(); // created_at, updated_at
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengaturan_situs');
    }
};
