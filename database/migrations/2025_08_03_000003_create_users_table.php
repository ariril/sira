<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('employee_number')->unique()->nullable();
            $table->string('name')->nullable();
            $table->date('start_date')->nullable();
            $table->string('gender', 10)->nullable();
            $table->string('nationality', 50)->nullable();
            $table->text('address')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email')->unique();
            $table->string('last_education', 50)->nullable();
            $table->string('position')->nullable();

            $table->foreignId('unit_id')->nullable()
                ->constrained('units')->nullOnDelete();

            $table->foreignId('profession_id')->nullable()
                ->constrained('professions')->nullOnDelete();

            $table->string('password');

            $table->enum('role', ['pegawai_medis', 'kepala_unit', 'kepala_poliklinik', 'admin_rs', 'super_admin'])
                ->default('pegawai_medis')->nullable();

            $table->index('name');

            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->index();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
