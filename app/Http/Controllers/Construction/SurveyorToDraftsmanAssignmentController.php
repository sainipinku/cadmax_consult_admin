<?php

namespace App\Http\Controllers\Construction;

use App\Http\Controllers\Concerns\ResolvesConstructionActor;
use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\Project;
use App\Services\Construction\SurveyorToDraftsmanAssignmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SurveyorToDraftsmanAssignmentController extends Controller
{
    use ResolvesConstructionActor;

    public function store(
        Request $request,
        Project $project,
        SurveyorToDraftsmanAssignmentService $assignmentService
    ): RedirectResponse {
        $actor = $this->constructionActor();
        abort_unless($this->isFullAdminActor($actor), 403);
        abort_unless($this->getAccessibleProjectIds($actor)->contains($project->id), 403);

        $validated = $request->validate([
            'task_source' => ['required', Rule::in(['execution', 'task'])],
            'task_id' => ['required', 'integer', 'min:1'],
            'member_id' => ['required', 'integer', 'exists:members,id'],
            'due_date' => ['nullable', 'date'],
        ]);

        $assignmentService->assign(
            $project,
            $validated['task_source'],
            (int) $validated['task_id'],
            (int) $validated['member_id'],
            $validated['due_date'] ?? null,
            $actor,
            $request
        );

        return back()->with('success', 'Task assigned to Draftsman successfully.');
    }
}
