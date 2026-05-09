<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminUsersTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_access_admin_users_page(): void
    {
        $this->seed(RolesSeeder::class);

        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this
            ->get('/admin/users')
            ->assertStatus(403);
    }

    public function test_admin_can_access_admin_users_page(): void
    {
        $this->seed(RolesSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Sanctum::actingAs($admin);

        $this
            ->get('/admin/users')
            ->assertOk();
    }

    public function test_admin_can_assign_multiple_roles_to_user(): void
    {
        $this->seed(RolesSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $target = User::factory()->create();

        Sanctum::actingAs($admin);

        $this
            ->put(route('admin.users.roles.update', $target), [
                'roles' => ['operaciones', 'facturacion'],
            ])
            ->assertRedirect();

        $this->assertTrue($target->fresh()->hasRole('operaciones'));
        $this->assertTrue($target->fresh()->hasRole('facturacion'));
    }

    public function test_admin_cannot_remove_own_admin_role(): void
    {
        $this->seed(RolesSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Sanctum::actingAs($admin);

        $this
            ->put(route('admin.users.roles.update', $admin), [
                'roles' => [],
            ])
            ->assertSessionHasErrors('roles');
    }

    public function test_cannot_remove_admin_role_from_last_admin(): void
    {
        $this->seed(RolesSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Sanctum::actingAs($admin);

        $this
            ->put(route('admin.users.roles.update', $admin), [
                'roles' => ['operaciones'],
            ])
            ->assertSessionHasErrors('roles');
    }
}
