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
            'due_date' => now()->addDay()->toDateString(),
            'due_time' => '23:59:59',
            'points' => 10,
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
            ->post(route('pegawai_medis.additional_tasks.submit', $task->id), [
                'note' => 'Submit 1',
            ])
            ->assertRedirect();
        $this->actingAs($pegawai2)
            ->post(route('pegawai_medis.additional_tasks.submit', $task->id), [
                'note' => 'Submit 2',
            ])
            ->assertRedirect();

        $this->assertEquals(1, AdditionalTaskClaim::count(), 'Only one claim should exist when max_claims=1');
    }

    public function test_late_submit_auto_rejects(): void
    {
        $pegawai = $this->makeUser('pegawai_medis');
        $task = $this->makeTask($pegawai->unit_id, [
            'due_date' => now()->subDay()->toDateString(),
            'due_time' => '00:00:00',
        ]);

        $this->actingAs($pegawai)
            ->post(route('pegawai_medis.additional_tasks.submit', $task->id), [
                'note' => 'Telat submit',
            ])
            ->assertRedirect();

        $claim = AdditionalTaskClaim::query()->where('additional_task_id', $task->id)->where('user_id', $pegawai->id)->first();
        $this->assertNotNull($claim);
        $this->assertEquals('rejected', $claim->status);
        $this->assertEquals('0.00', (string) $claim->awarded_points);
        $this->assertNotNull($claim->reviewed_at);
    }

    public function test_full_approval_flow(): void
    {
        $pegawai = $this->makeUser('pegawai_medis');
        $kepala = $this->makeUser('kepala_unit', $pegawai->unit_id);
        $task = $this->makeTask($pegawai->unit_id);

        // Submit (creates claim)
        $this->actingAs($pegawai)
            ->post(route('pegawai_medis.additional_tasks.submit', $task->id), [
                'note' => 'Hasil pekerjaan terlampir.',
                'result_file' => UploadedFile::fake()->create('hasil.docx', 50, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
            ])
            ->assertRedirect();

        $claim = AdditionalTaskClaim::query()->where('additional_task_id', $task->id)->where('user_id', $pegawai->id)->first();
        $this->assertNotNull($claim);
        $claim->refresh();
        $this->assertEquals('submitted', $claim->status);

        // Validate

        // Approve
        $this->actingAs($kepala)
            ->post(route('kepala_unit.additional_task_claims.review_update', $claim->id), ['action' => 'approve'])
            ->assertRedirect();
        $claim->refresh();
        $this->assertEquals('approved', $claim->status);
        $this->assertEquals((string) number_format((float) $task->points, 2, '.', ''), (string) $claim->awarded_points);
    }

    public function test_submit_result_accepts_pdf(): void
    {
        $pegawai = $this->makeUser('pegawai_medis');
        $task = $this->makeTask($pegawai->unit_id);
        $this->actingAs($pegawai)
            ->post(route('pegawai_medis.additional_tasks.submit', $task->id), [
                'note' => 'Hasil PDF terlampir.',
                'result_file' => UploadedFile::fake()->create('hasil.pdf', 100, 'application/pdf'),
            ])
            ->assertRedirect();

        $claim = AdditionalTaskClaim::query()->where('additional_task_id', $task->id)->where('user_id', $pegawai->id)->first();
        $this->assertNotNull($claim);

        $claim->refresh();
        $this->assertEquals('submitted', $claim->status);
        $this->assertNotEmpty($claim->result_file_path);
    }
}
