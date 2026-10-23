<?php

namespace Tests\Feature\Construction;

use App\Models\Client;
use App\Models\Company;
use App\Models\DraftingJob;
use App\Models\Member;
use App\Models\Project;
use App\Models\SuperAdmin;
use App\Models\SurveyPlan;
use App\Models\SurveySubmission;
use App\Models\SurveyVisit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DraftingJobDetailsTest extends TestCase
{
    use RefreshDatabase;

    private function createCompany(): Company
    {
        return Company::create([
            'name' => 'Drafting Test Co',
            'status' => 'active',
        ]);
    }

    private function createSuperAdmin(Company $company): SuperAdmin
    {
        return SuperAdmin::create([
            'name' => 'Draft SuperAdmin',
            'email' => 'draftadmin@example.com',
            'username' => 'draftadmin',
            'password' => Hash::make('password'),
            'company_id' => $company->id,
            'status' => 1,
            'is_project_owner' => 0,
        ]);
    }

    private function createProject(Company $company): Project
    {
        $client = Client::create([
            'company_id' => $company->id,
            'name' => 'Client Draft Test',
            'client_code' => 'CLI-DRF-01',
            'status' => 'active',
        ]);

        return Project::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'name' => 'Drafting Layout Project',
            'slug' => 'drafting-layout-project',
            'project_code' => 'PRJ-DRF-001',
            'status' => 'active',
        ]);
    }

    private function createMember(SuperAdmin $admin, string $name): Member
    {
        static $counter = 100;
        $counter++;

        return Member::create([
            'name' => $name,
            'username' => "drafter{$counter}",
            'email' => "drafter{$counter}@example.com",
            'password' => Hash::make('password'),
            'created_by' => $admin->id,
            'slug' => "drafter-{$counter}",
            'status' => 1,
        ]);
    }

    private function createSurveySubmission(Project $project, Member $surveyor, int $status = 2): SurveySubmission
    {
        static $codeCounter = 1;
        $codeCounter++;

        $plan = SurveyPlan::create([
            'project_id' => $project->id,
            'survey_code' => "SP-00{$codeCounter}",
            'title' => 'Topographical Survey Plan',
            'status' => 1,
        ]);

        $visit = SurveyVisit::create([
            'project_id' => $project->id,
            'survey_plan_id' => $plan->id,
            'member_id' => $surveyor->id,
            'checked_in_by_member_id' => $surveyor->id,
            'status' => 1,
        ]);

        return SurveySubmission::create([
            'project_id' => $project->id,
            'survey_visit_id' => $visit->id,
            'submitted_by_member_id' => $surveyor->id,
            'status' => $status,
            'submitted_at' => now(),
        ]);
    }

    public function test_can_create_and_update_drafting_job_with_khasra_site_area_and_scope(): void
    {
        $company = $this->createCompany();
        $admin = $this->createSuperAdmin($company);
        $project = $this->createProject($company);
        $drafter = $this->createMember($admin, 'John Drafter');
        $surveyor = $this->createMember($admin, 'Bob Surveyor');

        $submission = $this->createSurveySubmission($project, $surveyor, 2);

        // 1. Create Drafting Job with Khasra, Site Area, and Scope of Work
        $res = $this->actingAs($admin, 'superadmin')->post(route('super.construction.drafting.jobs.store'), [
            'survey_submission_id' => $submission->id,
            'assigned_to_member_id' => $drafter->id,
            'due_date' => now()->addDays(7)->format('Y-m-d'),
            'khasra_number' => '142/3, 142/4',
            'site_area' => '2500 sq ft',
            'scope_of_work' => 'Prepare 2D Architectural Floor Plan and boundary elevation drawings',
        ]);

        $res->assertSessionHasNoErrors();
        $res->assertRedirect();

        $job = DraftingJob::where('survey_submission_id', $submission->id)->first();
        $this->assertNotNull($job);
        $this->assertEquals($drafter->id, $job->assigned_to_member_id);
        $this->assertEquals('142/3, 142/4', $job->khasra_number);
        $this->assertEquals('2500 sq ft', $job->site_area);
        $this->assertEquals('Prepare 2D Architectural Floor Plan and boundary elevation drawings', $job->scope_of_work);

        // Project should also be updated with these fields
        $project->refresh();
        $this->assertEquals('142/3, 142/4', $project->khasra_number);
        $this->assertEquals('2500 sq ft', $project->site_area);
        $this->assertEquals('Prepare 2D Architectural Floor Plan and boundary elevation drawings', $project->scope_of_work);

        // 2. Update Drafting Job
        $resUpdate = $this->actingAs($admin, 'superadmin')->put(route('super.construction.drafting.jobs.update', $job->id), [
            'assigned_to_member_id' => $drafter->id,
            'due_date' => now()->addDays(10)->format('Y-m-d'),
            'khasra_number' => '142/3 (Revised)',
            'site_area' => '3200 sq ft',
            'scope_of_work' => 'Updated scope: include structural foundation and electrical layout',
        ]);

        $resUpdate->assertSessionHasNoErrors();
        $resUpdate->assertRedirect();

        $job->refresh();
        $this->assertEquals('142/3 (Revised)', $job->khasra_number);
        $this->assertEquals('3200 sq ft', $job->site_area);
        $this->assertEquals('Updated scope: include structural foundation and electrical layout', $job->scope_of_work);

        $project->refresh();
        $this->assertEquals('142/3 (Revised)', $project->khasra_number);
        $this->assertEquals('3200 sq ft', $project->site_area);
        $this->assertEquals('Updated scope: include structural foundation and electrical layout', $project->scope_of_work);
    }

    public function test_survey_approval_creates_drafting_job_with_khasra_site_area_and_scope(): void
    {
        $company = $this->createCompany();
        $admin = $this->createSuperAdmin($company);
        $project = $this->createProject($company);
        $drafter = $this->createMember($admin, 'Alice Drafter');
        $surveyor = $this->createMember($admin, 'Bob Surveyor');

        $submission = $this->createSurveySubmission($project, $surveyor, SurveySubmission::STATUS_SUBMITTED);

        // Review and approve survey submission, assigning draftsperson and filling Khasra, Area, Scope
        $res = $this->actingAs($admin, 'superadmin')->post(route('super.construction.survey.submissions.review', $submission->id), [
            'status' => SurveySubmission::STATUS_APPROVED,
            'review_notes' => 'Survey measurements verified against site GPS.',
            'assigned_to_member_id' => $drafter->id,
            'due_date' => now()->addDays(5)->format('Y-m-d'),
            'khasra_number' => 'Khasra-889/2',
            'site_area' => '1.5 Acres',
            'scope_of_work' => 'Draft comprehensive site topography and boundary layout.',
        ]);

        $res->assertSessionHasNoErrors();
        $res->assertRedirect();

        $submission->refresh();
        $this->assertEquals(SurveySubmission::STATUS_APPROVED, $submission->status);

        $job = DraftingJob::where('survey_submission_id', $submission->id)->first();
        $this->assertNotNull($job);
        $this->assertEquals($drafter->id, $job->assigned_to_member_id);
        $this->assertEquals('Khasra-889/2', $job->khasra_number);
        $this->assertEquals('1.5 Acres', $job->site_area);
        $this->assertEquals('Draft comprehensive site topography and boundary layout.', $job->scope_of_work);

        $project->refresh();
        $this->assertEquals('Khasra-889/2', $project->khasra_number);
        $this->assertEquals('1.5 Acres', $project->site_area);
        $this->assertEquals('Draft comprehensive site topography and boundary layout.', $project->scope_of_work);
    }
}
