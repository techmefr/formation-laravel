<?php

namespace Tests\Feature\Api;

use App\Events\SeanceCreated;
use App\Events\SeanceDeleted;
use App\Models\Place;
use App\Models\Seance;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class SeanceRestTest extends TestCase
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
    private function attributes(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Yoga',
            'place_id' => $this->place->id,
            'coach_id' => $this->userWithRole('coach')->id,
            'started_at' => '2026-08-01T10:00:00',
            'ended_at' => '2026-08-01T11:00:00',
            'max_participants' => 10,
        ], $overrides);
    }

    public function test_search_without_a_token_is_unauthorized(): void
    {
        $this->postJson('/api/seances/search', [])->assertUnauthorized();
    }

    public function test_search_filters_by_field_and_includes_relations(): void
    {
        $coach = $this->userWithRole('coach');
        $otherCoach = $this->userWithRole('coach');
        $this->seanceOwnedBy($coach);
        $this->seanceOwnedBy($otherCoach);

        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($coach))
            ->postJson('/api/seances/search', [
                'search' => [
                    'filters' => [
                        ['field' => 'coach_id', 'operator' => '=', 'value' => $coach->id],
                    ],
                    'includes' => [
                        ['relation' => 'coach'],
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.coach.id', $coach->id);
    }

    public function test_search_without_the_search_wrapper_ignores_filters(): void
    {
        $coach = $this->userWithRole('coach');
        $otherCoach = $this->userWithRole('coach');
        $this->seanceOwnedBy($coach);
        $this->seanceOwnedBy($otherCoach);

        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($coach))
            ->postJson('/api/seances/search', [
                'filters' => [
                    ['field' => 'coach_id', 'operator' => '=', 'value' => $coach->id],
                ],
            ])
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_admin_can_create_a_seance_via_mutate(): void
    {
        $admin = $this->userWithRole('admin');

        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($admin))
            ->postJson('/api/seances/mutate', [
                'mutate' => [[
                    'operation' => 'create',
                    'attributes' => $this->attributes(['name' => 'Pilates']),
                ]],
            ])
            ->assertOk();

        $this->assertDatabaseHas('seances', ['name' => 'Pilates']);
    }

    public function test_create_via_mutate_requires_all_fields(): void
    {
        $admin = $this->userWithRole('admin');

        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($admin))
            ->postJson('/api/seances/mutate', [
                'mutate' => [[
                    'operation' => 'create',
                    'attributes' => [],
                ]],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'mutate.0.attributes.name',
                'mutate.0.attributes.place_id',
                'mutate.0.attributes.coach_id',
                'mutate.0.attributes.started_at',
                'mutate.0.attributes.ended_at',
            ]);

        $this->assertDatabaseCount('seances', 0);
    }

    public function test_ended_at_before_started_at_is_not_rejected_via_mutate(): void
    {
        $admin = $this->userWithRole('admin');

        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($admin))
            ->postJson('/api/seances/mutate', [
                'mutate' => [[
                    'operation' => 'create',
                    'attributes' => $this->attributes([
                        'started_at' => '2026-08-01T11:00:00',
                        'ended_at' => '2026-08-01T10:00:00',
                    ]),
                ]],
            ])
            ->assertOk();

        $this->assertDatabaseHas('seances', [
            'started_at' => '2026-08-01 11:00:00',
            'ended_at' => '2026-08-01 10:00:00',
        ]);
    }

    public function test_create_via_mutate_dispatches_seance_created(): void
    {
        Event::fake([SeanceCreated::class]);
        $admin = $this->userWithRole('admin');

        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($admin))
            ->postJson('/api/seances/mutate', [
                'mutate' => [[
                    'operation' => 'create',
                    'attributes' => $this->attributes(),
                ]],
            ])
            ->assertOk();

        Event::assertDispatched(SeanceCreated::class);
    }

    public function test_coach_can_update_their_own_seance_via_mutate(): void
    {
        $coach = $this->userWithRole('coach');
        $seance = $this->seanceOwnedBy($coach);

        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($coach))
            ->postJson('/api/seances/mutate', [
                'mutate' => [[
                    'operation' => 'update',
                    'key' => $seance->id,
                    'attributes' => $this->attributes(['name' => 'Renamed', 'coach_id' => $coach->id]),
                ]],
            ])
            ->assertOk();

        $this->assertDatabaseHas('seances', ['id' => $seance->id, 'name' => 'Renamed']);
    }

    public function test_coach_cannot_update_another_coachs_seance_via_mutate(): void
    {
        $owner = $this->userWithRole('coach');
        $intruder = $this->userWithRole('coach');
        $seance = $this->seanceOwnedBy($owner);

        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($intruder))
            ->postJson('/api/seances/mutate', [
                'mutate' => [[
                    'operation' => 'update',
                    'key' => $seance->id,
                    'attributes' => $this->attributes(['name' => 'Hacked', 'coach_id' => $owner->id]),
                ]],
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('seances', ['name' => 'Hacked']);
    }

    public function test_collaborator_cannot_create_a_seance_via_mutate(): void
    {
        $collaborator = $this->userWithRole('collaborator');

        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($collaborator))
            ->postJson('/api/seances/mutate', [
                'mutate' => [[
                    'operation' => 'create',
                    'attributes' => $this->attributes(),
                ]],
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('seances', 0);
    }

    public function test_admin_can_destroy_a_seance(): void
    {
        $admin = $this->userWithRole('admin');
        $seance = $this->seanceOwnedBy($this->userWithRole('coach'));

        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($admin))
            ->deleteJson('/api/seances', ['resources' => [$seance->id]])
            ->assertOk();

        $this->assertSoftDeleted('seances', ['id' => $seance->id]);
    }

    public function test_collaborator_cannot_destroy_a_seance(): void
    {
        $collaborator = $this->userWithRole('collaborator');
        $seance = $this->seanceOwnedBy($this->userWithRole('coach'));

        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($collaborator))
            ->deleteJson('/api/seances', ['resources' => [$seance->id]])
            ->assertForbidden();

        $this->assertNull($seance->fresh()->deleted_at);
    }

    public function test_destroy_dispatches_seance_deleted(): void
    {
        Event::fake([SeanceDeleted::class]);
        $admin = $this->userWithRole('admin');
        $seance = $this->seanceOwnedBy($this->userWithRole('coach'));

        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($admin))
            ->deleteJson('/api/seances', ['resources' => [$seance->id]])
            ->assertOk();

        Event::assertDispatched(SeanceDeleted::class);
    }
}
