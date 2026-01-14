<?php

namespace Tests\Feature;

use App\Enums\AssessmentValidationStatus;
use App\Models\AssessmentPeriod;
use App\Models\PerformanceAssessment;
use App\Models\Remuneration;
use App\Models\Role;
use App\Models\Unit;
use App\Models\Profession;
use App\Models\UnitRemunerationAllocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RemunerationFrozenSnapshotInvarianceTest extends TestCase
{
    use RefreshDatabase;

    public function test_frozen_period_uses_snapshot_membership_and_snapshot_mode(): void
    {
        $adminRole = Role::query()->create([
            'slug' => User::ROLE_ADMINISTRASI,
            'name' => 'Admin RS',
        ]);

        $pegawaiRole = Role::query()->create([
            'slug' => User::ROLE_PEGAWAI_MEDIS,
            'name' => 'Pegawai Medis',
        ]);

        $admin = User::factory()->create([
            'last_role' => User::ROLE_ADMINISTRASI,
            'email_verified_at' => now(),
        ]);
        $admin->roles()->attach($adminRole->id);

        $unit = Unit::query()->create([
            'name' => 'Unit A',
            'slug' => 'unit-a',
            'type' => 'poliklinik',
            'is_active' => true,
        ]);

        $profession = Profession::query()->create([
            'name' => 'Dokter',
            'code' => 'DOK',
            'is_active' => true,
        ]);

        $u1 = User::factory()->create([
            'last_role' => User::ROLE_PEGAWAI_MEDIS,
            'email_verified_at' => now(),
            'unit_id' => $unit->id,
            'profession_id' => $profession->id,
        ]);
        $u1->roles()->attach($pegawaiRole->id);

        $u2 = User::factory()->create([
            'last_role' => User::ROLE_PEGAWAI_MEDIS,
            'email_verified_at' => now(),
            'unit_id' => $unit->id,
            'profession_id' => $profession->id,
        ]);
        $u2->roles()->attach($pegawaiRole->id);

        $period = AssessmentPeriod::query()->create([
            'name' => 'Frozen Period',
            'start_date' => '2025-11-01',
            'end_date' => '2025-11-30',
            'status' => AssessmentPeriod::STATUS_LOCKED,
        ]);

        PerformanceAssessment::query()->create([
            'user_id' => $u1->id,
            'assessment_period_id' => $period->id,
            'assessment_date' => '2025-11-30',
            'total_wsm_score' => 100.00,
            'total_wsm_value_score' => 100.00,
            'validation_status' => AssessmentValidationStatus::VALIDATED->value,
        ]);

        PerformanceAssessment::query()->create([
            'user_id' => $u2->id,
            'assessment_period_id' => $period->id,
            'assessment_date' => '2025-11-30',
            'total_wsm_score' => 50.00,
            'total_wsm_value_score' => 50.00,
            'validation_status' => AssessmentValidationStatus::VALIDATED->value,
        ]);

        UnitRemunerationAllocation::query()->create([
            'assessment_period_id' => $period->id,
            'unit_id' => $unit->id,
            'profession_id' => null,
            'amount' => 1000.00,
            'published_at' => now(),
            'revised_by' => $admin->id,
        ]);

        // Snapshot membership (unit/profession) + snapshot calculation basis/totals.
        $now = now();
        DB::table('assessment_period_user_membership_snapshots')->insert([
            [
                'assessment_period_id' => (int) $period->id,
                'user_id' => (int) $u1->id,
                'unit_id' => (int) $unit->id,
                'profession_id' => (int) $profession->id,
                'snapshotted_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'assessment_period_id' => (int) $period->id,
                'user_id' => (int) $u2->id,
                'unit_id' => (int) $unit->id,
                'profession_id' => (int) $profession->id,
                'snapshotted_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        DB::table('performance_assessment_snapshots')->insert([
            [
                'assessment_period_id' => (int) $period->id,
                'user_id' => (int) $u1->id,
                'payload' => json_encode([
                    'version' => 1,
                    'calc' => [
                        // Force ATTACHED decision from snapshot (even if live config is missing/changed).
                        'basis_by_criteria' => [1 => 'average_unit'],
                        'total_wsm_relative' => 100.0,
                        'total_wsm_value' => 100.0,
                        'user' => [
                            'total_wsm_relative' => 100.0,
                            'total_wsm_value' => 100.0,
                        ],
                    ],
                ], JSON_UNESCAPED_UNICODE),
                'snapshotted_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'assessment_period_id' => (int) $period->id,
                'user_id' => (int) $u2->id,
                'payload' => json_encode([
                    'version' => 1,
                    'calc' => [
                        'basis_by_criteria' => [1 => 'average_unit'],
                        'total_wsm_relative' => 50.0,
                        'total_wsm_value' => 50.0,
                        'user' => [
                            'total_wsm_relative' => 50.0,
                            'total_wsm_value' => 50.0,
                        ],
                    ],
                ], JSON_UNESCAPED_UNICODE),
                'snapshotted_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        // First run: should use snapshot mode=ATTACHED and snapshot membership headcount=2.
        $this->actingAs($admin)
            ->post('/admin-rs/remunerations/calc/run', ['period_id' => $period->id])
            ->assertRedirect('/admin-rs/remunerations/calc?period_id=' . $period->id);

        $r1 = Remuneration::query()->where('assessment_period_id', $period->id)->where('user_id', $u1->id)->firstOrFail();
        $r2 = Remuneration::query()->where('assessment_period_id', $period->id)->where('user_id', $u2->id)->firstOrFail();

        // allocation=1000; headcount=2 => remunMax=500; payout%=100/50 => 500 / 250 (leftover 250)
        $this->assertEquals(500.00, (float) $r1->amount);
        $this->assertEquals(250.00, (float) $r2->amount);

        // Mutate LIVE membership: move u2 away. Frozen calculation must remain stable.
        $unitB = Unit::query()->create([
            'name' => 'Unit B',
            'slug' => 'unit-b',
            'type' => 'poliklinik',
            'is_active' => true,
        ]);
        $u2->unit_id = $unitB->id;
        $u2->save();

        $this->actingAs($admin)
            ->post('/admin-rs/remunerations/calc/run', ['period_id' => $period->id])
            ->assertRedirect('/admin-rs/remunerations/calc?period_id=' . $period->id);

        $r1->refresh();
        $r2->refresh();
        $this->assertEquals(500.00, (float) $r1->amount);
        $this->assertEquals(250.00, (float) $r2->amount);
    }
}
