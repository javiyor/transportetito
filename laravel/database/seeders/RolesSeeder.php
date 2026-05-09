<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesSeeder extends Seeder
{
    public function run(): void
    {
        $roles = config('roles.available', []);

        foreach ($roles as $role) {
            Role::findOrCreate($role);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
