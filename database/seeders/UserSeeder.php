<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
        ])->assignRole('admin');

        User::factory()->create([
            'name' => 'Manager',
            'email' => 'manager@example.com',
        ])->assignRole('manager');

        User::factory()->create([
            'name' => 'Coach',
            'email' => 'coach@example.com',
        ])->assignRole('coach');

        User::factory(5)->create()->each(fn ($user) => $user->assignRole('coach'));

        User::factory()->create([
            'name' => 'Collaborateur',
            'email' => 'collab@example.com',
        ])->assignRole('collaborator');

        User::factory(10)->create()->each(fn ($user) => $user->assignRole('collaborator'));
    }
}
