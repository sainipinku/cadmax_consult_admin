<?php

namespace Tests\Feature\Construction;

use App\Models\Client;
use App\Models\Company;
use App\Models\ExecutionTask;
use App\Models\Member;
use App\Models\Project;
use App\Models\SuperAdmin;
use App\Models\Task;
use App\Models\TaskChecklist;
use App\Models\TaskChecklistItem;
use Database\Seeders\Construction\FoundationSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\SuperAdminPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProjectChecklistForeignKeyTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private SuperAdmin $superAdmin;
    private Project $project;
    private Task $unifiedTask;
    private ExecutionTask $execTask;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->seed(SuperAdminPermissionSeeder::class);
        $this->seed(FoundationSeeder::class);

        $this->company = Company::create([
            'name' => 'CadMax Const Admin',
            'status' => 'active',
        ]);

        // Create SuperAdmin with is_project_owner=1 so all construction permissions pass
        $this->superAdmin = SuperAdmin::create([
            'name' => 'Super Admin User',
            'email' => 'superadmin_test@cadmax.com',
            'username' => 'superadmin_test_' . uniqid(),
            'password' => Hash::make('password'),
            'company_id' => $this->company->id,
            'status' => 1,
            'is_project_owner' => 1,
        ]);

        $client = Client::create([
            'company_id' => $this->company->id,
            'name' => 'Client Const',
            'client_code' => 'CLI-CONST-' . rand(100, 999),
            'status' => 'active',
        ]);

        $this->project = Project::create([
            'company_id' => $this->company->id,
            'client_id' => $client->id,
            'name' => 'Bridge Construction',
            'slug' => 'bridge-construction-' . rand(100, 999),
            'project_code' => 'PRJ-BRG-' . rand(100, 999),
            'status' => 'active',
        ]);

        $this->unifiedTask = Task::create([
            'project_id' => $this->project->id,
            'title' => 'Foundation Pouring',
            'task_code' => 'TASK-001',
            'status' => 'pending',
            'created_by' => $this->superAdmin->id,
        ]);

        $plan = \App\Models\ExecutionPlan::create([
            'project_id' => $this->project->id,
            'plan_code' => 'EXP-001',
            'title' => 'Master Plan',
            'status' => 'approved',
        ]);

        $this->execTask = ExecutionTask::create([
            'project_id' => $this->project->id,
            'execution_plan_id' => $plan->id,
            'title' => 'Foundation Pouring Exec',
            'task_code' => 'TASK-001',
            'status' => 'pending',
        ]);
    }

    public function test_superadmin_can_create_completed_unified_task_checklist_without_foreign_key_violation(): void
    {
        // Ensure no member exists with the superadmin ID
        Member::where('id', $this->superAdmin->id)->delete();

        $response = $this->actingAs($this->superAdmin, 'superadmin')
            ->from(route('super.construction.projects.show', $this->project->id))
            ->post(route('super.construction.projects.checklists.store', $this->project->id), [
                'scope' => 'unified_task',
                'unified_task_id' => $this->unifiedTask->id,
                'day_number' => 1,
                'item_title' => 'Pour foundation concrete',
                'assign_hours' => 4.5,
                'notes' => 'Quality check completed',
                'status' => 'completed',
                'is_completed' => 1,
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertSessionMissing('error');

        $this->assertDatabaseHas('task_checklist_items', [
            'task_id' => $this->unifiedTask->id,
            'project_id' => $this->project->id,
            'item_title' => 'Pour foundation concrete',
            'is_completed' => 1,
            'status' => 'completed',
            'completed_by_member_id' => null,
        ]);
    }

    public function test_superadmin_can_create_completed_execution_task_checklist_without_foreign_key_violation(): void
    {
        Member::where('id', $this->superAdmin->id)->delete();

        $response = $this->actingAs($this->superAdmin, 'superadmin')
            ->from(route('super.construction.projects.show', $this->project->id))
            ->post(route('super.construction.projects.checklists.store', $this->project->id), [
                'scope' => 'execution_task',
                'execution_task_id' => $this->execTask->id,
                'day_number' => 2,
                'item_title' => 'Reinforcement bar inspection',
                'assign_hours' => 3.0,
                'status' => 'completed',
                'is_completed' => 1,
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertSessionMissing('error');

        $this->assertDatabaseHas('construction_task_checklists', [
            'execution_task_id' => $this->execTask->id,
            'item_title' => 'Reinforcement bar inspection',
            'is_completed' => 1,
            'status' => 'completed',
            'completed_by_member_id' => null,
        ]);
    }

    public function test_superadmin_can_toggle_checklist_item_complete_without_foreign_key_violation(): void
    {
        Member::where('id', $this->superAdmin->id)->delete();

        $checklistItem = TaskChecklistItem::create([
            'task_id' => $this->unifiedTask->id,
            'project_id' => $this->project->id,
            'day_number' => 1,
            'item_title' => 'Excavation check',
            'is_completed' => false,
            'status' => 'pending',
            'source' => 'admin_custom',
        ]);

        $response = $this->actingAs($this->superAdmin, 'superadmin')
            ->from(route('super.construction.projects.show', $this->project->id))
            ->patch(route('super.construction.projects.checklists.toggle', [$this->project->id, $checklistItem->id]));

        $response->assertSessionHasNoErrors();
        $response->assertSessionMissing('error');

        $checklistItem->refresh();
        $this->assertTrue($checklistItem->is_completed);
        $this->assertNull($checklistItem->completed_by_member_id);
    }

    public function test_superadmin_with_matching_member_email_resolves_member_id_on_completion(): void
    {
        $linkedMember = Member::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'name' => $this->superAdmin->name,
            'username' => 'linked_admin_' . uniqid(),
            'email' => $this->superAdmin->email,
            'phone' => '98765' . rand(10000, 99999),
            'password' => bcrypt('secret123'),
            'status' => 1,
            'created_by' => 1,
            'slug' => 'linked-admin-' . uniqid(),
        ]);

        $response = $this->actingAs($this->superAdmin, 'superadmin')
            ->from(route('super.construction.projects.show', $this->project->id))
            ->post(route('super.construction.projects.checklists.store', $this->project->id), [
                'scope' => 'unified_task',
                'unified_task_id' => $this->unifiedTask->id,
                'day_number' => 1,
                'item_title' => 'Electrical conduit installation',
                'status' => 'completed',
                'is_completed' => 1,
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertSessionMissing('error');

        $this->assertDatabaseHas('task_checklist_items', [
            'task_id' => $this->unifiedTask->id,
            'project_id' => $this->project->id,
            'item_title' => 'Electrical conduit installation',
            'is_completed' => 1,
            'completed_by_member_id' => $linkedMember->id,
        ]);
    }

    public function test_superadmin_can_update_checklist_item_to_completed_without_foreign_key_violation(): void
    {
        Member::where('id', $this->superAdmin->id)->delete();

        $checklistItem = TaskChecklistItem::create([
            'task_id' => $this->unifiedTask->id,
            'project_id' => $this->project->id,
            'day_number' => 1,
            'item_title' => 'Plumbing pressure test',
            'is_completed' => false,
            'status' => 'pending',
            'source' => 'admin_custom',
        ]);

        $response = $this->actingAs($this->superAdmin, 'superadmin')
            ->from(route('super.construction.projects.show', $this->project->id))
            ->put(route('super.construction.projects.checklists.update', [$this->project->id, $checklistItem->id]), [
                'day_number' => 1,
                'item_title' => 'Plumbing pressure test',
                'status' => 'completed',
                'is_completed' => 1,
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertSessionMissing('error');

        $checklistItem->refresh();
        $this->assertTrue($checklistItem->is_completed);
        $this->assertEquals('completed', $checklistItem->status);
        $this->assertNull($checklistItem->completed_by_member_id);
    }
}
