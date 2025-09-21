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

            // Revisi: kaitkan dengan tugas opsional
            $table->foreignId('task_id')
                ->nullable()
                ->constrained('additional_tasks')
                ->nullOnDelete();

            $table->string('title');
            $table->text('description')->nullable();

            // tanggal claim & submit
            $table->date('submission_date');   // legacy field
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('submitted_at')->nullable();

            $table->string('evidence_file')->nullable();

            $table->enum('validation_status', [
                'Menunggu Persetujuan',
                'Disetujui',
                'Ditolak',
            ])->default('Menunggu Persetujuan');

            $table->text('supervisor_comment')->nullable();

            // Penilai tambahan
            $table->foreignId('reviewer_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // nilai & bonus
            $table->decimal('score', 8, 2)->nullable();
            $table->decimal('bonus_awarded', 15, 2)->nullable();

            $table->foreignId('assessment_period_id')
                ->nullable()
                ->constrained('assessment_periods')
                ->nullOnDelete();

            $table->timestamps();

            $table->index('task_id', 'idx_contrib_task');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('additional_contributions');
    }
};
