<?php

namespace Tests\Feature;

use App\Models\Place;
use App\Models\Seance;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SeanceUploadTest extends TestCase
{
    use RefreshDatabase;

    private Place $place;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->place = Place::factory()->create();
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
            'coach_id' => User::factory()->create()->id,
            'started_at' => '2026-08-01T10:00',
            'ended_at' => '2026-08-01T11:00',
            'max_participants' => 10,
        ], $overrides);
    }

    public function test_a_seance_can_be_created_with_an_attached_file(): void
    {
        Storage::fake('public');

        $this->actingAs(User::factory()->create()->assignRole('admin'))
            ->post(route('seances.store'), $this->payload([
                'files' => [UploadedFile::fake()->create('plan.pdf', 100, 'application/pdf')],
            ]));

        $this->assertCount(1, Seance::firstOrFail()->getMedia('files'));
    }

    public function test_the_upload_rejects_a_file_that_is_too_large(): void
    {
        Storage::fake('public');

        $this->actingAs(User::factory()->create()->assignRole('admin'))
            ->post(route('seances.store'), $this->payload([
                'files' => [UploadedFile::fake()->create('huge.pdf', 6000, 'application/pdf')],
            ]))
            ->assertSessionHasErrors('files.0');

        $this->assertDatabaseCount('seances', 0);
    }
}
