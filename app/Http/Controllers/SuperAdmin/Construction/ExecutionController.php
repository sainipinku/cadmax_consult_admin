<?php

namespace App\Http\Controllers\SuperAdmin\Construction;

use App\Http\Controllers\Concerns\ResolvesConstructionActor;
use App\Http\Controllers\Controller;
use App\Models\Construction\AttendanceRecord;
use App\Models\Construction\DailyProgressReport;
use App\Models\Construction\ExecutionPlan;
use App\Models\Construction\ExecutionTask;
use App\Models\Construction\Project;
use App\Models\Construction\ProjectTeamMember;
use App\Models\Member;
use App\Services\Construction\ConstructionExecutionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ExecutionController extends Controller
{
    use ResolvesConstructionActor;

    public function index(): Response
    {
        return Inertia::render('SuperAdmin/Construction/Execution/Index', [
            'stats' => [
                'plans' => ExecutionPlan::count(),
                'tasks' => ExecutionTask::count(),
                'activeTasks' => ExecutionTask::whereIn('status', ['planned', 'in_progress', 'blocked'])->count(),
                'pendingReports' => DailyProgressReport::where('status', 'submitted')->count(),
                'pendingAttendance' => AttendanceRecord::where('status', 'pending')->count(),
            ],
            'projects' => Project::with(['company', 'client'])
                ->orderBy('name')
                ->get(['id', 'company_id', 'client_id', 'name', 'project_code', 'current_stage', 'status']),
            'executionPlans' => ExecutionPlan::with(['project', 'tasks'])
                ->latest()
                ->get(),
            'executionTasks' => ExecutionTask::with(['project', 'executionPlan', 'supervisor', 'assignees.member'])
                ->latest()
                ->get(),
            'dailyProgressReports' => DailyProgressReport::with(['project', 'executionTask', 'submittedBy', 'reviewedBy', 'items', 'supportingDocument'])
                ->latest('report_date')
                ->get(),
            'attendanceRecords' => AttendanceRecord::with(['project', 'executionTask', 'member', 'reviewedBy'])
                ->latest('attendance_date')
                ->get(),
            'members' => Member::orderBy('name')->get(['id', 'name', 'email']),
            'projectTeamMembers' => ProjectTeamMember::with(['member', 'project'])
                ->where('status', 'active')
                ->get(['id', 'project_id', 'member_id', 'status']),
        ]);
    }

    public function storePlan(Request $request, ConstructionExecutionService $executionService): RedirectResponse
    {
        $actor = $this->constructionActor();

        $validated = $request->validate([
            'project_id' => ['required', 'exists:construction_projects,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'planned_start_date' => ['nullable', 'date'],
            'planned_end_date' => ['nullable', 'date', 'after_or_equal:planned_start_date'],
            'status' => ['required', 'in:planned,active'],
        ]);

        $project = Project::findOrFail($validated['project_id']);
        $executionService->createPlan($project, $validated, $actor, $request);

        return back()->with('success', 'Execution plan created successfully.');
    }

    public function storeTask(Request $request, ConstructionExecutionService $executionService): RedirectResponse
    {
        $actor = $this->constructionActor();

        $validated = $request->validate([
            'project_id' => ['required', 'exists:construction_projects,id'],
            'execution_plan_id' => ['required', 'exists:construction_execution_plans,id'],
            'parent_task_id' => ['nullable', 'exists:construction_execution_tasks,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'planned_start_date' => ['nullable', 'date'],
            'planned_end_date' => ['nullable', 'date', 'after_or_equal:planned_start_date'],
            'priority' => ['required', 'in:low,medium,high,critical'],
            'planned_quantity' => ['nullable', 'numeric', 'min:0'],
            'unit' => ['nullable', 'string', 'max:50'],
            'requires_daily_update' => ['nullable', 'boolean'],
            'requires_gps_verification' => ['nullable', 'boolean'],
            'supervisor_member_id' => ['nullable', 'exists:members,id'],
            'assignee_member_ids' => ['nullable', 'array'],
            'assignee_member_ids.*' => ['exists:members,id'],
            'primary_assignment_role' => ['nullable', 'string', 'max:100'],
        ]);

        $project = Project::findOrFail($validated['project_id']);
        $plan = ExecutionPlan::findOrFail($validated['execution_plan_id']);

        abort_unless((int) $plan->project_id === (int) $project->id, 422, 'Execution plan does not belong to the selected project.');

        $executionService->createTask($project, $validated, $actor, $request);

        return back()->with('success', 'Execution task created successfully.');
    }

    public function assignTask(ExecutionTask $task, Request $request, ConstructionExecutionService $executionService): RedirectResponse
    {
        $actor = $this->constructionActor();

        $validated = $request->validate([
            'member_id' => ['required', 'exists:members,id'],
            'assignment_role' => ['nullable', 'string', 'max:100'],
            'assigned_from' => ['nullable', 'date'],
            'assigned_to' => ['nullable', 'date', 'after_or_equal:assigned_from'],
            'is_primary' => ['nullable', 'boolean'],
        ]);

        $executionService->assignTask($task, $validated, $actor, $request);

        return back()->with('success', 'Task assignment saved successfully.');
    }

    public function updateTaskProgress(ExecutionTask $task, Request $request, ConstructionExecutionService $executionService): RedirectResponse
    {
        $actor = $this->constructionActor();

        $validated = $request->validate([
            'progress_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'completed_quantity' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', 'in:planned,in_progress,completed,blocked'],
        ]);

        $executionService->updateTaskProgress($task, $validated, $actor, $request);

        return back()->with('success', 'Task progress updated successfully.');
    }

    public function storeDailyProgress(Request $request, ConstructionExecutionService $executionService): RedirectResponse
    {
        $actor = $this->constructionActor();

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
            'weather_summary' => ['nullable', 'string', 'max:255'],
            'supporting_document' => ['nullable', 'file', 'max:20480'],
            'items' => ['nullable', 'array'],
            'items.*.execution_task_id' => ['nullable', 'exists:construction_execution_tasks,id'],
            'items.*.title' => ['required_with:items', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string'],
            'items.*.unit' => ['nullable', 'string', 'max:50'],
            'items.*.planned_quantity' => ['nullable', 'numeric', 'min:0'],
            'items.*.completed_quantity' => ['nullable', 'numeric', 'min:0'],
            'items.*.percent_complete' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.remarks' => ['nullable', 'string'],
        ]);

        $project = Project::findOrFail($validated['project_id']);
        $executionService->submitDailyProgress($project, $validated, $actor, $request);

        return back()->with('success', 'Daily progress report submitted successfully.');
    }

    public function reviewDailyProgress(DailyProgressReport $report, Request $request, ConstructionExecutionService $executionService): RedirectResponse
    {
        $actor = $this->constructionActor();

        $validated = $request->validate([
            'status' => ['required', 'in:approved,revision_requested,rejected'],
            'review_notes' => ['nullable', 'string'],
        ]);

        $executionService->reviewDailyProgress($report, $validated, $actor, $request);

        return back()->with('success', 'Daily progress review updated successfully.');
    }

    public function reviewAttendance(AttendanceRecord $attendance, Request $request, ConstructionExecutionService $executionService): RedirectResponse
    {
        $actor = $this->constructionActor();

        $validated = $request->validate([
            'status' => ['required', 'in:approved,rejected'],
            'review_notes' => ['nullable', 'string'],
        ]);

        $executionService->reviewAttendance($attendance, $validated, $actor, $request);

        return back()->with('success', 'Attendance review updated successfully.');
    }
}
