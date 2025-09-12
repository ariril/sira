<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('professions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();      // contoh: Doctor, Nurse
            $table->string('code')->unique();      // contoh: DOK, NRS
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('profession_id');
        });
        Schema::dropIfExists('professions');
    }

};
