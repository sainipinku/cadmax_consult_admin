<?php

namespace Database\Seeders;

use App\Models\ConstructionRole;
use App\Models\Permission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AttendancePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds for Granular Attendance & Shift Verification Permissions.
     */
    public function run(): void
    {
        $permissions = [
            [
                'name' => 'View Attendance',
                'slug' => 'attendance.view',
                'module' => 'Attendance & HR',
                'description' => 'View employee project and site attendance logs and overview metrics',
            ],
            [
                'name' => 'View Attendance Details',
                'slug' => 'attendance.details',
                'module' => 'Attendance & HR',
                'description' => 'View detailed shift breakdown, hours difference and daily work log',
            ],
            [
                'name' => 'View Attendance GPS Map',
                'slug' => 'attendance.map_view',
                'module' => 'Attendance & HR',
                'description' => 'View interactive map with check-in and check-out GPS pins and trajectory',
            ],
            [
                'name' => 'Review / Approve Attendance',
                'slug' => 'attendance.review',
                'module' => 'Attendance & HR',
                'description' => 'Review, approve, or reject site workforce attendance logs',
            ],
            [
                'name' => 'Edit Attendance',
                'slug' => 'attendance.edit',
                'module' => 'Attendance & HR',
                'description' => 'Edit site attendance timestamps, check-in/out and notes',
            ],
            [
                'name' => 'Export Attendance Logs',
                'slug' => 'attendance.export',
                'module' => 'Attendance & HR',
                'description' => 'Export project attendance logs in CSV or Excel format',
            ],
            [
                'name' => 'Delete Attendance',
                'slug' => 'attendance.delete',
                'module' => 'Attendance & HR',
                'description' => 'Delete invalid or erroneous attendance records',
            ],
        ];

        foreach ($permissions as $permData) {
            Permission::updateOrCreate(['slug' => $permData['slug']], $permData);
        }

        $superAdminRole = ConstructionRole::firstOrCreate(
            ['slug' => 'super_admin'],
            ['name' => 'Super Admin', 'description' => 'Full System Access', 'is_system_role' => true, 'status' => 'active']
        );

        $projectAdminRole = ConstructionRole::firstOrCreate(
            ['slug' => 'project_admin'],
            ['name' => 'Project Admin', 'description' => 'Project Administration Access', 'is_system_role' => true, 'status' => 'active']
        );

        foreach ($permissions as $perm) {
            $permission = Permission::where('slug', $perm['slug'])->first();
            if (!$permission) {
                continue;
            }

            // Assign all attendance permissions to Super Admin
            DB::table('construction_role_permissions')->updateOrInsert(
                ['role_id' => $superAdminRole->id, 'permission_id' => $permission->id],
                ['surface' => 'both', 'created_at' => now(), 'updated_at' => now()]
            );

            // Assign view, details, map_view, review, export to Project Admin (exclude delete/edit if restricted)
            if (in_array($perm['slug'], ['attendance.view', 'attendance.details', 'attendance.map_view', 'attendance.review', 'attendance.export'], true)) {
                DB::table('construction_role_permissions')->updateOrInsert(
                    ['role_id' => $projectAdminRole->id, 'permission_id' => $permission->id],
                    ['surface' => 'both', 'created_at' => now(), 'updated_at' => now()]
                );
            }
        }
    }
}

