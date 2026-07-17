<?php

namespace Database\Seeders;

use App\Models\Place;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $agencies = Place::where('type', 'agency')->get();
        $paris = $agencies->firstWhere('code', 'PAR1') ?? $agencies->first();

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
            'agency_id' => $paris->id,
        ])->assignRole('coach');

        User::factory(5)->create()->each(function ($user) use ($agencies) {
            $user->agency_id = $agencies->random()->id;
            $user->save();
            $user->assignRole('coach');
        });

        User::factory()->create([
            'name' => 'Collaborateur',
            'email' => 'collab@example.com',
            'agency_id' => $paris->id,
        ])->assignRole('collaborator');

        User::factory(10)->create()->each(function ($user) use ($agencies) {
            $user->agency_id = $agencies->random()->id;
            $user->save();
            $user->assignRole('collaborator');
        });
    }
}
