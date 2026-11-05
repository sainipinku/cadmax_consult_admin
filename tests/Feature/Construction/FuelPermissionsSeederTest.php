<?php

namespace Tests\Feature\Construction;

use App\Models\ConstructionRole;
use App\Models\Permission;
use Database\Seeders\FuelPermissionSeeder;
use Tests\TestCase;

class FuelPermissionsSeederTest extends TestCase
{
    public function test_fuel_permission_seeder_creates_all_granular_permissions(): void
    {
        $expectedSlugs = [
            'fuel_entry.view',
            'fuel_entry.details',
            'fuel_entry.verify',
            'fuel_entry.export',
            'fuel_entry.create',
            'fuel_entry.edit',
            'fuel_entry.delete',
        ];

        // Ensure roles exist
        $superAdminRole = ConstructionRole::firstOrCreate(
            ['slug' => 'super_admin'],
            ['name' => 'Super Admin', 'description' => 'Full System Access', 'is_system_role' => true, 'status' => 'active']
        );
        $projectAdminRole = ConstructionRole::firstOrCreate(
            ['slug' => 'project_admin'],
            ['name' => 'Project Admin', 'description' => 'Project Administration Access', 'is_system_role' => true, 'status' => 'active']
        );

        $seeder = new FuelPermissionSeeder();
        $seeder->run();

        foreach ($expectedSlugs as $slug) {
            $this->assertDatabaseHas('construction_permissions', [
                'slug' => $slug,
                'module' => 'Fleet & Equipment',
            ]);
        }

        foreach ($expectedSlugs as $slug) {
            $permission = Permission::where('slug', $slug)->first();
            $this->assertNotNull($permission);

            // Super Admin gets all permissions
            $this->assertDatabaseHas('construction_role_permissions', [
                'role_id' => $superAdminRole->id,
                'permission_id' => $permission->id,
            ]);
        }

        // Project Admin gets view, details, verify, export
        $projectAdminPerms = ['fuel_entry.view', 'fuel_entry.details', 'fuel_entry.verify', 'fuel_entry.export'];
        foreach ($projectAdminPerms as $slug) {
            $permission = Permission::where('slug', $slug)->first();
            $this->assertDatabaseHas('construction_role_permissions', [
                'role_id' => $projectAdminRole->id,
                'permission_id' => $permission->id,
            ]);
        }
    }
}

