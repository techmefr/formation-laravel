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

        $create = Permission::firstOrCreate([
            'name' => 'create seances',
            'guard_name' => 'web',
        ]);

        $update = Permission::firstOrCreate([
            'name' => 'update seances',
            'guard_name' => 'web',
        ]);

        $cancel = Permission::firstOrCreate([
            'name' => 'cancel seances',
            'guard_name' => 'web',
        ]);

        $delete = Permission::firstOrCreate([
            'name' => 'delete seances',
            'guard_name' => 'web',
        ]);

        $manageParticipants = Permission::firstOrCreate([
            'name' => 'manage participants',
            'guard_name' => 'web',
        ]);

        Role::firstOrCreate([
            'name' => 'admin',
            'guard_name' => 'web',
        ])->syncPermissions([
            $create,
            $update,
            $cancel,
            $delete,
            $manageParticipants,
        ]);

        Role::firstOrCreate([
            'name' => 'manager',
            'guard_name' => 'web',
        ])->syncPermissions([
            $create,
            $update,
            $cancel,
            $delete,
            $manageParticipants,
        ]);

        Role::firstOrCreate([
            'name' => 'coach',
            'guard_name' => 'web',
        ])->syncPermissions([
            $create,
            $update,
            $cancel,
            $delete,
            $manageParticipants,
        ]);

        Role::firstOrCreate([
            'name' => 'collaborator',
            'guard_name' => 'web',
        ]);
    }
}
