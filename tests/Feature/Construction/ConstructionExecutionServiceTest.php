<?php

namespace Tests\Feature\Construction;

use App\Models\Construction\Client;
use App\Models\Construction\Company;
use App\Models\Construction\ExecutionPlan;
use App\Models\Construction\ExecutionTask;
use App\Models\Construction\Project;
use App\Models\Construction\ProjectTeamMember;
use App\Models\Member;
use App\Models\SuperAdmin;
use App\Services\Construction\ConstructionAuthorizationService;
use App\Services\Construction\ConstructionExecutionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ConstructionExecutionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_rejects_assigning_a_member_who_is_not_on_the_project_team(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $project = $this->createProject();
        $assignedMember = $this->createMember($superAdmin, 'Assigned Member');
        $outsideMember = $this->createMember($superAdmin, 'Outside Member');

        ProjectTeamMember::create([
            'project_id' => $project->id,
            'member_id' => $assignedMember->id,
            'status' => 'active',
        ]);

        $plan = ExecutionPlan::create([
            'project_id' => $project->id,
            'plan_code' => 'EXP-00001',
            'title' => 'Foundation Plan',
            'status' => 'planned',
        ]);

        $task = ExecutionTask::create([
            'project_id' => $project->id,
            'execution_plan_id' => $plan->id,
            'task_code' => 'EXT-00001',
            'title' => 'Excavation',
            'priority' => 'medium',
            'status' => 'planned',
        ]);

        $service = app(ConstructionExecutionService::class);

        try {
            $service->assignTask($task, [
                'member_id' => $outsideMember->id,
            ], $assignedMember);

            $this->fail('Expected assigning a non-project member to fail.');
        } catch (ValidationException $exception) {
            $this->assertSame([
                'The selected member is not assigned to the chosen project.',
            ], $exception->errors()['member_id']);
        }
    }

    public function test_it_rejects_daily_progress_for_a_task_from_another_project(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $projectA = $this->createProject('PRJ-A', 'project-a', 'Project A');
        $projectB = $this->createProject('PRJ-B', 'project-b', 'Project B');
        $member = $this->createMember($superAdmin, 'Execution Member');

        ProjectTeamMember::create([
            'project_id' => $projectA->id,
            'member_id' => $member->id,
            'status' => 'active',
        ]);

        $planB = ExecutionPlan::create([
            'project_id' => $projectB->id,
            'plan_code' => 'EXP-00002',
            'title' => 'Project B Plan',
            'status' => 'planned',
        ]);

        $taskB = ExecutionTask::create([
            'project_id' => $projectB->id,
            'execution_plan_id' => $planB->id,
            'task_code' => 'EXT-00002',
            'title' => 'Column Work',
            'priority' => 'high',
            'status' => 'planned',
        ]);

        $service = app(ConstructionExecutionService::class);

        try {
            $service->submitDailyProgress($projectA, [
                'execution_task_id' => $taskB->id,
                'report_date' => now()->toDateString(),
                'summary' => 'Mismatched project update',
                'items' => [],
            ], $member);

            $this->fail('Expected daily progress with a cross-project task to fail.');
        } catch (ValidationException $exception) {
            $this->assertSame([
                'The selected task does not belong to the chosen project.',
            ], $exception->errors()['execution_task_id']);
        }
    }

    public function test_it_infers_project_id_from_execution_route_models(): void
    {
        $project = $this->createProject();
        $plan = ExecutionPlan::create([
            'project_id' => $project->id,
            'plan_code' => 'EXP-00003',
            'title' => 'Execution Plan',
            'status' => 'planned',
        ]);

        $task = ExecutionTask::create([
            'project_id' => $project->id,
            'execution_plan_id' => $plan->id,
            'task_code' => 'EXT-00003',
            'title' => 'Brick Work',
            'priority' => 'medium',
            'status' => 'planned',
        ]);

        $request = request()->create('/mobile/construction/tasks/' . $task->id . '/progress', 'POST');
        $request->setRouteResolver(fn () => new class($task)
        {
            public function __construct(private readonly ExecutionTask $task)
            {
            }

            public function parameters(): array
            {
                return ['task' => $this->task];
            }
        });

        $service = app(ConstructionAuthorizationService::class);

        $this->assertSame($project->id, $service->inferProjectId($request));
    }

    private function createSuperAdmin(): SuperAdmin
    {
        return SuperAdmin::create([
            'uuid' => (string) Str::uuid(),
            'roles' => json_encode(['super_admin']),
            'name' => 'Construction Super Admin',
            'email' => 'super-admin@example.com',
            'phone' => '9999999999',
            'whatsapp_phone' => '9999999999',
            'password' => 'password',
        ]);
    }

    private function createMember(SuperAdmin $superAdmin, string $name): Member
    {
        $slug = Str::slug($name) . '-' . Str::lower(Str::random(5));

        return Member::create([
            'uuid' => (string) Str::uuid(),
            'created_by' => $superAdmin->id,
            'name' => $name,
            'username' => $slug,
            'email' => $slug . '@example.com',
            'phone' => (string) random_int(7000000000, 9999999999),
            'password' => 'password',
            'slug' => $slug,
        ]);
    }

    private function createProject(
        string $projectCode = 'PRJ-001',
        string $slug = 'project-001',
        string $name = 'Construction Project'
    ): Project {
        $company = Company::create([
            'name' => $name . ' Company',
            'status' => 'active',
        ]);

        $client = Client::create([
            'company_id' => $company->id,
            'client_code' => $projectCode . '-CLIENT',
            'name' => $name . ' Client',
            'status' => 'active',
        ]);

        return Project::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'project_code' => $projectCode,
            'name' => $name,
            'slug' => $slug,
            'status' => 'active',
            'current_stage' => 'ready_for_construction',
        ]);
    }
}
