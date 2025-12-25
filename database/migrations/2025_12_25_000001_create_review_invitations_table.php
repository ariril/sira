<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('review_invitations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('review_id')
                ->constrained('reviews')
                ->cascadeOnDelete();

            // Store only hash of token (sha256 hex = 64)
            $table->string('token_hash', 64)->unique();

            $table->enum('status', ['active', 'used', 'expired', 'revoked'])->default('active')->index();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('used_at')->nullable()->index();

            // Optional metadata from import
            $table->string('sent_via', 20)->nullable(); // whatsapp/sms/email (optional)
            $table->timestamp('sent_at')->nullable();

            $table->timestamps();

            $table->unique(['review_id'], 'uniq_review_invitation_review');
            $table->index(['status', 'expires_at'], 'idx_review_inv_status_expires');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_invitations');
    }
};
