<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = collect([
            'create seances',
            'update seances',
            'cancel seances',
            'delete seances',
            'manage participants',
        ])->map(fn (string $name) => Permission::firstOrCreate([
            'name' => $name,
            'guard_name' => 'web',
        ]));

        collect(['admin', 'manager', 'coach'])->each(fn (string $roleName) => Role::firstOrCreate([
            'name' => $roleName,
            'guard_name' => 'web',
        ])->syncPermissions($permissions));

        Role::firstOrCreate([
            'name' => 'collaborator',
            'guard_name' => 'web',
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Role::whereIn('name', ['admin', 'manager', 'coach', 'collaborator'])
            ->where('guard_name', 'web')
            ->delete();

        Permission::whereIn('name', [
            'create seances',
            'update seances',
            'cancel seances',
            'delete seances',
            'manage participants',
        ])->where('guard_name', 'web')->delete();
    }
};
