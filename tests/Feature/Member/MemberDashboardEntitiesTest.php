<?php

namespace Tests\Feature\Member;

use App\Models\Client;
use App\Models\Company;
use App\Models\ConstructionEquipment;
use App\Models\ConstructionRole;
use App\Models\ConstructionVehicle;
use App\Models\DailyProgressReport;
use App\Models\DraftingJob;
use App\Models\Equipment;
use App\Models\EquipmentAllocation;
use App\Models\EquipmentCategory;
use App\Models\Member;
use App\Models\MemberRoleAssignment;
use App\Models\Project;
use App\Models\ProjectTeamMember;
use App\Models\SuperAdmin;
use App\Models\SurveyPlan;
use App\Models\SurveyPlanMember;
use App\Models\SurveySubmission;
use App\Models\SurveyVisit;
use App\Models\VehicleAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MemberDashboardEntitiesTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private SuperAdmin $admin;
    private Project $project;
    private Member $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'CadMax Infrastructure',
            'status' => 'active',
        ]);

        $this->admin = SuperAdmin::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Admin User',
            'email' => 'admin@cadmax.com',
            'password' => 'password',
            'company_id' => $this->company->id,
            'roles' => json_encode(['super_admin']),
        ]);

        $client = Client::create([
            'company_id' => $this->company->id,
            'name' => 'Client One',
            'client_code' => 'CLI-001',
            'status' => 'active',
        ]);

        $this->project = Project::create([
            'company_id' => $this->company->id,
            'client_id' => $client->id,
            'name' => 'Abhiste Project',
            'project_code' => 'PRJ-00001',
            'slug' => 'abhiste-project',
            'status' => 'active',
        ]);

        $this->member = Member::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Ram ji',
            'email' => 'ram@gamil.com',
            'username' => 'ram_ji',
            'slug' => 'ram-ji',
            'password' => 'password',
            'company_name' => 'CadMax Infrastructure',
            'created_by' => $this->admin->id,
            'status' => 1,
            'roles' => ['driver'],
        ]);
    }

    public function test_driver_member_receives_assigned_vehicles_on_dashboard(): void
    {
        $vehicle = ConstructionVehicle::create([
            'project_id' => $this->project->id,
            'vehicle_code' => 'VHC-000001',
            'registration_number' => 'RJ14DE3551',
            'make' => 'TATA',
            'model' => 'Nexon',
            'status' => 'active',
        ]);

        VehicleAssignment::create([
            'project_id' => $this->project->id,
            'vehicle_id' => $vehicle->id,
            'driver_member_id' => $this->member->id,
            'assigned_from' => now()->subDay(),
            'assigned_to' => now()->addDay(),
            'status' => 'active',
            'assigned_by_type' => SuperAdmin::class,
            'assigned_by_id' => $this->admin->id,
        ]);

        // When driver role header is present, vehicles_assigned MUST NOT be empty!
        $response = $this->actingAs($this->member, 'sanctum')
            ->withHeader('X-App-Role', 'driver')
            ->getJson('/api/member/dashboard?role=driver');

        $response->assertOk();
        $response->assertJsonPath('success', true);

        $vehicles = $response->json('vehicles_assigned');
        $this->assertIsArray($vehicles);
        $this->assertNotEmpty($vehicles, 'vehicles_assigned should not be empty for driver member');
        $this->assertEquals('RJ14DE3551', $vehicles[0]['vehicle']['registration_number'] ?? null);
        $this->assertEquals(1, $response->json('summary.vehicles_assigned_count'));
    }

    public function test_member_receives_assigned_equipment_on_dashboard(): void
    {
        $equipment = ConstructionEquipment::create([
            'project_id' => $this->project->id,
            'equipment_code' => 'CEQ-001',
            'name' => 'Excavator Cat 320',
            'equipment_type' => 'Excavator',
            'status' => 'active',
        ]);

        EquipmentAllocation::create([
            'project_id' => $this->project->id,
            'equipment_id' => $equipment->id,
            'assigned_to_member_id' => $this->member->id,
            'assigned_from' => now()->subDay(),
            'assigned_to' => now()->addDay(),
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->member, 'sanctum')
            ->getJson('/api/member/dashboard');

        $response->assertOk();
        $equipmentList = $response->json('equipment_assigned');
        $this->assertIsArray($equipmentList);
        $this->assertNotEmpty($equipmentList, 'equipment_assigned should not be empty');
        $this->assertEquals(1, $response->json('summary.equipment_assigned_count'));
    }

    public function test_member_receives_assigned_drafting_jobs_on_dashboard(): void
    {
        $draftRole = ConstructionRole::create([
            'company_id' => $this->company->id,
            'name' => 'Draft Person',
            'slug' => 'draft_person',
            'status' => 'active',
        ]);

        ProjectTeamMember::create([
            'project_id' => $this->project->id,
            'member_id' => $this->member->id,
            'role_id' => $draftRole->id,
            'assignment_scope' => 'Drawing Revisions & CAD Drafting',
            'status' => 'active',
        ]);

        $plan = SurveyPlan::create([
            'project_id' => $this->project->id,
            'survey_code' => 'SP-001',
            'title' => 'Survey for Drafting',
            'status' => SurveyPlan::STATUS_APPROVED,
        ]);

        $visit = SurveyVisit::create([
            'project_id' => $this->project->id,
            'survey_plan_id' => $plan->id,
            'checked_in_by_member_id' => $this->member->id,
            'member_id' => $this->member->id,
            'check_in_at' => now(),
            'status' => 1,
        ]);

        $submission = SurveySubmission::create([
            'project_id' => $this->project->id,
            'survey_visit_id' => $visit->id,
            'submitted_by_member_id' => $this->member->id,
            'status' => 4,
        ]);

        DraftingJob::create([
            'project_id' => $this->project->id,
            'survey_submission_id' => $submission->id,
            'assigned_to_member_id' => $this->member->id,
            'khasra_number' => 'KH-452',
            'site_area' => '1200 sqft',
            'status' => 'in_progress',
        ]);

        $response = $this->actingAs($this->member, 'sanctum')
            ->getJson('/api/member/dashboard');

        $response->assertOk();
        $draftingJobs = $response->json('drafting_jobs');
        $this->assertIsArray($draftingJobs);
        $this->assertNotEmpty($draftingJobs, 'drafting_jobs should not be empty');
        $this->assertEquals('KH-452', $draftingJobs[0]['khasra_number'] ?? null);
    }

    public function test_member_receives_recent_dprs_on_dashboard(): void
    {
        DailyProgressReport::create([
            'project_id' => $this->project->id,
            'submitted_by_member_id' => $this->member->id,
            'report_date' => now()->toDateString(),
            'summary' => 'Earth excavation completed for block A',
            'status' => 'submitted',
        ]);

        $response = $this->actingAs($this->member, 'sanctum')
            ->getJson('/api/member/dashboard');

        $response->assertOk();
        $dprs = $response->json('recent_dprs');
        $this->assertIsArray($dprs);
        $this->assertNotEmpty($dprs, 'recent_dprs should not be empty');
        $this->assertEquals('Earth excavation completed for block A', $dprs[0]['summary'] ?? null);
    }

    public function test_member_receives_survey_plans_and_today_visits_on_dashboard(): void
    {
        $plan = SurveyPlan::create([
            'project_id' => $this->project->id,
            'survey_code' => 'SP-001',
            'title' => 'Topographic Survey Block 1',
            'status' => SurveyPlan::STATUS_IN_PROGRESS,
        ]);

        SurveyPlanMember::create([
            'survey_plan_id' => $plan->id,
            'member_id' => $this->member->id,
            'role_in_survey' => 'Chief Surveyor',
            'status' => 'active',
        ]);

        SurveyVisit::create([
            'project_id' => $this->project->id,
            'survey_plan_id' => $plan->id,
            'checked_in_by_member_id' => $this->member->id,
            'member_id' => $this->member->id,
            'check_in_at' => now()->setTimezone('Asia/Kolkata')->toDateTimeString(),
            'status' => 1,
        ]);

        $response = $this->actingAs($this->member, 'sanctum')
            ->getJson('/api/member/dashboard');

        $response->assertOk();
        $surveyPlans = $response->json('survey_plans');
        $this->assertIsArray($surveyPlans);
        $this->assertNotEmpty($surveyPlans, 'survey_plans should not be empty');
        $this->assertEquals('SP-001', $surveyPlans[0]['survey_code'] ?? null);

        $todayVisits = $response->json('today_survey_visits');
        $this->assertIsArray($todayVisits);
        $this->assertNotEmpty($todayVisits, 'today_survey_visits should not be empty');
    }

    public function test_surveyor_team_member_sees_project_survey_plans_without_direct_plan_member_entry(): void
    {
        $surveyorRole = ConstructionRole::create([
            'company_id' => $this->company->id,
            'name' => 'Surveyor',
            'slug' => 'surveyor',
            'status' => 'active',
        ]);

        ProjectTeamMember::create([
            'project_id' => $this->project->id,
            'member_id' => $this->member->id,
            'role_id' => $surveyorRole->id,
            'assignment_scope' => 'Field Survey & Site Data Collection',
            'status' => 'active',
        ]);

        // Survey plan created for the project without direct plan_members row
        SurveyPlan::create([
            'project_id' => $this->project->id,
            'survey_code' => 'SP-PROJECT-01',
            'title' => 'Project Wide Survey Plan',
            'status' => SurveyPlan::STATUS_IN_PROGRESS,
        ]);

        $response = $this->actingAs($this->member, 'sanctum')
            ->getJson('/api/member/dashboard');

        $response->assertOk();
        $plans = $response->json('survey_plans');
        $this->assertIsArray($plans);
        $this->assertNotEmpty($plans, 'Surveyor team member should see survey plans of assigned project');
        $this->assertEquals('SP-PROJECT-01', $plans[0]['survey_code'] ?? null);
    }

    public function test_drafter_team_member_sees_project_drafting_jobs_without_direct_assignment_entry(): void
    {
        $draftRole = ConstructionRole::create([
            'company_id' => $this->company->id,
            'name' => 'Draft Person',
            'slug' => 'draft_person',
            'status' => 'active',
        ]);

        ProjectTeamMember::create([
            'project_id' => $this->project->id,
            'member_id' => $this->member->id,
            'role_id' => $draftRole->id,
            'assignment_scope' => 'Drawing Revisions & CAD Drafting',
            'status' => 'active',
        ]);

        $plan = SurveyPlan::create([
            'project_id' => $this->project->id,
            'survey_code' => 'SP-002',
            'title' => 'Survey Plan Two',
            'status' => SurveyPlan::STATUS_APPROVED,
        ]);

        $visit = SurveyVisit::create([
            'project_id' => $this->project->id,
            'survey_plan_id' => $plan->id,
            'checked_in_by_member_id' => $this->member->id,
            'member_id' => $this->member->id,
            'check_in_at' => now(),
            'status' => 1,
        ]);

        $submission = SurveySubmission::create([
            'project_id' => $this->project->id,
            'survey_visit_id' => $visit->id,
            'submitted_by_member_id' => $this->member->id,
            'status' => 4,
        ]);

        // Drafting job created on project without assigned_to_member_id
        DraftingJob::create([
            'project_id' => $this->project->id,
            'survey_submission_id' => $submission->id,
            'assigned_to_member_id' => null,
            'khasra_number' => 'KH-PROJECT-DRAfter',
            'site_area' => '2500 sqft',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->member, 'sanctum')
            ->getJson('/api/member/dashboard');

        $response->assertOk();
        $draftingJobs = $response->json('drafting_jobs');
        $this->assertIsArray($draftingJobs);
        $this->assertNotEmpty($draftingJobs, 'Drafter team member should see drafting jobs of assigned project');
        $this->assertEquals('KH-PROJECT-DRAfter', $draftingJobs[0]['khasra_number'] ?? null);
    }
}
