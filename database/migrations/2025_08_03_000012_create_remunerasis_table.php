<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('remunerasis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('periode_penilaian_id')->constrained('periode_penilaians')->onDelete('cascade');
            $table->decimal('nilai_remunerasi', 15, 2);
            $table->date('tanggal_pembayaran')->nullable();
            $table->string('status_pembayaran', 50)->default('Belum Dibayar');
            $table->json('rincian_perhitungan')->nullable(); // Menyimpan rincian perhitungan dalam format JSON
            $table->timestamp('published_at')->nullable();
            $table->timestamp('calculated_at')->nullable();
            $table->foreignId('revised_by')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'periode_penilaian_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('remunerasis');
    }
};
