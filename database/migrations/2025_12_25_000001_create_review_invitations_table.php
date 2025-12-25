<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('review_invitations', function (Blueprint $table) {
            $table->id();

            $table->string('patient_name');
            $table->string('phone')->nullable();
            $table->string('no_rm')->nullable();

            // Plain token (kept for invitation URL); keep it unique
            $table->string('token', 80)->unique();
            $table->timestamp('expires_at')->nullable()->index();
            $table->string('status', 20)->default('pending')->index();

            $table->timestamps();

            $table->index(['status', 'expires_at'], 'idx_review_inv_status_expires');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_invitations');
    }
};
