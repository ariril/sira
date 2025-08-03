<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaksi_pembayarans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pasien_id')->constrained('pasiens')->onDelete('cascade');
            $table->date('tanggal_transaksi');
            $table->decimal('jumlah_pembayaran', 15, 2);
            $table->string('metode_pembayaran', 50); // Tunai, Transfer Bank, QRIS
            $table->string('status_pembayaran', 50); // Berhasil, Pending, Gagal
            $table->string('nomor_referensi_pembayaran')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaksi_pembayarans');
    }
};
