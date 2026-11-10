<?php

namespace Tests\Feature\Construction;

use App\Models\Task;
use Tests\TestCase;

class TaskCrossBindingResolutionFeatureTest extends TestCase
{
    public function test_task_model_has_cross_resolution_code_for_execution_task(): void
    {
        $taskFile = app_path('Models/Task.php');
        $this->assertFileExists($taskFile);

        $code = file_get_contents($taskFile);
        $this->assertStringContainsString('function resolveRouteBinding', $code);
        $this->assertStringContainsString('ExecutionTask', $code);
    }

    public function test_execution_task_model_has_cross_resolution_code_for_unified_task(): void
    {
        $execTaskFile = app_path('Models/ExecutionTask.php');
        $this->assertFileExists($execTaskFile);

        $code = file_get_contents($execTaskFile);
        $this->assertStringContainsString('function resolveRouteBinding', $code);
        $this->assertStringContainsString('Task', $code);
    }
}

