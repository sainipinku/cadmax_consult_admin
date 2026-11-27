<?php

namespace Tests\Feature\Construction;

use App\Enums\ClientReviewStatus;
use App\Models\Client;
use App\Models\Company;
use App\Models\ConstructionRole;
use App\Models\DraftingJob;
use App\Models\DrawingApproval;
use App\Models\DrawingRevision;
use App\Models\ExecutionPlan;
use App\Models\ExecutionTask;
use App\Models\ExecutionTaskAssignee;
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
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Services\Construction\CompanyRoleProvisioningService;
use App\Services\Construction\SurveyorToDraftsmanAssignmentService;
use Database\Seeders\Construction\FoundationSeeder;
use Database\Seeders\DrawingApprovalAssignPermissionSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\SuperAdminPermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class CadApprovalCompletesTaskStatusFeatureTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

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

    private function createWorkflowFixture(string $suffix): array
    {
        $this->seed(PermissionSeeder::class);
        $this->seed(SuperAdminPermissionSeeder::class);
        $this->seed(FoundationSeeder::class);
        $this->seed(DrawingApprovalAssignPermissionSeeder::class);

        $company = Company::create([
            'name' => "CAD Complete Co {$suffix}",
            'status' => 'active',
        ]);

        $superAdmin = SuperAdmin::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'roles' => ['super_admin'],
            'name' => "SuperAdmin {$suffix}",
            'email' => "sa-cad-{$suffix}@example.com",
            'phone' => (string) random_int(7000000000, 9999999999),
            'whatsapp_phone' => (string) random_int(7000000000, 9999999999),
            'password' => Hash::make('password123'),
            'status' => 1,
        ]);

        $company->update([
            'created_by_type' => SuperAdmin::class,
            'created_by_id' => $superAdmin->id,
        ]);

        app(CompanyRoleProvisioningService::class)->provisionCompanyRoles($company->id);
        app(CompanyRoleProvisioningService::class)->syncAllSuperAdminAndAdminPermissions();

        $adminSysRole = Role::firstOrCreate(['slug' => 'project_admin'], ['name' => 'Project Admin', 'status' => 1, 'is_default' => 1, 'created_by' => 1]);
        $surveyorSysRole = Role::firstOrCreate(['slug' => 'surveyor'], ['name' => 'Surveyor', 'status' => 1, 'is_default' => 1, 'created_by' => 1]);
        $draftSysRole = Role::firstOrCreate(['slug' => 'draft_person'], ['name' => 'Draft Person', 'status' => 1, 'is_default' => 1, 'created_by' => 1]);

        $cAdminRole = ConstructionRole::where('company_id', $company->id)->where('slug', 'project_admin')->firstOrFail();
        $cSurveyorRole = ConstructionRole::where('company_id', $company->id)->where('slug', 'surveyor')->firstOrFail();
        $cDraftRole = ConstructionRole::where('company_id', $company->id)->where('slug', 'draft_person')->firstOrFail();

        $approvePermIds = Permission::whereIn('slug', [
            'project.view',
            'project.manage',
            'execution.view',
            'execution_task.manage',
            'drafting.manage',
            'drawing_approval.manage',
            'drawing_approval.approve',
            'drawing_approval.assign_admin',
        ])->pluck('id')->all();
        $cAdminRole->permissions()->syncWithoutDetaching($approvePermIds);

        $draftPermIds = Permission::whereIn('slug', [
            'project.view',
            'execution_task.manage',
            'drafting.manage',
            'document.manage',
        ])->pluck('id')->all();
        $cDraftRole->permissions()->syncWithoutDetaching($draftPermIds);

        $makeMember = function (string $label, Role $sysRole, ConstructionRole $cRole) use ($company, $superAdmin): Member {
            $token = Str::lower(Str::random(6));
            $member = Member::create([
                'uuid' => (string) Str::uuid(),
                'created_by' => $superAdmin->id,
                'company_name' => $company->name,
                'name' => "{$label} {$token}",
                'username' => "{$label}_{$token}",
                'slug' => Str::slug("{$label}-{$token}"),
                'email' => Str::slug("{$label}-{$token}") . '@example.com',
                'phone' => (string) random_int(7000000000, 9999999999),
                'password' => Hash::make('password123'),
                'status' => 1,
                'roles' => [$sysRole->id],
            ]);

            MemberRoleAssignment::create([
                'member_id' => $member->id,
                'role_id' => $cRole->id,
                'project_id' => null,
                'status' => 1,
            ]);

            return $member;
        };

        $admin = $makeMember('admin', $adminSysRole, $cAdminRole);
        $surveyor = $makeMember('surveyor', $surveyorSysRole, $cSurveyorRole);
        $draftsman = $makeMember('draftsman', $draftSysRole, $cDraftRole);

        $client = Client::create([
            'company_id' => $company->id,
            'client_code' => 'CL-' . Str::upper(Str::random(6)),
            'name' => "Client {$suffix}",
            'status' => 'active',
        ]);

        $project = Project::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'project_code' => 'PRJ-' . Str::upper(Str::random(6)),
            'name' => "CAD Completion Project {$suffix}",
            'slug' => 'cad-comp-' . Str::lower(Str::random(6)),
            'status' => 'active',
            'current_stage' => 'survey_in_progress',
        ]);

        foreach ([[$admin, $cAdminRole], [$surveyor, $cSurveyorRole], [$draftsman, $cDraftRole]] as [$m, $r]) {
            ProjectTeamMember::create([
                'project_id' => $project->id,
                'member_id' => $m->id,
                'role_id' => $r->id,
                'status' => 'active',
            ]);
        }

        $plan = ExecutionPlan::create([
            'project_id' => $project->id,
            'plan_code' => 'EP-' . Str::upper(Str::random(5)),
            'title' => 'Master Plan',
            'status' => 'approved',
        ]);

        $surveyPlan = SurveyPlan::create([
            'project_id' => $project->id,
            'survey_code' => 'SP-' . Str::upper(Str::random(6)),
            'title' => 'Main Survey',
            'status' => 1,
        ]);

        $unifiedTask = Task::create([
            'uuid' => (string) Str::uuid(),
            'project_id' => $project->id,
            'execution_plan_id' => $plan->id,
            'task_code' => 'TSK-00017',
            'title' => 'today tast title 26112026',
            'status' => 'completed',
            'progress_percent' => 100,
            'created_by' => $superAdmin->id,
            'assigned_supervisor_member_id' => $surveyor->id,
            'member_id' => $surveyor->id,
        ]);

        $execTask = ExecutionTask::create([
            'project_id' => $project->id,
            'execution_plan_id' => $plan->id,
            'task_code' => 'TSK-00017',
            'title' => 'today tast title 26112026',
            'status' => 'completed',
            'progress_percent' => 100,
            'supervisor_member_id' => $surveyor->id,
        ]);

        TaskAssignment::create([
            'uuid' => (string) Str::uuid(),
            'task_id' => $unifiedTask->id,
            'project_id' => $project->id,
            'assigned_to' => $surveyor->id,
            'assigned_by' => $superAdmin->id,
            'assignment_role' => 'surveyor',
            'status' => 'active',
        ]);

        ExecutionTaskAssignee::create([
            'project_id' => $project->id,
            'execution_task_id' => $execTask->id,
            'member_id' => $surveyor->id,
            'assignment_role' => 'surveyor',
            'status' => 'active',
        ]);

        $visit = SurveyVisit::create([
            'project_id' => $project->id,
            'survey_plan_id' => $surveyPlan->id,
            'task_id' => $unifiedTask->id,
            'execution_task_id' => $execTask->id,
            'member_id' => $surveyor->id,
            'checked_in_by_member_id' => $surveyor->id,
            'check_in_at' => now()->subHour(),
            'check_out_at' => now()->subMinutes(10),
            'submitted_at' => now()->subMinutes(10),
            'status' => 2,
        ]);

        // Separate unrelated in_progress task without CAD job
        $unrelatedTask = Task::create([
            'uuid' => (string) Str::uuid(),
            'project_id' => $project->id,
            'execution_plan_id' => $plan->id,
            'task_code' => 'TSK-00016',
            'title' => 'survey site plan',
            'status' => 'in_progress',
            'progress_percent' => 50,
            'created_by' => $superAdmin->id,
            'member_id' => $surveyor->id,
        ]);

        return compact('company', 'superAdmin', 'admin', 'surveyor', 'draftsman', 'project', 'unifiedTask', 'execTask', 'unrelatedTask', 'visit');
    }

    public function test_admin_approving_cad_via_review_drafting_job_marks_in_progress_paired_tasks_as_completed(): void
    {
        $f = $this->createWorkflowFixture(Str::lower(Str::random(5)));

        // Assigning to draftsman moves both paired Task and ExecutionTask to 'in_progress' and creates DraftingJob
        app(SurveyorToDraftsmanAssignmentService::class)->assign(
            $f['project'],
            'task',
            $f['unifiedTask']->id,
            $f['draftsman']->id,
            null,
            $f['admin']
        );

        $this->assertSame('in_progress', $f['unifiedTask']->fresh()->status);
        $this->assertSame('in_progress', $f['execTask']->fresh()->status);

        $draftingJob = DraftingJob::where('project_id', $f['project']->id)->latest('id')->firstOrFail();

        // Draftsman submits CAD drawing
        $this->actingOnlyAs($f['draftsman'], 'admin')
            ->post(route('super.construction.projects.tasks-v2.submit-draft', $f['project']->id), [
                'drafting_job_id' => $draftingJob->id,
                'drawing_type' => 'topographic',
                'drawing_no' => 'DWG-00017',
                'pdf_file' => UploadedFile::fake()->create('plan.pdf', 120, 'application/pdf'),
            ])
            ->assertRedirect();

        $this->assertSame('drawing_approval_pending', $f['project']->fresh()->current_stage);

        // Assigned / authorized Admin approves CAD drawing via reviewDraftingJob
        $this->actingOnlyAs($f['admin'], 'admin')
            ->post(route('admin.construction.projects.drafting-jobs.review', [$f['project']->id, $draftingJob->id]), [
                'decision' => 'approved',
                'remarks' => 'Approved CAD plan',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('approved', $draftingJob->fresh()->status);
        $this->assertSame('ready_for_construction', $f['project']->fresh()->current_stage);

        // Both mirrored tables MUST now report 'completed' with 100% progress
        $freshUnified = $f['unifiedTask']->fresh();
        $freshExec = $f['execTask']->fresh();

        $this->assertSame('completed', $freshUnified->status);
        $this->assertSame(100, (int) $freshUnified->progress_percent);
        $this->assertNotNull($freshUnified->completed_at);
        $this->assertSame(ClientReviewStatus::APPROVED, $freshUnified->client_review_status);

        $this->assertSame('completed', $freshExec->status);
        $this->assertEquals(100, (float) $freshExec->progress_percent);
        $this->assertNotNull($freshExec->actual_end_date);

        // Unrelated in_progress task on same project stays in_progress
        $this->assertSame('in_progress', $f['unrelatedTask']->fresh()->status);

        // Project summary reflects completion count properly
        $showRes = $this->actingOnlyAs($f['admin'], 'admin')
            ->get(route('admin.construction.projects.show', $f['project']->id))
            ->assertOk();

        $summaryCounts = $showRes->viewData('page')['props']['workflowSummary']['task_counts'];
        $this->assertGreaterThanOrEqual(1, $summaryCounts['completed']);
    }

    public function test_revision_requested_does_not_mark_drafting_tasks_as_completed(): void
    {
        $f = $this->createWorkflowFixture(Str::lower(Str::random(5)));

        app(SurveyorToDraftsmanAssignmentService::class)->assign(
            $f['project'],
            'task',
            $f['unifiedTask']->id,
            $f['draftsman']->id,
            null,
            $f['admin']
        );

        $draftingJob = DraftingJob::where('project_id', $f['project']->id)->latest('id')->firstOrFail();

        $this->actingOnlyAs($f['draftsman'], 'admin')
            ->post(route('super.construction.projects.tasks-v2.submit-draft', $f['project']->id), [
                'drafting_job_id' => $draftingJob->id,
                'drawing_type' => 'topographic',
                'drawing_no' => 'DWG-REV-1',
                'pdf_file' => UploadedFile::fake()->create('rev1.pdf', 120, 'application/pdf'),
            ])
            ->assertRedirect();

        $this->actingOnlyAs($f['admin'], 'admin')
            ->post(route('admin.construction.projects.drafting-jobs.review', [$f['project']->id, $draftingJob->id]), [
                'decision' => 'revision_requested',
                'remarks' => 'Need setback dimensions',
            ])
            ->assertRedirect();

        $this->assertSame('revision_requested', $draftingJob->fresh()->status);
        $this->assertSame('in_progress', $f['unifiedTask']->fresh()->status);
        $this->assertSame('in_progress', $f['execTask']->fresh()->status);
    }

    public function test_admin_and_superadmin_approve_drawing_endpoints_also_mark_linked_tasks_completed(): void
    {
        $f = $this->createWorkflowFixture(Str::lower(Str::random(5)));

        app(SurveyorToDraftsmanAssignmentService::class)->assign(
            $f['project'],
            'task',
            $f['unifiedTask']->id,
            $f['draftsman']->id,
            null,
            $f['admin']
        );

        $draftingJob = DraftingJob::where('project_id', $f['project']->id)->latest('id')->firstOrFail();
        $revision = DrawingRevision::create([
            'project_id' => $f['project']->id,
            'drafting_job_id' => $draftingJob->id,
            'revision_no' => 1,
            'drawing_no' => 'DWG-ADMIN-1',
            'status' => 'submitted',
            'uploaded_by_member_id' => $f['draftsman']->id,
            'uploaded_at' => now(),
        ]);

        $approval = DrawingApproval::create([
            'project_id' => $f['project']->id,
            'drawing_revision_id' => $revision->id,
            'requested_by_type' => Member::class,
            'requested_by_id' => $f['draftsman']->id,
            'requested_at' => now(),
            'decision' => 'pending',
        ]);

        $this->actingOnlyAs($f['admin'], 'admin')
            ->post(route('admin.construction.drafting.approvals.update', $approval->id), [
                'decision' => 'approved',
                'remarks' => 'Approved via Admin Drafting',
            ])
            ->assertRedirect();

        $this->assertSame('approved', $draftingJob->fresh()->status);
        $this->assertSame('ready_for_construction', $f['project']->fresh()->current_stage);
        $this->assertSame('completed', $f['unifiedTask']->fresh()->status);
        $this->assertSame('completed', $f['execTask']->fresh()->status);
    }

    public function test_backfill_migration_completes_tasks_for_previously_approved_cad_jobs(): void
    {
        $f = $this->createWorkflowFixture(Str::lower(Str::random(5)));

        app(SurveyorToDraftsmanAssignmentService::class)->assign(
            $f['project'],
            'task',
            $f['unifiedTask']->id,
            $f['draftsman']->id,
            null,
            $f['admin']
        );

        $draftingJob = DraftingJob::where('project_id', $f['project']->id)->latest('id')->firstOrFail();
        $draftingJob->update(['status' => 'approved']);
        $f['project']->update(['current_stage' => 'ready_for_construction']);

        // Simulate legacy state where tasks stayed in_progress despite CAD job being approved
        $this->assertSame('in_progress', $f['unifiedTask']->fresh()->status);
        $this->assertSame('in_progress', $f['execTask']->fresh()->status);

        $migration = require database_path('migrations/2026_11_27_000003_complete_tasks_for_approved_cad_jobs.php');
        $migration->up();

        $this->assertSame('completed', $f['unifiedTask']->fresh()->status);
        $this->assertSame(100, (int) $f['unifiedTask']->fresh()->progress_percent);
        $this->assertSame('completed', $f['execTask']->fresh()->status);
        $this->assertEquals(100, (float) $f['execTask']->fresh()->progress_percent);
        $this->assertSame('in_progress', $f['unrelatedTask']->fresh()->status);
    }
}
