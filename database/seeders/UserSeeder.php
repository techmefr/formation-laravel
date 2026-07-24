<?php

namespace Database\Seeders;

use App\Models\Place;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $agencies = Place::where('type', 'agency')->get();

        foreach (config('demo.roles') as $role => $accounts) {
            foreach ($accounts as $account) {
                $agencyId = isset($account['agency'])
                    ? $agencies->firstWhere('code', $account['agency'])?->id
                    : null;

                User::factory()->create([
                    'name' => $account['name'],
                    'email' => $account['email'],
                    'password' => Hash::make(config('demo.password')),
                    'agency_id' => $agencyId,
                ])->assignRole($role);
            }
        }

        User::factory(5)->create()->each(function ($user) use ($agencies) {
            $user->agency_id = $agencies->random()->id;
            $user->save();
            $user->assignRole('coach');
        });

        User::factory(10)->create()->each(function ($user) use ($agencies) {
            $user->agency_id = $agencies->random()->id;
            $user->save();
            $user->assignRole('collaborator');
        });
    }
}
