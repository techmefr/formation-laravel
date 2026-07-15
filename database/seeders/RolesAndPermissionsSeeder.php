<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Repartir d'un cache de permissions propre (sinon celles qu'on vient de créer
        // peuvent ne pas être prises en compte tout de suite — cf. Cours 9 §1).
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // 1) Les permissions = le droit de faire une action précise.
        //    firstOrCreate → idempotent : relancer le seeder ne crée pas de doublon.
        $create = Permission::firstOrCreate(['name' => 'create seances']);
        $update = Permission::firstOrCreate(['name' => 'update seances']);
        $delete = Permission::firstOrCreate(['name' => 'delete seances']);

        // 2) Les rôles = des paquets de permissions.
        //    syncPermissions → fixe EXACTEMENT cette liste (re-jouable sans accumuler).
        Role::firstOrCreate(['name' => 'admin'])
            ->syncPermissions([$create, $update, $delete]);      // admin : tout

        Role::firstOrCreate(['name' => 'coach'])
            ->syncPermissions([$create, $update]);               // coach : créer + modifier
        // (« les siennes » = une Policy, plus tard)

        Role::firstOrCreate(['name' => 'collaborator']);         // collaborator : aucune permission d'écriture
    }
}
