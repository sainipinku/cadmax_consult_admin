<?php

namespace Tests\Unit\Construction;

use App\Http\Controllers\Concerns\ResolvesProjectDynamicTasks;
use App\Models\ExecutionTask;
use App\Models\Task;
use Tests\TestCase;

class DynamicProjectTaskResolutionUnitTest extends TestCase
{
    private object $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new class {
            use ResolvesProjectDynamicTasks;
        };
    }

    public function test_trait_is_available_and_defines_methods(): void
    {
        $this->assertTrue(method_exists($this->resolver, 'getDynamicTasksForProjects'));
        $this->assertTrue(method_exists($this->resolver, 'resolveExecutionTaskId'));
    }

    public function test_empty_project_ids_returns_empty_collection(): void
    {
        $tasks = $this->resolver->getDynamicTasksForProjects([]);
        $this->assertTrue($tasks->isEmpty());

        $tasksFromNull = $this->resolver->getDynamicTasksForProjects([null, 0]);
        $this->assertTrue($tasksFromNull->isEmpty());
    }

    public function test_resolves_null_for_empty_task_id(): void
    {
        $this->assertNull($this->resolver->resolveExecutionTaskId(null, 1));
        $this->assertNull($this->resolver->resolveExecutionTaskId(0, 1));
    }
}

