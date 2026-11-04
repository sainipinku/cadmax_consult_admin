<?php

namespace Tests\Feature\Construction;

use App\Models\ConstructionRole;
use App\Models\Permission;
use Database\Seeders\AttendancePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AttendancePermissionsSeederTest extends TestCase
{
    public function test_attendance_permission_seeder_creates_all_granular_permissions(): void
    {
        $expectedSlugs = [
            'attendance.view',
            'attendance.details',
            'attendance.map_view',
            'attendance.review',
            'attendance.edit',
            'attendance.export',
            'attendance.delete',
        ];

        // Ensure roles exist
        ConstructionRole::firstOrCreate(
            ['slug' => 'super_admin'],
            ['name' => 'Super Admin', 'description' => 'Full System Access', 'is_system_role' => true, 'status' => 'active']
        );
        ConstructionRole::firstOrCreate(
            ['slug' => 'project_admin'],
            ['name' => 'Project Admin', 'description' => 'Project Administration Access', 'is_system_role' => true, 'status' => 'active']
        );

        $seeder = new AttendancePermissionSeeder();
        $seeder->run();

        foreach ($expectedSlugs as $slug) {
            $this->assertDatabaseHas('construction_permissions', [
                'slug' => $slug,
                'module' => 'Attendance & HR',
            ]);
        }

        $superAdminRole = ConstructionRole::where('slug', 'super_admin')->first();
        $projectAdminRole = ConstructionRole::where('slug', 'project_admin')->first();

        $this->assertNotNull($superAdminRole);
        $this->assertNotNull($projectAdminRole);

        foreach ($expectedSlugs as $slug) {
            $permission = Permission::where('slug', $slug)->first();
            $this->assertNotNull($permission);

            $this->assertDatabaseHas('construction_role_permissions', [
                'role_id' => $superAdminRole->id,
                'permission_id' => $permission->id,
            ]);
        }
    }
}
