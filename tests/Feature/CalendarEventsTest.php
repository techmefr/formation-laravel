<?php

namespace Tests\Feature;

use Database\Seeders\RolesAndPermissionsSeeder;
use Functional\Places\Models\Place;
use Functional\Seances\Models\Seance;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarEventsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_a_coach_only_sees_their_own_seances(): void
    {
        $coach = User::factory()->create()->assignRole('coach');
        $own = Seance::factory()->create(['coach_id' => $coach->id]);
        Seance::factory()->create(['coach_id' => User::factory()->create()->id]);

        $this->actingAs($coach)
            ->getJson(route('calendar.events'))
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment(['id' => $own->id]);
    }

    public function test_a_collaborator_sees_their_agency_and_external_seances_only(): void
    {
        $agency = Place::factory()->create(['type' => 'agency']);
        $external = Place::factory()->create(['type' => 'external']);
        $otherAgency = Place::factory()->create(['type' => 'agency']);

        $collaborator = User::factory()->create(['agency_id' => $agency->id])->assignRole('collaborator');

        $mine = Seance::factory()->create(['place_id' => $agency->id]);
        $open = Seance::factory()->create(['place_id' => $external->id]);
        Seance::factory()->create(['place_id' => $otherAgency->id]);

        $this->actingAs($collaborator)
            ->getJson(route('calendar.events'))
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonFragment(['id' => $mine->id])
            ->assertJsonFragment(['id' => $open->id]);
    }

    public function test_an_admin_sees_every_seance(): void
    {
        $admin = User::factory()->create()->assignRole('admin');
        Seance::factory()->count(3)->create();

        $this->actingAs($admin)
            ->getJson(route('calendar.events'))
            ->assertOk()
            ->assertJsonCount(3);
    }

    public function test_the_mine_filter_returns_only_registered_seances(): void
    {
        $collaborator = User::factory()->create()->assignRole('collaborator');
        $registered = Seance::factory()->create();
        Seance::factory()->create();
        $registered->participants()->attach($collaborator->id, ['status' => 'registered', 'position' => 1]);

        $this->actingAs($collaborator)
            ->getJson(route('calendar.events', ['agency' => 'all', 'mine' => 1]))
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment(['id' => $registered->id]);
    }

    public function test_the_agency_filter_targets_another_agency(): void
    {
        $agency = Place::factory()->create(['type' => 'agency']);
        $external = Place::factory()->create(['type' => 'external']);
        $otherAgency = Place::factory()->create(['type' => 'agency']);

        $collaborator = User::factory()->create(['agency_id' => $agency->id])->assignRole('collaborator');

        Seance::factory()->create(['place_id' => $agency->id]);
        $open = Seance::factory()->create(['place_id' => $external->id]);
        $target = Seance::factory()->create(['place_id' => $otherAgency->id]);

        $this->actingAs($collaborator)
            ->getJson(route('calendar.events', ['agency' => $otherAgency->id]))
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonFragment(['id' => $open->id])
            ->assertJsonFragment(['id' => $target->id]);
    }

    public function test_a_guest_cannot_read_the_calendar_feed(): void
    {
        $this->getJson(route('calendar.events'))->assertUnauthorized();
    }
}
