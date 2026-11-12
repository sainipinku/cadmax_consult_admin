<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Concerns\ResolvesConstructionActor;
use App\Http\Controllers\Controller;
use App\Models\ConstructionActivityLog;
use App\Models\AttendanceRecord;
use App\Models\DailyProgressReport;
use App\Models\DraftingJob;
use App\Models\DrawingApproval;
use App\Models\ExecutionTask;
use App\Models\ExecutionTaskAssignee;
use App\Models\Material;
use App\Models\MaterialIssue;
use App\Models\MaterialStock;
use App\Models\Project;
use App\Models\SurveyPlan;
use App\Models\VehicleAssignment;
use App\Models\Member;
use App\Services\Construction\ConstructionAuthorizationService;
use App\Services\Construction\ConstructionExecutionService;
use App\Services\Construction\ConstructionMaterialService;
use App\Services\Construction\ConstructionMemberContextService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ConstructionController extends Controller
{
    use ResolvesConstructionActor;

    public function dashboard(Request $request, ConstructionMemberContextService $contextService): Response
    {
        /** @var Member|null $actor */
        $actor = $this->constructionActor();
        abort_unless($actor, 403);

        $context = $contextService->getWebContext(
            $actor,
            $this->requestedRole($request),
            $request->integer('project') ?: null
        );

        return Inertia::render('Member/Construction/Dashboard', [
            ...$this->serializeContext($context),
            'dashboard' => $this->dashboardData($actor, $context),
        ]);
    }

    public function projects(Request $request, ConstructionMemberContextService $contextService): Response
    {
        /** @var Member|null $actor */
        $actor = $this->constructionActor();
        abort_unless($actor, 403);

        $context = $contextService->getWebContext(
            $actor,
            $this->requestedRole($request),
            $request->integer('project') ?: null
        );

        return Inertia::render('Member/Construction/Projects/Index', [
            ...$this->serializeContext($context),
            'projects' => $context['projects']->load(['company', 'client', 'latestBudget']),
        ]);
    }

    public function showProject(
        Project $project,
        Request $request,
        ConstructionMemberContextService $contextService
    ): Response {
        /** @var Member|null $actor */
        $actor = $this->constructionActor();
        abort_unless($actor, 403);

        $context = $contextService->getWebContext(
            $actor,
            $this->requestedRole($request),
            $project->getKey()
        );

        $permissions = $context['permissions'];
        $activeRoleSlug = strtolower((string) ($context['active_role']?->slug ?? ''));
        $taskViewPermissions = match ($activeRoleSlug) {
            'surveyor' => ['execution.task.view', 'execution_task.manage', 'survey.view', 'survey_plan.manage'],
            'draft_person', 'draftsman', 'drafter', 'draft_man' => ['execution.task.view', 'execution_task.manage', 'drafting.manage'],
            default => ['execution.task.view', 'execution_task.manage'],
        };

        $project->load([
            'company',
            'client',
            'latestBudget',
            'teamMembers' => fn ($query) => $this->withPermission(
                $query->with(['member', 'role'])->latest(),
                ['project_team.manage'],
                $permissions
            ),
            'executionTasks' => fn ($query) => $this->withPermission(
                $this->scopeExecutionTasksForRole(
                    $query->with(['executionPlan', 'supervisor', 'assignees.member'])->latest(),
                    $actor,
                    $activeRoleSlug
                ),
                $taskViewPermissions,
                $permissions
            ),
            'dailyProgressReports' => fn ($query) => $this->withPermission(
                $query->with(['submittedBy', 'executionTask', 'items'])->latest('report_date'),
                ['dpr.create', 'dpr.submit', 'dpr.manage', 'dpr.review'],
                $permissions
            ),
            'attendanceRecords' => fn ($query) => $this->withPermission(
                $query->with(['member', 'executionTask'])->latest('attendance_date'),
                ['attendance.mark', 'attendance.manage', 'attendance.review'],
                $permissions
            ),
        ]);

        return Inertia::render('Member/Construction/Projects/Show', [
            ...$this->serializeContext($context),
            'project' => $project,
            'activityLog' => in_array('activity_log.view', $permissions)
                ? ConstructionActivityLog::with('actor')
                    ->where('project_id', $project->id)
                    ->latest('created_at')
                    ->take(15)
                    ->get()
                : [],
        ]);
    }

    public function execution(Request $request, ConstructionMemberContextService $contextService): Response
    {
        /** @var Member|null $actor */
        $actor = $this->constructionActor();
        abort_unless($actor, 403);

        $context = $contextService->getWebContext(
            $actor,
            $this->requestedRole($request),
            $request->integer('project') ?: null
        );

        $activeProjectId = $context['active_project']?->getKey();
        $permissions = $context['permissions'];
        $activeRoleSlug = strtolower((string) ($context['active_role']?->slug ?? ''));

        $canViewExecution = array_intersect(
            match ($activeRoleSlug) {
                'surveyor' => ['execution.task.view', 'execution_task.manage', 'survey.view', 'survey_plan.manage'],
                'draft_person', 'draftsman', 'drafter', 'draft_man' => ['execution.task.view', 'execution_task.manage', 'drafting.manage'],
                default => ['execution.task.view', 'execution_task.manage'],
            },
            $permissions
        ) !== [];

        if ($activeProjectId === null || !$canViewExecution) {
            return Inertia::render('Member/Construction/Execution/Index', [
                ...$this->serializeContext($context),
                'tasks' => [],
                'latestAttendance' => [],
                'latestReports' => [],
                'openAttendance' => null,
            ]);
        }

        $assignedTaskIds = $this->assignedTaskIdsForActor($actor);

        return Inertia::render('Member/Construction/Execution/Index', [
            ...$this->serializeContext($context),
            'tasks' => $this->scopeExecutionTasksForRole(
                ExecutionTask::with(['project', 'executionPlan', 'supervisor']),
                $actor,
                $activeRoleSlug
            )
                ->whereIn('id', $assignedTaskIds)
                ->where('project_id', $activeProjectId)
                ->latest()
                ->get(),
            'latestAttendance' => AttendanceRecord::with(['project', 'executionTask'])
                ->where('member_id', $actor?->getKey())
                ->where('project_id', $activeProjectId)
                ->latest('attendance_date')
                ->take(10)
                ->get(),
            'latestReports' => DailyProgressReport::with(['project', 'executionTask', 'items', 'supportingDocument'])
                ->where('submitted_by_member_id', $actor?->getKey())
                ->where('project_id', $activeProjectId)
                ->latest('report_date')
                ->take(10)
                ->get(),
            'openAttendance' => AttendanceRecord::with(['project', 'executionTask'])
                ->where('member_id', $actor?->getKey())
                ->where('project_id', $activeProjectId)
                ->whereNull('check_out_at')
                ->latest('attendance_date')
                ->first(),
        ]);
    }

    public function attendanceCheckIn(
        Request $request,
        ConstructionExecutionService $executionService,
        ConstructionMemberContextService $contextService
    ): RedirectResponse {
        /** @var Member|null $actor */
        $actor = $this->constructionActor();
        abort_unless($actor, 403);

        $validated = $request->validate([
            'project_id' => ['required', 'exists:construction_projects,id'],
            'execution_task_id' => ['nullable', 'exists:construction_execution_tasks,id'],
            'attendance_date' => ['required', 'date'],
            'attendance_type' => ['required', 'in:present,half_day,overtime,site_visit'],
            'notes' => ['nullable', 'string'],
            'check_in_latitude' => ['required', 'numeric'],
            'check_in_longitude' => ['required', 'numeric'],
            'gps_accuracy_meters' => ['nullable', 'numeric', 'min:0'],
        ]);

        $project = Project::findOrFail($validated['project_id']);
        $this->authorizeActionForContext($actor, $contextService, $project, $this->requestedRole($request), ['attendance.mark', 'attendance.manage']);

        $executionService->checkInAttendance($project, $validated, $actor, $request);

        return back()->with('success', 'Attendance check-in saved successfully.');
    }

    public function attendanceCheckOut(
        AttendanceRecord $attendance,
        Request $request,
        ConstructionExecutionService $executionService,
        ConstructionMemberContextService $contextService
    ): RedirectResponse {
        /** @var Member|null $actor */
        $actor = $this->constructionActor();
        abort_unless($actor, 403);

        abort_unless((int) $attendance->member_id === (int) $actor->getKey(), 403);

        $project = $attendance->project;
        $this->authorizeActionForContext($actor, $contextService, $project, $this->requestedRole($request), ['attendance.mark', 'attendance.manage']);

        $validated = $request->validate([
            'check_out_latitude' => ['required', 'numeric'],
            'check_out_longitude' => ['required', 'numeric'],
            'gps_accuracy_meters' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $executionService->checkOutAttendance($attendance, $validated, $actor, $request);

        return back()->with('success', 'Attendance check-out saved successfully.');
    }

    public function updateTaskProgress(
        ExecutionTask $task,
        Request $request,
        ConstructionExecutionService $executionService,
        ConstructionMemberContextService $contextService
    ): RedirectResponse {
        /** @var Member|null $actor */
        $actor = $this->constructionActor();
        abort_unless($actor, 403);

        $this->ensureTaskAccess($task, $actor);

        $project = $task->project;
        $this->authorizeActionForContext($actor, $contextService, $project, $this->requestedRole($request), ['execution.task.update', 'execution_task.manage']);

        $validated = $request->validate([
            'progress_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'completed_quantity' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', 'in:planned,in_progress,completed,blocked'],
        ]);

        $executionService->updateTaskProgress($task, $validated, $actor, $request);

        return back()->with('success', 'Task progress updated successfully.');
    }

    public function submitDailyProgress(
        Request $request,
        ConstructionExecutionService $executionService,
        ConstructionMemberContextService $contextService
    ): RedirectResponse {
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
        $this->authorizeActionForContext($actor, $contextService, $project, $this->requestedRole($request), ['dpr.create', 'dpr.submit', 'dpr.manage']);

        $executionService->submitDailyProgress($project, $validated, $actor, $request);

        return back()->with('success', 'Daily progress report submitted successfully.');
    }

    public function materials(Request $request, ConstructionMemberContextService $contextService): Response
    {
        /** @var Member|null $actor */
        $actor = $this->constructionActor();
        abort_unless($actor, 403);

        $context = $contextService->getWebContext(
            $actor,
            $this->requestedRole($request),
            $request->integer('project') ?: null
        );

        $permissions = $context['permissions'];
        $activeProjectId = $context['active_project']?->getKey();

        $canViewMaterials = array_intersect(
            ['material.manage', 'material_issue.manage', 'material_stock.manage'],
            $permissions
        ) !== [];

        if ($activeProjectId === null || !$canViewMaterials) {
            return Inertia::render('Member/Construction/Materials/Index', [
                ...$this->serializeContext($context),
                'materials' => [],
                'stocks' => [],
                'myIssues' => [],
            ]);
        }

        return Inertia::render('Member/Construction/Materials/Index', [
            ...$this->serializeContext($context),
            'materials' => Material::where('project_id', $activeProjectId)
                ->orderBy('name')
                ->get(['id', 'project_id', 'material_code', 'name', 'unit']),
            'stocks' => MaterialStock::with(['project', 'material'])
                ->where('project_id', $activeProjectId)
                ->orderByDesc('updated_at')
                ->take(150)
                ->get(),
            'myIssues' => MaterialIssue::with(['project', 'items.material'])
                ->where('issued_by_member_id', $actor?->getKey())
                ->where('project_id', $activeProjectId)
                ->latest()
                ->take(50)
                ->get(),
        ]);
    }

    public function submitMaterialIssue(
        Request $request,
        ConstructionMaterialService $materialService,
        ConstructionMemberContextService $contextService
    ): RedirectResponse {
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
        $this->authorizeActionForContext($actor, $contextService, $project, $this->requestedRole($request), ['material_issue.manage']);

        $materialService->issueMaterials($project, $validated, $actor);

        return back()->with('success', 'Material issue saved successfully.');
    }

    private function requestedRole(Request $request): ?string
    {
        $role = $request->query('role') ?? $request->input('role');

        return is_string($role) && $role !== '' ? $role : null;
    }

    private function assignedTaskIdsForActor(?Member $actor)
    {
        return ExecutionTaskAssignee::where('member_id', $actor?->getKey())
            ->where('status', 'active')
            ->pluck('execution_task_id');
    }

    private function scopeExecutionTasksForRole($query, Member $member, string $roleSlug)
    {
        $assignments = fn ($assignmentQuery) => $assignmentQuery
            ->where('member_id', $member->getKey())
            ->where('status', 'active');

        if ($roleSlug === 'surveyor') {
            return $query->whereHas('assignees', $assignments);
        }

        if (in_array($roleSlug, ['draft_person', 'draftsman', 'drafter', 'draft_man'], true)) {
            return $query->whereHas('assignees', fn ($assignmentQuery) => $assignments($assignmentQuery)
                ->whereIn('assignment_role', ['draft_person', 'draftsman', 'drafter', 'draft_man']));
        }

        if (in_array($roleSlug, ['driver', 'vehicle_driver'], true)) {
            return $query
                ->whereHas('assignees', $assignments)
                ->whereDoesntHave('assignees', fn ($assignmentQuery) => $assignmentQuery
                    ->where('status', 'active')
                    ->whereIn('assignment_role', ['draft_person', 'draftsman', 'drafter', 'draft_man']));
        }

        return $query;
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

    /**
     * Returns an empty relation when the active role lacks any required permission.
     */
    private function withPermission($query, array $permissions, array $activePermissions)
    {
        if (array_intersect($permissions, $activePermissions) === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query;
    }

    /**
     * Enforces member + exact project + active role + role permission.
     */
    private function authorizeActionForContext(
        Member $actor,
        ConstructionMemberContextService $contextService,
        Project $project,
        ?string $requestedRole,
        array $permissions
    ): void {
        /** @var ConstructionAuthorizationService $authorization */
        $authorization = app(ConstructionAuthorizationService::class);
        if ($authorization->isProjectOwnerOrSuperAdminEmulating($actor)) {
            return;
        }

        try {
            $context = $contextService->getWebContext($actor, $requestedRole, $project->getKey());
        } catch (AuthorizationException) {
            abort(403);
        }

        $activeRole = $context['active_role'];

        if ($activeRole === null) {
            abort(403);
        }

        /** @var ConstructionAuthorizationService $authorization */
        $authorization = app(ConstructionAuthorizationService::class);

        $rolePermissions = $authorization->getPermissionsForRole(
            $actor,
            $activeRole,
            $project->getKey()
        );

        if (array_intersect($permissions, $rolePermissions) === []) {
            abort(403);
        }
    }

    private function serializeContext(array $context): array
    {
        /** @var ConstructionAuthorizationService $authorization */
        $authorization = app(ConstructionAuthorizationService::class);

        $activeProject = $context['active_project'];

        $availableRoles = $activeProject !== null
            ? $authorization->getRoles($context['member'], $activeProject->getKey())
            : $authorization->getGlobalRoles($context['member']);

        return [
            'member' => $context['member'],
            'roles' => $context['roles'],
            'available_roles' => $availableRoles,
            'projects' => $context['projects']->load(['company', 'client']),
            'permissions' => $context['permissions'],
            'active_role' => $context['active_role'],
            'active_project' => $context['active_project'],
        ];
    }

    private function dashboardData(Member $member, array $context): array
    {
        $activeRole = $context['active_role'];
        $activeProject = $context['active_project'];
        $permissions = $context['permissions'];

        if ($activeRole === null || $activeProject === null) {
            return [];
        }

        $projectId = $activeProject->getKey();
        $assignedTaskIds = $this->assignedTaskIdsForActor($member);

        if ($activeRole->slug === 'surveyor' && array_intersect(['survey.view', 'survey_plan.manage'], $permissions)) {
            return [
                'assigned_surveys' => SurveyPlan::where('project_id', $projectId)
                    ->whereHas('planMembers', fn ($q) => $q->where('member_id', $member->getKey()))
                    ->count(),
            ];
        }

        if ($activeRole->slug === 'vehicle_driver' && in_array('vehicle_tracking.manage', $permissions)) {
            // Separate assigned vehicles section removed per driver home page requirements
            return [];
        }

        if ($activeRole->slug === 'site_employee'
            && array_intersect(['execution.task.view', 'execution_task.manage'], $permissions)) {
            return [
                'active_tasks' => ExecutionTask::whereIn('id', $assignedTaskIds)
                    ->where('project_id', $projectId)
                    ->whereIn('status', ['planned', 'in_progress', 'blocked'])
                    ->count(),
                'open_attendance' => in_array('attendance.mark', $permissions)
                    ? AttendanceRecord::where('member_id', $member->getKey())
                        ->where('project_id', $projectId)
                        ->whereNull('check_out_at')
                        ->count()
                    : 0,
                'submitted_reports' => in_array('dpr.submit', $permissions)
                    ? DailyProgressReport::where('submitted_by_member_id', $member->getKey())
                        ->where('project_id', $projectId)
                        ->count()
                    : 0,
            ];
        }

        if ($activeRole->slug === 'draft_person' && in_array('drafting.manage', $permissions)) {
            return [
                'drafting_jobs' => DraftingJob::where('project_id', $projectId)
                    ->where('assigned_to_member_id', $member->getKey())
                    ->count(),
            ];
        }

        if ($activeRole->slug === 'review_approver' && in_array('drawing_approval.manage', $permissions)) {
            return [
                'pending_approvals' => DrawingApproval::where('project_id', $projectId)
                    ->where('decision', 'pending')
                    ->count(),
            ];
        }

        return [];
    }
}