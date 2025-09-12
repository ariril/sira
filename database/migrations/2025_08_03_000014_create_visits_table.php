<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('visits', function (Blueprint $t) {
            $t->id();

            $t->string('ticket_code')->unique(); // kode pada QR/struk antrian

            $t->foreignId('unit_id')->nullable()
            ->constrained('units')
                ->nullOnDelete();

            $t->timestamp('visit_date')->nullable();

            $t->timestamps();

            $t->index(['unit_id', 'visit_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visits');
    }

};
