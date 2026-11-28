<?php

namespace Tests\Unit\Construction;

use App\Services\Construction\ConstructionAuthorizationService;
use Tests\TestCase;

class AdminRoleManagementAndTaskPermissionUnitTest extends TestCase
{
    public function test_permission_seeders_include_all_route_and_ui_permissions(): void
    {
        $requiredSlugs = [
            'attendance.manage',
            'audit_log.view',
            'billing.manage',
            'billing_invoice.view',
            'billing_invoice.create',
            'billing_invoice.edit',
            'billing_invoice.manage',
            'billing_payment.create',
            'client.manage',
            'document.manage',
            'dpr.manage',
            'drafting.manage',
            'drawing_approval.manage',
            'employee.manage',
            'equipment.manage',
            'equipment_allocation.view',
            'equipment_category.manage',
            'execution.manage',
            'execution.task.view',
            'execution_task.manage',
            'handover.manage',
            'material.manage',
            'material_issue.manage',
            'material_receipt.manage',
            'material_stock.manage',
            'project.manage',
            'project_team.manage',
            'purchase_order.manage',
            'purchase_request.manage',
            'purchase_request.review',
            'role.manage',
            'super_admin.manage',
            'survey_plan.manage',
            'task.manage',
            'vehicle.manage',
            'vehicle_tracking.manage',
            'vendor.manage',
        ];

        $seederFiles = [
            database_path('seeders/PermissionSeeder.php'),
            database_path('seeders/SuperAdminPermissionSeeder.php'),
        ];

        foreach ($seederFiles as $file) {
            $content = file_get_contents($file);

            foreach ($requiredSlugs as $slug) {
                $this->assertStringContainsString(
                    "'{$slug}'",
                    $content,
                    basename($file) . " must seed permission '{$slug}' so it appears in Role Management"
                );
            }

            $this->assertStringNotContainsString(
                "'slug' => 'approval_request.",
                $content,
                basename($file) . ' must keep approval_request.* permissions removed'
            );
        }
    }

    public function test_construction_authorization_service_expands_manage_permissions_to_crud_and_execution_edit(): void
    {
        $authz = app(ConstructionAuthorizationService::class);

        $this->assertTrue(method_exists($authz, 'matchesAnyPermission'));

        // Module .manage must authorize CRUD + action permissions in that module
        $this->assertTrue($authz->matchesAnyPermission(['execution.manage'], ['execution.view']));
        $this->assertTrue($authz->matchesAnyPermission(['execution.manage'], ['execution.create']));
        $this->assertTrue($authz->matchesAnyPermission(['execution.manage'], ['execution.edit']));
        $this->assertTrue($authz->matchesAnyPermission(['execution.manage'], ['execution.delete']));

        $this->assertTrue($authz->matchesAnyPermission(['execution_task.manage'], ['execution.edit']));
        $this->assertTrue($authz->matchesAnyPermission(['task.manage'], ['execution.edit']));

        $this->assertTrue($authz->matchesAnyPermission(['role.manage'], ['role.view']));
        $this->assertTrue($authz->matchesAnyPermission(['role.manage'], ['role.create']));
        $this->assertTrue($authz->matchesAnyPermission(['role.manage'], ['role.edit']));
        $this->assertTrue($authz->matchesAnyPermission(['role.manage'], ['role.delete']));
        $this->assertTrue($authz->matchesAnyPermission(['role.manage'], ['role.assign_permissions']));

        $this->assertTrue($authz->matchesAnyPermission(['drawing_approval.manage'], ['drawing_approval.view']));
        $this->assertTrue($authz->matchesAnyPermission(['drawing_approval.manage'], ['drawing_approval.approve']));
        $this->assertTrue($authz->matchesAnyPermission(['drawing_approval.manage'], ['drawing_approval.reject']));

        // CAD approval admin assignment remains explicitly gated (not unlocked by drafting.manage or drawing_approval.manage)
        $this->assertFalse($authz->matchesAnyPermission(['drafting.manage'], ['drawing_approval.assign_admin']));
        $this->assertFalse($authz->matchesAnyPermission(['drawing_approval.manage'], ['drawing_approval.assign_admin']));
    }

    public function test_role_accessible_for_guards_project_owner_check_with_super_admin_instance(): void
    {
        $roleModel = file_get_contents(app_path('Models/Role.php'));

        $this->assertMatchesRegularExpression(
            '/\$user\s+instanceof\s+SuperAdmin/',
            $roleModel,
            'Role::scopeAccessibleFor must verify $user instanceof SuperAdmin before checking owner privileges'
        );
        $this->assertStringContainsString(
            "if (\$user instanceof SuperAdmin && isset(\$user->id))",
            $roleModel,
            'Role::scopeAccessibleFor must only match created_by against $user->id for SuperAdmin instances'
        );
    }

    public function test_execution_assignment_routes_include_execution_manage_and_execution_task_manage(): void
    {
        $routes = file_get_contents(base_path('routes/web.php'));

        $this->assertMatchesRegularExpression(
            "/tasks-v2\/\{taskId\}\/transfer'[\s\S]{0,250}execution\.edit,execution_task\.manage,execution\.manage/",
            $routes,
            'tasks-v2.transfer must accept execution.edit, execution_task.manage, and execution.manage'
        );
        $this->assertMatchesRegularExpression(
            "/tasks-v2\/\{task\}\/assignments'[\s\S]{0,250}execution\.edit,execution_task\.manage,execution\.manage/",
            $routes,
            'tasks-v2.assignments.replace must accept execution.edit, execution_task.manage, and execution.manage'
        );
        $this->assertMatchesRegularExpression(
            "/tasks\/\{task\}\/assign'[\s\S]{0,250}execution\.edit,execution_task\.manage,execution\.manage/",
            $routes,
            'execution.tasks.assign routes must accept execution.edit, execution_task.manage, and execution.manage'
        );
    }

    public function test_superadmin_sidebar_supports_company_admin_role_management_and_list_avoids_member_created_by_collision(): void
    {
        $superSidebar = file_get_contents(resource_path('js/Pages/SuperAdmin/Layouts/Sidebar.jsx'));
        $adminSidebar = file_get_contents(resource_path('js/Pages/Admin/Layouts/Sidebar.jsx'));
        $rolesList = file_get_contents(resource_path('js/Pages/SuperAdmin/Roles/List.jsx'));

        $this->assertStringContainsString('role.assign_permissions', $superSidebar);
        $this->assertStringContainsString('role.assign_permissions', $adminSidebar);
        $this->assertStringContainsString('company_name', $superSidebar);
        $this->assertStringNotContainsString(
            'created_by: auth.user.id',
            $rolesList,
            'Roles/List.jsx should not pass Member id as created_by when creating roles from non-superadmin guard'
        );
    }
}
