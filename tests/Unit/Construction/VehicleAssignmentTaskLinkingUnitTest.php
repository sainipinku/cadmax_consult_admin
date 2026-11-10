<?php

namespace Tests\Unit\Construction;

use App\Models\ExecutionTask;
use App\Models\VehicleAssignment;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tests\TestCase;

class VehicleAssignmentTaskLinkingUnitTest extends TestCase
{
    public function test_vehicle_assignment_model_has_task_id_in_fillable(): void
    {
        $assignment = new VehicleAssignment();
        $this->assertContains('task_id', $assignment->getFillable(), 'VehicleAssignment model must have task_id in $fillable');
    }

    public function test_vehicle_assignment_defines_task_relationship(): void
    {
        $assignment = new VehicleAssignment();
        $this->assertTrue(method_exists($assignment, 'task'), 'VehicleAssignment must define a task() relationship method');

        $relation = $assignment->task();
        $this->assertInstanceOf(BelongsTo::class, $relation, 'VehicleAssignment::task() must be a BelongsTo relationship');
        $this->assertInstanceOf(ExecutionTask::class, $relation->getRelated(), 'VehicleAssignment::task() must relate to ExecutionTask');
        $this->assertSame('task_id', $relation->getForeignKeyName(), 'Foreign key must be task_id');
    }

    public function test_vehicle_assignment_defines_execution_task_alias_relationship(): void
    {
        $assignment = new VehicleAssignment();
        $this->assertTrue(method_exists($assignment, 'executionTask'), 'VehicleAssignment must define an executionTask() alias method');

        $relation = $assignment->executionTask();
        $this->assertInstanceOf(BelongsTo::class, $relation);
        $this->assertInstanceOf(ExecutionTask::class, $relation->getRelated());
    }
}

