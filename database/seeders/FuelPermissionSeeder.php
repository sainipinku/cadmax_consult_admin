<?php

namespace Database\Seeders;

use App\Models\ConstructionRole;
use App\Models\Permission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FuelPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds for Granular Fuel History & Verification Permissions.
     */
    public function run(): void
    {
        $permissions = [
            [
                'name' => 'View Fuel Logs',
                'slug' => 'fuel_entry.view',
                'module' => 'Fleet & Equipment',
                'description' => 'View driver fuel consumption logs, filters, and KPI summary stats',
            ],
            [
                'name' => 'View Fuel Details',
                'slug' => 'fuel_entry.details',
                'module' => 'Fleet & Equipment',
                'description' => 'View fuel entry breakdown modal, receipt photo preview, and odometer reading',
            ],
            [
                'name' => 'Verify / Approve Fuel Entry',
                'slug' => 'fuel_entry.verify',
                'module' => 'Fleet & Equipment',
                'description' => 'Approve or Reject driver fuel logs with admin remarks',
            ],
            [
                'name' => 'Export Fuel Logs',
                'slug' => 'fuel_entry.export',
                'module' => 'Fleet & Equipment',
                'description' => 'Export filtered vehicle fuel logs to CSV or Excel',
            ],
            [
                'name' => 'Create Fuel Entry',
                'slug' => 'fuel_entry.create',
                'module' => 'Fleet & Equipment',
                'description' => 'Manually record fuel logs on behalf of a vehicle or driver',
            ],
            [
                'name' => 'Edit Fuel Entry',
                'slug' => 'fuel_entry.edit',
                'module' => 'Fleet & Equipment',
                'description' => 'Edit unapproved vehicle fuel entries and readings',
            ],
            [
                'name' => 'Delete Fuel Entry',
                'slug' => 'fuel_entry.delete',
                'module' => 'Fleet & Equipment',
                'description' => 'Delete invalid or erroneous fuel records',
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

            // Assign all fuel permissions to Super Admin
            DB::table('construction_role_permissions')->updateOrInsert(
                ['role_id' => $superAdminRole->id, 'permission_id' => $permission->id],
                ['surface' => 'both', 'created_at' => now(), 'updated_at' => now()]
            );

            // Assign view, details, verify, export to Project Admin
            if (in_array($perm['slug'], ['fuel_entry.view', 'fuel_entry.details', 'fuel_entry.verify', 'fuel_entry.export'], true)) {
                DB::table('construction_role_permissions')->updateOrInsert(
                    ['role_id' => $projectAdminRole->id, 'permission_id' => $permission->id],
                    ['surface' => 'both', 'created_at' => now(), 'updated_at' => now()]
                );
            }
        }
    }
}

