<?php

namespace App\Services\Construction;

use App\Models\Company;
use App\Models\ConstructionRole;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CompanyRoleProvisioningService
{
    /**
     * Core system roles that must exist for every construction company.
     *
     * @var array<string, string>
     */
    protected array $defaultTemplateRoles = [
        'project_admin' => 'Project Admin',
        'surveyor' => 'Surveyor',
        'draft_person' => 'Draft Person',
        'vehicle_driver' => 'Vehicle Driver',
        'review_approver' => 'Review Approver',
        'site_employee' => 'Site Employee',
        'super_admin' => 'Super Admin',
        'admin' => 'Admin',
    ];

    /**
     * Provision and clone all system template roles and their permissions for a specific company.
     *
     * @param int $companyId
     * @return array<string, ConstructionRole>
     */
    public function provisionCompanyRoles(int $companyId): array
    {
        $provisionedRoles = [];

        // 1. Ensure global template roles exist (company_id is null)
        $templateRoles = ConstructionRole::whereNull('company_id')->get()->keyBy('slug');

        foreach ($this->defaultTemplateRoles as $slug => $name) {
            if (!$templateRoles->has($slug)) {
                $isSystem = !in_array($slug, ['admin'], true);
                $templateRole = ConstructionRole::firstOrCreate(
                    ['company_id' => null, 'slug' => $slug],
                    [
                        'name' => $name,
                        'description' => "System default {$name} role",
                        'is_system_role' => $isSystem,
                        'status' => 'active',
                    ]
                );
                $templateRoles->put($slug, $templateRole);
            }
        }

        // Also include any other default roles from the legacy `roles` table
        $legacyDefaultRoles = Role::where('is_default', 1)->orWhere('status', 1)->get();
        foreach ($legacyDefaultRoles as $legacyRole) {
            $slug = strtolower(trim($legacyRole->slug));
            if (!in_array($slug, ['member', 'client'], true) && !$templateRoles->has($slug)) {
                $templateRole = ConstructionRole::firstOrCreate(
                    ['company_id' => null, 'slug' => $slug],
                    [
                        'name' => $legacyRole->name,
                        'description' => "Template {$legacyRole->name} role",
                        'is_system_role' => false,
                        'status' => 'active',
                    ]
                );
                $templateRoles->put($slug, $templateRole);
            }
        }

        // 2. Clone each template role for the target company
        foreach ($templateRoles as $slug => $templateRole) {
            $companyRole = ConstructionRole::firstOrCreate(
                [
                    'company_id' => $companyId,
                    'slug' => $templateRole->slug,
                ],
                [
                    'name' => $templateRole->name,
                    'description' => $templateRole->description,
                    'is_system_role' => $templateRole->is_system_role,
                    'status' => 'active',
                ]
            );

            // 3. Duplicate permissions from template role to company role
            $templatePermissionIds = DB::table('construction_role_permissions')
                ->where('role_id', $templateRole->id)
                ->pluck('permission_id')
                ->toArray();

            // If super_admin has no permissions assigned in template yet, give all permissions
            if (empty($templatePermissionIds) && in_array($slug, ['super_admin', 'superadmin'], true)) {
                $templatePermissionIds = Permission::pluck('id')->toArray();
            }

            $existingPermissionIds = DB::table('construction_role_permissions')
                ->where('role_id', $companyRole->id)
                ->pluck('permission_id')
                ->toArray();

            $permissionsToInsert = array_diff($templatePermissionIds, $existingPermissionIds);

            if (!empty($permissionsToInsert)) {
                $rows = [];
                $now = now();
                foreach ($permissionsToInsert as $permId) {
                    $rows[] = [
                        'role_id' => $companyRole->id,
                        'permission_id' => $permId,
                        'surface' => 'both',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
                DB::table('construction_role_permissions')->insert($rows);
            }

            $provisionedRoles[$slug] = $companyRole;
        }

        Log::info("[CompanyRoleProvisioningService] Successfully provisioned " . count($provisionedRoles) . " roles for company ID {$companyId}");

        return $provisionedRoles;
    }

    /**
     * Backfill all existing companies that do not yet have scoped construction roles.
     *
     * @return array<int, int> [company_id => roles_count]
     */
    public function backfillAllCompanies(): array
    {
        $companies = Company::all();
        $results = [];

        foreach ($companies as $company) {
            $roles = $this->provisionCompanyRoles($company->id);
            $results[$company->id] = count($roles);
        }

        return $results;
    }
}

