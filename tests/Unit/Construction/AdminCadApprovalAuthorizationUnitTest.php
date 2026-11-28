<?php

namespace Tests\Unit\Construction;

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
use App\Models\SuperAdmin;
use App\Models\SurveyPlan;
use App\Models\SurveySubmission;
use App\Models\SurveyVisit;
use App\Services\Construction\CadApprovalAssignmentService;
use App\Services\Construction\ConstructionAuthorizationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminCadApprovalAuthorizationUnitTest extends TestCase
{
    use DatabaseTransactions;

    public function test_permissions_for_project_includes_company_wide_null_project_assignments_but_excludes_other_projects(): void
    {
        $suffix = Str::lower(Str::random(5));
        $company = Company::create(['name' => "Auth Co {$suffix}", 'status' => 'active']);
        $sa = SuperAdmin::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'name' => "SA {$suffix}",
            'email' => "sa-{$suffix}@example.com",
            'phone' => (string) random_int(7000000000, 9999999999),
            'whatsapp_phone' => (string) random_int(7000000000, 9999999999),
            'password' => Hash::make('secret'),
            'status' => 1,
        ]);

        $member = Member::create([
            'uuid' => (string) Str::uuid(),
            'created_by' => $sa->id,
            'company_name' => $company->name,
            'name' => "Ravi Gupta {$suffix}",
            'username' => "ravi_{$suffix}",
            'slug' => "ravi-{$suffix}",
            'email' => "ravi-{$suffix}@example.com",
            'phone' => (string) random_int(7000000000, 9999999999),
            'password' => Hash::make('secret'),
            'status' => 1,
            'roles' => [],
        ]);

        $client = Client::create([
            'company_id' => $company->id,
            'client_code' => "CL-{$suffix}",
            'name' => "Client {$suffix}",
            'status' => 'active',
        ]);

        $projectA = Project::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'project_code' => "PRJ-A-{$suffix}",
            'name' => "Project A {$suffix}",
            'slug' => "proj-a-{$suffix}",
            'status' => 'active',
        ]);

        $projectB = Project::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'project_code' => "PRJ-B-{$suffix}",
            'name' => "Project B {$suffix}",
            'slug' => "proj-b-{$suffix}",
            'status' => 'active',
        ]);

        $globalAdminRole = ConstructionRole::create([
            'company_id' => $company->id,
            'name' => "Project Admin {$suffix}",
            'slug' => "project_admin_{$suffix}",
            'status' => 'active',
        ]);
        $projectSurveyorRole = ConstructionRole::create([
            'company_id' => $company->id,
            'name' => "Surveyor {$suffix}",
            'slug' => "surveyor_{$suffix}",
            'status' => 'active',
        ]);
        $otherProjectRole = ConstructionRole::create([
            'company_id' => $company->id,
            'name' => "Other Role {$suffix}",
            'slug' => "other_{$suffix}",
            'status' => 'active',
        ]);

        $approvePerm = Permission::firstOrCreate(
            ['slug' => 'drawing_approval.approve'],
            ['name' => 'Approve Drawing', 'module' => 'Project Lifecycle']
        );
        $surveyPerm = Permission::firstOrCreate(
            ['slug' => 'survey_plan.manage'],
            ['name' => 'Manage Survey Plan', 'module' => 'Project Lifecycle']
        );
        $billingPerm = Permission::firstOrCreate(
            ['slug' => 'billing_invoice.manage'],
            ['name' => 'Manage Billing', 'module' => 'Billing']
        );

        DB::table('construction_role_permissions')->insertOrIgnore([
            ['role_id' => $globalAdminRole->id, 'permission_id' => $approvePerm->id, 'surface' => 'both'],
            ['role_id' => $projectSurveyorRole->id, 'permission_id' => $surveyPerm->id, 'surface' => 'both'],
            ['role_id' => $otherProjectRole->id, 'permission_id' => $billingPerm->id, 'surface' => 'both'],
        ]);

        // Company-wide role (project_id = null) + projectA surveyor role + projectB billing role
        MemberRoleAssignment::create([
            'member_id' => $member->id,
            'role_id' => $globalAdminRole->id,
            'project_id' => null,
            'status' => 1,
        ]);
        MemberRoleAssignment::create([
            'member_id' => $member->id,
            'role_id' => $projectSurveyorRole->id,
            'project_id' => $projectA->id,
            'status' => 1,
        ]);
        MemberRoleAssignment::create([
            'member_id' => $member->id,
            'role_id' => $otherProjectRole->id,
            'project_id' => $projectB->id,
            'status' => 1,
        ]);

        $authz = app(ConstructionAuthorizationService::class);
        $projectAPerms = $authz->permissionsFor($member, $projectA->id);

        $this->assertContains('drawing_approval.approve', $projectAPerms);
        $this->assertContains('survey_plan.manage', $projectAPerms);
        $this->assertNotContains('billing_invoice.manage', $projectAPerms);
    }

    public function test_can_actor_review_assigned_approval_matches_explicitly_assigned_member_and_rejects_others(): void
    {
        $suffix = Str::lower(Str::random(5));
        $company = Company::create(['name' => "Assign Co {$suffix}", 'status' => 'active']);
        $sa = SuperAdmin::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'name' => "SA {$suffix}",
            'email' => "sa2-{$suffix}@example.com",
            'phone' => (string) random_int(7000000000, 9999999999),
            'whatsapp_phone' => (string) random_int(7000000000, 9999999999),
            'password' => Hash::make('secret'),
            'status' => 1,
        ]);

        $assignedAdmin = Member::create([
            'uuid' => (string) Str::uuid(),
            'created_by' => $sa->id,
            'company_name' => $company->name,
            'name' => "Assigned Admin {$suffix}",
            'username' => "assigned_{$suffix}",
            'slug' => "assigned-{$suffix}",
            'email' => "assigned-{$suffix}@example.com",
            'phone' => (string) random_int(7000000000, 9999999999),
            'password' => Hash::make('secret'),
            'status' => 1,
            'roles' => [],
        ]);

        $otherAdmin = Member::create([
            'uuid' => (string) Str::uuid(),
            'created_by' => $sa->id,
            'company_name' => $company->name,
            'name' => "Other Admin {$suffix}",
            'username' => "other_{$suffix}",
            'slug' => "other-{$suffix}",
            'email' => "other-{$suffix}@example.com",
            'phone' => (string) random_int(7000000000, 9999999999),
            'password' => Hash::make('secret'),
            'status' => 1,
            'roles' => [],
        ]);

        $client = Client::create([
            'company_id' => $company->id,
            'client_code' => "CL2-{$suffix}",
            'name' => "Client {$suffix}",
            'status' => 'active',
        ]);

        $project = Project::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'project_code' => "PRJ2-{$suffix}",
            'name' => "Project {$suffix}",
            'slug' => "proj2-{$suffix}",
            'status' => 'active',
        ]);

        $plan = SurveyPlan::create(['project_id' => $project->id, 'survey_code' => "SP-{$suffix}", 'title' => 'Plan', 'status' => 1]);
        $visit = SurveyVisit::create(['project_id' => $project->id, 'survey_plan_id' => $plan->id, 'member_id' => $assignedAdmin->id, 'checked_in_by_member_id' => $assignedAdmin->id, 'status' => 2]);
        $sub = SurveySubmission::create(['project_id' => $project->id, 'survey_visit_id' => $visit->id, 'submitted_by_member_id' => $assignedAdmin->id, 'status' => 2]);
        $job = DraftingJob::create(['project_id' => $project->id, 'survey_submission_id' => $sub->id, 'assigned_to_member_id' => $assignedAdmin->id, 'status' => 'submitted']);
        $rev = DrawingRevision::create(['project_id' => $project->id, 'drafting_job_id' => $job->id, 'revision_no' => 1, 'drawing_no' => 'DWG-TSK-00016', 'status' => 'submitted']);

        $approval = DrawingApproval::create([
            'project_id' => $project->id,
            'drawing_revision_id' => $rev->id,
            'assigned_to_type' => Member::class,
            'assigned_to_id' => $assignedAdmin->id,
            'assigned_at' => now(),
            'decision' => 'pending',
        ]);

        $service = app(CadApprovalAssignmentService::class);

        $this->assertTrue($service->canActorReviewAssignedApproval($assignedAdmin, $project, $job, $approval));
        $this->assertFalse($service->canActorReviewAssignedApproval($otherAdmin, $project, $job, $approval));
    }
}
