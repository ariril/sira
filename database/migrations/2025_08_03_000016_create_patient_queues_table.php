<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_queues', function (Blueprint $table) {
            $table->id();


            $table->date('queue_date');
            $table->unsignedInteger('queue_number');

            $table->enum('queue_status', [
                'Menunggu',
                'Sedang Dilayani',
                'Selesai',
                'Batal',
            ])->default('Menunggu');

            // Waktu proses
            $table->time('queued_at')->nullable();
            $table->time('service_started_at')->nullable();
            $table->time('service_finished_at')->nullable();

            // Relasi ke dokter & unit
            $table->foreignId('on_duty_doctor_id')
            ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('unit_id')
            ->constrained('units')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            // Referensi pasien eksternal (opsional)
            $table->string('patient_ref', 50)->nullable();

            // Kunjungan terhubung (opsional)
            $table->foreignId('visit_id')
            ->nullable()
                ->constrained('visits')
                ->nullOnDelete();

            $table->timestamps();

            $table->unique(['queue_date','unit_id','queue_number'], 'uniq_queue_per_unit_date');

            // Index untuk pencarian umum
            $table->index('patient_ref', 'idx_queue_patient_ref');
            $table->index(['unit_id','queue_date','queue_status','queue_number'], 'idx_unit_date_status_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_queues');
    }

};
