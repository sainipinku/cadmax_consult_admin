<?php

namespace App\Http\Controllers\SuperAdmin\Construction;

use App\Http\Controllers\Controller;
use App\Models\Construction\Project;
use App\Models\Task;
use App\Services\Construction\TaskManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class ProjectTaskController extends Controller
{
    public function __construct(
        public readonly TaskManagementService $tasks,
    ) {
        $this->middleware('construction.permission:execution_task.manage')->except(['index','show']);
    }

    public function index(Request $request, Project $project): Response
    {
        $filters = $request->only(['status','priority','assignee_id','search','sort_by','sort_dir','task_source']);
        $perPage = (int) $request->input('per_page', 25);
        $result = $this->tasks->index($project, $filters, $perPage);

        return Inertia::render('SuperAdmin/Construction/Projects/Show', [
            'project' => $project,
            'tasks' => $result,
            'taskFilters' => (object) $filters,
        ]);
    }

    public function store(Request $request, Project $project): RedirectResponse
    {
        $actor = $request->user();
        try {
            $payload = $this->tasks->validateCreatePayload($project, $request->all());
            $this->tasks->create($project, $payload, $actor, $request);
            return Redirect::back()->with('success', 'Task created successfully.');
        } catch (ValidationException $e) {
            return Redirect::back()->withErrors($e->errors())->withInput();
        } catch (Throwable $e) {
            report($e);
            return Redirect::back()->with('error', config('app.debug') ? $e->getMessage() : 'Failed to create task.')->withInput();
        }
    }

    public function update(Request $request, Project $project, Task $task): RedirectResponse
    {
        $this->assertInProject($task, $project);
        $actor = $request->user();
        try {
            $this->tasks->update($task, $request->all(), $actor, $request);
            return Redirect::back()->with('success', 'Task updated.');
        } catch (ValidationException $e) {
            return Redirect::back()->withErrors($e->errors())->withInput();
        } catch (Throwable $e) {
            report($e);
            return Redirect::back()->with('error', config('app.debug') ? $e->getMessage() : 'Task update failed.')->withInput();
        }
    }

    public function destroy(Request $request, Project $project, Task $task): RedirectResponse
    {
        $this->assertInProject($task, $project);
        $this->tasks->deleteTask($task, $request->user(), $request);
        return Redirect::back()->with('success', 'Task deleted.');
    }

    public function replaceAssignments(Request $request, Project $project, Task $task): RedirectResponse
    {
        $this->assertInProject($task, $project);
        $rows = $request->validate([
            'assignments' => ['required', 'array'],
            'assignments.*.member_id' => ['required', 'integer', 'exists:members,id'],
            'assignments.*.assignment_role' => ['nullable', 'in:owner,executor,reviewer,checker,verifier'],
            'assignments.*.is_primary' => ['nullable', 'boolean'],
            'assignments.*.assigned_from' => ['nullable', 'date'],
            'assignments.*.assigned_until' => ['nullable', 'date', 'after_or_equal:assignments.*.assigned_from'],
        ])['assignments'] ?? [];

        try {
            $this->tasks->replaceAssignments($task, $rows, $request->user());
            return Redirect::back()->with('success', 'Assignments saved.');
        } catch (Throwable $e) {
            report($e);
            return Redirect::back()->with('error', config('app.debug') ? $e->getMessage() : 'Failed to update assignments.');
        }
    }

    public function saveChecklist(Request $request, Project $project, Task $task): RedirectResponse
    {
        $this->assertInProject($task, $project);
        $items = $request->validate([
            'items' => ['required', 'array'],
            'items.*.id' => ['nullable', 'integer', 'exists:task_checklist_items,id'],
            'items.*.item_title' => ['required', 'string', 'max:500'],
            'items.*.day_number' => ['nullable', 'integer', 'min:0', 'max:255'],
            'items.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'items.*.is_completed' => ['nullable', 'boolean'],
            'items.*.client_reference' => ['nullable', 'uuid'],
        ])['items'] ?? [];

        try {
            $this->tasks->replaceChecklistItems($task, $items, $request->user());
            return Redirect::back()->with('success', 'Checklist saved.');
        } catch (Throwable $e) {
            report($e);
            return Redirect::back()->with('error', config('app.debug') ? $e->getMessage() : 'Failed to save checklist.');
        }
    }

    private function assertInProject(Task $task, Project $project): void
    {
        if ((int) $task->project_id !== (int) $project->id) {
            abort(403, 'Task does not belong to this project.');
        }
    }
}
