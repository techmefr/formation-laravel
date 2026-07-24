<?php

namespace Tests\Feature;

use Database\Seeders\RolesAndPermissionsSeeder;
use Functional\Places\Models\Place;
use Functional\Seances\Models\Seance;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeanceCrudTest extends TestCase
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

    private function seanceOwnedBy(User $coach): Seance
    {
        return Seance::factory()->create([
            'place_id' => $this->place->id,
            'coach_id' => $coach->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Yoga',
            'place_id' => $this->place->id,
            'coach_id' => $this->userWithRole('coach')->id,
            'started_at' => '2026-08-01T10:00',
            'ended_at' => '2026-08-01T11:00',
            'max_participants' => 10,
        ], $overrides);
    }

    public function test_admin_can_create_a_seance(): void
    {
        $this->actingAs($this->userWithRole('admin'))
            ->post(route('seances.store'), $this->payload(['name' => 'Pilates']))
            ->assertRedirect();

        $this->assertDatabaseHas('seances', ['name' => 'Pilates']);
    }

    public function test_coach_can_create_a_seance(): void
    {
        $this->actingAs($this->userWithRole('coach'))
            ->post(route('seances.store'), $this->payload(['name' => 'Crossfit']))
            ->assertRedirect();

        $this->assertDatabaseHas('seances', ['name' => 'Crossfit']);
    }

    public function test_manager_can_create_a_seance(): void
    {
        $this->actingAs($this->userWithRole('manager'))
            ->post(route('seances.store'), $this->payload(['name' => 'Aquagym']))
            ->assertRedirect();

        $this->assertDatabaseHas('seances', ['name' => 'Aquagym']);
    }

    public function test_collaborator_cannot_create_a_seance(): void
    {
        $this->actingAs($this->userWithRole('collaborator'))
            ->post(route('seances.store'), $this->payload())
            ->assertForbidden();

        $this->assertDatabaseCount('seances', 0);
    }

    public function test_guest_cannot_create_a_seance(): void
    {
        $this->post(route('seances.store'), $this->payload())
            ->assertRedirect(route('login'));

        $this->assertDatabaseCount('seances', 0);
    }

    public function test_a_coach_creating_a_seance_is_forced_as_its_own_coach(): void
    {
        $coach = $this->userWithRole('coach');
        $otherCoach = $this->userWithRole('coach');

        $this->actingAs($coach)
            ->post(route('seances.store'), $this->payload([
                'name' => 'Boxe',
                'coach_id' => $otherCoach->id,
            ]));

        $this->assertDatabaseHas('seances', [
            'name' => 'Boxe',
            'coach_id' => $coach->id,
        ]);
    }

    public function test_admin_can_update_any_seance(): void
    {
        $seance = $this->seanceOwnedBy($this->userWithRole('coach'));

        $this->actingAs($this->userWithRole('admin'))
            ->put(route('seances.update', $seance), $this->payload([
                'name' => 'Renforcement',
                'coach_id' => $seance->coach_id,
            ]))
            ->assertRedirect();

        $this->assertDatabaseHas('seances', ['id' => $seance->id, 'name' => 'Renforcement']);
    }

    public function test_manager_can_update_any_seance(): void
    {
        $seance = $this->seanceOwnedBy($this->userWithRole('coach'));

        $this->actingAs($this->userWithRole('manager'))
            ->put(route('seances.update', $seance), $this->payload([
                'name' => 'Stretching',
                'coach_id' => $seance->coach_id,
            ]))
            ->assertRedirect();

        $this->assertDatabaseHas('seances', ['id' => $seance->id, 'name' => 'Stretching']);
    }

    public function test_coach_can_update_their_own_seance(): void
    {
        $coach = $this->userWithRole('coach');
        $seance = $this->seanceOwnedBy($coach);

        $this->actingAs($coach)
            ->put(route('seances.update', $seance), $this->payload([
                'name' => 'Cardio',
                'coach_id' => $coach->id,
            ]))
            ->assertRedirect();

        $this->assertDatabaseHas('seances', ['id' => $seance->id, 'name' => 'Cardio']);
    }

    public function test_coach_cannot_update_another_coachs_seance(): void
    {
        $seance = $this->seanceOwnedBy($this->userWithRole('coach'));

        $this->actingAs($this->userWithRole('coach'))
            ->put(route('seances.update', $seance), $this->payload([
                'name' => 'Vol',
                'coach_id' => $seance->coach_id,
            ]))
            ->assertForbidden();

        $this->assertDatabaseMissing('seances', ['name' => 'Vol']);
    }

    public function test_collaborator_cannot_update_a_seance(): void
    {
        $seance = $this->seanceOwnedBy($this->userWithRole('coach'));

        $this->actingAs($this->userWithRole('collaborator'))
            ->put(route('seances.update', $seance), $this->payload(['coach_id' => $seance->coach_id]))
            ->assertForbidden();
    }

    public function test_admin_can_cancel_a_seance(): void
    {
        $seance = $this->seanceOwnedBy($this->userWithRole('coach'));

        $this->actingAs($this->userWithRole('admin'))
            ->post(route('seances.cancel', $seance))
            ->assertRedirect();

        $this->assertNotNull($seance->fresh()->cancelled_at);
    }

    public function test_manager_can_cancel_a_seance(): void
    {
        $seance = $this->seanceOwnedBy($this->userWithRole('coach'));

        $this->actingAs($this->userWithRole('manager'))
            ->post(route('seances.cancel', $seance))
            ->assertRedirect();

        $this->assertNotNull($seance->fresh()->cancelled_at);
    }

    public function test_coach_can_cancel_their_own_seance(): void
    {
        $coach = $this->userWithRole('coach');
        $seance = $this->seanceOwnedBy($coach);

        $this->actingAs($coach)
            ->post(route('seances.cancel', $seance))
            ->assertRedirect();

        $this->assertNotNull($seance->fresh()->cancelled_at);
    }

    public function test_coach_cannot_cancel_another_coachs_seance(): void
    {
        $seance = $this->seanceOwnedBy($this->userWithRole('coach'));

        $this->actingAs($this->userWithRole('coach'))
            ->post(route('seances.cancel', $seance))
            ->assertForbidden();

        $this->assertNull($seance->fresh()->cancelled_at);
    }

    public function test_collaborator_cannot_cancel_a_seance(): void
    {
        $seance = $this->seanceOwnedBy($this->userWithRole('coach'));

        $this->actingAs($this->userWithRole('collaborator'))
            ->post(route('seances.cancel', $seance))
            ->assertForbidden();

        $this->assertNull($seance->fresh()->cancelled_at);
    }

    public function test_admin_can_delete_a_seance(): void
    {
        $seance = $this->seanceOwnedBy($this->userWithRole('coach'));

        $this->actingAs($this->userWithRole('admin'))
            ->delete(route('seances.destroy', $seance))
            ->assertRedirect();

        $this->assertSoftDeleted('seances', ['id' => $seance->id]);
    }

    public function test_manager_can_delete_a_seance(): void
    {
        $seance = $this->seanceOwnedBy($this->userWithRole('coach'));

        $this->actingAs($this->userWithRole('manager'))
            ->delete(route('seances.destroy', $seance))
            ->assertRedirect();

        $this->assertSoftDeleted('seances', ['id' => $seance->id]);
    }

    public function test_coach_can_delete_their_own_seance(): void
    {
        $coach = $this->userWithRole('coach');
        $seance = $this->seanceOwnedBy($coach);

        $this->actingAs($coach)
            ->delete(route('seances.destroy', $seance))
            ->assertRedirect();

        $this->assertSoftDeleted('seances', ['id' => $seance->id]);
    }

    public function test_coach_cannot_delete_another_coachs_seance(): void
    {
        $seance = $this->seanceOwnedBy($this->userWithRole('coach'));

        $this->actingAs($this->userWithRole('coach'))
            ->delete(route('seances.destroy', $seance))
            ->assertForbidden();

        $this->assertDatabaseHas('seances', ['id' => $seance->id, 'deleted_at' => null]);
    }

    public function test_collaborator_cannot_delete_a_seance(): void
    {
        $seance = $this->seanceOwnedBy($this->userWithRole('coach'));

        $this->actingAs($this->userWithRole('collaborator'))
            ->delete(route('seances.destroy', $seance))
            ->assertForbidden();

        $this->assertDatabaseHas('seances', ['id' => $seance->id, 'deleted_at' => null]);
    }

    public function test_creating_a_seance_requires_a_name(): void
    {
        $this->actingAs($this->userWithRole('admin'))
            ->post(route('seances.store'), $this->payload(['name' => '']))
            ->assertSessionHasErrors('name');

        $this->assertDatabaseCount('seances', 0);
    }

    public function test_creating_a_seance_requires_the_end_after_the_start(): void
    {
        $this->actingAs($this->userWithRole('admin'))
            ->post(route('seances.store'), $this->payload([
                'started_at' => '2026-08-01T11:00',
                'ended_at' => '2026-08-01T10:00',
            ]))
            ->assertSessionHasErrors('ended_at');

        $this->assertDatabaseCount('seances', 0);
    }

    public function test_creating_a_seance_rejects_a_non_positive_capacity(): void
    {
        $this->actingAs($this->userWithRole('admin'))
            ->post(route('seances.store'), $this->payload(['max_participants' => 0]))
            ->assertSessionHasErrors('max_participants');

        $this->assertDatabaseCount('seances', 0);
    }
}
