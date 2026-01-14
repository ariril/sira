<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('performance_assessments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->onDelete('cascade');

            $table->foreignId('assessment_period_id')
                ->constrained('assessment_periods')
                ->onDelete('cascade');

            $table->date('assessment_date');// tanggal_penilaian
            $table->decimal('total_wsm_score', 8, 2)->nullable(); // skor_total_wsm
            $table->decimal('total_wsm_value_score', 8, 2)->nullable();

            $table->enum('validation_status', [  // status_validasi
                'Menunggu Validasi',
                'Tervalidasi',
                'Ditolak',
            ])->default('Menunggu Validasi');

            $table->text('supervisor_comment')->nullable(); // komentar_atasan

            $table->timestamps();

            $table->unique(['user_id', 'assessment_period_id']);
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('penilaian_kinerja');
    }
};
