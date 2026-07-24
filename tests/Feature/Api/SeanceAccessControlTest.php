<?php

namespace Tests\Feature\Api;

use Database\Seeders\RolesAndPermissionsSeeder;
use Functional\Places\Models\Place;
use Functional\Seances\Models\Seance;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SeanceAccessControlTest extends TestCase
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
    private function attributes(User $coach, array $overrides = []): array
    {
        return array_merge([
            'name' => 'Yoga',
            'place_id' => $this->place->id,
            'coach_id' => $coach->id,
            'started_at' => '2026-08-01T10:00:00',
            'ended_at' => '2026-08-01T11:00:00',
            'max_participants' => 10,
        ], $overrides);
    }

    #[DataProvider('viewAnyRolesProvider')]
    public function test_every_role_can_call_search(string $role): void
    {
        $this->seanceOwnedBy($this->userWithRole('coach'));

        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($this->userWithRole($role)))
            ->postJson('/api/seances/search', ['search' => []])
            ->assertOk();
    }

    public static function viewAnyRolesProvider(): array
    {
        return [
            'admin' => ['admin'],
            'manager' => ['manager'],
            'coach' => ['coach'],
            'collaborator' => ['collaborator'],
        ];
    }

    public function test_collaborator_sees_every_seance_via_search_because_controlled_scope_is_not_wired(): void
    {
        $this->seanceOwnedBy($this->userWithRole('coach'));
        $this->seanceOwnedBy($this->userWithRole('coach'));
        $this->seanceOwnedBy($this->userWithRole('coach'));
        $collaborator = $this->userWithRole('collaborator');

        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($collaborator))
            ->postJson('/api/seances/search', ['search' => []])
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_coach_sees_every_seance_via_search_not_only_their_own(): void
    {
        $coach = $this->userWithRole('coach');
        $this->seanceOwnedBy($coach);
        $this->seanceOwnedBy($this->userWithRole('coach'));
        $this->seanceOwnedBy($this->userWithRole('coach'));

        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($coach))
            ->postJson('/api/seances/search', ['search' => []])
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    #[DataProvider('creatorRolesProvider')]
    public function test_admin_manager_and_coach_can_create_a_seance(string $role): void
    {
        $user = $this->userWithRole($role);

        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($user))
            ->postJson('/api/seances/mutate', [
                'mutate' => [[
                    'operation' => 'create',
                    'attributes' => $this->attributes($this->userWithRole('coach')),
                ]],
            ])
            ->assertOk();
    }

    public static function creatorRolesProvider(): array
    {
        return [
            'admin' => ['admin'],
            'manager' => ['manager'],
            'coach' => ['coach'],
        ];
    }

    #[DataProvider('staffRolesProvider')]
    public function test_admin_and_manager_can_update_any_coachs_seance(string $role): void
    {
        $seance = $this->seanceOwnedBy($this->userWithRole('coach'));
        $staff = $this->userWithRole($role);

        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($staff))
            ->postJson('/api/seances/mutate', [
                'mutate' => [[
                    'operation' => 'update',
                    'key' => $seance->id,
                    'attributes' => $this->attributes($seance->coach, ['name' => 'Updated by staff']),
                ]],
            ])
            ->assertOk();

        $this->assertDatabaseHas('seances', ['id' => $seance->id, 'name' => 'Updated by staff']);
    }

    #[DataProvider('staffRolesProvider')]
    public function test_admin_and_manager_can_delete_any_coachs_seance(string $role): void
    {
        $seance = $this->seanceOwnedBy($this->userWithRole('coach'));
        $staff = $this->userWithRole($role);

        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($staff))
            ->deleteJson('/api/seances', ['resources' => [$seance->id]])
            ->assertOk();

        $this->assertSoftDeleted('seances', ['id' => $seance->id]);
    }

    public static function staffRolesProvider(): array
    {
        return [
            'admin' => ['admin'],
            'manager' => ['manager'],
        ];
    }

    public function test_collaborator_cannot_create_update_or_delete_a_seance(): void
    {
        $seance = $this->seanceOwnedBy($this->userWithRole('coach'));
        $collaborator = $this->userWithRole('collaborator');
        $token = 'Bearer '.$this->tokenFor($collaborator);

        $this->withHeader('Authorization', $token)
            ->postJson('/api/seances/mutate', [
                'mutate' => [['operation' => 'create', 'attributes' => $this->attributes($seance->coach)]],
            ])
            ->assertForbidden();

        $this->withHeader('Authorization', $token)
            ->postJson('/api/seances/mutate', [
                'mutate' => [['operation' => 'update', 'key' => $seance->id, 'attributes' => $this->attributes($seance->coach)]],
            ])
            ->assertForbidden();

        $this->withHeader('Authorization', $token)
            ->deleteJson('/api/seances', ['resources' => [$seance->id]])
            ->assertForbidden();

        $this->assertNull($seance->fresh()->deleted_at);
    }

    public function test_coach_can_update_their_own_seance_but_not_another_coachs(): void
    {
        $owner = $this->userWithRole('coach');
        $intruder = $this->userWithRole('coach');
        $ownSeance = $this->seanceOwnedBy($owner);
        $othersSeance = $this->seanceOwnedBy($owner);

        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($owner))
            ->postJson('/api/seances/mutate', [
                'mutate' => [['operation' => 'update', 'key' => $ownSeance->id, 'attributes' => $this->attributes($owner, ['name' => 'Mine'])]],
            ])
            ->assertOk();

        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($intruder))
            ->postJson('/api/seances/mutate', [
                'mutate' => [['operation' => 'update', 'key' => $othersSeance->id, 'attributes' => $this->attributes($owner, ['name' => 'Stolen'])]],
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('seances', ['id' => $ownSeance->id, 'name' => 'Mine']);
        $this->assertDatabaseMissing('seances', ['name' => 'Stolen']);
    }
}
