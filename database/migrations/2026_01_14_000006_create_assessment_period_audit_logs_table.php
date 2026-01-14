<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('assessment_period_audit_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('assessment_period_id')
                ->constrained('assessment_periods')
                ->cascadeOnDelete();

            $table->foreignId('actor_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('action', 80);
            $table->text('reason')->nullable();
            $table->json('meta')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['assessment_period_id', 'action'], 'idx_period_audit_action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_period_audit_logs');
    }
};
