<?php

namespace Tests\Feature\Construction;

use App\Models\Client;
use App\Models\Company;
use App\Models\ConstructionRole;
use App\Models\DraftingJob;
use App\Models\DrawingApproval;
use App\Models\DrawingRevision;
use App\Models\Member;
use App\Models\MemberRoleAssignment;
use App\Models\Permission;
use App\Models\Project;
use App\Models\ProjectTeamMember;
use App\Models\Role;
use App\Models\SuperAdmin;
use App\Models\SurveyPlan;
use App\Models\SurveySubmission;
use App\Models\SurveyVisit;
use App\Services\Construction\CompanyRoleProvisioningService;
use Database\Seeders\Construction\FoundationSeeder;
use Database\Seeders\DrawingApprovalAssignPermissionSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\SuperAdminPermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminCadApprovalWorkflowBugfixFeatureTest extends TestCase
{
    use DatabaseTransactions;

    private function actingOnlyAs($user, string $guard)
    {
        foreach (['superadmin', 'admin', 'member', 'web'] as $g) {
            if (auth()->guard($g)->check()) {
                auth()->guard($g)->logout();
            }
        }
        session()->flush();

        return $this->actingAs($user, $guard);
    }

    private function createScenarioFixture(string $suffix): array
    {
        $this->seed(PermissionSeeder::class);
        $this->seed(SuperAdminPermissionSeeder::class);
        $this->seed(FoundationSeeder::class);
        $this->seed(DrawingApprovalAssignPermissionSeeder::class);

        $company = Company::create(['name' => "Fix Co {$suffix}", 'status' => 'active']);
        $superAdmin = SuperAdmin::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'roles' => ['super_admin'],
            'name' => "GeRun {$suffix}",
            'email' => "gerun-{$suffix}@example.com",
            'phone' => (string) random_int(7000000000, 9999999999),
            'whatsapp_phone' => (string) random_int(7000000000, 9999999999),
            'password' => Hash::make('secret123'),
            'status' => 1,
        ]);

        app(CompanyRoleProvisioningService::class)->provisionCompanyRoles($company->id);
        app(CompanyRoleProvisioningService::class)->syncAllSuperAdminAndAdminPermissions();

        $projectAdminSysRole = Role::firstOrCreate(['slug' => 'project_admin'], ['name' => 'Project Admin', 'status' => 1, 'is_default' => 1, 'created_by' => 1]);
        $cProjectAdminRole = ConstructionRole::where('company_id', $company->id)->where('slug', 'project_admin')->firstOrFail();
        $cSurveyorRole = ConstructionRole::where('company_id', $company->id)->where('slug', 'surveyor')->firstOrFail();
        $cDraftRole = ConstructionRole::where('company_id', $company->id)->where('slug', 'draft_person')->firstOrFail();

        $raviAdmin = Member::create([
            'uuid' => (string) Str::uuid(),
            'created_by' => $superAdmin->id,
            'company_name' => $company->name,
            'name' => "Ravi Gupta {$suffix}",
            'username' => "ravi_{$suffix}",
            'slug' => "ravi-{$suffix}",
            'email' => "ravi-{$suffix}@example.com",
            'phone' => (string) random_int(7000000000, 9999999999),
            'password' => Hash::make('secret123'),
            'status' => 1,
            'roles' => [$projectAdminSysRole->id],
        ]);

        $draftsman = Member::create([
            'uuid' => (string) Str::uuid(),
            'created_by' => $superAdmin->id,
            'company_name' => $company->name,
            'name' => "Drafter {$suffix}",
            'username' => "drafter_{$suffix}",
            'slug' => "drafter-{$suffix}",
            'email' => "drafter-{$suffix}@example.com",
            'phone' => (string) random_int(7000000000, 9999999999),
            'password' => Hash::make('secret123'),
            'status' => 1,
            'roles' => [3],
        ]);

        $client = Client::create([
            'company_id' => $company->id,
            'client_code' => "CL-{$suffix}",
            'name' => "Client {$suffix}",
            'status' => 'active',
        ]);

        $project = Project::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'project_code' => 'PRJ-00012-' . Str::upper($suffix),
            'name' => "Chomu Float Side Map Survey {$suffix}",
            'slug' => "chomu-{$suffix}",
            'status' => 'on_hold',
            'current_stage' => 'drawing_approval_pending',
            'created_by_type' => SuperAdmin::class,
            'created_by_id' => $superAdmin->id,
        ]);

        $plan = SurveyPlan::create(['project_id' => $project->id, 'survey_code' => "SP-{$suffix}", 'title' => 'Survey', 'status' => 1]);
        $visit = SurveyVisit::create(['project_id' => $project->id, 'survey_plan_id' => $plan->id, 'member_id' => $draftsman->id, 'checked_in_by_member_id' => $draftsman->id, 'status' => 2]);
        $submission = SurveySubmission::create(['project_id' => $project->id, 'survey_visit_id' => $visit->id, 'submitted_by_member_id' => $draftsman->id, 'status' => 2]);

        $jobOne = DraftingJob::create([
            'project_id' => $project->id,
            'survey_submission_id' => $submission->id,
            'assigned_to_member_id' => $draftsman->id,
            'status' => 'submitted',
        ]);

        return compact(
            'company',
            'superAdmin',
            'raviAdmin',
            'draftsman',
            'project',
            'plan',
            'visit',
            'submission',
            'jobOne',
            'cProjectAdminRole',
            'cSurveyorRole',
            'cDraftRole'
        );
    }

    public function test_admin_with_global_project_admin_role_and_project_scoped_surveyor_role_can_approve_assigned_cad(): void
    {
        $f = $this->createScenarioFixture(Str::lower(Str::random(5)));

        // Global project_admin role (project_id = null) + project-scoped surveyor role (project_id = $project->id)
        MemberRoleAssignment::create([
            'member_id' => $f['raviAdmin']->id,
            'role_id' => $f['cProjectAdminRole']->id,
            'project_id' => null,
            'status' => 1,
        ]);
        MemberRoleAssignment::create([
            'member_id' => $f['raviAdmin']->id,
            'role_id' => $f['cSurveyorRole']->id,
            'project_id' => $f['project']->id,
            'status' => 1,
        ]);

        $rev1 = DrawingRevision::create([
            'project_id' => $f['project']->id,
            'drafting_job_id' => $f['jobOne']->id,
            'revision_no' => 1,
            'drawing_no' => 'DWG-TSK-00016',
            'status' => 'submitted',
        ]);

        $approval = DrawingApproval::create([
            'project_id' => $f['project']->id,
            'drawing_revision_id' => $rev1->id,
            'assigned_to_type' => Member::class,
            'assigned_to_id' => $f['raviAdmin']->id,
            'assigned_by_type' => SuperAdmin::class,
            'assigned_by_id' => $f['superAdmin']->id,
            'assigned_at' => now(),
            'decision' => 'pending',
        ]);

        $response = $this->actingOnlyAs($f['raviAdmin'], 'admin')
            ->from(route('admin.construction.projects.show', $f['project']->id))
            ->post(route('admin.construction.projects.drafting-jobs.review', [$f['project']->id, $f['jobOne']->id]), [
                'decision' => 'approved',
                'remarks' => 'Approved by Project Admin',
                'drawing_approval_id' => $approval->id,
            ]);

        $response->assertStatus(302);
        $response->assertSessionHasNoErrors();

        $this->assertSame('approved', $approval->fresh()->decision);
        $this->assertSame('approved', $f['jobOne']->fresh()->status);
        $this->assertSame('ready_for_construction', $f['project']->fresh()->current_stage);

        // Banner clears completely from Admin project show and dashboard
        $this->actingOnlyAs($f['raviAdmin'], 'admin')
            ->get(route('admin.construction.projects.show', $f['project']->id))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('pending_assigned_cad_approvals', 0));
    }

    public function test_explicitly_assigned_approver_without_role_approval_permission_or_team_row_can_approve_assigned_cad_while_unassigned_gets_403(): void
    {
        $f = $this->createScenarioFixture(Str::lower(Str::random(5)));

        // Strip drawing_approval.* and drafting.* permissions from a custom restricted role assigned to raviAdmin
        $restrictedRole = ConstructionRole::create([
            'company_id' => $f['company']->id,
            'name' => 'Limited Reviewer',
            'slug' => 'limited_reviewer_' . Str::lower(Str::random(4)),
            'status' => 'active',
        ]);
        $viewPerm = Permission::firstOrCreate(['slug' => 'project.view'], ['name' => 'View Project', 'module' => 'Project']);
        DB::table('construction_role_permissions')->insertOrIgnore([
            ['role_id' => $restrictedRole->id, 'permission_id' => $viewPerm->id, 'surface' => 'both'],
        ]);

        MemberRoleAssignment::create([
            'member_id' => $f['raviAdmin']->id,
            'role_id' => $restrictedRole->id,
            'project_id' => $f['project']->id,
            'status' => 1,
        ]);

        // Another unassigned restricted member must still receive 403
        $unassignedMember = Member::create([
            'uuid' => (string) Str::uuid(),
            'created_by' => $f['superAdmin']->id,
            'company_name' => $f['company']->name,
            'name' => 'Unassigned User',
            'username' => 'unassigned_' . Str::lower(Str::random(5)),
            'slug' => 'unassigned-' . Str::lower(Str::random(5)),
            'email' => 'unassigned-' . Str::lower(Str::random(5)) . '@example.com',
            'phone' => (string) random_int(7000000000, 9999999999),
            'password' => Hash::make('secret123'),
            'status' => 1,
            'roles' => [3],
        ]);
        MemberRoleAssignment::create([
            'member_id' => $unassignedMember->id,
            'role_id' => $restrictedRole->id,
            'project_id' => $f['project']->id,
            'status' => 1,
        ]);

        $rev = DrawingRevision::create([
            'project_id' => $f['project']->id,
            'drafting_job_id' => $f['jobOne']->id,
            'revision_no' => 1,
            'drawing_no' => 'DWG-TSK-00016',
            'status' => 'submitted',
        ]);

        $approval = DrawingApproval::create([
            'project_id' => $f['project']->id,
            'drawing_revision_id' => $rev->id,
            'assigned_to_type' => Member::class,
            'assigned_to_id' => $f['raviAdmin']->id,
            'assigned_by_type' => SuperAdmin::class,
            'assigned_by_id' => $f['superAdmin']->id,
            'assigned_at' => now(),
            'decision' => 'pending',
        ]);

        // Unassigned user is still blocked with 403
        $this->actingOnlyAs($unassignedMember, 'admin')
            ->post(route('admin.construction.projects.drafting-jobs.review', [$f['project']->id, $f['jobOne']->id]), [
                'decision' => 'approved',
            ])
            ->assertStatus(403);

        // Explicitly assigned user succeeds even without drawing_approval.approve on their role
        $this->actingOnlyAs($f['raviAdmin'], 'admin')
            ->post(route('admin.construction.projects.drafting-jobs.review', [$f['project']->id, $f['jobOne']->id]), [
                'decision' => 'approved',
                'remarks' => 'Approved by assigned reviewer',
            ])
            ->assertStatus(302)
            ->assertSessionHasNoErrors();

        $this->assertSame('approved', $approval->fresh()->decision);
    }

    public function test_approving_drafting_job_closes_earlier_revision_pending_assigned_approvals_so_banner_clears(): void
    {
        $f = $this->createScenarioFixture(Str::lower(Str::random(5)));

        MemberRoleAssignment::create([
            'member_id' => $f['raviAdmin']->id,
            'role_id' => $f['cProjectAdminRole']->id,
            'project_id' => null,
            'status' => 1,
        ]);

        // Revision 1 had an assigned pending DrawingApproval; then Revision 2 was uploaded on same DraftingJob
        $rev1 = DrawingRevision::create([
            'project_id' => $f['project']->id,
            'drafting_job_id' => $f['jobOne']->id,
            'revision_no' => 1,
            'drawing_no' => 'DWG-TSK-00016',
            'status' => 'submitted',
            'created_at' => now()->subMinutes(10),
        ]);
        $rev2 = DrawingRevision::create([
            'project_id' => $f['project']->id,
            'drafting_job_id' => $f['jobOne']->id,
            'revision_no' => 2,
            'drawing_no' => 'DWG-TSK-00016',
            'status' => 'submitted',
            'created_at' => now()->subMinutes(5),
        ]);

        $approval1 = DrawingApproval::create([
            'project_id' => $f['project']->id,
            'drawing_revision_id' => $rev1->id,
            'assigned_to_type' => Member::class,
            'assigned_to_id' => $f['raviAdmin']->id,
            'assigned_at' => now()->subMinutes(8),
            'decision' => 'pending',
        ]);
        $approval2 = DrawingApproval::create([
            'project_id' => $f['project']->id,
            'drawing_revision_id' => $rev2->id,
            'decision' => 'pending',
        ]);

        $this->actingOnlyAs($f['raviAdmin'], 'admin')
            ->post(route('admin.construction.projects.drafting-jobs.review', [$f['project']->id, $f['jobOne']->id]), [
                'decision' => 'approved',
                'remarks' => 'Approve final CAD',
            ])
            ->assertStatus(302)
            ->assertSessionHasNoErrors();

        $this->assertSame('approved', $approval1->fresh()->decision);
        $this->assertSame('approved', $approval2->fresh()->decision);
        $this->assertSame('approved', $rev1->fresh()->status);
        $this->assertSame('approved', $rev2->fresh()->status);

        $this->actingOnlyAs($f['raviAdmin'], 'admin')
            ->get(route('admin.construction.projects.show', $f['project']->id))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('pending_assigned_cad_approvals', 0));
    }

    public function test_project_admin_can_approve_via_admin_drafting_approval_endpoint_and_ui_sends_valid_payload(): void
    {
        $f = $this->createScenarioFixture(Str::lower(Str::random(5)));

        // Provisioned project_admin has drawing_approval.approve (and not necessarily drawing_approval.manage)
        MemberRoleAssignment::create([
            'member_id' => $f['raviAdmin']->id,
            'role_id' => $f['cProjectAdminRole']->id,
            'project_id' => null,
            'status' => 1,
        ]);

        $rev = DrawingRevision::create([
            'project_id' => $f['project']->id,
            'drafting_job_id' => $f['jobOne']->id,
            'revision_no' => 1,
            'drawing_no' => 'DWG-TSK-00016',
            'status' => 'submitted',
        ]);

        $approval = DrawingApproval::create([
            'project_id' => $f['project']->id,
            'drawing_revision_id' => $rev->id,
            'assigned_to_type' => Member::class,
            'assigned_to_id' => $f['raviAdmin']->id,
            'assigned_at' => now(),
            'decision' => 'pending',
        ]);

        $this->actingOnlyAs($f['raviAdmin'], 'admin')
            ->post(route('admin.construction.drafting.approvals.update', $approval->id), [
                'decision' => 'approved',
                'remarks' => 'Approved from Drafting Queue',
            ])
            ->assertStatus(302)
            ->assertSessionHasNoErrors();

        $this->assertSame('approved', $approval->fresh()->decision);

        $adminDraftingJs = file_get_contents(resource_path('js/Pages/Admin/Construction/Drafting/Index.jsx'));
        $this->assertStringContainsString('approval.decision === "pending" ? "approved"', $adminDraftingJs);
        $this->assertStringContainsString('remarks', $adminDraftingJs);
    }

    public function test_banner_and_project_show_pages_wire_direct_review_action_for_assigned_cad_approvals(): void
    {
        $bannerJs = file_get_contents(resource_path('js/Pages/Construction/Components/PendingCadApprovalAlertBanner.jsx'));
        $adminShowJs = file_get_contents(resource_path('js/Pages/Admin/Construction/Projects/Show.jsx'));
        $superShowJs = file_get_contents(resource_path('js/Pages/SuperAdmin/Construction/Projects/Show.jsx'));

        $this->assertStringContainsString('onReview', $bannerJs);
        $this->assertStringContainsString('onReview={', $adminShowJs);
        $this->assertStringContainsString('onReview={', $superShowJs);
    }
}
