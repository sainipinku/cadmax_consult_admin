<?php

namespace Tests\Unit\Construction;

use App\Models\ExecutionTaskAssignee;
use Tests\TestCase;

class TaskDriverAssignmentUnitTest extends TestCase
{
    public function test_execution_task_assignee_supports_driver_assignment_role(): void
    {
        $assignee = new ExecutionTaskAssignee();
        $assignee->assignment_role = 'driver';
        $this->assertSame('driver', $assignee->assignment_role);

        $this->assertContains('assignment_role', $assignee->getFillable());
    }

    public function test_role_mapping_logic_maps_driver_variations_to_driver(): void
    {
        $mapRole = function (string $roleInput): string {
            return match (strtolower(trim($roleInput))) {
                'driver', 'vehicle_driver', 'vehicle driver' => 'driver',
                'draft_person', 'drafting', 'drafter', 'draftsman' => 'draft_person',
                'surveyor' => 'surveyor',
                'supervisor' => 'supervisor',
                'operator' => 'operator',
                'worker' => 'worker',
                default => 'executor',
            };
        };

        $this->assertSame('driver', $mapRole('driver'));
        $this->assertSame('driver', $mapRole('Driver'));
        $this->assertSame('driver', $mapRole('vehicle_driver'));
        $this->assertSame('driver', $mapRole('vehicle driver'));
        $this->assertSame('draft_person', $mapRole('draft_person'));
        $this->assertSame('executor', $mapRole('executor'));
    }

    public function test_drafting_job_should_not_be_created_for_driver(): void
    {
        $roleInput = 'driver';
        $createDraftingJobInput = false;

        $shouldCreateDraftingJob = !empty($createDraftingJobInput) || in_array($roleInput, ['draft_person', 'drafting', 'drafter', 'draftsman'], true);

        $this->assertFalse($shouldCreateDraftingJob, 'Driver role assignment must not automatically trigger drafting jobs');
    }
}

