<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\UploadedFile;
use App\Models\User;
use App\Models\AdditionalTask;
use App\Models\AdditionalTaskClaim;
use App\Models\AssessmentPeriod;
use App\Models\Role;

class AdditionalTaskClaimTest extends TestCase
{
    use RefreshDatabase;

    private function makeActivePeriod(): AssessmentPeriod
    {
        return AssessmentPeriod::query()->firstOrCreate([
            'name' => 'Periode Test',
            'start_date' => now()->startOfMonth()->toDateString(),
            'end_date' => now()->endOfMonth()->toDateString(),
        ], [
            'status' => 'active',
        ]);
    }

    private function makeRole(string $slug): Role
    {
        return Role::firstOrCreate(['slug' => $slug], ['name' => ucfirst(str_replace('_',' ', $slug))]);
    }

    private function makeUser(string $roleSlug, int $unitId = 1): User
    {
        // Ensure unit exists for FK
        if (!\DB::table('units')->where('id', $unitId)->exists()) {
            \DB::table('units')->insert([
                'id' => $unitId,
                'name' => 'Unit '.$unitId,
                'slug' => 'unit-'.$unitId,
                'type' => 'poliklinik',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        $role = $this->makeRole($roleSlug);
        $user = User::factory()->create(['unit_id' => $unitId]);
        DB::table('role_user')->insert(['user_id' => $user->id, 'role_id' => $role->id]);
        return $user->fresh();
    }

    private function makeTask(int $unitId, array $overrides = []): AdditionalTask
    {
        $period = $this->makeActivePeriod();

        return AdditionalTask::create(array_merge([
            'unit_id' => $unitId,
            'assessment_period_id' => $period->id,
            'title' => 'Tugas A',
            'description' => null,
            'start_date' => now()->toDateString(),
            'due_date' => now()->addDay()->toDateString(),
            'bonus_amount' => null,
            'points' => null,
            'max_claims' => 1,
            'status' => 'open',
            'created_by' => $this->makeUser('kepala_unit', $unitId)->id,
        ], $overrides));
    }

    public function test_single_claim_prevents_second_claim(): void
    {
        $pegawai1 = $this->makeUser('pegawai_medis');
        $pegawai2 = $this->makeUser('pegawai_medis');
        $task = $this->makeTask($pegawai1->unit_id);

        $this->actingAs($pegawai1)
            ->post(route('pegawai_medis.additional_tasks.claim', $task->id))
            ->assertRedirect();
        $this->actingAs($pegawai2)
            ->post(route('pegawai_medis.additional_tasks.claim', $task->id))
            ->assertRedirect();

        $this->assertEquals(1, AdditionalTaskClaim::count(), 'Only one active claim should exist');
    }

    public function test_cancel_after_deadline_sets_violation(): void
    {
        $pegawai = $this->makeUser('pegawai_medis');
        $task = $this->makeTask($pegawai->unit_id);
        $claim = AdditionalTaskClaim::create([
            'additional_task_id' => $task->id,
            'user_id' => $pegawai->id,
            'status' => 'active',
            'claimed_at' => now()->subDay(),
            'cancel_deadline_at' => now()->subHours(2),
            'penalty_type' => 'none',
            'penalty_value' => 0,
        ]);

        $this->actingAs($pegawai)
            ->post(route('pegawai_medis.additional_task_claims.cancel', $claim->id))
            ->assertRedirect();

        $claim->refresh();
        $this->assertEquals('cancelled', $claim->status);
        $this->assertTrue((bool)$claim->is_violation);
        $this->assertFalse((bool)$claim->penalty_applied);
    }

    public function test_full_approval_flow(): void
    {
        $pegawai = $this->makeUser('pegawai_medis');
        $kepala = $this->makeUser('kepala_unit', $pegawai->unit_id);
        $task = $this->makeTask($pegawai->unit_id);
        $claim = AdditionalTaskClaim::create([
            'additional_task_id' => $task->id,
            'user_id' => $pegawai->id,
            'status' => 'active',
            'claimed_at' => now(),
            'cancel_deadline_at' => now()->addDay(),
            'penalty_type' => 'none',
            'penalty_value' => 0,
        ]);

        // Submit
        $this->actingAs($pegawai)
            ->post(route('pegawai_medis.additional_task_claims.submit', $claim->id), [
                'note' => 'Hasil pekerjaan terlampir.',
                'result_file' => UploadedFile::fake()->create('hasil.docx', 50, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
            ])
            ->assertRedirect();
        $claim->refresh();
        $this->assertEquals('submitted', $claim->status);

        // Validate

        // Approve
        $this->actingAs($kepala)
            ->post(route('kepala_unit.additional_task_claims.review_update', $claim->id), ['action' => 'approve'])
            ->assertRedirect();
        $claim->refresh();
        $this->assertEquals('approved', $claim->status);
        $this->assertNotNull($claim->completed_at);
    }

    public function test_submit_result_accepts_pdf(): void
    {
        $pegawai = $this->makeUser('pegawai_medis');
        $task = $this->makeTask($pegawai->unit_id);
        $claim = AdditionalTaskClaim::create([
            'additional_task_id' => $task->id,
            'user_id' => $pegawai->id,
            'status' => 'active',
            'claimed_at' => now(),
            'cancel_deadline_at' => now()->addDay(),
            'penalty_type' => 'none',
            'penalty_value' => 0,
        ]);

        $this->actingAs($pegawai)
            ->post(route('pegawai_medis.additional_task_claims.submit', $claim->id), [
                'note' => 'Hasil PDF terlampir.',
                'result_file' => UploadedFile::fake()->create('hasil.pdf', 100, 'application/pdf'),
            ])
            ->assertRedirect();

        $claim->refresh();
        $this->assertEquals('submitted', $claim->status);
        $this->assertNotEmpty($claim->result_file_path);
    }
}
