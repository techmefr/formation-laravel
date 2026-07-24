<?php

namespace Tests\Feature\Api;

use Database\Seeders\RolesAndPermissionsSeeder;
use Functional\Places\Models\Place;
use Functional\Seances\Events\SeanceCancelled;
use Functional\Seances\Models\Seance;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class SeanceActionsTest extends TestCase
{
    use RefreshDatabase;

    private Place $place;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->place = Place::factory()->create();
    }

    private function userWithRole(string $role): User
    {
        return User::factory()->create()->assignRole($role);
    }

    private function tokenFor(User $user): string
    {
        return auth('api')->login($user);
    }

    private function seanceOwnedBy(User $coach, array $overrides = []): Seance
    {
        return Seance::factory()->create(array_merge([
            'place_id' => $this->place->id,
            'coach_id' => $coach->id,
        ], $overrides));
    }

    private function actionRequest(User $user, string $uriKey, Seance $seance, array $fields = [])
    {
        return $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($user))
            ->postJson("/api/seances/actions/{$uriKey}", [
                'search' => ['filters' => [['field' => 'id', 'operator' => '=', 'value' => $seance->id]]],
                'fields' => $fields,
            ]);
    }

    private function participantMutateRequest(User $user, Seance $seance, string $operation, User $participant, array $pivot = []): TestResponse
    {
        return $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($user))
            ->postJson('/api/seances/mutate', [
                'mutate' => [[
                    'operation' => 'update',
                    'key' => $seance->id,
                    'attributes' => [
                        'name' => $seance->name,
                        'place_id' => $seance->place_id,
                        'coach_id' => $seance->coach_id,
                        'started_at' => $seance->started_at->toIso8601String(),
                        'ended_at' => $seance->ended_at->toIso8601String(),
                    ],
                    'relations' => [
                        'participants' => [
                            array_merge(['operation' => $operation, 'key' => $participant->id], $pivot !== [] ? ['pivot' => $pivot] : []),
                        ],
                    ],
                ]],
            ]);
    }

    public function test_participant_can_register_to_a_seance(): void
    {
        $seance = $this->seanceOwnedBy($this->userWithRole('coach'), ['max_participants' => 10]);
        $participant = $this->userWithRole('collaborator');

        $this->actionRequest($participant, 'register', $seance)->assertOk();

        $this->assertDatabaseHas('seance_user', [
            'seance_id' => $seance->id,
            'user_id' => $participant->id,
            'status' => 'registered',
        ]);
    }

    public function test_participant_cannot_register_twice_to_the_same_seance(): void
    {
        $seance = $this->seanceOwnedBy($this->userWithRole('coach'), ['max_participants' => 10]);
        $participant = $this->userWithRole('collaborator');

        $this->actionRequest($participant, 'register', $seance)->assertOk();

        $this->actionRequest($participant, 'register', $seance)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['seance']);

        $this->assertDatabaseCount('seance_user', 1);
    }

    public function test_participant_cannot_register_to_two_overlapping_seances(): void
    {
        $coach = $this->userWithRole('coach');
        $first = $this->seanceOwnedBy($coach, [
            'started_at' => '2026-08-10T10:00:00',
            'ended_at' => '2026-08-10T11:00:00',
        ]);
        $overlapping = $this->seanceOwnedBy($coach, [
            'started_at' => '2026-08-10T10:30:00',
            'ended_at' => '2026-08-10T11:30:00',
        ]);
        $participant = $this->userWithRole('collaborator');

        $this->actionRequest($participant, 'register', $first)->assertOk();

        $this->actionRequest($participant, 'register', $overlapping)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['seance']);

        $this->assertDatabaseMissing('seance_user', ['seance_id' => $overlapping->id, 'user_id' => $participant->id]);
    }

    public function test_registering_beyond_capacity_puts_the_participant_on_the_waitlist(): void
    {
        $seance = $this->seanceOwnedBy($this->userWithRole('coach'), ['max_participants' => 1]);
        $first = $this->userWithRole('collaborator');
        $second = $this->userWithRole('collaborator');

        $this->actionRequest($first, 'register', $seance)->assertOk();
        $this->actionRequest($second, 'register', $seance)->assertOk();

        $this->assertDatabaseHas('seance_user', [
            'seance_id' => $seance->id, 'user_id' => $first->id, 'status' => 'registered',
        ]);
        $this->assertDatabaseHas('seance_user', [
            'seance_id' => $seance->id, 'user_id' => $second->id, 'status' => 'waitlist',
        ]);
    }

    public function test_unregistering_promotes_the_first_waitlisted_participant(): void
    {
        $seance = $this->seanceOwnedBy($this->userWithRole('coach'), ['max_participants' => 1]);
        $first = $this->userWithRole('collaborator');
        $waitlisted = $this->userWithRole('collaborator');

        $this->actionRequest($first, 'register', $seance)->assertOk();
        $this->actionRequest($waitlisted, 'register', $seance)->assertOk();

        $this->actionRequest($first, 'unregister', $seance)->assertOk();

        $this->assertDatabaseMissing('seance_user', ['seance_id' => $seance->id, 'user_id' => $first->id]);
        $this->assertDatabaseHas('seance_user', [
            'seance_id' => $seance->id, 'user_id' => $waitlisted->id, 'status' => 'registered',
        ]);
    }

    public function test_coach_can_add_a_participant_to_their_own_seance(): void
    {
        $coach = $this->userWithRole('coach');
        $seance = $this->seanceOwnedBy($coach, ['max_participants' => 10]);
        $participant = $this->userWithRole('collaborator');

        $this->participantMutateRequest($coach, $seance, 'attach', $participant, ['status' => 'registered', 'position' => 0])
            ->assertOk();

        $this->assertDatabaseHas('seance_user', [
            'seance_id' => $seance->id, 'user_id' => $participant->id, 'status' => 'registered',
        ]);
    }

    public function test_coach_cannot_manage_participants_on_another_coachs_seance(): void
    {
        $owner = $this->userWithRole('coach');
        $intruder = $this->userWithRole('coach');
        $seance = $this->seanceOwnedBy($owner, ['max_participants' => 10]);
        $participant = $this->userWithRole('collaborator');

        $this->participantMutateRequest($intruder, $seance, 'attach', $participant, ['status' => 'registered', 'position' => 0])
            ->assertForbidden();

        $this->assertDatabaseMissing('seance_user', ['seance_id' => $seance->id, 'user_id' => $participant->id]);
    }

    public function test_collaborator_cannot_manage_participants(): void
    {
        $seance = $this->seanceOwnedBy($this->userWithRole('coach'), ['max_participants' => 10]);
        $collaborator = $this->userWithRole('collaborator');
        $participant = $this->userWithRole('collaborator');

        $this->participantMutateRequest($collaborator, $seance, 'attach', $participant, ['status' => 'registered', 'position' => 0])
            ->assertForbidden();
    }

    public function test_coach_can_remove_a_participant_from_their_own_seance(): void
    {
        $coach = $this->userWithRole('coach');
        $seance = $this->seanceOwnedBy($coach, ['max_participants' => 10]);
        $participant = $this->userWithRole('collaborator');
        $seance->participants()->attach($participant->id, ['status' => 'registered', 'position' => 0]);

        $this->participantMutateRequest($coach, $seance, 'detach', $participant)->assertOk();

        $this->assertDatabaseMissing('seance_user', ['seance_id' => $seance->id, 'user_id' => $participant->id]);
    }

    public function test_coach_can_cancel_their_own_seance(): void
    {
        Event::fake([SeanceCancelled::class]);
        $coach = $this->userWithRole('coach');
        $seance = $this->seanceOwnedBy($coach);

        $this->actionRequest($coach, 'cancel-seance', $seance)->assertOk();

        $this->assertNotNull($seance->fresh()->cancelled_at);
        Event::assertDispatched(SeanceCancelled::class);
    }

    public function test_coach_cannot_cancel_another_coachs_seance(): void
    {
        $owner = $this->userWithRole('coach');
        $intruder = $this->userWithRole('coach');
        $seance = $this->seanceOwnedBy($owner);

        $this->actionRequest($intruder, 'cancel-seance', $seance)->assertForbidden();

        $this->assertNull($seance->fresh()->cancelled_at);
    }

    public function test_collaborator_cannot_cancel_a_seance(): void
    {
        $seance = $this->seanceOwnedBy($this->userWithRole('coach'));
        $collaborator = $this->userWithRole('collaborator');

        $this->actionRequest($collaborator, 'cancel-seance', $seance)->assertForbidden();
    }

    public function test_admin_can_cancel_any_seance(): void
    {
        $admin = $this->userWithRole('admin');
        $seance = $this->seanceOwnedBy($this->userWithRole('coach'));

        $this->actionRequest($admin, 'cancel-seance', $seance)->assertOk();

        $this->assertNotNull($seance->fresh()->cancelled_at);
    }
}
