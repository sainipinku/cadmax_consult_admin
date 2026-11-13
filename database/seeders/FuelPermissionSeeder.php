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

        $superAdminRoles = ConstructionRole::whereIn('slug', ['super_admin', 'superadmin'])->get();
        if ($superAdminRoles->isEmpty()) {
            $superAdminRoles = collect([
                ConstructionRole::firstOrCreate(
                    ['slug' => 'super_admin', 'company_id' => null],
                    ['name' => 'Super Admin', 'description' => 'Full System Access', 'is_system_role' => true, 'status' => 'active']
                )
            ]);
        }

        $adminRoles = ConstructionRole::whereIn('slug', ['project_admin', 'admin'])->get();
        if ($adminRoles->isEmpty()) {
            $adminRoles = collect([
                ConstructionRole::firstOrCreate(
                    ['slug' => 'project_admin', 'company_id' => null],
                    ['name' => 'Project Admin', 'description' => 'Project Administration Access', 'is_system_role' => true, 'status' => 'active']
                )
            ]);
        }

        foreach ($permissions as $perm) {
            $permission = Permission::where('slug', $perm['slug'])->first();
            if (!$permission) {
                continue;
            }

            // Assign all fuel permissions to EVERY Super Admin role (global and company-scoped)
            foreach ($superAdminRoles as $role) {
                DB::table('construction_role_permissions')->updateOrInsert(
                    ['role_id' => $role->id, 'permission_id' => $permission->id],
                    ['surface' => 'both', 'created_at' => now(), 'updated_at' => now()]
                );
            }

            // Assign view, details, verify, export to EVERY Admin / Project Admin role
            if (in_array($perm['slug'], ['fuel_entry.view', 'fuel_entry.details', 'fuel_entry.verify', 'fuel_entry.export'], true)) {
                foreach ($adminRoles as $role) {
                    DB::table('construction_role_permissions')->updateOrInsert(
                        ['role_id' => $role->id, 'permission_id' => $permission->id],
                        ['surface' => 'both', 'created_at' => now(), 'updated_at' => now()]
                    );
                }
            }
        }
    }
}

