<?php

namespace App\Http\Controllers\SuperAdmin\Construction;

use App\Http\Controllers\Controller;
use App\Models\Project;
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

    private function mapLegacyStatus(string $in): string
    {
        $map = [
            'draft' => 'planned', 'planned' => 'planned',
            'pending' => 'pending', 'assigned' => 'pending', 'not_started' => 'pending',
            'in_progress' => 'in_progress', 'in-progress' => 'in_progress',
            'active' => 'in_progress', 'progress' => 'in_progress',
            'in_review' => 'review', 'review' => 'review', 'submitted' => 'review',
            'completed' => 'completed', 'approved' => 'completed', 'done' => 'completed',
            'rejected' => 'blocked', 'blocked' => 'blocked', 'on_hold' => 'blocked',
            'cancelled' => 'cancelled', 'canceled' => 'cancelled',
        ];
        return $map[strtolower(trim($in))] ?? 'pending';
    }

    private function parseInputDate(mixed $in): ?\Illuminate\Support\Carbon
    {
        if ($in === null || $in === '') {
            return null;
        }
        if ($in instanceof \DateTimeInterface) {
            return \Illuminate\Support\Carbon::instance($in);
        }
        $s = is_string($in) ? trim($in) : (string) $in;
        if ($s === '') {
            return null;
        }
        if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $s, $m)) {
            try {
                return \Illuminate\Support\Carbon::createFromFormat('!d-m-Y', $s);
            } catch (\Throwable) {
                // fallthrough to generic parse below
            }
        }
        try {
            return \Illuminate\Support\Carbon::parse($s);
        } catch (\Throwable) {
            return null;
        }
    }

    private function mapEditorToService(Request $request, Project $project): array
    {
        $validated = $request->validate([
            'execution_plan_id' => ['nullable', 'integer', 'exists:construction_execution_plans,id'],
            'parent_task_id' => ['nullable', 'integer'],
            'title' => ['required', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:10000'],
            'planned_start_date' => ['nullable'],
            'planned_end_date' => ['nullable'],
            'actual_start_date' => ['nullable'],
            'actual_end_date' => ['nullable'],
            'priority' => ['nullable', 'in:low,medium,high,critical'],
            'planned_quantity' => ['nullable', 'numeric', 'min:0'],
            'completed_quantity' => ['nullable', 'numeric', 'min:0'],
            'unit' => ['nullable', 'string', 'max:50'],
            'progress_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'requires_daily_update' => ['nullable', 'boolean'],
            'requires_gps_verification' => ['nullable', 'boolean'],
            'supervisor_member_id' => ['nullable', 'integer', 'exists:members,id'],
            'status' => ['nullable', 'string', 'max:50'],
        ]);

        $plannedStart = $this->parseInputDate($validated['planned_start_date'] ?? null);
        $plannedEnd = $this->parseInputDate($validated['planned_end_date'] ?? null);
        if ($plannedStart && $plannedEnd && $plannedEnd->lessThan($plannedStart)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'planned_end_date' => ['Planned end date must be on or after the planned start date.'],
            ]);
        }
        $actualStart = $this->parseInputDate($validated['actual_start_date'] ?? null);
        $actualEnd = $this->parseInputDate($validated['actual_end_date'] ?? null);
        if ($actualStart && $actualEnd && $actualEnd->lessThan($actualStart)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'actual_end_date' => ['Actual end date must be on or after the actual start date.'],
            ]);
        }

        $parentId = $validated['parent_task_id'] ?? null;
        if ($parentId !== null && $parentId !== '') {
            $parentMatch = Task::query()->where('project_id', $project->id)->find((int) $parentId);
            $parentId = $parentMatch?->id;
        } else {
            $parentId = null;
        }

        $patch = [
            'execution_plan_id' => $validated['execution_plan_id'] ?? null,
            'parent_task_id' => $parentId,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'start_date' => ($actualStart ?? $plannedStart)?->toDateString(),
            'end_date' => ($actualEnd ?? $plannedEnd)?->toDateString(),
            'actual_start_date' => $actualStart?->toDateString(),
            'actual_end_date' => $actualEnd?->toDateString(),
            'planned_start_date' => $plannedStart?->toDateString(),
            'planned_end_date' => $plannedEnd?->toDateString(),
            'priority' => $validated['priority'] ?? 'medium',
            'planned_qty' => $validated['planned_quantity'] ?? null,
            'completed_qty' => $validated['completed_quantity'] ?? null,
            'qty_unit' => $validated['unit'] ?? null,
            'progress_percent' => isset($validated['progress_percent'])
                ? max(0, min(100, (int) round((float) $validated['progress_percent'])))
                : null,
            'requires_gps_verification' => isset($validated['requires_gps_verification'])
                ? (bool) $validated['requires_gps_verification']
                : null,
            'requires_daily_update' => isset($validated['requires_daily_update'])
                ? (bool) $validated['requires_daily_update']
                : null,
            'assigned_supervisor_member_id' => $validated['supervisor_member_id'] ?? null,
            'status' => isset($validated['status']) ? $this->mapLegacyStatus($validated['status']) : null,
            'task_source' => 'admin_created',
        ];

        return array_filter($patch, static fn ($v) => $v !== null, ARRAY_FILTER_USE_BOTH);
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
            $payload = $this->mapEditorToService($request, $project);
            $payload = $this->tasks->validateCreatePayload($project, $payload + [
                'assignments' => [],
                'checklist_items' => [],
            ]);
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
            $patch = $this->mapEditorToService($request, $project);
            $this->tasks->update($task, $patch, $actor, $request);
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
        try {
            $this->tasks->deleteTask($task, $request->user(), $request);
        } catch (Throwable $e) {
            report($e);
            return Redirect::back()->with('error', config('app.debug') ? $e->getMessage() : 'Failed to delete task.');
        }
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
