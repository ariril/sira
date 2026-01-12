<?php

namespace Tests\Feature;

use App\Models\Assessment360Window;
use App\Models\AssessmentPeriod;
use App\Models\MultiRaterAssessment;
use App\Models\MultiRaterAssessmentDetail;
use App\Models\PerformanceCriteria;
use App\Models\User;
use App\Services\AssessmentPeriods\AssessmentPeriodLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiRaterFinalizeAfterWindowEndTest extends TestCase
{
    use RefreshDatabase;

    public function test_lifecycle_marks_in_progress_with_details_as_submitted_after_window_end(): void
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

        $criteria = PerformanceCriteria::query()->create([
            'name' => 'Kedisiplinan (360)',
            'type' => 'benefit',
            'input_method' => '360',
            'is_360' => true,
            'is_active' => true,
        ]);

        $assessment = MultiRaterAssessment::query()->create([
            'assessee_id' => $assessee->id,
            'assessor_id' => $assessor->id,
            'assessor_profession_id' => null,
            'assessor_type' => 'peer',
            'assessment_period_id' => $period->id,
            'status' => 'in_progress',
            'submitted_at' => null,
        ]);

        MultiRaterAssessmentDetail::query()->create([
            'multi_rater_assessment_id' => $assessment->id,
            'performance_criteria_id' => $criteria->id,
            'score' => 80,
        ]);

        app(AssessmentPeriodLifecycleService::class)->sync();

        $assessment->refresh();
        $this->assertSame('submitted', $assessment->status);
        $this->assertNotNull($assessment->submitted_at);
    }
}
