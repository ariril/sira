<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_periods', function (Blueprint $table) {
            $table->id();
            $table->string('name');                  // nama_periode
            $table->date('start_date');              // tanggal_mulai
            $table->date('end_date');                // tanggal_akhir
            // Status lifecycle: draft -> active -> (locked|closed)
            $table->enum('status', ['draft','active','locked','closed'])->default('draft');
            // Lock / Close metadata
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('locked_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_periods');
    }

};
