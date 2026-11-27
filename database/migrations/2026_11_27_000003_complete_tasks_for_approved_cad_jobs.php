<?php

use App\Models\DraftingJob;
use App\Models\DrawingApproval;
use App\Services\Construction\CadApprovalAssignmentService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Backfill task completion statuses for drafting jobs / drawing approvals already approved by an Admin.
     */
    public function up(): void
    {
        $service = app(CadApprovalAssignmentService::class);
        $handledJobIds = [];

        DraftingJob::query()
            ->where('status', 'approved')
            ->with('project')
            ->chunkById(50, function ($jobs) use ($service, &$handledJobIds) {
                foreach ($jobs as $job) {
                    if ($job->project) {
                        $service->completeTasksOnCadApproval($job->project, $job);
                        $handledJobIds[] = (int) $job->id;
                    }
                }
            });

        DrawingApproval::query()
            ->where('decision', 'approved')
            ->with(['project', 'drawingRevision.draftingJob'])
            ->chunkById(50, function ($approvals) use ($service, &$handledJobIds) {
                foreach ($approvals as $approval) {
                    $project = $approval->project;
                    $job = $approval->drawingRevision?->draftingJob;
                    if ($project && (! $job || ! in_array((int) $job->id, $handledJobIds, true))) {
                        $service->completeTasksOnCadApproval($project, $job);
                        if ($job) {
                            $handledJobIds[] = (int) $job->id;
                        }
                    }
                }
            });
    }

    public function down(): void
    {
        // Idempotent data repair; no destructive rollback.
    }
};
