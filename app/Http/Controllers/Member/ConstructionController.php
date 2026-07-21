<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Concerns\ResolvesConstructionActor;
use App\Http\Controllers\Controller;
use App\Models\Construction\ActivityLog;
use App\Models\Construction\AttendanceRecord;
use App\Models\Construction\DailyProgressReport;
use App\Models\Construction\ExecutionTask;
use App\Models\Construction\ExecutionTaskAssignee;
use App\Models\Construction\Material;
use App\Models\Construction\MaterialIssue;
use App\Models\Construction\MaterialStock;
use App\Models\Construction\Project;
use App\Models\Construction\ProjectTeamMember;
use App\Models\Member;
use App\Services\Construction\ConstructionExecutionService;
use App\Services\Construction\ConstructionMaterialService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ConstructionController extends Controller
{
    use ResolvesConstructionActor;

    public function dashboard(): Response
    {
        /** @var Member|null $actor */
        $actor = $this->constructionActor();
        $projectIds = $this->projectIdsForActor($actor);
        $assignedTaskIds = $this->assignedTaskIdsForActor($actor);

        return Inertia::render('Member/Construction/Dashboard', [
            'stats' => [
                'assignedProjects' => $projectIds->count(),
                'activeTasks' => ExecutionTask::whereIn('id', $assignedTaskIds)
                    ->whereIn('status', ['planned', 'in_progress', 'blocked'])
                    ->count(),
                'completedTasks' => ExecutionTask::whereIn('id', $assignedTaskIds)
                    ->where('status', 'completed')
                    ->count(),
                'openAttendance' => AttendanceRecord::where('member_id', $actor?->getKey())
                    ->whereNull('check_out_at')
                    ->count(),
                'submittedReports' => DailyProgressReport::where('submitted_by_member_id', $actor?->getKey())
                    ->count(),
            ],
            'projects' => Project::with(['company', 'client', 'latestBudget'])
                ->whereIn('id', $projectIds)
                ->latest()
                ->get(),
            'tasks' => ExecutionTask::with(['project', 'executionPlan'])
                ->whereIn('id', $assignedTaskIds)
                ->latest()
                ->take(8)
                ->get(),
        ]);
    }

    public function projects(): Response
    {
        /** @var Member|null $actor */
        $actor = $this->constructionActor();
        $projectIds = $this->projectIdsForActor($actor);

        return Inertia::render('Member/Construction/Projects/Index', [
            'projects' => Project::with(['company', 'client', 'latestBudget'])
                ->whereIn('id', $projectIds)
                ->latest()
                ->get(),
        ]);
    }

    public function showProject(Project $project): Response
    {
        /** @var Member|null $actor */
        $actor = $this->constructionActor();
        $this->ensureProjectAccess($project, $actor);

        $project->load([
            'company',
            'client',
            'latestBudget',
            'teamMembers.member',
            'teamMembers.role',
            'executionTasks' => fn ($query) => $query
                ->with(['executionPlan', 'supervisor', 'assignees.member'])
                ->latest(),
            'dailyProgressReports' => fn ($query) => $query
                ->with(['submittedBy', 'executionTask', 'items'])
                ->latest('report_date'),
            'attendanceRecords' => fn ($query) => $query
                ->with(['member', 'executionTask'])
                ->latest('attendance_date'),
        ]);

        return Inertia::render('Member/Construction/Projects/Show', [
            'project' => $project,
            'activityLog' => ActivityLog::with('actor')
                ->where('project_id', $project->id)
                ->latest('created_at')
                ->take(15)
                ->get(),
        ]);
    }

    public function execution(): Response
    {
        /** @var Member|null $actor */
        $actor = $this->constructionActor();
        $projectIds = $this->projectIdsForActor($actor);
        $assignedTaskIds = $this->assignedTaskIdsForActor($actor);

        return Inertia::render('Member/Construction/Execution/Index', [
            'projects' => Project::with(['company', 'client'])
                ->whereIn('id', $projectIds)
                ->latest()
                ->get(),
            'tasks' => ExecutionTask::with(['project', 'executionPlan', 'supervisor'])
                ->whereIn('id', $assignedTaskIds)
                ->latest()
                ->get(),
            'latestAttendance' => AttendanceRecord::with(['project', 'executionTask'])
                ->where('member_id', $actor?->getKey())
                ->latest('attendance_date')
                ->take(10)
                ->get(),
            'latestReports' => DailyProgressReport::with(['project', 'executionTask', 'items', 'supportingDocument'])
                ->where('submitted_by_member_id', $actor?->getKey())
                ->latest('report_date')
                ->take(10)
                ->get(),
            'openAttendance' => AttendanceRecord::with(['project', 'executionTask'])
                ->where('member_id', $actor?->getKey())
                ->whereNull('check_out_at')
                ->latest('attendance_date')
                ->first(),
        ]);
    }

    public function attendanceCheckIn(Request $request, ConstructionExecutionService $executionService): RedirectResponse
    {
        /** @var Member|null $actor */
        $actor = $this->constructionActor();
        abort_unless($actor, 403);

        $validated = $request->validate([
            'project_id' => ['required', 'exists:construction_projects,id'],
            'execution_task_id' => ['nullable', 'exists:construction_execution_tasks,id'],
            'attendance_type' => ['required', 'in:present,half_day,overtime,site_visit'],
            'notes' => ['nullable', 'string'],
            'check_in_latitude' => ['required', 'numeric'],
            'check_in_longitude' => ['required', 'numeric'],
            'gps_accuracy_meters' => ['nullable', 'numeric', 'min:0'],
        ]);

        $project = Project::findOrFail($validated['project_id']);
        $this->ensureProjectAccess($project, $actor);

        $executionService->checkInAttendance($project, $validated, $actor, $request);

        return back()->with('success', 'Attendance check-in saved successfully.');
    }

    public function attendanceCheckOut(AttendanceRecord $attendance, Request $request, ConstructionExecutionService $executionService): RedirectResponse
    {
        /** @var Member|null $actor */
        $actor = $this->constructionActor();
        abort_unless($actor, 403);

        abort_unless((int) $attendance->member_id === (int) $actor->getKey(), 403);

        $validated = $request->validate([
            'check_out_latitude' => ['required', 'numeric'],
            'check_out_longitude' => ['required', 'numeric'],
            'gps_accuracy_meters' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $executionService->checkOutAttendance($attendance, $validated, $actor, $request);

        return back()->with('success', 'Attendance check-out saved successfully.');
    }

    public function updateTaskProgress(ExecutionTask $task, Request $request, ConstructionExecutionService $executionService): RedirectResponse
    {
        /** @var Member|null $actor */
        $actor = $this->constructionActor();
        abort_unless($actor, 403);

        $this->ensureTaskAccess($task, $actor);

        $validated = $request->validate([
            'progress_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'completed_quantity' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', 'in:planned,in_progress,completed,blocked'],
        ]);

        $executionService->updateTaskProgress($task, $validated, $actor, $request);

        return back()->with('success', 'Task progress updated successfully.');
    }

    public function submitDailyProgress(Request $request, ConstructionExecutionService $executionService): RedirectResponse
    {
        /** @var Member|null $actor */
        $actor = $this->constructionActor();
        abort_unless($actor, 403);

        $validated = $request->validate([
            'project_id' => ['required', 'exists:construction_projects,id'],
            'execution_task_id' => ['nullable', 'exists:construction_execution_tasks,id'],
            'report_date' => ['required', 'date'],
            'summary' => ['nullable', 'string'],
            'work_completed' => ['nullable', 'string'],
            'blockers' => ['nullable', 'string'],
            'workforce_count' => ['nullable', 'integer', 'min:0'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'gps_accuracy_meters' => ['nullable', 'numeric', 'min:0'],
            'weather_summary' => ['nullable', 'string'],
            'supporting_document' => ['nullable', 'file', 'max:20480'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.execution_task_id' => ['nullable', 'exists:construction_execution_tasks,id'],
            'items.*.title' => ['required', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string'],
            'items.*.unit' => ['nullable', 'string', 'max:50'],
            'items.*.planned_quantity' => ['nullable', 'numeric', 'min:0'],
            'items.*.completed_quantity' => ['nullable', 'numeric', 'min:0'],
            'items.*.percent_complete' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.remarks' => ['nullable', 'string'],
        ]);

        $project = Project::findOrFail($validated['project_id']);
        $this->ensureProjectAccess($project, $actor);

        $executionService->submitDailyProgress($project, $validated, $actor, $request);

        return back()->with('success', 'Daily progress report submitted successfully.');
    }

    public function materials(): Response
    {
        /** @var Member|null $actor */
        $actor = $this->constructionActor();
        $projectIds = $this->projectIdsForActor($actor);

        return Inertia::render('Member/Construction/Materials/Index', [
            'projects' => Project::with(['company', 'client'])
                ->whereIn('id', $projectIds)
                ->orderByDesc('id')
                ->get(['id', 'project_code', 'name', 'company_id', 'client_id']),
            'materials' => Material::whereIn('project_id', $projectIds)
                ->orderBy('name')
                ->get(['id', 'project_id', 'material_code', 'name', 'unit']),
            'stocks' => MaterialStock::with(['project', 'material'])
                ->whereIn('project_id', $projectIds)
                ->orderByDesc('updated_at')
                ->take(150)
                ->get(),
            'myIssues' => MaterialIssue::with(['project', 'items.material'])
                ->where('issued_by_member_id', $actor?->getKey())
                ->latest()
                ->take(50)
                ->get(),
        ]);
    }

    public function submitMaterialIssue(Request $request, ConstructionMaterialService $materialService): RedirectResponse
    {
        /** @var Member|null $actor */
        $actor = $this->constructionActor();
        abort_unless($actor, 403);

        $validated = $request->validate([
            'project_id' => ['required', 'exists:construction_projects,id'],
            'issue_date' => ['required', 'date'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'gps_accuracy_meters' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.material_id' => ['required', 'exists:construction_materials,id'],
            'items.*.execution_task_id' => ['nullable', 'exists:construction_execution_tasks,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit' => ['nullable', 'string', 'max:50'],
            'items.*.remarks' => ['nullable', 'string'],
        ]);

        $project = Project::findOrFail($validated['project_id']);
        $this->ensureProjectAccess($project, $actor);

        $materialService->issueMaterials($project, $validated, $actor);

        return back()->with('success', 'Material issue saved successfully.');
    }

    private function projectIdsForActor(?Member $actor)
    {
        return ProjectTeamMember::where('member_id', $actor?->getKey())
            ->where('status', 'active')
            ->pluck('project_id');
    }

    private function assignedTaskIdsForActor(?Member $actor)
    {
        return ExecutionTaskAssignee::where('member_id', $actor?->getKey())
            ->where('status', 'active')
            ->pluck('execution_task_id');
    }

    private function ensureProjectAccess(Project $project, ?Member $actor): void
    {
        abort_unless(
            $actor && ProjectTeamMember::where('project_id', $project->id)
                ->where('member_id', $actor->getKey())
                ->where('status', 'active')
                ->exists(),
            403
        );
    }

    private function ensureTaskAccess(ExecutionTask $task, ?Member $actor): void
    {
        abort_unless(
            $actor && ExecutionTaskAssignee::where('execution_task_id', $task->id)
                ->where('member_id', $actor->getKey())
                ->where('status', 'active')
                ->exists(),
            403
        );
    }
}
