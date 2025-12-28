<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('review_invitations', function (Blueprint $table) {
            $table->id();

            $table->string('registration_ref', 50);

            $table->foreignId('unit_id')
                ->nullable()
                ->constrained('units')
                ->nullOnDelete();

            $table->string('patient_name')->nullable();
            $table->string('contact')->nullable();

            // Optional link to assessment period (for lifecycle validation).
            // Note: existing DBs might not have this column; application code falls back safely.
            $table->foreignId('assessment_period_id')
                ->nullable()
                ->constrained('assessment_periods')
                ->nullOnDelete();

            // One-time token: store hash only (SHA256, 64 hex chars)
            $table->string('token_hash', 64)->unique();

            $table->enum('status', ['created', 'sent', 'opened', 'used', 'expired', 'cancelled'])
                ->default('created')
                ->index();

            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('used_at')->nullable();

            $table->string('client_ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();

            $table->timestamps();

            $table->index(['status', 'expires_at'], 'idx_review_inv_status_expires');
            $table->index(['registration_ref'], 'idx_review_inv_registration_ref');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_invitations');
    }
};
