<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visit_medical_staff', function (Blueprint $table) {
            $table->id();

            $table->foreignId('visit_id')
            ->constrained('visits')
                ->cascadeOnDelete();

            $table->foreignId('medical_staff_id')
            ->constrained('users')
                ->cascadeOnDelete();

            $table->enum('role', [
                'dokter',
                'perawat',
                'lab',
                'farmasi',
                'admin',
                'lainnya',
            ]);

            $table->unsignedInteger('duration_minutes')->nullable(); // durasi_menit

            $table->timestamps();

            $table->unique(['visit_id','medical_staff_id','role'], 'uniq_visit_staff_role');
            $table->index(['medical_staff_id','created_at'], 'idx_staff_time');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visit_medical_staff');
    }

};
