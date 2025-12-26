<?php

namespace Tests\Feature;

use App\Models\Profession;
use App\Models\Review;
use App\Models\ReviewDetail;
use App\Models\ReviewInvitation;
use App\Models\ReviewInvitationStaff;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicReviewInvitationTest extends TestCase
{
    use RefreshDatabase;

    public function test_invitation_link_shows_locked_fields_and_staff_list(): void
    {
        $unit = Unit::create([
            'name' => 'Poli Umum',
            'slug' => 'poli-umum',
            'code' => 'PU',
            'type' => 'poliklinik',
            'is_active' => true,
        ]);

        $profession = Profession::create(['name' => 'Dokter Umum', 'code' => 'DU', 'is_active' => true]);
        $role = Role::firstOrCreate(['slug' => User::ROLE_PEGAWAI_MEDIS], ['name' => 'Pegawai Medis']);

        $staff1 = User::factory()->create(['unit_id' => $unit->id, 'profession_id' => $profession->id, 'last_role' => User::ROLE_PEGAWAI_MEDIS]);
        $staff2 = User::factory()->create(['unit_id' => $unit->id, 'profession_id' => $profession->id, 'last_role' => User::ROLE_PEGAWAI_MEDIS]);
        $staff1->roles()->syncWithoutDetaching([$role->id]);
        $staff2->roles()->syncWithoutDetaching([$role->id]);

        $token = 'tok_' . rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $inv = ReviewInvitation::create([
            'registration_ref' => 'REG-1',
            'unit_id' => $unit->id,
            'patient_name' => 'Pasien A',
            'contact' => '081234',
            'token_hash' => hash('sha256', $token),
            'status' => 'sent',
            'expires_at' => Carbon::now()->addDays(2),
            'sent_at' => Carbon::now(),
        ]);

        ReviewInvitationStaff::insert([
            ['invitation_id' => $inv->id, 'user_id' => $staff1->id, 'role' => 'dokter', 'created_at' => now(), 'updated_at' => now()],
            ['invitation_id' => $inv->id, 'user_id' => $staff2->id, 'role' => 'dokter', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $resp = $this->get('/reviews/invite/' . $token);
        $resp->assertOk();
        $resp->assertSee('REG-1');
        $resp->assertSee('Poli Umum');
        $resp->assertSee($staff1->name);
        $resp->assertSee($staff2->name);
    }

    public function test_invitation_can_be_used_once_and_persists_ratings(): void
    {
        $unit = Unit::create([
            'name' => 'Poli Umum',
            'slug' => 'poli-umum',
            'code' => 'PU',
            'type' => 'poliklinik',
            'is_active' => true,
        ]);

        $profession = Profession::create(['name' => 'Dokter Umum', 'code' => 'DU', 'is_active' => true]);
        $role = Role::firstOrCreate(['slug' => User::ROLE_PEGAWAI_MEDIS], ['name' => 'Pegawai Medis']);

        $staff1 = User::factory()->create(['unit_id' => $unit->id, 'profession_id' => $profession->id, 'last_role' => User::ROLE_PEGAWAI_MEDIS]);
        $staff2 = User::factory()->create(['unit_id' => $unit->id, 'profession_id' => $profession->id, 'last_role' => User::ROLE_PEGAWAI_MEDIS]);
        $staff1->roles()->syncWithoutDetaching([$role->id]);
        $staff2->roles()->syncWithoutDetaching([$role->id]);

        $token = 'tok_' . rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $inv = ReviewInvitation::create([
            'registration_ref' => 'REG-2',
            'unit_id' => $unit->id,
            'patient_name' => 'Pasien B',
            'contact' => '081234',
            'token_hash' => hash('sha256', $token),
            'status' => 'sent',
            'expires_at' => Carbon::now()->addDays(2),
            'sent_at' => Carbon::now(),
        ]);

        ReviewInvitationStaff::insert([
            ['invitation_id' => $inv->id, 'user_id' => $staff1->id, 'role' => 'dokter', 'created_at' => now(), 'updated_at' => now()],
            ['invitation_id' => $inv->id, 'user_id' => $staff2->id, 'role' => 'dokter', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $payload = [
            'overall_rating' => 5,
            'comment' => 'Mantap',
            'details' => [
                ['user_id' => $staff1->id, 'rating' => 5, 'comment' => 'Bagus'],
                ['user_id' => $staff2->id, 'rating' => 4, 'comment' => 'Cukup'],
            ],
        ];

        $resp = $this->post('/reviews/invite/' . $token, $payload);
        $resp->assertRedirect('/reviews/thanks');

        $inv->refresh();
        $this->assertSame('used', $inv->status);
        $this->assertNotNull($inv->used_at);

        $this->assertDatabaseHas('reviews', [
            'registration_ref' => 'REG-2',
            'unit_id' => $unit->id,
        ]);

        $review = Review::query()->where('registration_ref', 'REG-2')->firstOrFail();

        $this->assertDatabaseHas('review_details', [
            'review_id' => $review->id,
            'medical_staff_id' => $staff1->id,
            'rating' => 5,
        ]);

        // Second submission should be blocked (invitation already used)
        $resp2 = $this->post('/reviews/invite/' . $token, $payload);
        $resp2->assertStatus(410);

        $inv->refresh();
        $this->assertSame('used', $inv->status);
    }
}
