<?php

namespace Database\Seeders;

use Functional\Places\Models\Place;
use Illuminate\Database\Seeder;

class PlaceSeeder extends Seeder
{
    public function run(): void
    {
        $agencies = [
            ['name' => 'Paris', 'code' => 'PAR1', 'description' => 'Agence de Paris, YottaCity'],
            ['name' => 'Lyon', 'code' => 'LYO2', 'description' => 'Agence de Lyon'],
            ['name' => 'Vichy', 'code' => 'VIC3', 'description' => 'Agence de Vichy'],
        ];

        foreach ($agencies as $agency) {
            Place::create([
                'name' => $agency['name'],
                'type' => 'agency',
                'code' => $agency['code'],
                'description' => $agency['description'],
            ]);
        }

        Place::create([
            'name' => 'Vichy',
            'type' => 'external',
            'code' => null,
            'description' => "Yotta, la course de l'entreprise",
        ]);

        Place::create([
            'name' => 'Marseille',
            'type' => 'external',
            'code' => null,
            'description' => 'Plage du Prado',
        ]);
    }
}
