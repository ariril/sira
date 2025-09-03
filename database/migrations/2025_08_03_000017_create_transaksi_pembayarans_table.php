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

            // Relasi ke operasional
            $table->foreignId('kunjungan_id')
                ->nullable()
                ->constrained('kunjungans')
                ->nullOnDelete();

            $table->foreignId('antrian_id')
                ->nullable()
                ->constrained('antrian_pasiens')
                ->nullOnDelete();

            // Detail transaksi
            $table->date('tanggal_transaksi');
            $table->decimal('jumlah_pembayaran', 15, 2);
            $table->string('metode_pembayaran', 50); // Tunai, Transfer Bank, QRIS, dll.

            // Kanal/rail pembayaran (metadata tambahan)
            $table->enum('channel', ['VA','QRIS','Transfer','Tunai'])
                ->default('QRIS');

            $table->string('status_pembayaran', 50); // Berhasil, Pending, Gagal
            $table->timestamp('paid_at')->nullable();

            // Referensi unik dari gateway/rail pembayaran
            $table->string('nomor_referensi_pembayaran')->nullable();
            $table->unique('nomor_referensi_pembayaran', 'uniq_nomor_referensi_pembayaran');

            // Jejak pencatat (opsional)
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            // Index bantu
            $table->index(['tanggal_transaksi', 'status_pembayaran'], 'idx_tanggal_status');
            $table->index(['kunjungan_id', 'antrian_id'], 'idx_relasi_kunjungan_antrian');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaksi_pembayarans');
    }
};
