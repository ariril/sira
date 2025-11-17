<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('attendance_import_batches')->cascadeOnDelete();
            $table->unsignedInteger('row_no');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('employee_number')->nullable();
            $table->json('raw_data')->nullable();
            $table->json('parsed_data')->nullable();
            $table->boolean('success')->default(false);
            $table->string('error_code', 50)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['batch_id','success']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_import_rows');
    }
};
