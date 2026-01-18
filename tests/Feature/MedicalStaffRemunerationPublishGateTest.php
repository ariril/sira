<?php

namespace Tests\Feature;

use App\Enums\AssessmentValidationStatus;
use App\Models\AssessmentPeriod;
use App\Models\PerformanceAssessment;
use App\Models\Profession;
use App\Models\Remuneration;
use App\Models\Role;
use App\Models\Unit;
use App\Models\UnitRemunerationAllocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MedicalStaffRemunerationPublishGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_medical_staff_only_sees_published_and_cannot_open_draft_detail(): void
    {
        $pegawaiRole = Role::query()->create([
            'slug' => User::ROLE_PEGAWAI_MEDIS,
            'name' => 'Pegawai Medis',
        ]);

        $adminRole = Role::query()->create([
            'slug' => User::ROLE_ADMINISTRASI,
            'name' => 'Admin RS',
        ]);

        $admin = User::factory()->create([
            'last_role' => User::ROLE_ADMINISTRASI,
            'email_verified_at' => now(),
        ]);
        $admin->roles()->attach($adminRole->id);

        $unit = Unit::query()->create([
            'name' => 'Unit Test',
            'slug' => 'unit-test',
            'type' => 'poliklinik',
            'is_active' => true,
        ]);

        $profession = Profession::query()->create([
            'name' => 'Dokter',
            'code' => 'DOK',
            'is_active' => true,
        ]);

        $pegawai = User::factory()->create([
            'last_role' => User::ROLE_PEGAWAI_MEDIS,
            'email_verified_at' => now(),
            'unit_id' => $unit->id,
            'profession_id' => $profession->id,
        ]);
        $pegawai->roles()->attach($pegawaiRole->id);

        $period = AssessmentPeriod::query()->create([
            'name' => 'December 2025',
            'start_date' => '2025-12-01',
            'end_date' => '2025-12-31',
            'status' => AssessmentPeriod::STATUS_LOCKED,
            'approval_attempt' => 1,
        ]);

        PerformanceAssessment::query()->create([
            'user_id' => $pegawai->id,
            'assessment_period_id' => $period->id,
            'assessment_date' => '2025-12-31',
            'total_wsm_score' => 100.0,
            'total_wsm_value_score' => 100.0,
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

        $draft = Remuneration::query()->create([
            'user_id' => $pegawai->id,
            'assessment_period_id' => $period->id,
            'amount' => 123.45,
            'payment_status' => 'Belum Dibayar',
            'published_at' => null,
            'calculated_at' => now(),
        ]);

        // List must not include draft remunerations.
        $this->actingAs($pegawai)
            ->withSession(['active_role' => User::ROLE_PEGAWAI_MEDIS])
            ->get(route('pegawai_medis.remunerations.index'))
            ->assertOk()
            ->assertDontSee(route('pegawai_medis.remunerations.show', $draft->id))
            ->assertSee('Belum ada data remunerasi.')
            ->assertSee('Status Proses Penilaian & Remunerasi')
            ->assertSee('Remunerasi Anda telah dihitung');

        // Draft detail must be inaccessible.
        $this->actingAs($pegawai)
            ->withSession(['active_role' => User::ROLE_PEGAWAI_MEDIS])
            ->get(route('pegawai_medis.remunerations.show', $draft->id))
            ->assertStatus(404);

        // Publish -> should appear in list and detail.
        $draft->update(['published_at' => now()]);

        $this->actingAs($pegawai)
            ->withSession(['active_role' => User::ROLE_PEGAWAI_MEDIS])
            ->get(route('pegawai_medis.remunerations.index'))
            ->assertOk()
            ->assertSee('December 2025')
            ->assertSee(route('pegawai_medis.remunerations.show', $draft->id));

        $this->actingAs($pegawai)
            ->withSession(['active_role' => User::ROLE_PEGAWAI_MEDIS])
            ->get(route('pegawai_medis.remunerations.show', $draft->id))
            ->assertOk();
    }
}
