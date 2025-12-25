<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('review_details', function (Blueprint $t) {
            $t->id();

            $t->foreignId('review_id')                     // ulasan_id
            ->constrained('reviews')
                ->cascadeOnDelete();

            $t->foreignId('medical_staff_id')              // tenaga_medis_id
            ->constrained('users')
                ->cascadeOnDelete();

            $t->enum('role', ['dokter','perawat','lainnya'])->nullable(); // peran
            $t->unsignedTinyInteger('rating')->nullable();
            $t->text('comment')->nullable();

            $t->timestamps();

            $t->unique(['review_id','medical_staff_id']);          // cegah duplikasi
            $t->index(['medical_staff_id','created_at']);          // rekap cepat per tenaga medis
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_details');
    }

};
