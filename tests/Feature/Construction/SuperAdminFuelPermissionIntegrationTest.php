<?php

namespace Tests\Feature\Construction;

use App\Models\ConstructionRole;
use App\Models\Permission;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\SuperAdminPermissionSeeder;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SuperAdminFuelPermissionIntegrationTest extends TestCase
{
    protected array $expectedFuelSlugs = [
        'fuel_entry.view',
        'fuel_entry.details',
        'fuel_entry.verify',
        'fuel_entry.export',
        'fuel_entry.create',
        'fuel_entry.edit',
        'fuel_entry.delete',
    ];

    public function test_super_admin_permission_seeder_defines_all_fuel_permissions(): void
    {
        $content = file_get_contents(base_path('database/seeders/SuperAdminPermissionSeeder.php'));
        foreach ($this->expectedFuelSlugs as $slug) {
            $this->assertStringContainsString("'slug' => '{$slug}'", $content, "SuperAdminPermissionSeeder should define '{$slug}'");
        }
    }

    public function test_permission_seeder_defines_all_fuel_permissions(): void
    {
        $content = file_get_contents(base_path('database/seeders/PermissionSeeder.php'));
        foreach ($this->expectedFuelSlugs as $slug) {
            $this->assertStringContainsString("'slug' => '{$slug}'", $content, "PermissionSeeder should define '{$slug}'");
        }
    }

    public function test_database_seeder_includes_fuel_and_attendance_permission_seeders(): void
    {
        $databaseSeederContent = file_get_contents(base_path('database/seeders/DatabaseSeeder.php'));
        $this->assertStringContainsString('FuelPermissionSeeder::class', $databaseSeederContent);
        $this->assertStringContainsString('AttendancePermissionSeeder::class', $databaseSeederContent);
    }

    public function test_deploy_workflow_executes_seeders_on_live_deployment(): void
    {
        $deployYmlContent = file_get_contents(base_path('.github/workflows/deploy.yml'));
        $this->assertStringContainsString('SuperAdminPermissionSeeder', $deployYmlContent);
        $this->assertStringContainsString('FuelPermissionSeeder', $deployYmlContent);
        $this->assertStringContainsString('AttendancePermissionSeeder', $deployYmlContent);
        $this->assertStringContainsString('--force --no-interaction', $deployYmlContent);
    }

    public function test_seeder_execution_assigns_permissions_to_super_admin_role(): void
    {
        try {
            DB::connection()->getPdo();
        } catch (\Throwable $e) {
            $this->markTestSkipped('Local MySQL server is currently offline; skipping live database query assertion.');
        }

        $superAdminRole = ConstructionRole::firstOrCreate(
            ['slug' => 'super_admin'],
            ['name' => 'Super Admin', 'description' => 'Full System Access', 'is_system_role' => true, 'status' => 'active']
        );

        $seeder = new SuperAdminPermissionSeeder();
        $seeder->run();

        foreach ($this->expectedFuelSlugs as $slug) {
            $permission = Permission::where('slug', $slug)->first();
            $this->assertNotNull($permission, "Permission {$slug} must exist in database");

            $this->assertDatabaseHas('construction_role_permissions', [
                'role_id' => $superAdminRole->id,
                'permission_id' => $permission->id,
            ]);
        }
    }
}
