<?php

namespace Tests\Feature;

use App\Models\AssessmentPeriod;
use App\Models\Profession;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AssessmentPeriodWeightsAutoActivateTest extends TestCase
{
    use RefreshDatabase;

    public function test_close_period_promotes_draft_rater_weights_to_active(): void
    {
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

        User::factory()->create([
            'last_role' => User::ROLE_PEGAWAI_MEDIS,
            'unit_id' => $unit->id,
            'profession_id' => $profession->id,
            'email_verified_at' => now(),
        ]);

        $period = AssessmentPeriod::query()->create([
            'name' => 'Test Period',
            'start_date' => '2025-11-01',
            'end_date' => '2025-11-30',
            'status' => AssessmentPeriod::STATUS_ACTIVE,
        ]);

        $criteriaId = (int) DB::table('performance_criterias')->insertGetId([
            'name' => 'Kedisiplinan (360)',
            'type' => 'benefit',
            'description' => null,
            'is_active' => 1,
            'data_type' => 'numeric',
            'input_method' => '360',
            'source' => 'assessment_360',
            'is_360' => 1,
            'aggregation_method' => 'avg',
            'normalization_basis' => 'total_unit',
            'custom_target_value' => null,
            'suggested_weight' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('unit_criteria_weights')->insert([
            'unit_id' => $unit->id,
            'performance_criteria_id' => $criteriaId,
            'weight' => 50.0,
            'assessment_period_id' => $period->id,
            'status' => 'draft',
            'was_active_before' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('unit_rater_weights')->insert([
            'assessment_period_id' => $period->id,
            'unit_id' => $unit->id,
            'performance_criteria_id' => $criteriaId,
            'assessee_profession_id' => $profession->id,
            'assessor_type' => 'self',
            'assessor_profession_id' => null,
            'assessor_level' => null,
            'weight' => 100.0,
            'status' => 'draft',
            'was_active_before' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $period->lock();

        $this->assertTrue(DB::table('unit_criteria_weights')
            ->where('assessment_period_id', $period->id)
            ->where('unit_id', $unit->id)
            ->where('performance_criteria_id', $criteriaId)
            ->where('status', 'active')
            ->exists());

        $this->assertTrue(DB::table('unit_rater_weights')
            ->where('assessment_period_id', $period->id)
            ->where('unit_id', $unit->id)
            ->where('performance_criteria_id', $criteriaId)
            ->where('status', 'active')
            ->exists());
    }

    public function test_close_period_throws_when_no_weights_anywhere(): void
    {
        $unit = Unit::query()->create([
            'name' => 'Unit B',
            'slug' => 'unit-b',
            'type' => 'poliklinik',
            'is_active' => true,
        ]);

        $profession = Profession::query()->create([
            'name' => 'Perawat',
            'code' => 'PRW',
            'is_active' => true,
        ]);

        User::factory()->create([
            'last_role' => User::ROLE_PEGAWAI_MEDIS,
            'unit_id' => $unit->id,
            'profession_id' => $profession->id,
            'email_verified_at' => now(),
        ]);

        $period = AssessmentPeriod::query()->create([
            'name' => 'Empty Period',
            'start_date' => '2025-10-01',
            'end_date' => '2025-10-31',
            'status' => AssessmentPeriod::STATUS_ACTIVE,
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Periode tidak dapat diproses karena bobot kinerja tidak tersedia dan tidak ditemukan bobot aktif pada periode sebelumnya.');

        $period->lock();
    }
}
