<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('kunjungans', function (Blueprint $t) {
            $t->id();
            $t->string('ticket_code')->unique();                 // kode pada QR/struk antrian
            $t->foreignId('unit_kerja_id')->nullable()
                ->constrained('unit_kerjas')->nullOnDelete();      // poli/unit tempat dilayani
            $t->timestamp('tanggal')->nullable();
            $t->timestamps();

            $t->index(['unit_kerja_id', 'tanggal']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('kunjungans');
    }
};
