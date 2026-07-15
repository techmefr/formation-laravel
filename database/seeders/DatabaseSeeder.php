<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);

        User::factory()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
        ])->assignRole('admin');

        User::factory()->create([
            'name' => 'Coach',
            'email' => 'coach@example.com',
        ])->assignRole('coach');

        User::factory()->create([
            'name' => 'Collaborateur',
            'email' => 'collab@example.com',
        ])->assignRole('collaborator');
    }
}
