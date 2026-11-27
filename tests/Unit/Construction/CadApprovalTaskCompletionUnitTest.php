<?php

namespace Tests\Unit\Construction;

use App\Services\Construction\CadApprovalAssignmentService;
use ReflectionClass;
use Tests\TestCase;

class CadApprovalTaskCompletionUnitTest extends TestCase
{
    public function test_cad_approval_service_exposes_shared_task_completion_and_approval_decision_methods(): void
    {
        $reflection = new ReflectionClass(CadApprovalAssignmentService::class);

        $this->assertTrue(
            $reflection->hasMethod('completeTasksOnCadApproval'),
            'CadApprovalAssignmentService must define completeTasksOnCadApproval()'
        );
        $this->assertTrue(
            $reflection->hasMethod('processDrawingApprovalDecision'),
            'CadApprovalAssignmentService must define processDrawingApprovalDecision()'
        );
    }

    public function test_all_cad_approval_controllers_delegate_task_completion_to_shared_service(): void
    {
        $projectTaskController = file_get_contents(app_path('Http/Controllers/SuperAdmin/Construction/ProjectTaskController.php'));
        $superDraftingController = file_get_contents(app_path('Http/Controllers/SuperAdmin/Construction/DraftingController.php'));
        $adminDraftingController = file_get_contents(app_path('Http/Controllers/Admin/Construction/DraftingController.php'));

        $this->assertStringContainsString('completeTasksOnCadApproval', $projectTaskController);
        $this->assertStringNotContainsString(
            "->where('status', 'drafting_in_progress')\n                ->update(['status' => 'completed'])",
            $projectTaskController
        );

        $this->assertStringContainsString('processDrawingApprovalDecision', $superDraftingController);
        $this->assertStringContainsString('processDrawingApprovalDecision', $adminDraftingController);
    }

    public function test_task_merge_prioritizes_completed_state_over_stale_active_draftsman_assignment(): void
    {
        $taskMergeJs = file_get_contents(resource_path('js/lib/taskMerge.js'));

        $this->assertMatchesRegularExpression(
            '/pickFresherStateSource[\s\S]*completed[\s\S]*hasActiveDraftsman/',
            $taskMergeJs,
            'taskMerge.js pickFresherStateSource must check completed state before active draftsman heuristic'
        );
    }
}
