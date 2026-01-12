<?php

namespace Tests\Feature;

use App\Models\Assessment360Window;
use App\Models\AssessmentPeriod;
use App\Models\MultiRaterAssessment;
use App\Models\User;
use App\Services\AssessmentPeriods\AssessmentPeriodLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiRaterInvitedBecomesCancelledAfterWindowEndTest extends TestCase
{
    use RefreshDatabase;

    public function test_lifecycle_cancels_invited_after_window_end(): void
    {
        $period = AssessmentPeriod::query()->create([
            'name' => 'Periode Uji',
            'start_date' => now()->subDays(5)->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            'status' => AssessmentPeriod::STATUS_ACTIVE,
        ]);

        Assessment360Window::query()->create([
            'assessment_period_id' => $period->id,
            'start_date' => now()->subDays(3)->toDateString(),
            'end_date' => now()->subDay()->toDateString(),
            'is_active' => true,
        ]);

        $assessee = User::factory()->create();
        $assessor = User::factory()->create();

        $assessment = MultiRaterAssessment::query()->create([
            'assessee_id' => $assessee->id,
            'assessor_id' => $assessor->id,
            'assessor_profession_id' => null,
            'assessor_type' => 'peer',
            'assessment_period_id' => $period->id,
            'status' => 'invited',
            'submitted_at' => null,
        ]);

        app(AssessmentPeriodLifecycleService::class)->sync();

        $assessment->refresh();
        $this->assertSame('cancelled', $assessment->status);
        $this->assertNull($assessment->submitted_at);
    }
}
