<?php

namespace Tests\Feature;

use App\Models\Assessment360Window;
use App\Models\AssessmentPeriod;
use App\Models\PerformanceCriteria;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiRaterSimpleFormStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_simple_form_store_allows_self_and_persists_to_assessment_details(): void
    {
        $period = AssessmentPeriod::query()->create([
            'name' => 'Periode Uji',
            'start_date' => now()->subDays(5)->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            'status' => AssessmentPeriod::STATUS_ACTIVE,
        ]);

        Assessment360Window::query()->create([
            'assessment_period_id' => $period->id,
            'start_date' => now()->subDay(),
            'end_date' => now()->addDay(),
            'is_active' => true,
        ]);

        $criteria = PerformanceCriteria::query()->create([
            'name' => 'Kedisiplinan (360)',
            'type' => 'benefit',
            'input_method' => '360',
            'is_360' => true,
            'is_active' => true,
        ]);

        $role = Role::query()->create([
            'slug' => User::ROLE_PEGAWAI_MEDIS,
            'name' => 'Pegawai Medis',
        ]);

        $user = User::factory()->create([
            'email_verified_at' => now(),
            'last_role' => User::ROLE_PEGAWAI_MEDIS,
        ]);
        $user->roles()->attach($role->id);

        $this->actingAs($user);

        $resp = $this->postJson(route('pegawai_medis.multi_rater.store'), [
            'period_id' => $period->id,
            'target_user_id' => $user->id,
            'score' => 80,
            'performance_criteria_id' => $criteria->id,
        ]);

        $resp->assertOk();
        $resp->assertJsonPath('ok', true);

        $this->assertDatabaseHas('multi_rater_assessments', [
            'assessee_id' => $user->id,
            'assessor_id' => $user->id,
            'assessor_type' => 'self',
            'assessment_period_id' => $period->id,
        ]);

        $assessmentId = \DB::table('multi_rater_assessments')->where([
            'assessee_id' => $user->id,
            'assessor_id' => $user->id,
            'assessor_type' => 'self',
            'assessment_period_id' => $period->id,
        ])->value('id');

        $this->assertDatabaseHas('multi_rater_assessment_details', [
            'multi_rater_assessment_id' => $assessmentId,
            'performance_criteria_id' => $criteria->id,
            'score' => 80,
        ]);
    }
}
