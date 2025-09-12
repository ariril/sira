<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();

            // Relasi ke operasional
            $table->foreignId('visit_id')
            ->nullable()
                ->constrained('visits')
                ->nullOnDelete();

            $table->foreignId('queue_id')
            ->nullable()
                ->constrained('patient_queues')
                ->nullOnDelete();

            // Detail transaksi
            $table->date('transaction_date');
            $table->decimal('amount', 15, 2);
            $table->string('payment_method', 50);

            // Kanal/rail pembayaran
            $table->enum('channel', ['VA','QRIS','Transfer','Tunai'])
                ->default('QRIS');

            $table->enum('payment_status', [
                'Berhasil',
                'Pending',
                'Gagal',
            ])->default('Pending');

            $table->timestamp('paid_at')->nullable();

            // Referensi unik dari gateway/rail pembayaran
            $table->string('payment_reference_number')->nullable();
            $table->unique('payment_reference_number', 'uniq_payment_reference_number');

            // Jejak pencatat (opsional)
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->index(['transaction_date', 'payment_status'], 'idx_date_status');
            $table->index(['visit_id', 'queue_id'], 'idx_visit_queue');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }

};
