<?php

namespace Tests\Feature;

use Database\Seeders\RolesAndPermissionsSeeder;
use Functional\Places\Models\Place;
use Functional\Seances\Models\Seance;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InscriptionTest extends TestCase
{
    use RefreshDatabase;

    private Place $place;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->place = Place::factory()->create();
    }

    private function seance(array $overrides = []): Seance
    {
        return Seance::factory()->create(array_merge([
            'place_id' => $this->place->id,
            'started_at' => '2026-08-01 10:00:00',
            'ended_at' => '2026-08-01 11:00:00',
            'max_participants' => 10,
        ], $overrides));
    }

    public function test_a_user_can_register_to_a_seance(): void
    {
        $user = User::factory()->create();
        $seance = $this->seance();

        $this->actingAs($user)->post(route('seances.inscription.store', $seance));

        $this->assertDatabaseHas('seance_user', [
            'seance_id' => $seance->id,
            'user_id' => $user->id,
            'status' => 'registered',
        ]);
    }

    public function test_registering_twice_stays_a_single_registration(): void
    {
        $user = User::factory()->create();
        $seance = $this->seance();

        $this->actingAs($user)->post(route('seances.inscription.store', $seance));
        $this->actingAs($user)->post(route('seances.inscription.store', $seance));

        $this->assertDatabaseCount('seance_user', 1);
    }

    public function test_a_full_seance_puts_the_next_user_on_the_waitlist(): void
    {
        $seance = $this->seance(['max_participants' => 1]);
        $first = User::factory()->create();
        $second = User::factory()->create();

        $this->actingAs($first)->post(route('seances.inscription.store', $seance));
        $this->actingAs($second)->post(route('seances.inscription.store', $seance));

        $this->assertDatabaseHas('seance_user', [
            'seance_id' => $seance->id,
            'user_id' => $second->id,
            'status' => 'waitlist',
        ]);
    }

    public function test_overlapping_seances_cannot_both_be_registered(): void
    {
        $user = User::factory()->create();
        $morning = $this->seance(['started_at' => '2026-08-01 10:00:00', 'ended_at' => '2026-08-01 11:00:00']);
        $overlap = $this->seance(['started_at' => '2026-08-01 10:30:00', 'ended_at' => '2026-08-01 11:30:00']);

        $this->actingAs($user)->post(route('seances.inscription.store', $morning));
        $this->actingAs($user)->post(route('seances.inscription.store', $overlap));

        $this->assertDatabaseMissing('seance_user', [
            'seance_id' => $overlap->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_unregistering_promotes_the_first_waitlisted(): void
    {
        $seance = $this->seance(['max_participants' => 1]);
        $registered = User::factory()->create();
        $waiting = User::factory()->create();

        $this->actingAs($registered)->post(route('seances.inscription.store', $seance));
        $this->actingAs($waiting)->post(route('seances.inscription.store', $seance));
        $this->actingAs($registered)->delete(route('seances.inscription.destroy', $seance));

        $this->assertDatabaseHas('seance_user', [
            'seance_id' => $seance->id,
            'user_id' => $waiting->id,
            'status' => 'registered',
        ]);

        $this->assertDatabaseMissing('seance_user', [
            'seance_id' => $seance->id,
            'user_id' => $registered->id,
        ]);
    }

    public function test_a_guest_cannot_register(): void
    {
        $seance = $this->seance();

        $this->post(route('seances.inscription.store', $seance))
            ->assertRedirect(route('login'));

        $this->assertDatabaseCount('seance_user', 0);
    }

    public function test_admin_can_add_a_participant(): void
    {
        $admin = User::factory()->create()->assignRole('admin');
        $participant = User::factory()->create();
        $seance = $this->seance();

        $this->actingAs($admin)->post(route('seances.participants.store', $seance), [
            'user_id' => $participant->id,
        ]);

        $this->assertDatabaseHas('seance_user', [
            'seance_id' => $seance->id,
            'user_id' => $participant->id,
        ]);
    }

    public function test_coach_can_add_a_participant_to_their_own_seance(): void
    {
        $coach = User::factory()->create()->assignRole('coach');
        $participant = User::factory()->create();
        $seance = $this->seance(['coach_id' => $coach->id]);

        $this->actingAs($coach)->post(route('seances.participants.store', $seance), [
            'user_id' => $participant->id,
        ]);

        $this->assertDatabaseHas('seance_user', [
            'seance_id' => $seance->id,
            'user_id' => $participant->id,
        ]);
    }

    public function test_coach_cannot_add_a_participant_to_another_coachs_seance(): void
    {
        $coach = User::factory()->create()->assignRole('coach');
        $participant = User::factory()->create();
        $seance = $this->seance(['coach_id' => User::factory()->create()->assignRole('coach')->id]);

        $this->actingAs($coach)->post(route('seances.participants.store', $seance), [
            'user_id' => $participant->id,
        ])->assertForbidden();

        $this->assertDatabaseCount('seance_user', 0);
    }

    public function test_collaborator_cannot_manage_participants(): void
    {
        $collaborator = User::factory()->create()->assignRole('collaborator');
        $participant = User::factory()->create();
        $seance = $this->seance();

        $this->actingAs($collaborator)->post(route('seances.participants.store', $seance), [
            'user_id' => $participant->id,
        ])->assertForbidden();

        $this->assertDatabaseCount('seance_user', 0);
    }

    public function test_admin_can_remove_a_participant(): void
    {
        $admin = User::factory()->create()->assignRole('admin');
        $participant = User::factory()->create();
        $seance = $this->seance();
        $seance->participants()->attach($participant->id, ['status' => 'registered', 'position' => 1]);

        $this->actingAs($admin)->delete(route('seances.participants.destroy', [$seance, $participant]));

        $this->assertDatabaseMissing('seance_user', [
            'seance_id' => $seance->id,
            'user_id' => $participant->id,
        ]);
    }
}
