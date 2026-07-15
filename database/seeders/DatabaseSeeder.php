<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // D'abord les rôles/permissions : les users créés juste après pourront s'y rattacher.
        $this->call(RolesAndPermissionsSeeder::class);

        // Un utilisateur par rôle, pour tester la connexion tout de suite.
        // Mot de passe par défaut de la factory : « password ».
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
