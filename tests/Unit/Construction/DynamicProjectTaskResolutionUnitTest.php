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

    public function test_stringify_task_field_handles_task_priority_enum(): void
    {
        $this->assertTrue(method_exists($this->resolver, 'stringifyTaskField'), 'ResolvesProjectDynamicTasks must define stringifyTaskField');

        $this->assertSame('high', $this->resolver->stringifyTaskField(\App\Enums\TaskPriority::HIGH));
        $this->assertSame('medium', $this->resolver->stringifyTaskField(\App\Enums\TaskPriority::MEDIUM));
        $this->assertSame('critical', $this->resolver->stringifyTaskField(\App\Enums\TaskPriority::CRITICAL));
        $this->assertSame('low', $this->resolver->stringifyTaskField(\App\Enums\TaskPriority::LOW));
        $this->assertSame('medium', $this->resolver->stringifyTaskField(null, 'medium'));
        $this->assertSame('custom_string', $this->resolver->stringifyTaskField('custom_string'));
    }

    public function test_normalize_task_priority_handles_enum_and_strings(): void
    {
        $this->assertTrue(method_exists($this->resolver, 'normalizeTaskPriority'), 'ResolvesProjectDynamicTasks must define normalizeTaskPriority');

        $this->assertSame('high', $this->resolver->normalizeTaskPriority(\App\Enums\TaskPriority::HIGH));
        $this->assertSame('medium', $this->resolver->normalizeTaskPriority(\App\Enums\TaskPriority::MEDIUM));
        $this->assertSame('critical', $this->resolver->normalizeTaskPriority(\App\Enums\TaskPriority::CRITICAL));
        $this->assertSame('low', $this->resolver->normalizeTaskPriority(\App\Enums\TaskPriority::LOW));
        $this->assertSame('medium', $this->resolver->normalizeTaskPriority(null));
        $this->assertSame('medium', $this->resolver->normalizeTaskPriority('invalid_priority'));
        $this->assertSame('high', $this->resolver->normalizeTaskPriority('HIGH'));
    }
}

