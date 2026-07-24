<?php

namespace Database\Seeders;

use Functional\Places\Models\Place;
use Functional\Users\Models\User;
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

        User::factory(5)
            ->recycle($agencies)
            ->create(['agency_id' => Place::factory()])
            ->each->assignRole('coach');

        User::factory(10)
            ->recycle($agencies)
            ->create(['agency_id' => Place::factory()])
            ->each->assignRole('collaborator');
    }
}
