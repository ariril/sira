<?php

namespace Tests\Feature;

use App\Models\AssessmentApproval;
use App\Models\AssessmentPeriod;
use App\Models\PerformanceAssessment;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminRsAssessmentPendingFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_period_filter_all_shows_pending_l1_from_any_period(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-12-16 10:00:00'));

        $adminRole = Role::query()->create([
            'slug' => User::ROLE_ADMINISTRASI,
            'name' => 'Admin RS',
        ]);

        $admin = User::factory()->create([
            'last_role' => User::ROLE_ADMINISTRASI,
        ]);
        $admin->roles()->attach($adminRole->id);

        $assessedUser = User::factory()->create();

        $novPeriod = AssessmentPeriod::query()->create([
            'name' => 'November 2025',
            'start_date' => '2025-11-01',
            'end_date' => '2025-11-30',
            'status' => AssessmentPeriod::STATUS_DRAFT,
        ]);

        $decPeriod = AssessmentPeriod::query()->create([
            'name' => 'December 2025',
            'start_date' => '2025-12-01',
            'end_date' => '2025-12-31',
            'status' => AssessmentPeriod::STATUS_ACTIVE,
        ]);

        $paNov = PerformanceAssessment::query()->create([
            'user_id' => $assessedUser->id,
            'assessment_period_id' => $novPeriod->id,
            'assessment_date' => '2025-11-30',
            'total_wsm_score' => 100.00,
            'validation_status' => 'Menunggu Validasi',
        ]);

        AssessmentApproval::query()->create([
            'performance_assessment_id' => $paNov->id,
            'approver_id' => $admin->id,
            'level' => 1,
            'status' => 'pending',
        ]);

        // When user explicitly selects (Semua), the browser sends `period_id=`.
        // This must NOT fall back to the active period, otherwise pending from other periods gets hidden.
        $this->actingAs($admin)
            ->get('/admin-rs/assessments/pending?period_id=&status=pending_l1')
            ->assertStatus(200)
            ->assertSee('November 2025')
            ->assertSee($assessedUser->name);

        // Sanity: if user selects a specific period, it should filter.
        $this->actingAs($admin)
            ->get('/admin-rs/assessments/pending?period_id=' . $decPeriod->id . '&status=pending_l1')
            ->assertStatus(200)
            ->assertDontSee($assessedUser->name);
    }
}
