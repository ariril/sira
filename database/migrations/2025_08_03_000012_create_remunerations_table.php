<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('remunerations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->onDelete('cascade');

            $table->foreignId('assessment_period_id')
            ->constrained('assessment_periods')
                ->onDelete('cascade');

            $table->decimal('amount', 15, 2); // nilai_remunerasi
            $table->date('payment_date')->nullable();

            $table->enum('payment_status', [
                'Belum Dibayar',
                'Dibayar',
                'Ditahan',
            ])->default('Belum Dibayar');

            $table->json('calculation_details')->nullable(); // rincian_perhitungan (JSON)

            $table->timestamp('published_at')->nullable();
            $table->timestamp('calculated_at')->nullable();

            $table->foreignId('revised_by')->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->unique(['user_id', 'assessment_period_id']);
            $table->index(['payment_status', 'payment_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('remunerations');
    }
};
