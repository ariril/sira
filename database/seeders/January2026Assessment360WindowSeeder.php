<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Carbon;

class January2026Assessment360WindowSeeder extends Seeder
{
    public function run(): void
    {
        if (!Schema::hasTable('assessment_periods') || !Schema::hasTable('assessment_360_windows')) {
            return;
        }

        $period = DB::table('assessment_periods')
            ->whereIn('name', ['Januari 2026', 'January 2026'])
            ->orderByDesc('start_date')
            ->first(['id', 'start_date', 'end_date']);

        if (!$period) {
            return;
        }

        $periodId = (int) $period->id;

        $exists = DB::table('assessment_360_windows')
            ->where('assessment_period_id', $periodId)
            ->exists();

        if ($exists) {
            // Do not override existing configuration.
            return;
        }

        $now = Carbon::now();

        $openedBy = null;
        if (Schema::hasTable('users')) {
            $openedBy = DB::table('users')->where('email', 'admin.rs@rsud.local')->value('id');
            $openedBy = $openedBy ? (int) $openedBy : null;
        }

        DB::table('assessment_360_windows')->insert([
            'assessment_period_id' => $periodId,
            'start_date' => (string) $period->start_date,
            'end_date' => (string) $period->end_date,
            'is_active' => true,
            'opened_by' => $openedBy,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
