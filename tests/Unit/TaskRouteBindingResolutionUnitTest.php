<?php

namespace Tests\Unit;

use App\Models\ExecutionTask;
use App\Models\Task;
use Tests\TestCase;

class TaskRouteBindingResolutionUnitTest extends TestCase
{
    public function test_task_model_implements_resolve_route_binding(): void
    {
        $task = new Task();
        $this->assertTrue(method_exists($task, 'resolveRouteBinding'), 'Task model must implement resolveRouteBinding');
    }

    public function test_execution_task_model_implements_resolve_route_binding(): void
    {
        $execTask = new ExecutionTask();
        $this->assertTrue(method_exists($execTask, 'resolveRouteBinding'), 'ExecutionTask model must implement resolveRouteBinding');
    }
}

