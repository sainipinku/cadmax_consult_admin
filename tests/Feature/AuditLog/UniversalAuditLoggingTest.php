<?php

namespace Tests\Feature\AuditLog;

use App\Models\Client;
use App\Models\Company;
use App\Models\ConstructionActivityLog;
use App\Models\ConstructionRole;
use App\Models\Employee;
use App\Models\Member;
use App\Models\MemberRoleAssignment;
use App\Models\Project;
use App\Models\ProjectTeamMember;
use App\Models\SuperAdmin;
use App\Models\Task;
use App\Services\Construction\ConstructionActivityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UniversalAuditLoggingTest extends TestCase
{
    use RefreshDatabase;

    private function createCompany(string $name = 'Alpha Corp'): Company
    {
        return Company::create([
            'name' => $name,
            'status' => 'active',
        ]);
    }

    private function createProjectOwner(): SuperAdmin
    {
        $owner = new SuperAdmin([
            'name' => 'Project Owner Root',
            'email' => 'owner@cadmax.com',
            'username' => 'projectowner',
            'password' => Hash::make('password'),
            'company_id' => null,
            'status' => 1,
            'roles' => ['crm_super_admin', 'project_owner'],
        ]);
        $owner->id = 1;
        $owner->save();

        return $owner;
    }

    private function createCompanySuperAdmin(Company $company, string $name = 'Company SuperAdmin'): SuperAdmin
    {
        static $seq = 300;
        $seq++;
        return SuperAdmin::create([
            'name' => $name,
            'email' => "comp_admin_{$seq}@example.com",
            'username' => "compadmin_{$seq}",
            'password' => Hash::make('password'),
            'company_id' => $company->id,
            'status' => 1,
            'roles' => ['superadmin'],
        ]);
    }

    private function createProject(Company $company, string $code = 'PRJ-AUD-01'): Project
    {
        $client = Client::create([
            'name' => 'Test Client',
            'company_id' => $company->id,
            'status' => 'active',
        ]);

        return Project::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'project_code' => $code,
            'name' => 'Audit Test Project',
            'status' => 'active',
        ]);
    }

    private function createMemberWithEmployee(Company $company, SuperAdmin $admin, string $name = 'Pinku Saini'): Member
    {
        static $mSeq = 400;
        $mSeq++;
        $member = Member::create([
            'name' => $name,
            'slug' => "member-{$mSeq}",
            'username' => "member_{$mSeq}",
            'email' => "member_{$mSeq}@cadmax.com",
            'password' => Hash::make('password'),
            'status' => 1,
            'created_by' => $admin->id,
            'company_name' => $company->name,
        ]);

        Employee::create([
            'member_id' => $member->id,
            'employee_id' => "EMP-{$mSeq}",
        ]);

        return $member;
    }

    public function test_activity_service_records_action_with_scrubbing_and_channel(): void
    {
        $company = $this->createCompany('PCL Corp');
        $superAdmin = $this->createCompanySuperAdmin($company);
        $project = $this->createProject($company);

        $service = app(ConstructionActivityService::class);

        $request = Request::create('/api/member/tasks', 'POST', [
            'password' => 'supersecret123',
            'token' => 'plain-token-abc',
            'task_title' => 'Survey Boundary Check',
        ], [], [], [
            'REMOTE_ADDR' => '192.168.1.50',
            'HTTP_USER_AGENT' => 'CadMaxMobileApp/2.0',
        ]);

        $service->log(
            module: 'execution_task',
            action: 'created',
            actor: $superAdmin,
            reference: $project,
            companyId: $company->id,
            projectId: $project->id,
            description: 'Created new execution task for survey boundary',
            referenceCode: $project->project_code,
            channel: 'api_mobile',
            meta: [
                'password' => 'supersecret123',
                'api_token' => 'plain-token-abc',
                'task_title' => 'Survey Boundary Check',
            ],
            request: $request
        );

        $log = ConstructionActivityLog::latest('id')->first();
        $this->assertNotNull($log);
        $this->assertEquals('execution_task', $log->module);
        $this->assertEquals('created', $log->action);
        $this->assertEquals('api_mobile', $log->channel);
        $this->assertEquals($project->project_code, $log->reference_code);
        $this->assertEquals('192.168.1.50', $log->ip_address);
        $this->assertEquals('CadMaxMobileApp/2.0', $log->user_agent);

        // Assert sensitive parameters are sanitized
        $this->assertEquals('[REDACTED]', $log->meta['password']);
        $this->assertEquals('[REDACTED]', $log->meta['api_token']);
        $this->assertEquals('Survey Boundary Check', $log->meta['task_title']);
    }

    public function test_activity_service_auto_resolves_member_employee_and_company(): void
    {
        $company = $this->createCompany('L&T Infra');
        $superAdmin = $this->createCompanySuperAdmin($company);
        $member = $this->createMemberWithEmployee($company, $superAdmin, 'Ramesh Surveyor');
        $project = $this->createProject($company, 'PRJ-LNT-01');

        $service = app(ConstructionActivityService::class);

        // When logging without explicitly passing companyId or employee_id, service auto-resolves from Member
        $service->log(
            module: 'survey',
            action: 'check_in',
            actor: $member,
            reference: $project,
            projectId: $project->id,
            description: 'Surveyor checked in on site',
            referenceCode: $project->project_code
        );

        $log = ConstructionActivityLog::latest('id')->first();
        $this->assertNotNull($log);
        $this->assertEquals($company->id, $log->company_id);
        $this->assertEquals($member->employee->id, $log->employee_id);
        $this->assertEquals(Member::class, $log->actor_type);
        $this->assertEquals($member->id, $log->actor_id);
    }

    public function test_project_owner_can_see_all_activity_logs_across_companies(): void
    {
        $companyA = $this->createCompany('Alpha Builders');
        $companyB = $this->createCompany('Beta Infra');

        $adminA = $this->createCompanySuperAdmin($companyA, 'Admin Alpha');
        $adminB = $this->createCompanySuperAdmin($companyB, 'Admin Beta');

        $projectA = $this->createProject($companyA, 'PRJ-ALP-01');
        $projectB = $this->createProject($companyB, 'PRJ-BET-01');

        $owner = $this->createProjectOwner();
        $service = app(ConstructionActivityService::class);

        // Log action in Company A
        $service->log('project', 'created', $adminA, $projectA, $companyA->id, $projectA->id, [], null, 'Created Project Alpha');
        // Log action in Company B
        $service->log('project', 'created', $adminB, $projectB, $companyB->id, $projectB->id, [], null, 'Created Project Beta');

        // Project Owner query returns both logs
        $ownerLogs = ConstructionActivityLog::forActor($owner)->get();
        $this->assertCount(2, $ownerLogs);
    }

    public function test_company_super_admin_only_sees_their_company_and_project_logs(): void
    {
        $companyA = $this->createCompany('Alpha Builders');
        $companyB = $this->createCompany('Beta Infra');

        $adminA = $this->createCompanySuperAdmin($companyA, 'Admin Alpha');
        $adminB = $this->createCompanySuperAdmin($companyB, 'Admin Beta');

        $projectA = $this->createProject($companyA, 'PRJ-ALP-02');
        $projectB = $this->createProject($companyB, 'PRJ-BET-02');

        $service = app(ConstructionActivityService::class);

        $service->log('project', 'created', $adminA, $projectA, $companyA->id, $projectA->id, [], null, 'Alpha Log');
        $service->log('project', 'created', $adminB, $projectB, $companyB->id, $projectB->id, [], null, 'Beta Log');

        // Admin A only sees Alpha Log
        $adminALogs = ConstructionActivityLog::forActor($adminA)->get();
        $this->assertCount(1, $adminALogs);
        $this->assertEquals($companyA->id, $adminALogs->first()->company_id);

        // Admin B only sees Beta Log
        $adminBLogs = ConstructionActivityLog::forActor($adminB)->get();
        $this->assertCount(1, $adminBLogs);
        $this->assertEquals($companyB->id, $adminBLogs->first()->company_id);
    }

    public function test_audit_logs_portal_endpoint_is_accessible_and_filterable(): void
    {
        $company = $this->createCompany('PCL Portal Corp');
        $superAdmin = $this->createCompanySuperAdmin($company);
        $project = $this->createProject($company, 'PRJ-PRT-01');

        $service = app(ConstructionActivityService::class);
        $service->log('execution_task', 'status_changed', $superAdmin, $project, $company->id, $project->id, [], null, 'Task in progress');

        $res = $this->actingAs($superAdmin, 'superadmin')
            ->get(route('super.audit_logs.index'));

        $res->assertOk();
        $res->assertInertia(fn (Assert $page) => $page
            ->component('SuperAdmin/AuditLogs/Index')
            ->has('logs.data')
            ->has('filters')
        );
    }

    public function test_member_checkin_via_api_is_logged_with_gps_and_channel(): void
    {
        $company = $this->createCompany('Apex Infrastructure');
        $superAdmin = $this->createCompanySuperAdmin($company);
        $project = $this->createProject($company, 'PRJ-APX-01');
        $member = $this->createMemberWithEmployee($company, $superAdmin, 'Deepak Saini');

        // Assign member to project
        ProjectTeamMember::create([
            'project_id' => $project->id,
            'member_id' => $member->id,
            'status' => 'active',
            'role' => 'surveyor',
        ]);

        $response = $this->actingAs($member, 'sanctum')
            ->postJson('/api/attendance/check-in', [
                'latitude' => 26.9124,
                'longitude' => 75.7873,
                'accuracy' => 12.5,
                'notes' => 'Arrived at site gate',
                'project_id' => $project->id,
            ], [
                'User-Agent' => 'CadMaxMobileApp/2.1 (Android)',
            ]);

        $response->assertOk();

        $log = ConstructionActivityLog::where('module', 'attendance')
            ->where('action', 'check_in')
            ->where('actor_id', $member->id)
            ->first();

        $this->assertNotNull($log, 'Attendance check_in must be recorded in activity logs');
        $this->assertEquals($company->id, $log->company_id);
        $this->assertEquals($project->id, $log->project_id);
        $this->assertEquals('api_mobile', $log->channel);
        $this->assertStringContainsString('Deepak Saini', $log->description);
        $this->assertEquals(26.9124, $log->meta['latitude']);
    }

    public function test_member_task_status_update_via_api_is_logged(): void
    {
        $company = $this->createCompany('BuildFast Ltd');
        $superAdmin = $this->createCompanySuperAdmin($company);
        $project = $this->createProject($company, 'PRJ-BLD-01');
        $member = $this->createMemberWithEmployee($company, $superAdmin, 'Rahul Verma');

        $task = Task::create([
            'project_id' => $project->id,
            'title' => 'Initial Site Surveying',
            'status' => 'planned',
            'assigned_to' => $member->id,
            'created_by' => $superAdmin->id,
        ]);

        $response = $this->actingAs($member, 'sanctum')
            ->postJson("/api/member/tasks/{$task->id}/status", [
                'status' => 'in_progress',
            ]);

        $response->assertOk();

        $log = ConstructionActivityLog::where('module', 'execution_task')
            ->where('action', 'status_changed')
            ->where('actor_id', $member->id)
            ->first();

        $this->assertNotNull($log, 'Task status change must be recorded in activity logs');
        $this->assertEquals($project->id, $log->project_id);
        $this->assertEquals('in_progress', $log->meta['status']);
    }

    public function test_driver_trip_submission_via_api_is_logged(): void
    {
        $company = $this->createCompany('Roadways Corp');
        $superAdmin = $this->createCompanySuperAdmin($company);
        $project = $this->createProject($company, 'PRJ-RD-01');
        $driver = $this->createMemberWithEmployee($company, $superAdmin, 'Driver Ramesh');

        $plan = \App\Models\ExecutionPlan::create([
            'project_id' => $project->id,
            'plan_code' => 'PLN-01',
            'title' => 'Master Plan',
            'status' => 'approved',
        ]);

        $execTask = \App\Models\ExecutionTask::create([
            'project_id' => $project->id,
            'execution_plan_id' => $plan->id,
            'task_code' => 'TSK-LOG-01',
            'title' => 'Logistics & Hauling Task',
            'status' => 'in_progress',
        ]);

        $response = $this->actingAs($driver, 'sanctum')
            ->postJson('/api/member/driver-duty/submit-data', [
                'task_id' => $execTask->id,
                'project_id' => $project->id,
                'vehicle_number' => 'RJ14-AB-1234',
                'start_location' => 'Jaipur Hub',
                'destination' => 'Site A',
                'start_km' => 100,
                'end_km' => 150,
                'total_distance' => 50,
                'trip_status' => 'completed',
                'notes' => 'Materials delivered safely',
            ], [
                'User-Agent' => 'CadMaxMobileApp/2.1',
            ]);

        $response->assertOk();

        $log = ConstructionActivityLog::where('module', 'fleet')
            ->where('action', 'driver_trip_submitted')
            ->where('actor_id', $driver->id)
            ->first();

        $this->assertNotNull($log, 'Driver trip submission must be logged in activity logs');
        $this->assertEquals('api_mobile', $log->channel);
        $this->assertEquals('RJ14-AB-1234', $log->meta['vehicle_number']);
        $this->assertStringContainsString('Driver Ramesh', $log->description);
    }

    public function test_survey_duty_checkin_and_checkout_are_logged(): void
    {
        $company = $this->createCompany('Surveyors India');
        $superAdmin = $this->createCompanySuperAdmin($company);
        $project = $this->createProject($company, 'PRJ-SRV-01');
        $surveyor = $this->createMemberWithEmployee($company, $superAdmin, 'Sunil Surveyor');

        // Check in
        $checkInRes = $this->actingAs($surveyor, 'sanctum')
            ->postJson('/api/member/survey-duty/check-in', [
                'project_id' => $project->id,
                'latitude' => 26.9000,
                'longitude' => 75.8000,
            ]);

        $checkInRes->assertOk();

        $checkInLog = ConstructionActivityLog::where('module', 'survey')
            ->where('action', 'duty_check_in')
            ->where('actor_id', $surveyor->id)
            ->first();

        $this->assertNotNull($checkInLog, 'Survey duty check-in must be logged');
        $this->assertEquals($project->id, $checkInLog->project_id);

        $visitId = $checkInRes->json('visit.id');
        $visit = \App\Models\SurveyVisit::find($visitId);
        $this->assertNotNull($visit);

        // Ensure check_in_at is in the past for duration calculation
        $visit->update(['check_in_at' => now()->subHours(2)]);

        // Check out
        $checkOutRes = $this->actingAs($surveyor, 'sanctum')
            ->postJson("/api/member/survey-duty/{$visit->id}/check-out", [
                'latitude' => 26.9050,
                'longitude' => 75.8050,
                'notes' => 'Completed perimeter survey',
            ]);

        $checkOutRes->assertOk();

        $checkOutLog = ConstructionActivityLog::where('module', 'survey')
            ->where('action', 'duty_check_out')
            ->where('actor_id', $surveyor->id)
            ->first();

        $this->assertNotNull($checkOutLog, 'Survey duty check-out must be logged');
        $this->assertEquals($project->id, $checkOutLog->project_id);
    }

    public function test_admin_account_creation_and_status_toggle_are_logged(): void
    {
        $company = $this->createCompany('Metro Infra');
        $superAdmin = $this->createCompanySuperAdmin($company);

        // Store admin
        $response = $this->actingAs($superAdmin, 'superadmin')
            ->post(route('super.super_admins.store_admin'), [
                'name' => 'Project Admin Vikash',
                'email' => 'vikash.admin@metroinfra.com',
                'phone' => '9876543210',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'role_slug' => 'project_admin',
            ]);

        $response->assertSessionHasNoErrors();

        $adminLog = ConstructionActivityLog::where('module', 'admin_management')
            ->where('action', 'admin_created')
            ->first();

        $this->assertNotNull($adminLog, 'Admin account creation must be logged');
        $this->assertEquals($company->id, $adminLog->company_id);
        $this->assertStringContainsString('Project Admin Vikash', $adminLog->description);

        $createdAdmin = Member::where('email', 'vikash.admin@metroinfra.com')->first();
        $this->assertNotNull($createdAdmin);

        // Toggle admin status
        $toggleRes = $this->actingAs($superAdmin, 'superadmin')
            ->post(route('super.super_admins.status_admin', $createdAdmin->id));

        $toggleRes->assertSessionHasNoErrors();

        $toggleLog = ConstructionActivityLog::where('module', 'admin_management')
            ->where('action', 'status_toggled')
            ->first();

        $this->assertNotNull($toggleLog, 'Admin status toggle must be logged');
    }

    public function test_project_team_assignment_and_budget_are_logged(): void
    {
        $company = $this->createCompany('Skyline Builders');
        $superAdmin = $this->createCompanySuperAdmin($company);
        $project = $this->createProject($company, 'PRJ-SKY-01');
        $member = $this->createMemberWithEmployee($company, $superAdmin, 'Site Eng Manoj');

        // Store budget
        $budgetRes = $this->actingAs($superAdmin, 'sanctum')
            ->postJson("/api/construction/projects/{$project->id}/budget", [
                'estimated_amount' => 500000,
                'currency' => 'INR',
                'status' => 'approved',
                'notes' => 'Phase 1 preliminary budget',
            ]);

        $budgetRes->assertStatus(201);

        $budgetLog = ConstructionActivityLog::where('module', 'project_budget')
            ->where('action', 'version_created')
            ->where('project_id', $project->id)
            ->first();

        $this->assertNotNull($budgetLog, 'Budget version creation must be logged');
        $this->assertEquals(500000, $budgetLog->meta['estimated_amount']);

        // Assign team member
        $teamRes = $this->actingAs($superAdmin, 'sanctum')
            ->postJson("/api/construction/projects/{$project->id}/team", [
                'member_id' => $member->id,
                'status' => 'active',
                'is_primary' => true,
            ]);

        $teamRes->assertOk();

        $teamLog = ConstructionActivityLog::where('module', 'project_team')
            ->where('action', 'assigned')
            ->where('project_id', $project->id)
            ->first();

        $this->assertNotNull($teamLog, 'Team assignment must be logged');
        $this->assertEquals($member->id, $teamLog->meta['member_id']);
    }

    public function test_employee_creation_and_deletion_are_logged(): void
    {
        $company = $this->createCompany('Constro Solutions');
        $superAdmin = $this->createCompanySuperAdmin($company);

        // Create role first
        \App\Models\Role::firstOrCreate(
            ['slug' => 'member'],
            ['name' => 'Member', 'status' => 1, 'created_by' => $superAdmin->id]
        );

        // Store employee
        $response = $this->actingAs($superAdmin, 'superadmin')
            ->post(route('super.employees.store'), [
                'full_name' => 'Vijay Sharma',
                'email' => 'vijay.sharma@constro.com',
                'phone' => '9811223344',
                'password' => 'password123',
                'confirm_password' => 'password123',
                'role' => 'member',
                'roles' => [],
                'company_id' => $company->id,
                'department' => 'Operations',
                'designation' => 'Driver',
            ]);

        $response->assertSessionHasNoErrors();

        $createLog = ConstructionActivityLog::where('module', 'employee')
            ->where('action', 'created')
            ->first();

        $this->assertNotNull($createLog, 'Employee creation must be logged');
        $this->assertEquals($company->id, $createLog->company_id);
        $this->assertStringContainsString('Vijay Sharma', $createLog->description);

        $createdEmp = Employee::latest('id')->first();
        $this->assertNotNull($createdEmp);

        // Delete employee
        $deleteRes = $this->actingAs($superAdmin, 'superadmin')
            ->delete(route('super.employees.destroy', $createdEmp->uuid));

        $deleteRes->assertSessionHasNoErrors();

        $deleteLog = ConstructionActivityLog::where('module', 'employee')
            ->where('action', 'deleted')
            ->where('employee_id', $createdEmp->id)
            ->first();

        $this->assertNotNull($deleteLog, 'Employee deletion must be logged');
    }

    public function test_role_management_actions_are_logged(): void
    {
        $company = $this->createCompany('Infra Group');
        $superAdmin = $this->createCompanySuperAdmin($company);

        // Add role
        $createRoleRes = $this->actingAs($superAdmin, 'superadmin')
            ->post(route('super.role.add'), [
                'name' => 'Safety Officer',
            ]);

        $createRoleRes->assertSessionHasNoErrors();

        $createLog = ConstructionActivityLog::where('module', 'role_management')
            ->where('action', 'role_created')
            ->first();

        $this->assertNotNull($createLog, 'Role creation must be logged');
        $this->assertStringContainsString('Safety Officer', $createLog->description);

        $role = \App\Models\Role::where('name', 'Safety Officer')->first();
        $this->assertNotNull($role);

        // Toggle status
        $statusRes = $this->actingAs($superAdmin, 'superadmin')
            ->post(route('super.role.status', $role->uuid), [
                'status' => 0,
            ]);

        $statusRes->assertSessionHasNoErrors();

        $statusLog = ConstructionActivityLog::where('module', 'role_management')
            ->where('action', 'status_changed')
            ->first();

        $this->assertNotNull($statusLog, 'Role status toggle must be logged');
    }
}
