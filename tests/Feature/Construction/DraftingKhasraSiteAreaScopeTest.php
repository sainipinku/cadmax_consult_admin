<?php

namespace Tests\Feature\Construction;

use App\Models\Client;
use App\Models\Company;
use App\Models\ConstructionRole;
use App\Models\DraftingJob;
use App\Models\Member;
use App\Models\MemberRoleAssignment;
use App\Models\Permission;
use App\Models\Project;
use App\Models\SuperAdmin;
use App\Models\SurveyPlan;
use App\Models\SurveySubmission;
use App\Models\SurveyVisit;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DraftingKhasraSiteAreaScopeTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private SuperAdmin $superAdmin;
    private Client $client;
    private Member $draftPerson;
    private Project $project;
    private Task $task;
    private SurveySubmission $surveySubmission;
    private DraftingJob $draftingJob;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'BETAXTECH Infra',
            'status' => 'active',
        ]);

        $this->superAdmin = SuperAdmin::create([
            'id' => 1,
            'name' => 'Super Admin',
            'email' => 'superadmin@betaxtech.com',
            'username' => 'superadmin',
            'password' => Hash::make('password'),
            'company_id' => $this->company->id,
            'status' => 1,
        ]);

        $this->client = Client::create([
            'company_id' => $this->company->id,
            'name' => 'Govt Client',
            'client_code' => 'CLI-001',
            'status' => 'active',
        ]);

        $this->draftPerson = Member::create([
            'name' => 'Ravi Kumar Admin',
            'email' => 'ravikumar@gmail.com',
            'username' => 'ravikumar',
            'password' => Hash::make('password'),
            'created_by' => $this->superAdmin->id,
            'company_id' => $this->company->id,
            'slug' => 'draft-person',
            'status' => 1,
            'roles' => [],
        ]);

        $role = ConstructionRole::firstOrCreate(
            ['slug' => 'draft_person'],
            ['name' => 'Draft Person', 'status' => 'active']
        );

        $perm = Permission::firstOrCreate(
            ['slug' => 'drafting.manage'],
            ['name' => 'Manage Drafting', 'module' => 'drafting']
        );
        $role->permissions()->syncWithoutDetaching([$perm->id]);

        MemberRoleAssignment::create([
            'member_id' => $this->draftPerson->id,
            'role_id' => $role->id,
            'status' => 1,
        ]);

        $this->project = Project::create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'name' => 'Water Canal Project',
            'slug' => 'water-canal-project',
            'project_code' => 'PRJ-00013',
            'status' => 'active',
            'current_stage' => 'drafting_in_progress',
        ]);

        $this->task = Task::create([
            'project_id' => $this->project->id,
            'member_id' => $this->draftPerson->id,
            'task_code' => 'TSK-00013',
            'title' => 'deep survey process in water',
            'status' => 'in_progress',
        ]);

        $surveyPlan = SurveyPlan::create([
            'project_id' => $this->project->id,
            'survey_code' => 'SP-00013',
            'title' => 'deep survey process in water plan',
            'status' => 1,
        ]);

        $surveyVisit = SurveyVisit::create([
            'project_id' => $this->project->id,
            'survey_plan_id' => $surveyPlan->id,
            'task_id' => $this->task->id,
            'member_id' => $this->draftPerson->id,
            'checked_in_by_member_id' => $this->draftPerson->id,
            'status' => 1,
        ]);

        $this->surveySubmission = SurveySubmission::create([
            'project_id' => $this->project->id,
            'survey_visit_id' => $surveyVisit->id,
            'submitted_by_member_id' => $this->draftPerson->id,
            'status' => SurveySubmission::STATUS_APPROVED,
        ]);

        $this->draftingJob = DraftingJob::create([
            'project_id' => $this->project->id,
            'survey_submission_id' => $this->surveySubmission->id,
            'assigned_to_member_id' => $this->draftPerson->id,
            'status' => 'in_progress',
        ]);
    }

    public function test_submitting_draft_drawing_via_tasks_v2_saves_khasra_site_area_and_scope_of_work(): void
    {
        $response = $this->actingAs($this->superAdmin, 'superadmin')
            ->post(route('super.construction.projects.tasks-v2.submit-draft', $this->project->id), [
                'task_id' => $this->task->id,
                'drafting_job_id' => $this->draftingJob->id,
                'drawing_title' => 'TSK-00013 - CAD Plan',
                'drawing_no' => 'DWG-TSK-00013',
                'drawing_type' => 'topographic',
                'revision_no' => 1,
                'khasra_number' => '142/3 & 144/1',
                'site_area' => '5.2 Acres (226,500 sq ft)',
                'scope_of_work' => 'Boundary demarcation, 2D architectural layout, contour mapping',
                'changes_made' => 'Initial drawing revision with contours',
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $this->assertDatabaseHas('construction_drafting_jobs', [
            'id' => $this->draftingJob->id,
            'khasra_number' => '142/3 & 144/1',
            'site_area' => '5.2 Acres (226,500 sq ft)',
            'scope_of_work' => 'Boundary demarcation, 2D architectural layout, contour mapping',
        ]);

        $this->assertDatabaseHas('construction_projects', [
            'id' => $this->project->id,
            'khasra_number' => '142/3 & 144/1',
            'site_area' => '5.2 Acres (226,500 sq ft)',
            'scope_of_work' => 'Boundary demarcation, 2D architectural layout, contour mapping',
        ]);
    }

    public function test_submitting_draft_drawing_via_workflow_controller_saves_khasra_site_area_and_scope_of_work(): void
    {
        $response = $this->actingAs($this->superAdmin, 'superadmin')
            ->post(route('super.construction.projects.workflow.submit-draft', $this->project->id), [
                'drawing_type' => 'topographic',
                'drawing_no' => 'DWG-002',
                'khasra_number' => 'Plot 55 / Sector 9',
                'site_area' => '15,000 sq ft',
                'scope_of_work' => 'Full site CAD draft and boundary verification',
                'changes_made' => 'Updated contours',
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $this->assertDatabaseHas('construction_drafting_jobs', [
            'id' => $this->draftingJob->id,
            'khasra_number' => 'Plot 55 / Sector 9',
            'site_area' => '15,000 sq ft',
            'scope_of_work' => 'Full site CAD draft and boundary verification',
        ]);

        $this->assertDatabaseHas('construction_projects', [
            'id' => $this->project->id,
            'khasra_number' => 'Plot 55 / Sector 9',
            'site_area' => '15,000 sq ft',
            'scope_of_work' => 'Full site CAD draft and boundary verification',
        ]);
    }

    public function test_submitting_draft_revision_via_api_saves_khasra_site_area_and_scope_of_work(): void
    {
        Sanctum::actingAs($this->draftPerson, ['*']);

        $response = $this->postJson("/api/construction/mobile/construction/drafting-jobs/{$this->draftingJob->id}/revisions", [
            'khasra_number' => 'Khasra 99/A',
            'site_area' => '3 Acres',
            'scope_of_work' => 'Mobile drafting submission scope',
            'notes' => 'Submitted from field unit',
            'dwg_file_name' => 'cad_plan_rev1.dwg',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('construction_drafting_jobs', [
            'id' => $this->draftingJob->id,
            'khasra_number' => 'Khasra 99/A',
            'site_area' => '3 Acres',
            'scope_of_work' => 'Mobile drafting submission scope',
        ]);

        $this->assertDatabaseHas('construction_projects', [
            'id' => $this->project->id,
            'khasra_number' => 'Khasra 99/A',
            'site_area' => '3 Acres',
            'scope_of_work' => 'Mobile drafting submission scope',
        ]);
    }
}
