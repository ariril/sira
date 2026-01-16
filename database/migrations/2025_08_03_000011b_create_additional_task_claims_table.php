<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('additional_task_claims', function (Blueprint $table) {
            $table->id();

            // Tugas & siapa yang mengklaim
            $table->foreignId('additional_task_id')
                ->constrained('additional_tasks')
                ->cascadeOnDelete();

            $table->foreignId('user_id') // pegawai pemilik klaim
            ->constrained('users')
                ->cascadeOnDelete();

            $table->enum('status', ['active','submitted', 'approved', 'rejected'])
                ->default('active')
                ->index();

            $table->timestamp('submitted_at')->nullable()->index();

            $table->string('result_file_path')->nullable();
            $table->text('result_note')->nullable();

            $table->decimal('awarded_points', 8, 2)->nullable();

            // Audit proses review
            $table->foreignId('reviewed_by_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_comment')->nullable();

            $table->index('reviewed_at', 'idx_claim_reviewed_at');

            $table->timestamps();

            $table->unique(['additional_task_id', 'user_id'], 'uniq_task_user_claim');
            $table->index(['additional_task_id', 'status'], 'idx_claim_task_status');
            $table->index(['user_id', 'status'], 'idx_claim_user_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('additional_task_claims');
    }
};
