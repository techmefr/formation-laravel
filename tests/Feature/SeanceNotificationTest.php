<?php

namespace Tests\Feature;

use Database\Seeders\RolesAndPermissionsSeeder;
use Functional\Places\Models\Place;
use Functional\Seances\Models\Seance;
use Functional\Seances\Notifications\SeanceCancelledNotification;
use Functional\Seances\Notifications\SeanceCreatedNotification;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SeanceNotificationTest extends TestCase
{
    use RefreshDatabase;

    private Place $place;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->place = Place::factory()->create();
    }

    public function test_creating_a_seance_notifies_the_coach(): void
    {
        Notification::fake();

        $coach = User::factory()->create()->assignRole('coach');

        $this->actingAs($this->userWithRole('admin'))->post(route('seances.store'), [
            'name' => 'Yoga',
            'place_id' => $this->place->id,
            'coach_id' => $coach->id,
            'started_at' => '2026-08-01T10:00',
            'ended_at' => '2026-08-01T11:00',
            'max_participants' => 10,
        ]);

        Notification::assertSentTo($coach, SeanceCreatedNotification::class);
    }

    public function test_cancelling_a_seance_notifies_every_participant(): void
    {
        Notification::fake();

        $seance = Seance::factory()->create(['place_id' => $this->place->id]);
        $first = User::factory()->create();
        $second = User::factory()->create();
        $seance->participants()->attach($first->id, ['status' => 'registered', 'position' => 1]);
        $seance->participants()->attach($second->id, ['status' => 'waitlist', 'position' => 2]);

        $this->actingAs($this->userWithRole('admin'))
            ->post(route('seances.cancel', $seance));

        Notification::assertSentTo($first, SeanceCancelledNotification::class);
        Notification::assertSentTo($second, SeanceCancelledNotification::class);
    }

    private function userWithRole(string $role): User
    {
        return User::factory()->create()->assignRole($role);
    }
}
