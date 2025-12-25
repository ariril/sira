<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('review_invitation_staff', function (Blueprint $table) {
            $table->id();

            $table->foreignId('invitation_id')
                ->constrained('review_invitations')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->timestamps();

            $table->unique(['invitation_id', 'user_id'], 'uniq_inv_staff');
            $table->index(['user_id'], 'idx_inv_staff_staff');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_invitation_staff');
    }
};
