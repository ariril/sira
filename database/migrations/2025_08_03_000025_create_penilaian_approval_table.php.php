<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('penilaian_approval', function (Blueprint $table) {
            $table->id();
            $table->foreignId('penilaian_kinerja_id')->constrained('penilaian_kinerja')->cascadeOnDelete();
            $table->foreignId('approver_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedSmallInteger('level'); // 1=atasan langsung, 2=manajer, dst.
            $table->enum('status', ['pending','approved','rejected'])->default('pending');
            $table->text('catatan')->nullable();
            $table->timestamp('acted_at')->nullable();
            $table->timestamps();

            $table->unique(['penilaian_kinerja_id','level'], 'uniq_penilaian_level');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('penilaian_approval');
    }
};
