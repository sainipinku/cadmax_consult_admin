<?php

namespace Tests\Unit\Construction;

use App\Models\EquipmentAllocation;
use App\Models\ExecutionTask;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tests\TestCase;

class EquipmentAllocationTaskLinkingUnitTest extends TestCase
{
    public function test_equipment_allocation_model_has_task_id_in_fillable(): void
    {
        $allocation = new EquipmentAllocation();
        $this->assertContains('task_id', $allocation->getFillable(), 'EquipmentAllocation model must have task_id in $fillable');
    }

    public function test_equipment_allocation_defines_task_relationship(): void
    {
        $allocation = new EquipmentAllocation();
        $this->assertTrue(method_exists($allocation, 'task'), 'EquipmentAllocation must define a task() relationship method');

        $relation = $allocation->task();
        $this->assertInstanceOf(BelongsTo::class, $relation, 'EquipmentAllocation::task() must be a BelongsTo relationship');
        $this->assertInstanceOf(ExecutionTask::class, $relation->getRelated(), 'EquipmentAllocation::task() must relate to ExecutionTask');
        $this->assertSame('task_id', $relation->getForeignKeyName(), 'Foreign key must be task_id');
    }

    public function test_equipment_allocation_defines_execution_task_alias_relationship(): void
    {
        $allocation = new EquipmentAllocation();
        $this->assertTrue(method_exists($allocation, 'executionTask'), 'EquipmentAllocation must define an executionTask() alias method');

        $relation = $allocation->executionTask();
        $this->assertInstanceOf(BelongsTo::class, $relation);
        $this->assertInstanceOf(ExecutionTask::class, $relation->getRelated());
    }
}

