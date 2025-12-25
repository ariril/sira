<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use App\Models\Role;
use App\Models\User;
use App\Models\AdditionalTask;
use App\Models\AdditionalTaskClaim;

class AdditionalTaskFlowSanityTest extends TestCase
{
    use RefreshDatabase;

    private function makeRole(string $slug): Role
    {
        return Role::firstOrCreate(['slug' => $slug], ['name' => ucfirst(str_replace('_', ' ', $slug))]);
    }

    private function attachRole(User $user, string $slug): void
    {
        $role = $this->makeRole($slug);
        DB::table('role_user')->insert(['user_id' => $user->id, 'role_id' => $role->id]);
        $user->unsetRelation('roles');
    }

    private function ensureUnit(int $unitId = 1): void
    {
        if (!DB::table('units')->where('id', $unitId)->exists()) {
            DB::table('units')->insert([
                'id' => $unitId,
                'name' => 'Unit '.$unitId,
                'slug' => 'unit-'.$unitId,
                'type' => 'poliklinik',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function ensureActivePeriod(): int
    {
        $today = Carbon::today()->toDateString();
        $row = DB::table('assessment_periods')->where('status', 'active')->first();
        if ($row) return (int) $row->id;

        return (int) DB::table('assessment_periods')->insertGetId([
            'name' => 'Desember 2025',
            'start_date' => $today,
            'end_date' => $today,
            'status' => 'active',
            'locked_at' => null,
            'closed_at' => null,
            'notes' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function ensureTimezone(string $tz = 'Asia/Jakarta'): void
    {
        config(['app.timezone' => $tz]);
        date_default_timezone_set($tz);

        // minimal site settings row so AppServiceProvider logic (if triggered) has a value
        if (DB::table('site_settings')->count() === 0) {
            DB::table('site_settings')->insert([
                'name' => 'RS Test',
                'short_name' => 'RST',
                'short_description' => null,
                'address' => null,
                'phone' => null,
                'email' => null,
                'logo_path' => null,
                'favicon_path' => null,
                'hero_path' => null,
                'facebook_url' => null,
                'instagram_url' => null,
                'twitter_url' => null,
                'youtube_url' => null,
                'footer_text' => null,
                'timezone' => $tz,
                'updated_by' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function test_end_to_end_flow_pages_and_statuses(): void
    {
        $this->ensureUnit(1);
        $this->ensureTimezone('Asia/Jakarta');

        Carbon::setTestNow(Carbon::create(2025, 12, 24, 15, 17, 0, 'Asia/Jakarta'));

        $periodId = $this->ensureActivePeriod();
        $this->assertNotNull($periodId);

        // User A: kepala_unit + pegawai_medis (switch role) => creator
        $creator = User::factory()->create(['unit_id' => 1]);
        $this->attachRole($creator, 'kepala_unit');
        $this->attachRole($creator, 'pegawai_medis');

        // User B: pegawai medis biasa
        $pegawai = User::factory()->create(['unit_id' => 1]);
        $this->attachRole($pegawai, 'pegawai_medis');

        // Create task (periode otomatis, start_time default now)
        $resp = $this->actingAs($creator)
            ->withSession(['active_role' => 'kepala_unit'])
            ->post(route('kepala_unit.additional-tasks.store'), [
                'title' => 'Pembuatan Buku TBC',
                'description' => 'Test',
                'start_date' => Carbon::today('Asia/Jakarta')->toDateString(),
                // start_time omitted intentionally (should default to now)
                'due_date' => Carbon::today('Asia/Jakarta')->toDateString(),
                'due_time' => '23:59',
                'points' => 10,
                'max_claims' => 1,
            ]);
        $resp->assertRedirect();

        $task = AdditionalTask::query()->latest('id')->first();
        $this->assertNotNull($task);
        $this->assertEquals('open', $task->status);
        $this->assertEquals($periodId, (int) $task->assessment_period_id);
        $this->assertEquals($creator->id, (int) $task->created_by);

        // Creator cannot claim own task (even if role switched)
        $this->actingAs($creator)
            ->withSession(['active_role' => 'pegawai_medis'])
            ->post(route('pegawai_medis.additional_tasks.claim', $task->id))
            ->assertRedirect();
        $this->assertEquals(0, AdditionalTaskClaim::count());

        // Pegawai claims
        $this->actingAs($pegawai)
            ->withSession(['active_role' => 'pegawai_medis'])
            ->post(route('pegawai_medis.additional_tasks.claim', $task->id))
            ->assertRedirect();

        $claim = AdditionalTaskClaim::query()->where('additional_task_id', $task->id)->first();
        $this->assertNotNull($claim);
        $this->assertEquals('active', $claim->status);

        // Submit result => submitted
        $this->actingAs($pegawai)
            ->withSession(['active_role' => 'pegawai_medis'])
            ->post(route('pegawai_medis.additional_task_claims.submit', $claim->id), [
                'note' => 'Terlampir',
                'result_file' => UploadedFile::fake()->create('hasil.docx', 50, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
            ])
            ->assertRedirect();
        $claim->refresh();
        $this->assertEquals('submitted', $claim->status);

        // Sanity: unit head pages render and show non-ambiguous labels
        $this->actingAs($creator)
            ->withSession(['active_role' => 'kepala_unit'])
            ->get(route('kepala_unit.additional-tasks.index'))
            ->assertOk()
            ->assertSee('Menunggu Review');

        $this->actingAs($creator)
            ->withSession(['active_role' => 'kepala_unit'])
            ->get(route('kepala_unit.additional_task_claims.index', ['status' => 'submitted']))
            ->assertOk()
            ->assertSee('Menunggu Validasi');

        // Review page should not require a separate "Tandai Valid" step anymore
        $this->actingAs($creator)
            ->withSession(['active_role' => 'kepala_unit'])
            ->get(route('kepala_unit.additional_task_claims.review_index'))
            ->assertOk()
            ->assertDontSee('Tandai Valid');

        // Review validate -> validated

        // Review approve -> approved
        $this->actingAs($creator)
            ->withSession(['active_role' => 'kepala_unit'])
            ->post(route('kepala_unit.additional_task_claims.review_update', $claim->id), ['action' => 'approve'])
            ->assertRedirect();
        $claim->refresh();
        $this->assertEquals('approved', $claim->status);

        Carbon::setTestNow();
    }
}
