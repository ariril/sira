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

            // Tambahan: scope administrasi/poli
            $table->enum('queue_scope', ['administrasi','unit'])
                ->default('unit');

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

            // Target poli saat antri administrasi
            $table->foreignId('target_unit_id')
                ->nullable()
                ->constrained('units')
                ->nullOnDelete();

            $table->timestamp('routed_at')->nullable();

            // Petugas administrasi
            $table->foreignId('admin_officer_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Referensi pasien eksternal (opsional)
            $table->string('patient_ref', 50)->nullable();

            // Visit terhubung (opsional)
            $table->foreignId('visit_id')
                ->nullable()
                ->constrained('visits')
                ->nullOnDelete();

            $table->timestamps();

            $table->unique(['queue_date','unit_id','queue_number'], 'uniq_queue_per_unit_date');

            // Index untuk pencarian umum
            $table->index('patient_ref', 'idx_queue_patient_ref');
            $table->index(['unit_id','queue_date','queue_status','queue_number'], 'idx_unit_date_status_number');
            $table->index('queue_scope', 'idx_queue_scope');
            $table->index('target_unit_id', 'idx_target_unit');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_queues');
    }
};
