<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\TaskChecklistItem;
use App\Models\TaskChecklist;
use App\Models\Member;
use App\Models\Task;
use App\Models\Project;
use App\Models\Company;
use App\Models\SuperAdmin;
use App\Models\ExecutionPlan;
use App\Models\ExecutionTask;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class TaskChecklistItemModelTest extends TestCase
{
    use DatabaseTransactions;

    public function test_task_checklist_item_nulls_invalid_completed_by_member_id(): void
    {
        $company = Company::create([
            'name' => 'Unit Company',
            'status' => 'active',
        ]);

        $project = Project::create([
            'company_id' => $company->id,
            'name' => 'Unit Project',
            'status' => 'active',
            'slug' => 'unit-project-' . uniqid(),
            'project_code' => 'UPRJ-' . rand(100, 999),
        ]);

        $task = Task::create([
            'project_id' => $project->id,
            'title' => 'Unit Task',
            'status' => 'pending',
        ]);

        // Non-existent member ID
        $nonExistentMemberId = 9999999;

        $item = TaskChecklistItem::create([
            'task_id' => $task->id,
            'project_id' => $project->id,
            'item_title' => 'Unit Checklist Item',
            'is_completed' => true,
            'completed_by_member_id' => $nonExistentMemberId,
            'status' => 'completed',
            'source' => 'admin_custom',
        ]);

        $this->assertNull($item->completed_by_member_id);
    }

    public function test_task_checklist_item_preserves_valid_completed_by_member_id(): void
    {
        $company = Company::create([
            'name' => 'Unit Company 2',
            'status' => 'active',
        ]);

        $project = Project::create([
            'company_id' => $company->id,
            'name' => 'Unit Project 2',
            'status' => 'active',
            'slug' => 'unit-project2-' . uniqid(),
            'project_code' => 'UPRJ2-' . rand(100, 999),
        ]);

        $task = Task::create([
            'project_id' => $project->id,
            'title' => 'Unit Task 2',
            'status' => 'pending',
        ]);

        $member = Member::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Valid Member',
            'username' => 'valid_member_' . uniqid(),
            'email' => 'valid_' . uniqid() . '@example.com',
            'phone' => '98765' . rand(10000, 99999),
            'password' => bcrypt('secret123'),
            'status' => 1,
            'created_by' => 1,
            'slug' => 'valid-member-' . uniqid(),
        ]);

        $item = TaskChecklistItem::create([
            'task_id' => $task->id,
            'project_id' => $project->id,
            'item_title' => 'Unit Checklist Item Valid',
            'is_completed' => true,
            'completed_by_member_id' => $member->id,
            'status' => 'completed',
            'source' => 'admin_custom',
        ]);

        $this->assertEquals($member->id, $item->completed_by_member_id);
    }
}

