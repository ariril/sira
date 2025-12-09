<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE unit_criteria_weights MODIFY status ENUM('draft','pending','active','rejected','archived') NOT NULL DEFAULT 'draft'");
    }

    public function down(): void
    {
        DB::statement("UPDATE unit_criteria_weights SET status='active' WHERE status='archived'");
        DB::statement("ALTER TABLE unit_criteria_weights MODIFY status ENUM('draft','pending','active','rejected') NOT NULL DEFAULT 'draft'");
    }
};
