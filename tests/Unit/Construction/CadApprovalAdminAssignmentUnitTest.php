<?php

namespace Tests\Unit\Construction;

use App\Models\Member;
use App\Services\Construction\ConstructionAuthorizationService;
use Tests\TestCase;

class CadApprovalAdminAssignmentUnitTest extends TestCase
{
    public function test_permission_seeders_and_provisioning_include_drawing_approval_assign_admin(): void
    {
        $seederFiles = [
            database_path('seeders/PermissionSeeder.php'),
            database_path('seeders/SuperAdminPermissionSeeder.php'),
            database_path('seeders/Construction/FoundationSeeder.php'),
            database_path('seeders/DrawingApprovalAssignPermissionSeeder.php'),
        ];

        foreach ($seederFiles as $file) {
            $this->assertFileExists($file, basename($file) . ' must exist');
            $content = file_get_contents($file);
            $this->assertStringContainsString(
                'drawing_approval.assign_admin',
                $content,
                basename($file) . ' must register drawing_approval.assign_admin permission'
            );
        }

        $databaseSeeder = file_get_contents(database_path('seeders/DatabaseSeeder.php'));
        $this->assertStringContainsString(
            'DrawingApprovalAssignPermissionSeeder',
            $databaseSeeder,
            'DatabaseSeeder must call DrawingApprovalAssignPermissionSeeder'
        );

        $provisioning = file_get_contents(app_path('Services/Construction/CompanyRoleProvisioningService.php'));
        $this->assertStringContainsString(
            'drawing_approval.assign_admin',
            $provisioning,
            'CompanyRoleProvisioningService must include drawing_approval.assign_admin for admin and super_admin roles'
        );

        $deployWorkflow = file_get_contents(base_path('.github/workflows/deploy.yml'));
        $this->assertStringContainsString(
            'DrawingApprovalAssignPermissionSeeder',
            $deployWorkflow,
            'Deploy workflow must execute DrawingApprovalAssignPermissionSeeder during deployment'
        );
    }

    public function test_foundation_seeder_does_not_grant_assign_admin_to_non_admin_worker_roles(): void
    {
        $foundation = file_get_contents(database_path('seeders/Construction/FoundationSeeder.php'));

        foreach (['surveyor', 'draft_person', 'vehicle_driver', 'review_approver', 'site_employee'] as $workerRole) {
            $pattern = "/'{$workerRole}'\s*=>\s*\[([\s\S]*?)\]/";
            $this->assertMatchesRegularExpression($pattern, $foundation);
            preg_match($pattern, $foundation, $matches);
            $this->assertStringNotContainsString(
                'drawing_approval.assign_admin',
                $matches[1] ?? '',
                "FoundationSeeder must NOT grant drawing_approval.assign_admin to {$workerRole}"
            );
        }
    }

    public function test_drafting_manage_does_not_alias_to_drawing_approval_assign_admin(): void
    {
        $authzPath = app_path('Services/Construction/ConstructionAuthorizationService.php');
        $content = file_get_contents($authzPath);
        $this->assertStringNotContainsString(
            "'drawing_approval.assign_admin'",
            substr($content, strpos($content, '$permissionAliases') ?: 0, 2200),
            'drawing_approval.assign_admin must not be aliased to drafting.manage or document.manage'
        );
    }

    public function test_assign_approval_admin_component_uses_simple_select_box_and_assign_button(): void
    {
        $componentPath = resource_path('js/Pages/Construction/Components/AssignApprovalAdminButton.jsx');
        $this->assertFileExists($componentPath);

        $code = file_get_contents($componentPath);
        $this->assertStringContainsString('<select', $code, 'AssignApprovalAdminButton must render a simple select box');
        $this->assertStringContainsString('Assign', $code, 'AssignApprovalAdminButton must render an Assign button');
        $this->assertStringContainsString('authorized', $code, 'AssignApprovalAdminButton must gate visibility via authorized prop');
        $this->assertStringContainsString('companyAdmins', $code, 'AssignApprovalAdminButton must accept companyAdmins prop');
    }

    public function test_superadmin_and_admin_project_views_wire_assign_button_and_blinking_border(): void
    {
        $superShow = file_get_contents(resource_path('js/Pages/SuperAdmin/Construction/Projects/Show.jsx'));
        $adminShow = file_get_contents(resource_path('js/Pages/Admin/Construction/Projects/Show.jsx'));
        $adminDashboard = file_get_contents(resource_path('js/Pages/Admin/Construction/Dashboard.jsx'));
        $superDashboard = file_get_contents(resource_path('js/Pages/SuperAdmin/Construction/Dashboard.jsx'));
        $adminProjects = file_get_contents(resource_path('js/Pages/Admin/Construction/Projects/Index.jsx'));
        $superProjects = file_get_contents(resource_path('js/Pages/SuperAdmin/Construction/Projects/Index.jsx'));
        $appCss = file_get_contents(resource_path('css/app.css'));

        $this->assertStringContainsString('AssignApprovalAdminButton', $superShow);
        $this->assertStringContainsString('drawing_approval.assign_admin', $superShow);
        $this->assertStringContainsString('cad-action-blink-border', $superShow);

        $this->assertStringContainsString('AssignApprovalAdminButton', $adminShow);
        $this->assertStringContainsString('drawing_approval.assign_admin', $adminShow);
        $this->assertStringContainsString('cad-action-blink-border', $adminShow);

        $this->assertStringContainsString('cad-action-blink-border', $adminDashboard);
        $this->assertStringContainsString('pending_assigned_cad_approvals', $adminDashboard);

        $this->assertStringContainsString('cad-action-blink-border', $superDashboard);
        $this->assertStringContainsString('pending_assigned_cad_approvals', $superDashboard);

        $this->assertStringContainsString('cad-action-blink-border', $adminProjects);
        $this->assertStringContainsString('cad-action-blink-border', $superProjects);

        $this->assertStringContainsString('@keyframes cad-action-blink-border', $appCss);
        $this->assertStringContainsString('.cad-action-blink-border', $appCss);
    }

    public function test_workflow_tracker_replaces_supervisor_with_admin_before_accounts_and_hides_edit_path_button(): void
    {
        $tracker = file_get_contents(resource_path('js/Pages/Construction/Components/WorkflowTracker.jsx'));
        $superShow = file_get_contents(resource_path('js/Pages/SuperAdmin/Construction/Projects/Show.jsx'));
        $adminShow = file_get_contents(resource_path('js/Pages/Admin/Construction/Projects/Show.jsx'));

        // Header flow label must show Survey -> Draft -> Admin -> Accounts -> Completed (no Supervisor)
        $this->assertStringContainsString('Survey → Draft → Admin → Accounts → Completed', $tracker);
        $this->assertStringNotContainsString('Survey → Draft → Accounts → Supervisor → Completed', $tracker);

        // Steps array must have admin before accounts and exclude supervisor
        $this->assertMatchesRegularExpression(
            "/key:\s*[\"']drafting[\"'][\s\S]*?key:\s*[\"']admin[\"'][\s\S]*?key:\s*[\"']accounts[\"'][\s\S]*?key:\s*[\"']completed[\"']/s",
            $tracker,
            'WorkflowTracker steps must progress Draft -> Admin -> Accounts -> Completed'
        );
        preg_match('/const\s+steps\s*=\s*\[([^\]]*)\];/s', $tracker, $stepsMatches);
        $stepsBlock = $stepsMatches[1] ?? '';
        $this->assertNotEmpty($stepsBlock, 'WorkflowTracker steps definition must exist');
        $this->assertStringNotContainsStringIgnoringCase('Supervisor', $stepsBlock, 'WorkflowTracker steps must remove Supervisor stage');
        $this->assertStringNotContainsString('Final Chain Review', $tracker, 'WorkflowTracker must remove Final Chain Review supervisor subtext');
        $this->assertStringContainsString('drawing_approval_pending: "admin"', $tracker);
        $this->assertStringContainsString('ready_for_construction: "accounts"', $tracker);

        // Edit Path toggle button must be hidden from UI
        $this->assertStringNotContainsString('Edit Path', $tracker, 'Edit Path button must be hidden in WorkflowTracker');
        $this->assertStringNotContainsString('Done Path', $tracker, 'Done Path button must be hidden in WorkflowTracker');

        // Project show views pass assignedAdminName to WorkflowTracker
        $this->assertStringContainsString('assignedAdminName={', $superShow);
        $this->assertStringContainsString('assignedAdminName={', $adminShow);
    }

    public function test_project_views_define_page_props_where_used_and_keep_use_page_hooks_at_top_level(): void
    {
        $superShow = file_get_contents(resource_path('js/Pages/SuperAdmin/Construction/Projects/Show.jsx'));
        $superIndex = file_get_contents(resource_path('js/Pages/SuperAdmin/Construction/Projects/Index.jsx'));

        // TaskRow uses pageProps, so it must bind pageProps from usePage().props
        preg_match('/function\s+TaskRow\s*\([\s\S]*/', $superShow, $taskRowMatch);
        $taskRowCode = $taskRowMatch[0] ?? '';
        $this->assertNotEmpty($taskRowCode);
        $this->assertMatchesRegularExpression(
            '/const\s+(?:pageProps\s*=\s*usePage\(\)\.props|\{\s*props:\s*pageProps\s*\}\s*=\s*usePage\(\))/',
            $taskRowCode,
            'TaskRow in SuperAdmin/Construction/Projects/Show.jsx must define pageProps from usePage()'
        );

        // No inline usePage() calls inside JSX or .map callbacks in Show.jsx or Index.jsx
        $this->assertDoesNotMatchRegularExpression('/approvals=\{[\s\S]{0,60}usePage\(\)/', $superShow);
        $this->assertDoesNotMatchRegularExpression('/filteredProjects\.map\([\s\S]{0,600}usePage\(\)/', $superIndex);
        $this->assertDoesNotMatchRegularExpression('/approvals=\{usePage\(\)/', $superIndex);
    }
}
