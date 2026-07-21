<?php

namespace Database\Seeders\Construction;

use App\Models\Construction\Permission;
use App\Models\Construction\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FoundationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            ['name' => 'Company Manage', 'slug' => 'company.manage', 'module' => 'company'],
            ['name' => 'Client Manage', 'slug' => 'client.manage', 'module' => 'client'],
            ['name' => 'Project Manage', 'slug' => 'project.manage', 'module' => 'project'],
            ['name' => 'Project Budget Approve', 'slug' => 'project_budget.approve', 'module' => 'project_budget'],
            ['name' => 'Project Team Manage', 'slug' => 'project_team.manage', 'module' => 'project_team'],
            ['name' => 'Survey Plan Manage', 'slug' => 'survey_plan.manage', 'module' => 'survey_plan'],
            ['name' => 'Survey Submission Review', 'slug' => 'survey_submission.review', 'module' => 'survey_submission'],
            ['name' => 'Drafting Manage', 'slug' => 'drafting.manage', 'module' => 'drafting'],
            ['name' => 'Drawing Approval Manage', 'slug' => 'drawing_approval.manage', 'module' => 'drawing_approval'],
            ['name' => 'Execution Manage', 'slug' => 'execution.manage', 'module' => 'execution'],
            ['name' => 'Execution Task Manage', 'slug' => 'execution_task.manage', 'module' => 'execution_task'],
            ['name' => 'Daily Progress Manage', 'slug' => 'dpr.manage', 'module' => 'daily_progress'],
            ['name' => 'Daily Progress Review', 'slug' => 'dpr.review', 'module' => 'daily_progress'],
            ['name' => 'Attendance Manage', 'slug' => 'attendance.manage', 'module' => 'attendance'],
            ['name' => 'Attendance Review', 'slug' => 'attendance.review', 'module' => 'attendance'],
            ['name' => 'Vendor Manage', 'slug' => 'vendor.manage', 'module' => 'materials'],
            ['name' => 'Material Manage', 'slug' => 'material.manage', 'module' => 'materials'],
            ['name' => 'Purchase Request Manage', 'slug' => 'purchase_request.manage', 'module' => 'materials'],
            ['name' => 'Purchase Order Manage', 'slug' => 'purchase_order.manage', 'module' => 'materials'],
            ['name' => 'Material Receipt Manage', 'slug' => 'material_receipt.manage', 'module' => 'materials'],
            ['name' => 'Material Issue Manage', 'slug' => 'material_issue.manage', 'module' => 'materials'],
            ['name' => 'Material Stock Manage', 'slug' => 'material_stock.manage', 'module' => 'materials'],
            ['name' => 'Vehicle Manage', 'slug' => 'vehicle.manage', 'module' => 'vehicles'],
            ['name' => 'Vehicle Assignment Manage', 'slug' => 'vehicle_assignment.manage', 'module' => 'vehicles'],
            ['name' => 'Vehicle Tracking Manage', 'slug' => 'vehicle_tracking.manage', 'module' => 'vehicles'],
            ['name' => 'Equipment Manage', 'slug' => 'equipment.manage', 'module' => 'equipment'],
            ['name' => 'Equipment Allocation Manage', 'slug' => 'equipment_allocation.manage', 'module' => 'equipment'],
            ['name' => 'Equipment Usage Manage', 'slug' => 'equipment_usage.manage', 'module' => 'equipment'],
            ['name' => 'Billing Invoice Manage', 'slug' => 'billing_invoice.manage', 'module' => 'billing'],
            ['name' => 'Billing Payment Manage', 'slug' => 'billing_payment.manage', 'module' => 'billing'],
            ['name' => 'Handover Manage', 'slug' => 'handover.manage', 'module' => 'handover'],
            ['name' => 'Project Closure Manage', 'slug' => 'project_closure.manage', 'module' => 'handover'],
            ['name' => 'Document Manage', 'slug' => 'document.manage', 'module' => 'document'],
            ['name' => 'Activity Log View', 'slug' => 'activity_log.view', 'module' => 'activity_log'],
            ['name' => 'Dashboard View', 'slug' => 'dashboard.view', 'module' => 'dashboard'],
        ];

        $permissionSlugs = collect($permissions)->pluck('slug')->all();
        DB::table('construction_permissions')
            ->whereNotIn('slug', $permissionSlugs)
            ->delete();

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(['slug' => $permission['slug']], $permission);
        }

        $roles = [
            ['name' => 'Super Admin', 'slug' => 'super_admin', 'description' => 'Global construction ERP access', 'is_system_role' => true],
            ['name' => 'Project Admin', 'slug' => 'project_admin', 'description' => 'Project scoped ERP access', 'is_system_role' => true],
            ['name' => 'Surveyor', 'slug' => 'surveyor', 'description' => 'Field survey execution', 'is_system_role' => true],
            ['name' => 'Draft Person', 'slug' => 'draft_person', 'description' => 'Drafting and revisions', 'is_system_role' => true],
            ['name' => 'Review Approver', 'slug' => 'review_approver', 'description' => 'Workflow approvals', 'is_system_role' => true],
            ['name' => 'Site Employee', 'slug' => 'site_employee', 'description' => 'Construction execution updates and attendance', 'is_system_role' => true],
        ];

        $roleSlugs = collect($roles)->pluck('slug')->all();
        DB::table('construction_roles')
            ->where('is_system_role', true)
            ->whereNotIn('slug', $roleSlugs)
            ->delete();

        foreach ($roles as $roleData) {
            $role = Role::updateOrCreate(
                ['slug' => $roleData['slug']],
                [...$roleData, 'status' => 'active']
            );

            $rolePermissionSlugs = match ($role->slug) {
                'super_admin' => $permissionSlugs,
                'project_admin' => [
                    'dashboard.view',
                    'project.manage',
                    'project_budget.approve',
                    'project_team.manage',
                    'survey_plan.manage',
                    'survey_submission.review',
                    'drafting.manage',
                    'drawing_approval.manage',
                    'execution.manage',
                    'execution_task.manage',
                    'dpr.manage',
                    'dpr.review',
                    'attendance.manage',
                    'attendance.review',
                    'vendor.manage',
                    'material.manage',
                    'purchase_request.manage',
                    'purchase_order.manage',
                    'material_receipt.manage',
                    'material_issue.manage',
                    'material_stock.manage',
                    'vehicle.manage',
                    'vehicle_assignment.manage',
                    'vehicle_tracking.manage',
                    'equipment.manage',
                    'equipment_allocation.manage',
                    'equipment_usage.manage',
                    'billing_invoice.manage',
                    'billing_payment.manage',
                    'handover.manage',
                    'project_closure.manage',
                    'document.manage',
                    'activity_log.view',
                ],
                'surveyor' => [
                    'survey_plan.manage',
                    'document.manage',
                ],
                'draft_person' => [
                    'drafting.manage',
                    'document.manage',
                ],
                'review_approver' => [
                    'dashboard.view',
                    'survey_submission.review',
                    'drawing_approval.manage',
                    'dpr.review',
                    'attendance.review',
                    'material_stock.manage',
                    'activity_log.view',
                ],
                'site_employee' => [
                    'dashboard.view',
                    'execution_task.manage',
                    'dpr.manage',
                    'attendance.manage',
                    'material_issue.manage',
                    'vehicle_tracking.manage',
                    'equipment_allocation.manage',
                    'equipment_usage.manage',
                    'handover.manage',
                    'document.manage',
                ],
                default => [],
            };

            $role->permissions()->sync(
                Permission::whereIn('slug', $rolePermissionSlugs)->pluck('id')->all()
            );
        }
    }
}
