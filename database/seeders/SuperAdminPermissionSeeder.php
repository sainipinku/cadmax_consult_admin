<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\ConstructionRole;
use App\Models\Role;
use App\Models\SuperAdmin;
use App\Models\Member;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SuperAdminPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds to grant ALL granular CRUD permissions to Super Admin.
     */
    public function run(): void
    {
        $permissions = [
            // Overview Module
            ['name' => 'View ERP Dashboard', 'slug' => 'dashboard.view', 'module' => 'Overview', 'description' => 'View ERP Dashboard analytics and summary widgets'],
            ['name' => 'Export ERP Analytics', 'slug' => 'dashboard.export', 'module' => 'Overview', 'description' => 'Export dashboard metrics and system reports'],
            ['name' => 'View Permission Requests', 'slug' => 'approval_request.view', 'module' => 'Overview', 'description' => 'View pending role and permission requests'],
            ['name' => 'Approve Permission Request', 'slug' => 'approval_request.approve', 'module' => 'Overview', 'description' => 'Approve user requested permissions'],
            ['name' => 'Reject Permission Request', 'slug' => 'approval_request.reject', 'module' => 'Overview', 'description' => 'Reject user requested permissions'],
            ['name' => 'View Super Admins & Admins', 'slug' => 'super_admin.view', 'module' => 'Overview', 'description' => 'View Super Admin and Admin account listing'],
            ['name' => 'Create Admin Account', 'slug' => 'super_admin.create', 'module' => 'Overview', 'description' => 'Create new Super Admin or Admin account'],
            ['name' => 'Edit Admin Account', 'slug' => 'super_admin.edit', 'module' => 'Overview', 'description' => 'Edit Super Admin or Admin profile details'],
            ['name' => 'Delete Admin Account', 'slug' => 'super_admin.delete', 'module' => 'Overview', 'description' => 'Delete or deactivate Admin account'],
            ['name' => 'Change Admin Password', 'slug' => 'super_admin.change_password', 'module' => 'Overview', 'description' => 'Change password for Admin user'],
            ['name' => 'Emulate Admin User', 'slug' => 'super_admin.emulate', 'module' => 'Overview', 'description' => 'Emulate Admin or Super Admin user session'],

            // Foundation Module
            ['name' => 'View Employees', 'slug' => 'employee.view', 'module' => 'Foundation', 'description' => 'View employee directory'],
            ['name' => 'Create Employee', 'slug' => 'employee.create', 'module' => 'Foundation', 'description' => 'Register new employee'],
            ['name' => 'Edit Employee', 'slug' => 'employee.edit', 'module' => 'Foundation', 'description' => 'Update employee profile and designation'],
            ['name' => 'Delete Employee', 'slug' => 'employee.delete', 'module' => 'Foundation', 'description' => 'Delete employee profile'],
            ['name' => 'Emulate Employee', 'slug' => 'employee.emulate', 'module' => 'Foundation', 'description' => 'Emulate employee user session'],

            ['name' => 'View Clients', 'slug' => 'client.view', 'module' => 'Foundation', 'description' => 'View client register'],
            ['name' => 'Create Client', 'slug' => 'client.create', 'module' => 'Foundation', 'description' => 'Register new client'],
            ['name' => 'Edit Client', 'slug' => 'client.edit', 'module' => 'Foundation', 'description' => 'Edit client details and contact info'],
            ['name' => 'Delete Client', 'slug' => 'client.delete', 'module' => 'Foundation', 'description' => 'Delete client record'],

            ['name' => 'View Roles', 'slug' => 'role.view', 'module' => 'Foundation', 'description' => 'View system roles listing'],
            ['name' => 'Create Role', 'slug' => 'role.create', 'module' => 'Foundation', 'description' => 'Create custom system role'],
            ['name' => 'Edit Role', 'slug' => 'role.edit', 'module' => 'Foundation', 'description' => 'Edit role name and status'],
            ['name' => 'Delete Role', 'slug' => 'role.delete', 'module' => 'Foundation', 'description' => 'Delete system role'],
            ['name' => 'Assign Role Permissions', 'slug' => 'role.assign_permissions', 'module' => 'Foundation', 'description' => 'Manage role permissions matrix'],

            // Project Lifecycle Module
            ['name' => 'View Projects', 'slug' => 'project.view', 'module' => 'Project Lifecycle', 'description' => 'View construction projects'],
            ['name' => 'Create Project', 'slug' => 'project.create', 'module' => 'Project Lifecycle', 'description' => 'Create new construction project'],
            ['name' => 'Edit Project', 'slug' => 'project.edit', 'module' => 'Project Lifecycle', 'description' => 'Edit project details and dates'],
            ['name' => 'Delete Project', 'slug' => 'project.delete', 'module' => 'Project Lifecycle', 'description' => 'Delete construction project'],
            ['name' => 'Approve Project Budget', 'slug' => 'project_budget.approve', 'module' => 'Project Lifecycle', 'description' => 'Approve project cost budget'],

            ['name' => 'View Project Team', 'slug' => 'project_team.view', 'module' => 'Project Lifecycle', 'description' => 'View members assigned to project team'],
            ['name' => 'Add Project Team Member', 'slug' => 'project_team.create', 'module' => 'Project Lifecycle', 'description' => 'Assign user to project team'],
            ['name' => 'Edit Project Team Member', 'slug' => 'project_team.edit', 'module' => 'Project Lifecycle', 'description' => 'Update team member role in project'],
            ['name' => 'Remove Project Team Member', 'slug' => 'project_team.delete', 'module' => 'Project Lifecycle', 'description' => 'Remove user from project team'],

            ['name' => 'View Survey Planning', 'slug' => 'survey_plan.view', 'module' => 'Project Lifecycle', 'description' => 'View survey planning list'],
            ['name' => 'Create Survey Plan', 'slug' => 'survey_plan.create', 'module' => 'Project Lifecycle', 'description' => 'Create field survey plan'],
            ['name' => 'Edit Survey Plan', 'slug' => 'survey_plan.edit', 'module' => 'Project Lifecycle', 'description' => 'Edit field survey plan'],
            ['name' => 'Delete Survey Plan', 'slug' => 'survey_plan.delete', 'module' => 'Project Lifecycle', 'description' => 'Delete field survey plan'],

            ['name' => 'View Survey Entries', 'slug' => 'survey.view', 'module' => 'Project Lifecycle', 'description' => 'View field survey measurements'],
            ['name' => 'Create Survey Entry', 'slug' => 'survey.create', 'module' => 'Project Lifecycle', 'description' => 'Create new survey measurement entry'],
            ['name' => 'Edit Survey Entry', 'slug' => 'survey.edit', 'module' => 'Project Lifecycle', 'description' => 'Edit field survey entry'],
            ['name' => 'Delete Survey Entry', 'slug' => 'survey.delete', 'module' => 'Project Lifecycle', 'description' => 'Delete field survey entry'],
            ['name' => 'Submit Survey Entry', 'slug' => 'survey.submit', 'module' => 'Project Lifecycle', 'description' => 'Submit survey for approval'],
            ['name' => 'Review Survey Submissions', 'slug' => 'survey_submission.review', 'module' => 'Project Lifecycle', 'description' => 'Review survey submission details'],
            ['name' => 'Approve Survey Submission', 'slug' => 'survey_submission.approve', 'module' => 'Project Lifecycle', 'description' => 'Approve survey submission'],
            ['name' => 'Reject Survey Submission', 'slug' => 'survey_submission.reject', 'module' => 'Project Lifecycle', 'description' => 'Reject survey submission'],

            ['name' => 'View Drafting & CAD', 'slug' => 'drafting.view', 'module' => 'Project Lifecycle', 'description' => 'View CAD drawings and drafting workspace'],
            ['name' => 'Create Drafting Revision', 'slug' => 'drafting.create', 'module' => 'Project Lifecycle', 'description' => 'Create new CAD drawing revision'],
            ['name' => 'Edit Drafting Revision', 'slug' => 'drafting.edit', 'module' => 'Project Lifecycle', 'description' => 'Edit CAD drawing revision'],
            ['name' => 'Delete Drafting Revision', 'slug' => 'drafting.delete', 'module' => 'Project Lifecycle', 'description' => 'Delete CAD drawing revision'],

            ['name' => 'View Drawing Approvals', 'slug' => 'drawing_approval.view', 'module' => 'Project Lifecycle', 'description' => 'View drawing approval queue'],
            ['name' => 'Upload Drawing Entry', 'slug' => 'drawing_approval.create', 'module' => 'Project Lifecycle', 'description' => 'Upload new drawing for approval'],
            ['name' => 'Edit Drawing Entry', 'slug' => 'drawing_approval.edit', 'module' => 'Project Lifecycle', 'description' => 'Edit drawing entry metadata'],
            ['name' => 'Delete Drawing Entry', 'slug' => 'drawing_approval.delete', 'module' => 'Project Lifecycle', 'description' => 'Delete drawing entry'],
            ['name' => 'Approve Drawing', 'slug' => 'drawing_approval.approve', 'module' => 'Project Lifecycle', 'description' => 'Approve CAD drawing'],

            ['name' => 'View Site Execution', 'slug' => 'execution.view', 'module' => 'Project Lifecycle', 'description' => 'View site execution workspace'],
            ['name' => 'Create Execution Task', 'slug' => 'execution.create', 'module' => 'Project Lifecycle', 'description' => 'Create site execution task'],
            ['name' => 'Edit Execution Task', 'slug' => 'execution.edit', 'module' => 'Project Lifecycle', 'description' => 'Edit site execution task'],
            ['name' => 'Delete Execution Task', 'slug' => 'execution.delete', 'module' => 'Project Lifecycle', 'description' => 'Delete site execution task'],
            ['name' => 'Update Task Progress', 'slug' => 'execution.task.update', 'module' => 'Project Lifecycle', 'description' => 'Update progress status of site task'],

            ['name' => 'View Daily Progress (DPR)', 'slug' => 'dpr.view', 'module' => 'Project Lifecycle', 'description' => 'View Daily Progress Reports'],
            ['name' => 'Create Daily Progress Report', 'slug' => 'dpr.create', 'module' => 'Project Lifecycle', 'description' => 'Create new Daily Progress Report'],
            ['name' => 'Edit Daily Progress Report', 'slug' => 'dpr.edit', 'module' => 'Project Lifecycle', 'description' => 'Edit Daily Progress Report'],
            ['name' => 'Delete Daily Progress Report', 'slug' => 'dpr.delete', 'module' => 'Project Lifecycle', 'description' => 'Delete Daily Progress Report'],
            ['name' => 'Submit Daily Progress Report', 'slug' => 'dpr.submit', 'module' => 'Project Lifecycle', 'description' => 'Submit DPR for verification'],
            ['name' => 'Review / Approve DPR', 'slug' => 'dpr.review', 'module' => 'Project Lifecycle', 'description' => 'Review and approve DPR log'],

            // Fleet & Equipment Module
            ['name' => 'View Vehicles', 'slug' => 'vehicle.view', 'module' => 'Fleet & Equipment', 'description' => 'View fleet vehicle master list'],
            ['name' => 'Add Vehicle', 'slug' => 'vehicle.create', 'module' => 'Fleet & Equipment', 'description' => 'Register new vehicle to fleet'],
            ['name' => 'Edit Vehicle', 'slug' => 'vehicle.edit', 'module' => 'Fleet & Equipment', 'description' => 'Edit vehicle details'],
            ['name' => 'Delete Vehicle', 'slug' => 'vehicle.delete', 'module' => 'Fleet & Equipment', 'description' => 'Delete vehicle entry'],
            ['name' => 'Assign Vehicle', 'slug' => 'vehicle_assignment.manage', 'module' => 'Fleet & Equipment', 'description' => 'Assign vehicle to driver/site'],

            ['name' => 'View Vehicle Tracking', 'slug' => 'vehicle_tracking.view', 'module' => 'Fleet & Equipment', 'description' => 'View vehicle GPS and trip logs'],
            ['name' => 'Start Vehicle Trip', 'slug' => 'vehicle.trip.start', 'module' => 'Fleet & Equipment', 'description' => 'Start vehicle trip record'],
            ['name' => 'End Vehicle Trip', 'slug' => 'vehicle.trip.end', 'module' => 'Fleet & Equipment', 'description' => 'End vehicle trip record'],
            ['name' => 'Update Vehicle Location', 'slug' => 'vehicle.location.update', 'module' => 'Fleet & Equipment', 'description' => 'Update vehicle GPS coordinates'],

            ['name' => 'View Equipment Categories', 'slug' => 'equipment_category.view', 'module' => 'Fleet & Equipment', 'description' => 'View equipment categories'],
            ['name' => 'Create Equipment Category', 'slug' => 'equipment_category.create', 'module' => 'Fleet & Equipment', 'description' => 'Create equipment category'],
            ['name' => 'Edit Equipment Category', 'slug' => 'equipment_category.edit', 'module' => 'Fleet & Equipment', 'description' => 'Edit equipment category'],
            ['name' => 'Delete Equipment Category', 'slug' => 'equipment_category.delete', 'module' => 'Fleet & Equipment', 'description' => 'Delete equipment category'],

            ['name' => 'View Equipment', 'slug' => 'equipment.view', 'module' => 'Fleet & Equipment', 'description' => 'View machinery and equipment master list'],
            ['name' => 'Add Equipment', 'slug' => 'equipment.create', 'module' => 'Fleet & Equipment', 'description' => 'Register new equipment'],
            ['name' => 'Edit Equipment', 'slug' => 'equipment.edit', 'module' => 'Fleet & Equipment', 'description' => 'Edit equipment details'],
            ['name' => 'Delete Equipment', 'slug' => 'equipment.delete', 'module' => 'Fleet & Equipment', 'description' => 'Delete equipment entry'],
            ['name' => 'Manage Equipment Allocation', 'slug' => 'equipment_allocation.manage', 'module' => 'Fleet & Equipment', 'description' => 'Allocate equipment to site'],
            ['name' => 'Manage Equipment Usage', 'slug' => 'equipment_usage.manage', 'module' => 'Fleet & Equipment', 'description' => 'Log equipment runtime and usage'],

            // Materials & Procurement Module
            ['name' => 'View Vendors', 'slug' => 'vendor.view', 'module' => 'Materials & Procurement', 'description' => 'View registered supplier vendors'],
            ['name' => 'Add Vendor', 'slug' => 'vendor.create', 'module' => 'Materials & Procurement', 'description' => 'Add new vendor supplier'],
            ['name' => 'Edit Vendor', 'slug' => 'vendor.edit', 'module' => 'Materials & Procurement', 'description' => 'Edit vendor details'],
            ['name' => 'Delete Vendor', 'slug' => 'vendor.delete', 'module' => 'Materials & Procurement', 'description' => 'Delete vendor record'],

            ['name' => 'View Material Master', 'slug' => 'material.view', 'module' => 'Materials & Procurement', 'description' => 'View material item master'],
            ['name' => 'Add Material', 'slug' => 'material.create', 'module' => 'Materials & Procurement', 'description' => 'Add new material item'],
            ['name' => 'Edit Material', 'slug' => 'material.edit', 'module' => 'Materials & Procurement', 'description' => 'Edit material details'],
            ['name' => 'Delete Material', 'slug' => 'material.delete', 'module' => 'Materials & Procurement', 'description' => 'Delete material item'],

            ['name' => 'View Purchase Requests', 'slug' => 'purchase_request.view', 'module' => 'Materials & Procurement', 'description' => 'View purchase requisitions'],
            ['name' => 'Create Purchase Request', 'slug' => 'purchase_request.create', 'module' => 'Materials & Procurement', 'description' => 'Create new purchase request'],
            ['name' => 'Edit Purchase Request', 'slug' => 'purchase_request.edit', 'module' => 'Materials & Procurement', 'description' => 'Edit purchase request'],
            ['name' => 'Delete Purchase Request', 'slug' => 'purchase_request.delete', 'module' => 'Materials & Procurement', 'description' => 'Delete purchase request'],

            ['name' => 'View Purchase Orders', 'slug' => 'purchase_order.view', 'module' => 'Materials & Procurement', 'description' => 'View purchase orders'],
            ['name' => 'Create Purchase Order', 'slug' => 'purchase_order.create', 'module' => 'Materials & Procurement', 'description' => 'Generate new purchase order'],
            ['name' => 'Edit Purchase Order', 'slug' => 'purchase_order.edit', 'module' => 'Materials & Procurement', 'description' => 'Edit purchase order details'],
            ['name' => 'Delete Purchase Order', 'slug' => 'purchase_order.delete', 'module' => 'Materials & Procurement', 'description' => 'Delete purchase order'],

            ['name' => 'View Material Receipts', 'slug' => 'material_receipt.view', 'module' => 'Materials & Procurement', 'description' => 'View material receipt notes (MRN)'],
            ['name' => 'Create Material Receipt', 'slug' => 'material_receipt.create', 'module' => 'Materials & Procurement', 'description' => 'Create material receipt entry'],
            ['name' => 'Edit Material Receipt', 'slug' => 'material_receipt.edit', 'module' => 'Materials & Procurement', 'description' => 'Edit material receipt entry'],
            ['name' => 'Delete Material Receipt', 'slug' => 'material_receipt.delete', 'module' => 'Materials & Procurement', 'description' => 'Delete material receipt'],

            ['name' => 'View Material Issue Logs', 'slug' => 'material_issue.view', 'module' => 'Materials & Procurement', 'description' => 'View site material issue logs'],
            ['name' => 'Create Material Issue', 'slug' => 'material_issue.create', 'module' => 'Materials & Procurement', 'description' => 'Issue materials to site team'],
            ['name' => 'Edit Material Issue', 'slug' => 'material_issue.edit', 'module' => 'Materials & Procurement', 'description' => 'Edit material issue record'],
            ['name' => 'Delete Material Issue', 'slug' => 'material_issue.delete', 'module' => 'Materials & Procurement', 'description' => 'Delete material issue record'],

            ['name' => 'View Material Stock', 'slug' => 'material_stock.view', 'module' => 'Materials & Procurement', 'description' => 'View real-time inventory stock levels'],
            ['name' => 'Update Material Stock', 'slug' => 'material_stock.update', 'module' => 'Materials & Procurement', 'description' => 'Adjust inventory stock balance'],

            // Attendance & HR Module
            ['name' => 'View Attendance', 'slug' => 'attendance.view', 'module' => 'Attendance & HR', 'description' => 'View employee site attendance logs'],
            ['name' => 'Mark Attendance', 'slug' => 'attendance.mark', 'module' => 'Attendance & HR', 'description' => 'Mark daily attendance for workers'],
            ['name' => 'Edit Attendance', 'slug' => 'attendance.edit', 'module' => 'Attendance & HR', 'description' => 'Edit site attendance records'],
            ['name' => 'Review / Approve Attendance', 'slug' => 'attendance.review', 'module' => 'Attendance & HR', 'description' => 'Review and verify site attendance'],

            // Finance & Closure Module
            ['name' => 'View Invoices & Billing', 'slug' => 'billing.view', 'module' => 'Finance & Closure', 'description' => 'View client invoices and payment records'],
            ['name' => 'Create Invoice', 'slug' => 'billing.create', 'module' => 'Finance & Closure', 'description' => 'Create new client billing invoice'],
            ['name' => 'Edit Invoice', 'slug' => 'billing.edit', 'module' => 'Finance & Closure', 'description' => 'Edit billing invoice details'],
            ['name' => 'Delete Invoice', 'slug' => 'billing.delete', 'module' => 'Finance & Closure', 'description' => 'Delete billing invoice'],
            ['name' => 'Approve Billing & Payments', 'slug' => 'billing_payment.manage', 'module' => 'Finance & Closure', 'description' => 'Approve client billing and payment entries'],

            ['name' => 'View Project Handover', 'slug' => 'handover.view', 'module' => 'Finance & Closure', 'description' => 'View project handover checklists'],
            ['name' => 'Create Handover Checklist', 'slug' => 'handover.create', 'module' => 'Finance & Closure', 'description' => 'Create project handover checklist'],
            ['name' => 'Edit Handover Checklist', 'slug' => 'handover.edit', 'module' => 'Finance & Closure', 'description' => 'Edit project handover checklist'],
            ['name' => 'Delete Handover Checklist', 'slug' => 'handover.delete', 'module' => 'Finance & Closure', 'description' => 'Delete project handover checklist'],
            ['name' => 'Approve Project Closure', 'slug' => 'project_closure.manage', 'module' => 'Finance & Closure', 'description' => 'Approve final project closure'],

            ['name' => 'View Documents', 'slug' => 'document.view', 'module' => 'Finance & Closure', 'description' => 'View uploaded project documents'],
            ['name' => 'Upload Document', 'slug' => 'document.create', 'module' => 'Finance & Closure', 'description' => 'Upload project document file'],
            ['name' => 'Delete Document', 'slug' => 'document.delete', 'module' => 'Finance & Closure', 'description' => 'Delete project document file'],

            ['name' => 'View Activity Logs', 'slug' => 'activity_log.view', 'module' => 'Finance & Closure', 'description' => 'View audit trail and activity logs'],
        ];

        // Remove standalone company creation/management permissions
        Permission::where('slug', 'like', 'company.%')->delete();

        foreach ($permissions as $permData) {
            Permission::updateOrCreate(['slug' => $permData['slug']], $permData);
        }

        // 2. Fetch all permission IDs
        $allPermissions = Permission::all();

        // 3. Ensure Super Admin role exists in construction_roles
        $superAdminRole = ConstructionRole::firstOrCreate(
            ['slug' => 'super_admin'],
            [
                'name' => 'Super Admin',
                'description' => 'Full System Access',
                'is_system_role' => true,
                'status' => 'active'
            ]
        );

        $superAdminUser = SuperAdmin::first();
        $superAdminId = $superAdminUser ? $superAdminUser->id : 1;

        // Also sync in legacy/system roles table
        Role::firstOrCreate(
            ['slug' => 'super_admin'],
            [
                'name' => 'Super Admin',
                'status' => 1,
                'created_by' => $superAdminId,
            ]
        );

        // 4. Attach ALL permissions to Super Admin role
        $attachedCount = 0;
        foreach ($allPermissions as $perm) {
            DB::table('construction_role_permissions')->updateOrInsert(
                [
                    'role_id' => $superAdminRole->id,
                    'permission_id' => $perm->id,
                ],
                [
                    'surface' => 'both',
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
            $attachedCount++;
        }

        // 5. Ensure Super Admin members have member role assignments
        $members = Member::where('slug', 'admin')
            ->orWhere('slug', 'super-admin')
            ->orWhereJsonContains('roles', 1)
            ->get();

        foreach ($members as $member) {
            DB::table('construction_member_role_assignments')->updateOrInsert(
                [
                    'member_id' => $member->id,
                    'role_id' => $superAdminRole->id,
                ],
                [
                    'status' => 1,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }

        $this->command?->info("SuperAdminPermissionSeeder successfully granted all {$attachedCount} granular permissions to Super Admin role!");
    }
}
