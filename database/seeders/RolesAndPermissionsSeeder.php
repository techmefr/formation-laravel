<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $create = Permission::firstOrCreate(['name' => 'create seances']);
        $update = Permission::firstOrCreate(['name' => 'update seances']);
        $delete = Permission::firstOrCreate(['name' => 'delete seances']);

        Role::firstOrCreate(['name' => 'admin'])
            ->syncPermissions([$create, $update, $delete]);

        Role::firstOrCreate(['name' => 'coach'])
            ->syncPermissions([$create, $update]);

        Role::firstOrCreate(['name' => 'collaborator']);
    }
}
