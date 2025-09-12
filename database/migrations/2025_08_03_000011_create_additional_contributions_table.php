<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('additional_contributions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->onDelete('cascade');

            $table->string('title');                         // judul_kontribusi
            $table->text('description')->nullable();         // deskripsi_kontribusi
            $table->date('submission_date');                 // tanggal_pengajuan
            $table->string('evidence_file')->nullable();     // file_bukti (path/URL)

            $table->enum('validation_status', [              // status_validasi
                'Menunggu Persetujuan',
                'Disetujui',
                'Ditolak',
            ])->default('Menunggu Persetujuan');

            $table->text('supervisor_comment')->nullable();  // komentar_supervisor

            $table->foreignId('assessment_period_id')        // periode_penilaian_id
            ->nullable()
                ->constrained('assessment_periods')
                ->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('additional_contributions');
    }

};
