<?php

namespace Tests\Feature;

use App\Models\Assessment360Window;
use App\Models\AssessmentPeriod;
use App\Models\PerformanceCriteria;
use App\Models\Profession;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiRaterUnitHeadSelfUsesSupervisorTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_unit_head_can_rate_self_as_supervisor_bucket(): void
    {
        $period = AssessmentPeriod::query()->create([
            'name' => 'Periode Uji',
            'start_date' => now()->subDays(5)->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            'status' => AssessmentPeriod::STATUS_ACTIVE,
        ]);

        Assessment360Window::query()->create([
            'assessment_period_id' => $period->id,
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'is_active' => true,
        ]);

        $criteria = PerformanceCriteria::query()->create([
            'name' => 'Kedisiplinan (360)',
            'type' => 'benefit',
            'input_method' => '360',
            'is_360' => true,
            'is_active' => true,
        ]);

        $roleUnitHead = Role::query()->create([
            'slug' => User::ROLE_KEPALA_UNIT,
            'name' => 'Kepala Unit',
        ]);

        $roleMedicalStaff = Role::query()->create([
            'slug' => User::ROLE_PEGAWAI_MEDIS,
            'name' => 'Pegawai Medis',
        ]);

        $profession = Profession::query()->create([
            'name' => 'Dokter',
            'code' => 'DOK',
            'description' => 'Test',
            'is_active' => true,
        ]);

        $unit = Unit::query()->create([
            'name' => 'Poli Uji',
            'slug' => 'poli-uji',
            'code' => 'UNIT-TEST',
            'type' => 'poliklinik',
            'is_active' => true,
        ]);

        $user = User::factory()->create([
            'email_verified_at' => now(),
            'last_role' => User::ROLE_KEPALA_UNIT,
            'profession_id' => $profession->id,
            'unit_id' => $unit->id,
        ]);
        $user->roles()->attach([$roleUnitHead->id, $roleMedicalStaff->id]);

        $this->actingAs($user);

        $resp = $this->postJson(route('kepala_unit.multi_rater.store'), [
            'period_id' => $period->id,
            'rater_role' => 'kepala_unit',
            'target_user_id' => $user->id,
            'unit_id' => $unit->id,
            'score' => 80,
            'performance_criteria_id' => $criteria->id,
        ]);

        $resp->assertOk();
        $resp->assertJsonPath('ok', true);

        $this->assertDatabaseHas('multi_rater_assessments', [
            'assessee_id' => $user->id,
            'assessor_id' => $user->id,
            'assessor_type' => 'supervisor',
            'assessment_period_id' => $period->id,
            'status' => 'in_progress',
        ]);
    }
}
