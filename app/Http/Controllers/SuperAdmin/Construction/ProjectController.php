<?php

namespace App\Http\Controllers\SuperAdmin\Construction;

use App\Http\Controllers\Concerns\ResolvesConstructionActor;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Company;
use App\Models\ConstructionActivityLog;
use App\Models\MemberRoleAssignment;
use App\Models\Project;
use App\Models\ProjectBudget;
use App\Models\ProjectTeamMember;
use App\Models\ConstructionRole;
use App\Models\AttendanceRecord;
use App\Models\DailyProgressReport;
use App\Models\ExecutionTask;
use App\Models\SurveyPlan;
use App\Models\SurveySubmission;
use App\Models\SurveyVisit;
use App\Models\VehicleAssignment;
use App\Models\VehicleLocationPing;
use App\Models\Member;
use App\Services\Construction\ConstructionActivityService;
use App\Services\Construction\ConstructionTeamAssignmentService;
use App\Services\Construction\SurveyDataService;
use App\Services\Construction\TaskManagementService;
use App\Models\Task;
use Illuminate\Validation\ValidationException;
use App\Support\Construction\SurveyStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use App\Models\SuperAdmin;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;
use App\Http\Controllers\Concerns\IntersectsAdminDeletion;

class ProjectController extends Controller
{
    use ResolvesConstructionActor, IntersectsAdminDeletion;

    public function index(): Response
    {
        $actor = $this->constructionActor();
        $accessibleProjectIds = $this->getAccessibleProjectIds($actor);

        $projects = Project::with(['company', 'client', 'latestBudget'])
            ->whereIn('id', $accessibleProjectIds)
            ->withCount([
                'executionTasks as tasks_total_count',
                'surveyPlans as survey_plans_count',
                'surveySubmissions as survey_submissions_count',
                'dailyProgressReports as dpr_count',
                'attendanceRecords as attendance_count',
                'clientInvoices as invoice_count',
            ])
            ->latest()
            ->get();

        $projectIds = $projects->pluck('id')->all();

        $taskChecklistsByProject = [];
        \App\Models\TaskChecklist::query()
            ->selectRaw('et.project_id as p_id, COUNT(*) as c_total, SUM(CASE WHEN construction_task_checklists.is_completed = 1 THEN 1 ELSE 0 END) as c_completed')
            ->join('construction_execution_tasks as et', 'et.id', '=', 'construction_task_checklists.execution_task_id')
            ->whereIn('et.project_id', $projectIds)
            ->groupBy('et.project_id')
            ->reorder()
            ->orderBy('p_id')
            ->each(static function ($row) use (&$taskChecklistsByProject) {
                $taskChecklistsByProject[(int) $row->p_id] = [
                    'total' => (int) ($taskChecklistsByProject[(int) $row->p_id]['total'] ?? 0) + (int) $row->c_total,
                    'completed' => (int) ($taskChecklistsByProject[(int) $row->p_id]['completed'] ?? 0) + (int) $row->c_completed,
                ];
            });

        \App\Models\TaskChecklist::query()
            ->selectRaw('sp.project_id as p_id, COUNT(*) as c_total, SUM(CASE WHEN construction_task_checklists.is_completed = 1 THEN 1 ELSE 0 END) as c_completed')
            ->join('construction_survey_plans as sp', 'sp.id', '=', 'construction_task_checklists.survey_plan_id')
            ->whereIn('sp.project_id', $projectIds)
            ->groupBy('sp.project_id')
            ->reorder()
            ->orderBy('p_id')
            ->each(static function ($row) use (&$taskChecklistsByProject) {
                $taskChecklistsByProject[(int) $row->p_id] = [
                    'total' => (int) ($taskChecklistsByProject[(int) $row->p_id]['total'] ?? 0) + (int) $row->c_total,
                    'completed' => (int) ($taskChecklistsByProject[(int) $row->p_id]['completed'] ?? 0) + (int) $row->c_completed,
                ];
            });

        \App\Models\TaskChecklistItem::query()
            ->selectRaw('task_checklist_items.project_id as p_id, COUNT(*) as c_total, SUM(CASE WHEN task_checklist_items.is_completed = 1 THEN 1 ELSE 0 END) as c_completed')
            ->whereNotNull('task_checklist_items.project_id')
            ->whereIn('task_checklist_items.project_id', $projectIds)
            ->whereNull('task_checklist_items.deleted_at')
            ->groupBy('task_checklist_items.project_id')
            ->reorder()
            ->orderBy('p_id')
            ->each(static function ($row) use (&$taskChecklistsByProject) {
                $taskChecklistsByProject[(int) $row->p_id] = [
                    'total' => (int) ($taskChecklistsByProject[(int) $row->p_id]['total'] ?? 0) + (int) $row->c_total,
                    'completed' => (int) ($taskChecklistsByProject[(int) $row->p_id]['completed'] ?? 0) + (int) $row->c_completed,
                ];
            });

        $tasksCompletedByProject = \App\Models\ExecutionTask::query()
            ->selectRaw('project_id, COUNT(*) as total, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as completed, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as in_progress', ['completed', 'in_progress'])
            ->whereIn('project_id', $projectIds)
            ->groupBy('project_id')
            ->reorder()
            ->orderBy('project_id')
            ->get()
            ->keyBy('project_id');

        $unifiedTasksSummaryByProject = \App\Models\Task::query()
            ->selectRaw('project_id, COUNT(*) as total, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as completed, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as in_progress', ['completed', 'in_progress'])
            ->whereIn('project_id', $projectIds)
            ->whereNull('deleted_at')
            ->groupBy('project_id')
            ->reorder()
            ->orderBy('project_id')
            ->get()
            ->keyBy('project_id');

        $projects->transform(function (Project $project) use (
            $taskChecklistsByProject,
            $tasksCompletedByProject,
            $unifiedTasksSummaryByProject
        ) {
            $taskStats = $tasksCompletedByProject->get($project->id);
            $unifiedStats = $unifiedTasksSummaryByProject->get($project->id);
            $checklistStats = $taskChecklistsByProject[(int) $project->id] ?? ['total' => 0, 'completed' => 0];
            $totalTasks = (int) ($taskStats?->total ?? 0) + (int) ($unifiedStats?->total ?? 0);
            $completedTasks = (int) ($taskStats?->completed ?? 0) + (int) ($unifiedStats?->completed ?? 0);
            $inProgressTasks = (int) ($taskStats?->in_progress ?? 0) + (int) ($unifiedStats?->in_progress ?? 0);
            $project->workflow_counts = [
                'execution_tasks' => [
                    'total' => max($totalTasks, (int) ($project->tasks_total_count ?? 0)),
                    'completed' => $completedTasks,
                    'in_progress' => $inProgressTasks,
                    'pending' => max(
                        0,
                        max($totalTasks, (int) ($project->tasks_total_count ?? 0)) - $completedTasks - $inProgressTasks
                    ),
                ],
                'survey' => [
                    'plans' => (int) ($project->survey_plans_count ?? 0),
                    'submissions' => (int) ($project->survey_submissions_count ?? 0),
                ],
                'daily_progress' => [
                    'reports' => (int) ($project->dpr_count ?? 0),
                    'attendance' => (int) ($project->attendance_count ?? 0),
                ],
                'billing' => [
                    'invoices' => (int) ($project->invoice_count ?? 0),
                ],
                'checklists' => [
                    'total' => (int) $checklistStats['total'],
                    'completed' => (int) $checklistStats['completed'],
                ],
            ];

            unset($project->tasks_total_count);
            unset($project->survey_plans_count);
            unset($project->survey_submissions_count);
            unset($project->dpr_count);
            unset($project->attendance_count);
            unset($project->invoice_count);

            return $project;
        });

        $userCompanyId = $this->getAccessibleCompanyId($actor);

        $companies = Company::orderBy('name')->get(['id', 'name']);
        $clientsQuery = Client::orderBy('name');
        if ($userCompanyId) {
            $clientsQuery->where('company_id', $userCompanyId);
        }
        $clients = $clientsQuery->get([
            'id',
            'name',
            'company_id',
        ]);

        return Inertia::render('SuperAdmin/Construction/Projects/Index', [
            'projects' => $projects,
            'companies' => $companies,
            'clients' => $clients,
            'user_company_id' => $userCompanyId,
            'next_project_code' => Project::generateUniqueProjectCode(),
        ]);
    }

    public function nextProjectCode(): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'code' => Project::generateUniqueProjectCode(),
        ]);
    }

    public function show(Project $project, SurveyDataService $surveyData): Response
    {
        $actor = $this->constructionActor();
        $accessibleProjectIds = $this->getAccessibleProjectIds($actor);
        abort_unless($accessibleProjectIds->contains($project->id), 403, 'Unauthorized project access.');

        $project->load([
            'company',
            'client',
            'budgets' => fn ($query) => $query->latest('version_no'),
            'teamMembers.member',
            'teamMembers.role',
            'surveyPlans.planMembers.member',
            'surveyPlans.visits.checkedInBy',
            'surveyVisits.checkedInBy',
            'surveyPlans.visits.entries.capturedBy',
            'surveyPlans.visits.measurements.capturedBy',
            'surveyPlans.visits.submission.submittedBy',
            'surveyPlans.visits.submission.reviewedBy',
            'surveyPlans.checklists.completedBy',
            'surveySubmissions.submittedBy',
            'surveySubmissions.reviewedBy',
            'surveySubmissions.surveyVisit.checkedInBy',
            'draftingJobs.assignedTo',
            'draftingJobs.surveySubmission',
            'draftingJobs.drawingRevisions.uploadedBy',
            'draftingJobs.drawingRevisions.dwgDocument',
            'draftingJobs.drawingRevisions.pdfDocument',
            'draftingJobs.drawingRevisions.approvals.approvedBy',
            'drawingApprovals.drawingRevision',
            'executionTasks.supervisor',
            'executionTasks.assignees.member',
            'executionTasks.checklists.completedBy',
            'executionTasks.surveyVisits.checkedInBy',
            'executionTasks.surveyVisits.submission.submittedBy',
            'executionTasks.surveyVisits.submission.reviewedBy',
            'executionTasks.progressReports.submittedBy',
            'executionTasks.progressReports.reviewedBy',
            'tasks.checklistItems.completedBy',
            'tasks.surveyVisits.checkedInBy',
            'tasks.surveyVisits.submission.submittedBy',
            'tasks.surveyVisits.submission.reviewedBy',
            'tasks.assignments.assignee',
            'tasks.supervisor',
            'tasks.assignedMembers',
            'dailyProgressReports.submittedBy',
            'dailyProgressReports.reviewedBy',
            'dailyProgressReports.items',
            'attendanceRecords.member',
            'attendanceRecords.checkedInBy',
            'attendanceRecords.checkedOutBy',
            'materialStocks.material',
            'materialIssues.issuedBy',
            'materialIssues.material',
            'purchaseRequests.createdBy',
            'purchaseOrders.createdBy',
            'purchaseOrders.vendor',
            'materialReceipts.receivedBy',
            'materialReceipts.material',
            'clientInvoices',
            'clientPayments',
            'handovers.items',
            'workflowLogs.actionBy',
            'accountsProofDocument',
        ]);

        // Ensure core construction roles exist.
        $defaultRoles = [
            [
                'name' => 'Project Admin',
                'slug' => 'project_admin',
                'description' => 'Project scoped ERP access',
                'is_system_role' => true,
                'status' => 'active',
            ],
            [
                'name' => 'Surveyor',
                'slug' => 'surveyor',
                'description' => 'Field survey execution',
                'is_system_role' => true,
                'status' => 'active',
            ],
            [
                'name' => 'Draft Person',
                'slug' => 'draft_person',
                'description' => 'Drafting and revisions',
                'is_system_role' => true,
                'status' => 'active',
            ],
            [
                'name' => 'Vehicle Driver',
                'slug' => 'vehicle_driver',
                'description' => 'Vehicle transport and site movement',
                'is_system_role' => true,
                'status' => 'active',
            ],
            [
                'name' => 'Review Approver',
                'slug' => 'review_approver',
                'description' => 'Workflow approvals',
                'is_system_role' => true,
                'status' => 'active',
            ],
            [
                'name' => 'Site Employee',
                'slug' => 'site_employee',
                'description' => 'Construction execution updates and attendance',
                'is_system_role' => true,
                'status' => 'active',
            ],
        ];

        foreach ($defaultRoles as $rData) {
            ConstructionRole::firstOrCreate(
                ['slug' => $rData['slug']],
                $rData
            );
        }

        $members = $this->getProjectMembers($project);

        return Inertia::render(
            'SuperAdmin/Construction/Projects/Show',
            [
                'project' => $project,
                'members' => $members,
                'roles' => ConstructionRole::where('status', 'active')
                    ->where('slug', '!=', 'super_admin')
                    ->orderBy('name')
                    ->get([
                        'id',
                        'name',
                        'slug',
                    ]),
                'activityLog' => ConstructionActivityLog::with('actor')
                    ->where('project_id', $project->id)
                    ->latest('created_at')
                    ->take(15)
                    ->get(),
                'workflowSummary' => [
                    'total_survey_days' => $surveyData->getTotalSurveyDays(
                        $project->surveyPlans->first(),
                        $project
                    ),
                    'default_shift' => $surveyData->getShiftConfig(),
                    'default_checklist_items' => $surveyData->getDefaultChecklistItems(),
                    'site_address' => $surveyData->resolveSiteAddress(
                        $project->surveyPlans->first(),
                        $project
                    ),
                    'site_coordinates' => $surveyData->resolveSiteCoordinates(
                        $project->surveyPlans->first(),
                        $project
                    ),
                    'task_counts' => [
                        'total' => $project->executionTasks->count() + $project->tasks->count(),
                        'completed' => $project->executionTasks
                                ->where(fn ($t) => $t->status === 'completed')
                                ->count()
                            + $project->tasks
                                ->where(fn ($t) => $t->status === 'completed')
                                ->count(),
                        'in_progress' => $project->executionTasks
                                ->where(fn ($t) => $t->status === 'in_progress')
                                ->count()
                            + $project->tasks
                                ->where(fn ($t) => $t->status === 'in_progress')
                                ->count(),
                        'pending' => $project->executionTasks
                                ->where(fn ($t) => !in_array($t->status, ['completed', 'in_progress']))
                                ->count()
                            + $project->tasks
                                ->where(fn ($t) => !in_array($t->status, ['completed', 'in_progress']))
                                ->count(),
                    ],
                    'checklist_counts' => [
                        'total' => $project->executionTasks->flatMap(fn ($t) => $t->checklists ?? collect())->count()
                            + $project->surveyPlans->flatMap(fn ($s) => $s->checklists ?? collect())->count()
                            + $project->tasks->flatMap(fn ($t) => $t->checklistItems ?? collect())->count(),
                        'completed' => $project->executionTasks
                                ->flatMap(fn ($t) => $t->checklists ?? collect())
                                ->where('is_completed', true)
                                ->count()
                            + $project->surveyPlans
                                ->flatMap(fn ($s) => $s->checklists ?? collect())
                                ->where('is_completed', true)
                                ->count()
                            + $project->tasks
                                ->flatMap(fn ($t) => $t->checklistItems ?? collect())
                                ->where('is_completed', true)
                                ->count(),
                    ],
                ],
            ]
        );
    }

    public function store(
        Request $request,
        ConstructionActivityService $activityService
    ) {
        $actor = $this->constructionActor();
        $userCompanyId = $this->getAccessibleCompanyId($actor);

        if ($userCompanyId) {
            $request->merge(['company_id' => $userCompanyId]);
        }

        $validated = $request->validate([
            'company_id' => [
                'required',
                'exists:construction_companies,id',
            ],
            'client_id' => [
                'required',
                'exists:construction_clients,id',
            ],
            'project_code' => [
                'nullable',
                'string',
                'max:32',
                Rule::unique('construction_projects', 'project_code')->whereNull('deleted_at'),
            ],
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'category' => [
                'nullable',
                'string',
                'max:100',
            ],
            'description' => [
                'nullable',
                'string',
            ],
            'project_address' => [
                'nullable',
                'string',
            ],
            'location_name' => [
                'nullable',
                'string',
                'max:500',
            ],
            'latitude' => [
                'nullable',
                'numeric',
                'between:-90,90',
            ],
            'longitude' => [
                'nullable',
                'numeric',
                'between:-180,180',
            ],
            'start_date' => [
                'nullable',
                'date',
            ],
            'expected_end_date' => [
                'nullable',
                'date',
                'after_or_equal:start_date',
            ],
            'priority' => [
                'required',
                'in:low,medium,high,critical',
            ],
            'status' => [
                'nullable',
                'string',
                'max:32',
            ],
            'current_stage' => [
                'nullable',
                'string',
                'max:32',
            ],
            'client_review_status' => [
                'nullable',
                'in:pending,requested,approved,revision_requested,rejected',
            ],
            'client_revision_comment' => [
                'nullable',
                'string',
            ],
        ]);

        $projectCode = Project::generateUniqueProjectCode($validated['project_code'] ?? null);
        $projectSlug = Project::generateUniqueSlug($validated['name'] ?? 'project');

        $clientReviewStatus = !empty($validated['client_review_status']) ? $validated['client_review_status'] : 'pending';
        $status = !empty($validated['status']) ? $validated['status'] : 'draft';
        $currentStage = !empty($validated['current_stage']) ? $validated['current_stage'] : 'budget_pending';
        $priority = !empty($validated['priority']) ? $validated['priority'] : 'medium';

        $insertData = array_merge($validated, [
            'project_code' => $projectCode,
            'slug' => $projectSlug,
            'status' => $status,
            'current_stage' => $currentStage,
            'client_review_status' => $clientReviewStatus,
            'priority' => $priority,
            'created_by_type' => $actor ? $actor::class : null,
            'created_by_id' => $actor?->getKey(),
        ]);

        try {
            $project = Project::create($insertData);
        } catch (\Illuminate\Database\UniqueConstraintViolationException | \Illuminate\Database\QueryException $e) {
            $insertData['project_code'] = Project::generateUniqueProjectCode();
            $insertData['slug'] = Project::generateUniqueSlug($validated['name'] ?? 'project');
            $project = Project::create($insertData);
        }

        $activityService->log(
            module: 'project',
            action: 'created',
            actor: $actor,
            reference: $project,
            companyId: $project->company_id,
            projectId: $project->id,
            meta: [
                'project_code' => $project->project_code,
            ],
            request: $request
        );

        return redirect()
            ->route(
                'super.construction.projects.show',
                $project
            )
            ->with(
                'success',
                'Project created successfully.'
            );
    }

    public function update(
        Project $project,
        Request $request,
        ConstructionActivityService $activityService
    ) {
        $actor = $this->constructionActor();
        $userCompanyId = $this->getAccessibleCompanyId($actor);

        if ($userCompanyId) {
            $request->merge(['company_id' => $userCompanyId]);
        }

        $validated = $request->validate([
            'company_id' => [
                'required',
                'exists:construction_companies,id',
            ],
            'client_id' => [
                'required',
                'exists:construction_clients,id',
            ],
            'project_code' => [
                'nullable',
                'string',
                'max:32',
                Rule::unique('construction_projects', 'project_code')->ignore($project->id),
            ],
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'category' => [
                'nullable',
                'string',
                'max:100',
            ],
            'description' => [
                'nullable',
                'string',
            ],
            'project_address' => [
                'nullable',
                'string',
            ],
            'location_name' => [
                'nullable',
                'string',
                'max:500',
            ],
            'latitude' => [
                'nullable',
                'numeric',
                'between:-90,90',
            ],
            'longitude' => [
                'nullable',
                'numeric',
                'between:-180,180',
            ],
            'start_date' => [
                'nullable',
                'date',
            ],
            'expected_end_date' => [
                'nullable',
                'date',
                'after_or_equal:start_date',
            ],
            'priority' => [
                'required',
                'in:low,medium,high,critical',
            ],
            'status' => [
                'nullable',
                'string',
                'max:32',
            ],
            'current_stage' => [
                'nullable',
                'string',
                'max:32',
            ],
            'client_review_status' => [
                'nullable',
                'in:pending,requested,approved,revision_requested,rejected',
            ],
            'client_revision_comment' => [
                'nullable',
                'string',
            ],
        ]);

        if (array_key_exists('client_review_status', $validated) && empty($validated['client_review_status'])) {
            unset($validated['client_review_status']);
        }

        $project->update(array_filter($validated, static fn ($value) => $value !== null));

        $activityService->log(
            module: 'project',
            action: 'updated',
            actor: $actor,
            reference: $project,
            companyId: $project->company_id,
            projectId: $project->id,
            meta: [
                'project_code' => $project->project_code,
            ],
            request: $request
        );

        return back()->with(
            'success',
            'Project updated successfully.'
        );
    }

    public function storeBudget(
        Project $project,
        Request $request,
        ConstructionActivityService $activityService
    ) {
        $actor = $this->constructionActor();

        $validated = $request->validate([
            'estimated_amount' => [
                'required',
                'numeric',
                'min:0',
            ],
            'approved_amount' => [
                'nullable',
                'numeric',
                'min:0',
            ],
            'currency' => [
                'required',
                'string',
                'size:3',
            ],
            'notes' => [
                'nullable',
                'string',
            ],
            'status' => [
                'required',
                'in:pending,approved,rejected',
            ],
        ]);

        $budget = ProjectBudget::create([
            ...$validated,
            'project_id' => $project->id,
            'version_no' =>
                (int) $project->budgets()->max('version_no') + 1,
            'submitted_by_type' => $actor
                ? $actor::class
                : null,
            'submitted_by_id' => $actor?->getKey(),
            'approved_by_type' =>
                $validated['status'] === 'approved'
                && $actor
                    ? $actor::class
                    : null,
            'approved_by_id' =>
                $validated['status'] === 'approved'
                    ? $actor?->getKey()
                    : null,
            'approved_at' =>
                $validated['status'] === 'approved'
                    ? now()
                    : null,
        ]);

        $project->update([
            'status' =>
                $validated['status'] === 'approved'
                    ? 'active'
                    : $project->status,
            'current_stage' =>
                $validated['status'] === 'approved'
                    ? 'budget_approved'
                    : 'budget_pending',
        ]);

        $activityService->log(
            module: 'project_budget',
            action: $validated['status'],
            actor: $actor,
            reference: $budget,
            companyId: $project->company_id,
            projectId: $project->id,
            meta: [
                'version_no' => $budget->version_no,
            ],
            request: $request
        );

        return back()->with(
            'success',
            'Project budget saved successfully.'
        );
    }

    public function assignTeam(
        Project $project,
        Request $request,
        ConstructionActivityService $activityService,
        ConstructionTeamAssignmentService $teamAssignmentService
    ) {
        $actor = $this->constructionActor();

        $validated = $request->validate([
            'member_id' => [
                'required',
                'integer',
                'exists:members,id',
            ],
            'role_id' => [
                'nullable',
                'integer',
                'exists:construction_roles,id',
            ],
            'assigned_from' => [
                'nullable',
                'date',
            ],
            'assigned_to' => [
                'nullable',
                'date',
                'after_or_equal:assigned_from',
            ],
            'assignment_scope' => [
                'nullable',
                'string',
                'max:500',
            ],
            'is_primary' => [
                'boolean',
            ],
            'status' => [
                'nullable',
                Rule::in(['active', 'inactive']),
            ],
        ], [
            'member_id.required' =>
                'Please select a team member.',
            'member_id.exists' =>
                'The selected member does not exist.',
            'role_id.exists' =>
                'The selected role does not exist.',
            'assigned_to.after_or_equal' =>
                'The assignment end date must be after or equal to the start date.',
            'status.in' =>
                'The status must be either active or inactive.',
        ]);

        $teamMember = $teamAssignmentService->assign(
            $project,
            $validated,
            $actor
        );

        if ($project->current_stage === 'budget_approved') {
            $project->update([
                'current_stage' => 'team_assigned',
            ]);
        }

        $activityService->log(
            module: 'project_team',
            action: 'assigned',
            actor: $actor,
            reference: $teamMember,
            companyId: $project->company_id,
            projectId: $project->id,
            meta: [
                'member_id' => $teamMember->member_id,
                'role_id' => $teamMember->role_id,
            ],
            request: $request
        );

        return back()->with(
            'success',
            'Project team member assigned successfully.'
        );
    }

    public function updateTeamMember(
        Project $project,
        ProjectTeamMember $teamMember,
        Request $request,
        ConstructionActivityService $activityService,
        ConstructionTeamAssignmentService $teamAssignmentService
    ) {
        if (
            (int) $teamMember->project_id
            !== (int) $project->id
        ) {
            return back()->with(
                'error',
                'The selected team member assignment does not belong to this project.'
            );
        }

        $actor = $this->constructionActor();

        $validated = $request->validate([
            'member_id' => [
                'required',
                'integer',
                'exists:members,id',
            ],
            'role_id' => [
                'nullable',
                'integer',
                'exists:construction_roles,id',
            ],
            'assigned_from' => [
                'nullable',
                'date',
            ],
            'assigned_to' => [
                'nullable',
                'date',
                'after_or_equal:assigned_from',
            ],
            'assignment_scope' => [
                'nullable',
                'string',
                'max:500',
            ],
            'is_primary' => [
                'boolean',
            ],
            'status' => [
                'nullable',
                Rule::in(['active', 'inactive']),
            ],
        ], [
            'member_id.required' =>
                'Please select a team member.',
            'member_id.exists' =>
                'The selected member does not exist.',
            'role_id.exists' =>
                'The selected role does not exist.',
            'assigned_to.after_or_equal' =>
                'The assignment end date must be after or equal to the start date.',
            'status.in' =>
                'The status must be either active or inactive.',
        ]);

        $teamMember = $teamAssignmentService->update(
            $project,
            $teamMember,
            $validated,
            $actor
        );

        $activityService->log(
            module: 'project_team',
            action: 'updated',
            actor: $actor,
            reference: $teamMember,
            companyId: $project->company_id,
            projectId: $project->id,
            meta: [
                'member_id' => $teamMember->member_id,
                'role_id' => $teamMember->role_id,
            ],
            request: $request
        );

        return back()->with(
            'success',
            'Team member assignment updated successfully.'
        );
    }

    public function toggleTeamMemberStatus(
        Project $project,
        ProjectTeamMember $teamMember,
        ConstructionActivityService $activityService,
        ConstructionTeamAssignmentService $teamAssignmentService
    ) {
        if (
            (int) $teamMember->project_id
            !== (int) $project->id
        ) {
            return back()->with(
                'error',
                'The selected team member assignment does not belong to this project.'
            );
        }

        $actor = $this->constructionActor();

        $teamMember = $teamAssignmentService->toggleStatus(
            $project,
            $teamMember,
            $actor
        );

        $newStatus = $teamMember->status;

        $activityService->log(
    module: 'project_team',
    action: $newStatus === 'active'
        ? 'activated'
        : 'deactivated',
    actor: $actor,
    reference: $teamMember,
    companyId: $project->company_id,
    projectId: $project->id,
    meta: [
        'member_id' => $teamMember->member_id,
        'role_id' => $teamMember->role_id,
        'status' => $newStatus,
    ],
    request: request()
);

        return back()->with(
            'success',
            "Team member {$newStatus} successfully."
        );
    }

    public function destroyTeamMember(
        Project $project,
        ProjectTeamMember $teamMember,
        ConstructionActivityService $activityService,
        ConstructionTeamAssignmentService $teamAssignmentService
    ) {
        if (
            (int) $teamMember->project_id
            !== (int) $project->id
        ) {
            return back()->with(
                'error',
                'The selected team member assignment does not belong to this project.'
            );
        }

        $actor = $this->constructionActor();

        $memberName = $teamMember->member?->name ?? 'Unknown';
        $memberId = $teamMember->member_id;
        $roleId = $teamMember->role_id;

        $teamAssignmentService->remove(
            $project,
            $teamMember,
            $actor
        );

        $activityService->log(
            module: 'project_team',
            action: 'removed',
            actor: $actor,
            reference: null,
            companyId: $project->company_id,
            projectId: $project->id,
            meta: [
                'member_id' => $memberId,
                'role_id' => $roleId,
                'member_name' => $memberName,
            ],
            request: request()
        );

        return back()->with(
            'success',
            "Team member '{$memberName}' removed from project successfully."
        );
    }

    public function showTeamMember(
        Project $project,
        ProjectTeamMember $teamMember
    ): Response {
        $teamMember->load([
            'member',
            'role',
        ]);

        $memberId = $teamMember->member_id;

        // Load actual project work/submissions by this team member for this project.

        // 1. Survey submissions submitted by this member.
        $surveySubmissions = SurveySubmission::with([
            'surveyVisit.checkedInBy',
            'submittedBy',
            'reviewedBy',
        ])
            ->where('project_id', $project->id)
            ->where('submitted_by_member_id', $memberId)
            ->latest('submitted_at')
            ->get();

        // 2. Survey visits where this member checked in.
        $surveyVisits = SurveyVisit::with([
            'checkedInBy',
            'surveyPlan',
            'entries.capturedBy',
            'measurements.capturedBy',
            'submission.submittedBy',
            'submission.reviewedBy',
        ])
            ->where('project_id', $project->id)
            ->where('checked_in_by_member_id', $memberId)
            ->latest('check_in_at')
            ->get();

        // 3. Survey plans where this member is assigned.
        $surveyPlans = SurveyPlan::with([
            'planMembers.member',
        ])
            ->where('project_id', $project->id)
            ->whereHas(
                'planMembers',
                function ($query) use ($memberId) {
                    $query->where(
                        'member_id',
                        $memberId
                    );
                }
            )
            ->latest('planned_date')
            ->get();

        // 4. Execution tasks supervised by this member.
        $supervisedTasks = ExecutionTask::with([
            'supervisor',
            'assignees.member',
            'progressReports.submittedBy',
            'checklists',
        ])
            ->where('project_id', $project->id)
            ->where(
                'supervisor_member_id',
                $memberId
            )
            ->latest('created_at')
            ->get();

        // 5. Execution tasks where this member is assigned as assignee.
        $assignedTasks = ExecutionTask::with([
            'supervisor',
            'assignees.member',
            'progressReports.submittedBy',
            'checklists',
        ])
            ->where('project_id', $project->id)
            ->whereHas(
                'assignees',
                function ($query) use ($memberId) {
                    $query->where(
                        'member_id',
                        $memberId
                    );
                }
            )
            ->latest('created_at')
            ->get();

        // 6. Daily progress reports submitted by this member.
        $progressReports = DailyProgressReport::with([
            'task',
            'submittedBy',
            'reviewedBy',
        ])
            ->where('project_id', $project->id)
            ->where(
                'submitted_by_member_id',
                $memberId
            )
            ->latest('report_date')
            ->get();

        // 7. Attendance records for this member.
        $attendanceRecords = AttendanceRecord::with([
            'checkedInBy',
            'checkedOutBy',
        ])
            ->where(
                'project_id',
                $project->id
            )
            ->where(
                'member_id',
                $memberId
            )
            ->latest('attendance_date')
            ->get();

        // 8. Activity logs for this member in this project.
        $activityLog = ConstructionActivityLog::with('actor')
            ->where('project_id', $project->id)
            ->where(
                function ($query) use ($memberId, $teamMember) {
                    $query->where(
                        'meta->member_id',
                        $memberId
                    )->orWhere(
                        'reference_id',
                        $teamMember->id
                    );
                }
            )
            ->latest('created_at')
            ->take(20)
            ->get();

        return Inertia::render(
            'SuperAdmin/Construction/Projects/TeamMemberShow',
            [
                'project' => $project,
                'teamMember' => $teamMember,
                'surveySubmissions' => $surveySubmissions,
                'surveyVisits' => $surveyVisits,
                'surveyPlans' => $surveyPlans,
                'supervisedTasks' => $supervisedTasks,
                'assignedTasks' => $assignedTasks,
                'progressReports' => $progressReports,
                'attendanceRecords' => $attendanceRecords,
                'activityLog' => $activityLog,
            ]
        );
    }

    public function destroy(
        Project $project,
        Request $request,
        ConstructionActivityService $activityService
    ) {
        $actor = $this->constructionActor();

        if ($this->requiresSuperAdminApproval('project', $project->id)) {
            return $this->requestDeleteApproval('project', $project->id, $project->name);
        }

        try {
            $projectId = $project->id;
            $companyId = $project->company_id;
            $projectCode = $project->project_code;

            \DB::transaction(function () use ($project) {
                $projectId = $project->id;

                \App\Models\ConstructionActivityLog::where(
                    'project_id',
                    $projectId
                )->delete();

                MemberRoleAssignment::where(
                    'project_id',
                    $projectId
                )->delete();

                \App\Models\ProjectTeamMember::where(
                    'project_id',
                    $projectId
                )->delete();

                \App\Models\ProjectBudget::where(
                    'project_id',
                    $projectId
                )->delete();

                VehicleAssignment::where(
                    'project_id',
                    $projectId
                )->delete();

                VehicleLocationPing::where(
                    'project_id',
                    $projectId
                )->delete();

                \App\Models\EquipmentAllocation::where(
                    'project_id',
                    $projectId
                )->delete();

                \App\Models\EquipmentUsageLog::where(
                    'project_id',
                    $projectId
                )->delete();

                \App\Models\ProjectHandoverItem::whereIn(
                    'handover_id',
                    \App\Models\ProjectHandover::where(
                        'project_id',
                        $projectId
                    )->select('id')
                )->delete();

                \App\Models\ProjectHandover::where(
                    'project_id',
                    $projectId
                )->delete();

                \App\Models\ClientPayment::where(
                    'project_id',
                    $projectId
                )->delete();

                \App\Models\ClientInvoice::where(
                    'project_id',
                    $projectId
                )->delete();

                \App\Models\MaterialIssue::where(
                    'project_id',
                    $projectId
                )->delete();

                \App\Models\MaterialReceipt::where(
                    'project_id',
                    $projectId
                )->delete();

                \App\Models\MaterialStock::where(
                    'project_id',
                    $projectId
                )->delete();

                \App\Models\PurchaseOrder::where(
                    'project_id',
                    $projectId
                )->delete();

                \App\Models\PurchaseRequest::where(
                    'project_id',
                    $projectId
                )->delete();

                \App\Models\DrawingApproval::where(
                    'project_id',
                    $projectId
                )->delete();

                \App\Models\DrawingRevision::where(
                    'project_id',
                    $projectId
                )->delete();

                \App\Models\DraftingJob::where(
                    'project_id',
                    $projectId
                )->delete();

                \App\Models\ConstructionDocument::where(
                    'project_id',
                    $projectId
                )->delete();

                \App\Models\AttendanceRecord::where(
                    'project_id',
                    $projectId
                )->delete();

                \App\Models\DailyProgressItem::whereIn(
                    'daily_progress_report_id',
                    \App\Models\DailyProgressReport::where(
                        'project_id',
                        $projectId
                    )->select('id')
                )->delete();

                \App\Models\DailyProgressReport::where(
                    'project_id',
                    $projectId
                )->delete();

                \App\Models\ExecutionTaskAssignee::where(
                    'project_id',
                    $projectId
                )->delete();

                \App\Models\ExecutionTask::where(
                    'project_id',
                    $projectId
                )->delete();

                \App\Models\ExecutionPlan::where(
                    'project_id',
                    $projectId
                )->delete();

                \App\Models\SurveyMeasurement::where(
                    'project_id',
                    $projectId
                )->delete();

                \App\Models\SurveyEntry::where(
                    'project_id',
                    $projectId
                )->delete();

                \App\Models\SurveySubmission::where(
                    'project_id',
                    $projectId
                )->delete();

                \App\Models\SurveyVisit::where(
                    'project_id',
                    $projectId
                )->delete();

                \App\Models\SurveyPlan::where(
                    'project_id',
                    $projectId
                )->delete();

                \App\Models\ConstructionVehicle::where(
                    'project_id',
                    $projectId
                )->update([
                    'project_id' => null,
                ]);

                \App\Models\ConstructionEquipment::where(
                    'project_id',
                    $projectId
                )->update([
                    'project_id' => null,
                ]);

                \App\Models\Material::where(
                    'project_id',
                    $projectId
                )->update([
                    'project_id' => null,
                ]);

                \App\Models\Vendor::where(
                    'project_id',
                    $projectId
                )->update([
                    'project_id' => null,
                ]);

                $project->delete();
            });

            $activityService->log(
                module: 'project',
                action: 'deleted',
                actor: $actor,
                reference: null,
                companyId: $companyId,
                projectId: null,
                meta: [
                    'project_code' => $projectCode,
                    'project_id' => $projectId,
                ],
                request: $request
            );

            $fallback = route(
                'super.construction.projects.index'
            );

            $intended = redirect()->getIntendedUrl();

            $target = $intended
                && $intended !== route(
                    'super.construction.projects.show',
                    $projectId ?? 0
                )
                ? $intended
                : $fallback;

            return redirect()
                ->to($target)
                ->with(
                    'success',
                    'Project and all related data deleted successfully.'
                );
        } catch (\Throwable $e) {
            report($e);

            return back()->with(
                'error',
                'Failed to delete project. ' . $e->getMessage()
            );
        }
    }

    private function resolveUnifiedTask(Project $project, ExecutionTask $et): ?Task
    {
        $taskCode = (string) ($et->task_code ?? sprintf('ET-%s-%06d', (int) $project->id, (int) $et->id));
        return Task::query()
            ->where('project_id', $project->id)
            ->where(fn ($q) => $q->where('task_code', $taskCode)
                ->orWhere('task_code', sprintf('ET-%s-%06d', (int) $project->id, (int) $et->id)))
            ->first();
    }

    private function resolveUnifiedParentTaskId(Project $project, mixed $legacyParentId): ?int
    {
        if (empty($legacyParentId)) {
            return null;
        }
        $legacyId = (int) $legacyParentId;
        $alreadyUnified = Task::query()->find($legacyId);
        if ($alreadyUnified && (int) $alreadyUnified->project_id === (int) $project->id) {
            return $legacyId;
        }
        $legacyParent = ExecutionTask::query()->find($legacyId);
        if (!$legacyParent || (int) $legacyParent->project_id !== (int) $project->id) {
            return null;
        }
        $unified = $this->resolveUnifiedTask($project, $legacyParent);
        return $unified?->id;
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

    public function storeTask(
        Project $project,
        Request $request,
        TaskManagementService $tasks
    ) {
        $actor = $this->constructionActor();

        try {
            $validated = $request->validate([
                'execution_plan_id' => ['nullable', 'integer', 'exists:construction_execution_plans,id'],
                'parent_task_id' => ['nullable', 'integer'],
                'title' => ['required', 'string', 'max:500'],
                'description' => ['nullable', 'string', 'max:10000'],
                'planned_start_date' => ['nullable', 'date'],
                'planned_end_date' => ['nullable', 'date', 'after_or_equal:planned_start_date'],
                'actual_start_date' => ['nullable', 'date'],
                'actual_end_date' => ['nullable', 'date', 'after_or_equal:actual_start_date'],
                'priority' => ['nullable', 'in:low,medium,high,critical'],
                'planned_quantity' => ['nullable', 'numeric', 'min:0'],
                'unit' => ['nullable', 'string', 'max:50'],
                'requires_daily_update' => ['nullable', 'boolean'],
                'requires_gps_verification' => ['nullable', 'boolean'],
                'supervisor_member_id' => ['nullable', 'integer', 'exists:members,id'],
                'status' => ['nullable', 'string', 'max:50'],
            ]);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        $payload = [
            'execution_plan_id' => $validated['execution_plan_id'] ?? null,
            'parent_task_id' => $this->resolveUnifiedParentTaskId($project, $validated['parent_task_id'] ?? null),
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'start_date' => $validated['actual_start_date'] ?? $validated['planned_start_date'] ?? null,
            'end_date' => $validated['actual_end_date'] ?? $validated['planned_end_date'] ?? null,
            'priority' => $validated['priority'] ?? 'medium',
            'planned_qty' => $validated['planned_quantity'] ?? null,
            'qty_unit' => $validated['unit'] ?? null,
            'requires_gps_verification' => (bool) ($validated['requires_gps_verification'] ?? false),
            'assigned_supervisor_member_id' => $validated['supervisor_member_id'] ?? null,
            'status' => isset($validated['status']) ? $this->mapLegacyStatus($validated['status']) : 'pending',
            'task_source' => 'admin_created',
            'checklist_items' => [],
            'assignments' => [],
        ];

        try {
            $task = $tasks->create($project, $payload, $actor, $request);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        } catch (\Throwable $e) {
            report($e);
            return back()->with('error', 'Failed to create task. ' . $e->getMessage())->withInput();
        }

        return back()->with('success', "Task {$task->task_code} created successfully.");
    }

    public function updateTask(
        Project $project,
        ExecutionTask $task,
        Request $request,
        TaskManagementService $tasks
    ) {
        if ((int) $task->project_id !== (int) $project->id) {
            return back()->with('error', 'The selected task does not belong to this project.');
        }

        $actor = $this->constructionActor();
        $unified = $this->resolveUnifiedTask($project, $task);
        if (!$unified) {
            return back()->with('error', 'Backfilled task record not found. Re-run migrations or create a new task.');
        }

        try {
            $validated = $request->validate([
                'execution_plan_id' => ['nullable', 'integer', 'exists:construction_execution_plans,id'],
                'parent_task_id' => ['nullable', 'integer'],
                'title' => ['required', 'string', 'max:500'],
                'description' => ['nullable', 'string', 'max:10000'],
                'planned_start_date' => ['nullable', 'date'],
                'planned_end_date' => ['nullable', 'date', 'after_or_equal:planned_start_date'],
                'actual_start_date' => ['nullable', 'date'],
                'actual_end_date' => ['nullable', 'date', 'after_or_equal:actual_start_date'],
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
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        $patch = [
            'execution_plan_id' => $validated['execution_plan_id'] ?? null,
            'parent_task_id' => $this->resolveUnifiedParentTaskId($project, $validated['parent_task_id'] ?? null),
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'start_date' => $validated['actual_start_date'] ?? $validated['planned_start_date'] ?? $unified->start_date ?? null,
            'end_date' => $validated['actual_end_date'] ?? $validated['planned_end_date'] ?? $unified->end_date ?? null,
            'priority' => $validated['priority'] ?? 'medium',
            'planned_qty' => $validated['planned_quantity'] ?? null,
            'completed_qty' => $validated['completed_quantity'] ?? null,
            'qty_unit' => $validated['unit'] ?? null,
            'progress_percent' => isset($validated['progress_percent']) ? max(0, min(100, (int) round((float) $validated['progress_percent']))) : null,
            'requires_gps_verification' => isset($validated['requires_gps_verification']) ? (bool) $validated['requires_gps_verification'] : null,
            'assigned_supervisor_member_id' => $validated['supervisor_member_id'] ?? null,
            'status' => isset($validated['status']) ? $this->mapLegacyStatus($validated['status']) : null,
        ];
        $patch = array_filter($patch, static fn ($v) => $v !== null, ARRAY_FILTER_USE_BOTH);

        try {
            $updated = $tasks->update($unified, $patch, $actor, $request);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        } catch (\Throwable $e) {
            report($e);
            return back()->with('error', 'Failed to update task. ' . $e->getMessage())->withInput();
        }

        return back()->with('success', "Task {$updated->task_code} updated successfully.");
    }

    public function destroyTask(
        Project $project,
        ExecutionTask $task,
        TaskManagementService $tasks
    ) {
        if ((int) $task->project_id !== (int) $project->id) {
            return back()->with('error', 'The selected task does not belong to this project.');
        }

        $actor = $this->constructionActor();
        $unified = $this->resolveUnifiedTask($project, $task);
        $code = $unified?->task_code ?? ($task->task_code ?? (string) $task->id);

        if (!$unified) {
            try {
                \DB::transaction(function () use ($task) {
                    \App\Models\TaskChecklist::where('execution_task_id', $task->id)->delete();
                    \App\Models\ExecutionTaskAssignee::where('execution_task_id', $task->id)->delete();
                    \App\Models\DailyProgressReport::where('execution_task_id', $task->id)->update(['execution_task_id' => null]);
                    \App\Models\ExecutionTask::where('parent_task_id', $task->id)->update(['parent_task_id' => null]);
                    $task->delete();
                });
            } catch (\Throwable $e) {
                report($e);
                return back()->with('error', 'Failed to delete task. ' . $e->getMessage());
            }
            return back()->with('success', "Task {$code} deleted successfully.");
        }

        try {
            $tasks->deleteTask($unified, $actor, $request);
        } catch (\Throwable $e) {
            report($e);
            return back()->with('error', 'Failed to delete task. ' . $e->getMessage());
        }

        return back()->with('success', "Task {$code} deleted successfully.");
    }

    public function storeChecklistItem(
        Project $project,
        Request $request,
        ConstructionActivityService $activityService,
        SurveyDataService $surveyData
    ) {
        $actor = $this->constructionActor();

        $validated = $request->validate([
            'scope' => ['required', 'in:execution_task,survey_plan,project,unified_task'],
            'execution_task_id' => [
                'nullable',
                'integer',
                'exists:construction_execution_tasks,id',
            ],
            'survey_plan_id' => [
                'nullable',
                'integer',
                'exists:construction_survey_plans,id',
            ],
            'unified_task_id' => [
                'nullable',
                'integer',
                'exists:tasks,id',
            ],
            'day_number' => ['required', 'integer', 'min:1', 'max:366'],
            'item_title' => ['required', 'string', 'max:255'],
            'assign_hours' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'in:pending,in_progress,completed'],
            'is_completed' => ['nullable', 'boolean'],
            'image_1' => ['nullable', 'image', 'max:10240'],
            'image_2' => ['nullable', 'image', 'max:10240'],
        ]);

        if (
            $validated['scope'] === 'execution_task'
            && (empty($validated['execution_task_id']) || \App\Models\ExecutionTask::where('id', $validated['execution_task_id'])->value('project_id') !== $project->id)
        ) {
            return back()->with('error', 'The selected execution task does not belong to this project.');
        }

        if (
            $validated['scope'] === 'survey_plan'
            && (empty($validated['survey_plan_id']) || \App\Models\SurveyPlan::where('id', $validated['survey_plan_id'])->value('project_id') !== $project->id)
        ) {
            return back()->with('error', 'The selected survey plan does not belong to this project.');
        }

        if (
            $validated['scope'] === 'unified_task'
            && (empty($validated['unified_task_id']) || \App\Models\Task::where('id', $validated['unified_task_id'])->value('project_id') !== $project->id)
        ) {
            return back()->with('error', 'The selected task does not belong to this project.');
        }

        try {
            $isDone = !empty($validated['is_completed']) || ($validated['status'] ?? '') === 'completed';
            $statusVal = $validated['status'] ?? ($isDone ? 'completed' : 'pending');
            $completedAt = $isDone ? now() : null;
            $completedBy = $isDone ? $actor?->getKey() : null;

            $imageUrl1 = null;
            if ($request->hasFile('image_1')) {
                $path = $request->file('image_1')->store('checklists', 'public');
                $imageUrl1 = '/storage/' . $path;
            }

            $imageUrl2 = null;
            if ($request->hasFile('image_2')) {
                $path = $request->file('image_2')->store('checklists', 'public');
                $imageUrl2 = '/storage/' . $path;
            }

            $checklist = null;

            if ($validated['scope'] === 'unified_task') {
                $unifiedTask = \App\Models\Task::where('project_id', $project->id)->findOrFail($validated['unified_task_id']);

                $checklist = \App\Models\TaskChecklistItem::create([
                    'task_id' => $unifiedTask->id,
                    'project_id' => $project->id,
                    'day_number' => $validated['day_number'],
                    'item_title' => $validated['item_title'],
                    'assign_hours' => $validated['assign_hours'] ?? null,
                    'notes' => $validated['notes'] ?? null,
                    'status' => $statusVal,
                    'image_url_1' => $imageUrl1,
                    'image_url_2' => $imageUrl2,
                    'is_completed' => $isDone,
                    'completed_by_member_id' => $completedBy,
                    'completed_at' => $completedAt,
                    'source' => 'admin_custom',
                ]);

                // Mirror to legacy TaskChecklist only if a matching ExecutionTask actually exists
                $execTask = \App\Models\ExecutionTask::where('project_id', $project->id)
                    ->where(function ($q) use ($unifiedTask) {
                        if (!empty($unifiedTask->task_code)) {
                            $q->where('task_code', $unifiedTask->task_code);
                        } else {
                            $q->where('title', $unifiedTask->title);
                        }
                    })
                    ->first();

                if ($execTask) {
                    try {
                        \App\Models\TaskChecklist::create([
                            'execution_task_id' => $execTask->id,
                            'survey_plan_id' => null,
                            'day_number' => $validated['day_number'],
                            'item_title' => $validated['item_title'],
                            'assign_hours' => $validated['assign_hours'] ?? null,
                            'notes' => $validated['notes'] ?? null,
                            'status' => $statusVal,
                            'image_url_1' => $imageUrl1,
                            'image_url_2' => $imageUrl2,
                            'is_completed' => $isDone,
                            'completed_by_member_id' => $completedBy,
                            'completed_at' => $completedAt,
                        ]);
                    } catch (\Throwable $e) {
                        report($e);
                    }
                }
            } elseif ($validated['scope'] === 'execution_task') {
                $execTask = \App\Models\ExecutionTask::where('project_id', $project->id)->findOrFail($validated['execution_task_id']);

                $checklist = \App\Models\TaskChecklist::create([
                    'execution_task_id' => $execTask->id,
                    'survey_plan_id' => null,
                    'day_number' => $validated['day_number'],
                    'item_title' => $validated['item_title'],
                    'assign_hours' => $validated['assign_hours'] ?? null,
                    'notes' => $validated['notes'] ?? null,
                    'status' => $statusVal,
                    'image_url_1' => $imageUrl1,
                    'image_url_2' => $imageUrl2,
                    'is_completed' => $isDone,
                    'completed_by_member_id' => $completedBy,
                    'completed_at' => $completedAt,
                ]);

                // Mirror to matching unified Task if exists
                $unifiedTask = !empty($execTask->task_code)
                    ? \App\Models\Task::where('project_id', $project->id)->where('task_code', $execTask->task_code)->first()
                    : \App\Models\Task::where('project_id', $project->id)->where('title', $execTask->title)->first();

                if ($unifiedTask) {
                    try {
                        \App\Models\TaskChecklistItem::updateOrCreate(
                            [
                                'task_id' => $unifiedTask->id,
                                'project_id' => $project->id,
                                'item_title' => $validated['item_title'],
                                'day_number' => $validated['day_number'],
                            ],
                            [
                                'assign_hours' => $validated['assign_hours'] ?? null,
                                'notes' => $validated['notes'] ?? null,
                                'status' => $statusVal,
                                'image_url_1' => $imageUrl1,
                                'image_url_2' => $imageUrl2,
                                'is_completed' => $isDone,
                                'completed_by_member_id' => $completedBy,
                                'completed_at' => $completedAt,
                                'source' => 'admin_custom',
                            ]
                        );
                    } catch (\Throwable $e) {
                        report($e);
                    }
                }
            } elseif ($validated['scope'] === 'survey_plan') {
                $plan = \App\Models\SurveyPlan::where('project_id', $project->id)->findOrFail($validated['survey_plan_id']);

                $checklist = \App\Models\TaskChecklist::create([
                    'execution_task_id' => null,
                    'survey_plan_id' => $plan->id,
                    'day_number' => $validated['day_number'],
                    'item_title' => $validated['item_title'],
                    'assign_hours' => $validated['assign_hours'] ?? null,
                    'notes' => $validated['notes'] ?? null,
                    'status' => $statusVal,
                    'image_url_1' => $imageUrl1,
                    'image_url_2' => $imageUrl2,
                    'is_completed' => $isDone,
                    'completed_by_member_id' => $completedBy,
                    'completed_at' => $completedAt,
                ]);
            } elseif ($validated['scope'] === 'project') {
                $firstPlan = $project->surveyPlans->first();
                $firstExec = $project->executionTasks->first();

                $checklist = \App\Models\TaskChecklist::create([
                    'execution_task_id' => $firstExec?->id,
                    'survey_plan_id' => $firstPlan?->id,
                    'day_number' => $validated['day_number'],
                    'item_title' => $validated['item_title'],
                    'assign_hours' => $validated['assign_hours'] ?? null,
                    'notes' => $validated['notes'] ?? null,
                    'status' => $statusVal,
                    'image_url_1' => $imageUrl1,
                    'image_url_2' => $imageUrl2,
                    'is_completed' => $isDone,
                    'completed_by_member_id' => $completedBy,
                    'completed_at' => $completedAt,
                ]);

                if ($firstExec) {
                    try {
                        $surveyData->ensureDefaultTaskChecklists($firstExec, (int) $validated['day_number']);
                    } catch (\Throwable $e) {
                        report($e);
                    }
                }
            }
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Failed to create checklist item. ' . $e->getMessage());
        }

        if ($checklist) {
            $activityService->log(
                module: 'task_checklist',
                action: 'created',
                actor: $actor,
                reference: $checklist,
                companyId: $project->company_id,
                projectId: $project->id,
                meta: [
                    'day_number' => $checklist->day_number,
                    'scope' => $validated['scope'],
                ],
                request: $request
            );

            return back()->with('success', "Checklist item added for Day {$checklist->day_number}.");
        }

        return back()->with('error', 'Failed to create checklist item: target not resolved.');
    }

    public function updateChecklistItem(
        Project $project,
        $checklist,
        Request $request,
        ConstructionActivityService $activityService
    ) {
        $actor = $this->constructionActor();

        $validated = $request->validate([
            'day_number' => ['nullable', 'integer', 'min:1', 'max:366'],
            'item_title' => ['nullable', 'string', 'max:255'],
            'assign_hours' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'in:pending,in_progress,completed'],
            'is_completed' => ['nullable', 'boolean'],
            'image_1' => ['nullable', 'image', 'max:10240'],
            'image_2' => ['nullable', 'image', 'max:10240'],
            'remove_image_1' => ['nullable', 'boolean'],
            'remove_image_2' => ['nullable', 'boolean'],
        ]);

        $item = $checklist instanceof \App\Models\TaskChecklist
            ? $checklist
            : \App\Models\TaskChecklist::find($checklist);

        if ($item) {
            $taskProjectId = null;
            if ($item->execution_task_id) {
                $taskProjectId = \App\Models\ExecutionTask::where('id', $item->execution_task_id)->value('project_id');
            }
            $planProjectId = null;
            if ($item->survey_plan_id) {
                $planProjectId = \App\Models\SurveyPlan::where('id', $item->survey_plan_id)->value('project_id');
            }
            if (
                ($taskProjectId !== null && (int) $taskProjectId !== (int) $project->id)
                || ($planProjectId !== null && (int) $planProjectId !== (int) $project->id)
            ) {
                return back()->with('error', 'The selected checklist item does not belong to this project.');
            }

            try {
                $attributes = [];
                if (isset($validated['day_number'])) {
                    $attributes['day_number'] = $validated['day_number'];
                }
                if (array_key_exists('item_title', $validated)) {
                    $attributes['item_title'] = $validated['item_title'];
                }
                if (array_key_exists('assign_hours', $validated)) {
                    $attributes['assign_hours'] = $validated['assign_hours'];
                }
                if (array_key_exists('notes', $validated)) {
                    $attributes['notes'] = $validated['notes'];
                }
                if (array_key_exists('status', $validated)) {
                    $attributes['status'] = $validated['status'];
                    if ($validated['status'] === 'completed') {
                        $attributes['is_completed'] = true;
                        $attributes['completed_at'] = $item->completed_at ?? now();
                        $attributes['completed_by_member_id'] = $item->completed_by_member_id ?? $actor?->getKey();
                    }
                }
                if (array_key_exists('is_completed', $validated)) {
                    $attributes['is_completed'] = (bool) $validated['is_completed'];
                    if ((bool) $validated['is_completed']) {
                        $attributes['status'] = 'completed';
                        $attributes['completed_at'] = $item->completed_at ?? now();
                        $attributes['completed_by_member_id'] = $item->completed_by_member_id ?? $actor?->getKey();
                    } else {
                        $attributes['status'] = 'pending';
                        $attributes['completed_at'] = null;
                        $attributes['completed_by_member_id'] = null;
                    }
                }

                if ($request->hasFile('image_1')) {
                    $path = $request->file('image_1')->store('checklists', 'public');
                    $attributes['image_url_1'] = '/storage/' . $path;
                } elseif ($request->boolean('remove_image_1')) {
                    $attributes['image_url_1'] = null;
                }

                if ($request->hasFile('image_2')) {
                    $path = $request->file('image_2')->store('checklists', 'public');
                    $attributes['image_url_2'] = '/storage/' . $path;
                } elseif ($request->boolean('remove_image_2')) {
                    $attributes['image_url_2'] = null;
                }

                $item->update($attributes);
            } catch (\Throwable $e) {
                report($e);
                return back()->with('error', 'Failed to update checklist item. ' . $e->getMessage());
            }

            $activityService->log(
                module: 'task_checklist',
                action: 'updated',
                actor: $actor,
                reference: $item,
                companyId: $project->company_id,
                projectId: $project->id,
                meta: [
                    'day_number' => $item->day_number,
                    'is_completed' => $item->is_completed,
                ],
                request: $request
            );

            return back()->with('success', 'Checklist item updated successfully.');
        }

        // Try unified TaskChecklistItem
        $unifiedItem = \App\Models\TaskChecklistItem::where('id', is_object($checklist) ? $checklist->id : $checklist)
            ->where('project_id', $project->id)
            ->first();

        if ($unifiedItem) {
            try {
                $uAttrs = [];
                if (isset($validated['day_number'])) $uAttrs['day_number'] = $validated['day_number'];
                if (array_key_exists('item_title', $validated)) $uAttrs['item_title'] = $validated['item_title'];
                if (array_key_exists('assign_hours', $validated)) $uAttrs['assign_hours'] = $validated['assign_hours'];
                if (array_key_exists('notes', $validated)) $uAttrs['notes'] = $validated['notes'];
                if (array_key_exists('status', $validated)) $uAttrs['status'] = $validated['status'];
                if (array_key_exists('is_completed', $validated)) {
                    $uAttrs['is_completed'] = (bool) $validated['is_completed'];
                    $uAttrs['status'] = (bool) $validated['is_completed'] ? 'completed' : 'pending';
                    $uAttrs['completed_at'] = (bool) $validated['is_completed'] ? now() : null;
                }
                $unifiedItem->update($uAttrs);
            } catch (\Throwable $e) {
                report($e);
                return back()->with('error', 'Failed to update checklist item. ' . $e->getMessage());
            }
            return back()->with('success', 'Checklist item updated successfully.');
        }

        return back()->with('error', 'The selected checklist item does not belong to this project.');
    }

    public function toggleChecklistItem(
        Project $project,
        $checklist,
        ConstructionActivityService $activityService
    ) {
        $actor = $this->constructionActor();

        $item = $checklist instanceof \App\Models\TaskChecklist
            ? $checklist
            : \App\Models\TaskChecklist::find($checklist);

        if ($item) {
            $taskProjectId = null;
            if ($item->execution_task_id) {
                $taskProjectId = \App\Models\ExecutionTask::where('id', $item->execution_task_id)->value('project_id');
            }
            $planProjectId = null;
            if ($item->survey_plan_id) {
                $planProjectId = \App\Models\SurveyPlan::where('id', $item->survey_plan_id)->value('project_id');
            }
            if (
                ($taskProjectId !== null && (int) $taskProjectId !== (int) $project->id)
                || ($planProjectId !== null && (int) $planProjectId !== (int) $project->id)
            ) {
                return back()->with('error', 'The selected checklist item does not belong to this project.');
            }

            $nextCompleted = !$item->is_completed;
            try {
                $item->update([
                    'is_completed' => $nextCompleted,
                    'status' => $nextCompleted ? 'completed' : 'pending',
                    'completed_by_member_id' => $nextCompleted ? $actor?->getKey() : null,
                    'completed_at' => $nextCompleted ? now() : null,
                ]);

                // Also sync matching unified item if exists
                try {
                    \App\Models\TaskChecklistItem::where('project_id', $project->id)
                        ->where('item_title', $item->item_title)
                        ->update([
                            'is_completed' => $nextCompleted,
                            'status' => $nextCompleted ? 'completed' : 'pending',
                        ]);
                } catch (\Throwable $e) {}
            } catch (\Throwable $e) {
                report($e);
                return back()->with('error', 'Failed to toggle checklist item. ' . $e->getMessage());
            }

            $activityService->log(
                module: 'task_checklist',
                action: $nextCompleted ? 'completed' : 'reopened',
                actor: $actor,
                reference: $item,
                companyId: $project->company_id,
                projectId: $project->id,
                meta: ['day_number' => $item->day_number],
                request: request()
            );

            return back()->with('success', $nextCompleted ? 'Checklist item marked complete.' : 'Checklist item reopened.');
        }

        // Try finding unified TaskChecklistItem
        $unifiedItem = \App\Models\TaskChecklistItem::where('id', is_object($checklist) ? $checklist->id : $checklist)
            ->where('project_id', $project->id)
            ->first();

        if ($unifiedItem) {
            $nextCompleted = !$unifiedItem->is_completed;
            try {
                $unifiedItem->update([
                    'is_completed' => $nextCompleted,
                    'status' => $nextCompleted ? 'completed' : 'pending',
                    'completed_at' => $nextCompleted ? now() : null,
                    'completed_by_id' => $nextCompleted ? $actor?->getKey() : null,
                ]);

                // Sync matching TaskChecklist if exists
                try {
                    \App\Models\TaskChecklist::where('item_title', $unifiedItem->item_title)
                        ->update([
                            'is_completed' => $nextCompleted,
                            'status' => $nextCompleted ? 'completed' : 'pending',
                        ]);
                } catch (\Throwable $e) {}
            } catch (\Throwable $e) {
                report($e);
                return back()->with('error', 'Failed to toggle checklist item. ' . $e->getMessage());
            }

            return back()->with('success', $nextCompleted ? 'Checklist item marked complete.' : 'Checklist item reopened.');
        }

        return back()->with('error', 'The selected checklist item does not belong to this project.');
    }

    public function destroyChecklistItem(
        Project $project,
        $checklist,
        ConstructionActivityService $activityService
    ) {
        $actor = $this->constructionActor();

        $item = $checklist instanceof \App\Models\TaskChecklist
            ? $checklist
            : \App\Models\TaskChecklist::find($checklist);

        if ($item) {
            $taskProjectId = null;
            if ($item->execution_task_id) {
                $taskProjectId = \App\Models\ExecutionTask::where('id', $item->execution_task_id)->value('project_id');
            }
            $planProjectId = null;
            if ($item->survey_plan_id) {
                $planProjectId = \App\Models\SurveyPlan::where('id', $item->survey_plan_id)->value('project_id');
            }
            if (
                ($taskProjectId !== null && (int) $taskProjectId !== (int) $project->id)
                || ($planProjectId !== null && (int) $planProjectId !== (int) $project->id)
            ) {
                return back()->with('error', 'The selected checklist item does not belong to this project.');
            }

            $title = $item->item_title;
            $dayNumber = $item->day_number;

            try {
                $item->delete();
                try {
                    \App\Models\TaskChecklistItem::where('project_id', $project->id)
                        ->where('item_title', $title)
                        ->delete();
                } catch (\Throwable $e) {}
            } catch (\Throwable $e) {
                report($e);
                return back()->with('error', 'Failed to delete checklist item. ' . $e->getMessage());
            }

            $activityService->log(
                module: 'task_checklist',
                action: 'deleted',
                actor: $actor,
                reference: null,
                companyId: $project->company_id,
                projectId: $project->id,
                meta: [
                    'item_title' => $title,
                    'day_number' => $dayNumber,
                ],
                request: request()
            );

            return back()->with('success', "Checklist item removed from Day {$dayNumber}.");
        }

        // Try finding unified TaskChecklistItem
        $unifiedItem = \App\Models\TaskChecklistItem::where('id', is_object($checklist) ? $checklist->id : $checklist)
            ->where('project_id', $project->id)
            ->first();

        if ($unifiedItem) {
            $title = $unifiedItem->item_title;
            $dayNumber = $unifiedItem->day_number;
            try {
                $unifiedItem->delete();
                try {
                    \App\Models\TaskChecklist::where('item_title', $title)->delete();
                } catch (\Throwable $e) {}
            } catch (\Throwable $e) {
                report($e);
                return back()->with('error', 'Failed to delete checklist item. ' . $e->getMessage());
            }

            return back()->with('success', "Checklist item removed from Day {$dayNumber}.");
        }

        return back()->with('error', 'The selected checklist item does not belong to this project.');
    }

    public function seedDefaultChecklists(
        Project $project,
        Request $request,
        ConstructionActivityService $activityService,
        SurveyDataService $surveyData
    ) {
        $actor = $this->constructionActor();

        $validated = $request->validate([
            'day_number' => ['required', 'integer', 'min:1', 'max:366'],
            'target_scope' => ['required', 'in:default,survey_plan,execution_task,all'],
            'survey_plan_id' => ['nullable', 'integer', 'exists:construction_survey_plans,id'],
            'execution_task_id' => ['nullable', 'integer', 'exists:construction_execution_tasks,id'],
        ]);

        $items = $surveyData->getDefaultChecklistItems();
        if (empty($items)) {
            return back()->with(
                'error',
                'No default checklist items are configured in the survey defaults.'
            );
        }

        try {
            $targets = [];
            $scope = $validated['target_scope'];

            if ($scope === 'default' || $scope === 'all') {
                $plan = isset($validated['survey_plan_id'])
                    ? SurveyPlan::find($validated['survey_plan_id'])
                    : $project->surveyPlans->first();
                if ($plan && (int) $plan->project_id === (int) $project->id) {
                    $targets[] = [
                        'label' => "Survey Plan {$plan->survey_code}",
                        'survey_plan_id' => $plan->id,
                        'execution_task_id' => null,
                    ];
                }
                $task = isset($validated['execution_task_id'])
                    ? \App\Models\ExecutionTask::find($validated['execution_task_id'])
                    : $project->executionTasks->first();
                if ($task && (int) $task->project_id === (int) $project->id) {
                    $surveyData->ensureDefaultTaskChecklists($task, (int) $validated['day_number']);
                }
            }

            if ($scope === 'survey_plan' || $scope === 'all') {
                $plans = isset($validated['survey_plan_id'])
                    ? SurveyPlan::where('id', $validated['survey_plan_id'])->where('project_id', $project->id)->get()
                    : $project->surveyPlans;
                foreach ($plans as $plan) {
                    $targets[] = [
                        'label' => "Survey Plan {$plan->survey_code}",
                        'survey_plan_id' => $plan->id,
                        'execution_task_id' => null,
                    ];
                }
            }

            if ($scope === 'execution_task' || $scope === 'all') {
                $tasks = isset($validated['execution_task_id'])
                    ? \App\Models\ExecutionTask::where('id', $validated['execution_task_id'])->where('project_id', $project->id)->get()
                    : $project->executionTasks;
                foreach ($tasks as $task) {
                    $surveyData->ensureDefaultTaskChecklists($task, (int) $validated['day_number']);
                }
            }

            $inserted = 0;
            foreach ($targets as $target) {
                $existing = TaskChecklist::where('survey_plan_id', $target['survey_plan_id'])
                    ->whereNull('execution_task_id')
                    ->where('day_number', $validated['day_number'])
                    ->pluck('item_title')
                    ->all();
                $payloads = [];
                foreach ($items as $title) {
                    if (in_array($title, $existing, true)) {
                        continue;
                    }
                    $payloads[] = [
                        'survey_plan_id' => $target['survey_plan_id'],
                        'execution_task_id' => null,
                        'day_number' => $validated['day_number'],
                        'item_title' => $title,
                        'is_completed' => false,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
                if (!empty($payloads)) {
                    TaskChecklist::insert($payloads);
                    $inserted += count($payloads);
                }
            }
        } catch (\Throwable $e) {
            report($e);

            return back()->with(
                'error',
                'Failed to seed default checklists. ' . $e->getMessage()
            );
        }

        $activityService->log(
            module: 'task_checklist',
            action: 'seeded_defaults',
            actor: $actor,
            reference: null,
            companyId: $project->company_id,
            projectId: $project->id,
            meta: [
                'day_number' => $validated['day_number'],
                'scope' => $validated['target_scope'],
                'items_seeded' => $inserted,
            ],
            request: $request
        );

        return back()->with(
            'success',
            "Seeded {$inserted} default checklist item(s) for Day {$validated['day_number']}."
        );
    }

    /**
     * Resolve project members scoped strictly to this company / project:
     * - Company Super Admin(s) (ensured as active Member records)
     * - Company Admin(s) (Members created by company Super Admin with admin role)
     * - Company Employees/Members (created by company Super Admin/Admin or matching company name)
     * - Project Team Members assigned to this project
     */
    protected function getProjectMembers(Project $project)
    {
        // 1. Determine company ID
        $companyId = $project->company_id;
        if (!$companyId && in_array($project->created_by_type, [SuperAdmin::class, 'App\Models\SuperAdmin', '\\App\\Models\\SuperAdmin'], true)) {
            $companyId = SuperAdmin::where('id', $project->created_by_id)->value('company_id');
        }

        $company = $project->company ?: ($companyId ? Company::find($companyId) : null);
        if (!$company && $project->created_by_id) {
            $creatorMember = Member::find($project->created_by_id);
            if ($creatorMember && !empty($creatorMember->company_name)) {
                $company = Company::where('name', $creatorMember->company_name)
                    ->orWhere('legal_name', $creatorMember->company_name)
                    ->first();
                if ($company) {
                    $companyId = $company->id;
                }
            }
        }

        $companyNames = [];
        if ($company) {
            if (!empty($company->name)) {
                $companyNames[] = trim($company->name);
            }
            if (!empty($company->legal_name) && $company->legal_name !== $company->name) {
                $companyNames[] = trim($company->legal_name);
            }
        }
        $companyNames = array_values(array_unique(array_filter($companyNames)));

        // 2. Resolve SuperAdmins strictly associated with this company
        $companySuperAdmins = collect();
        if ($companyId) {
            $companySuperAdmins = SuperAdmin::where('company_id', $companyId)->get();
        }
        $companySuperAdminIds = $companySuperAdmins->pluck('id')->filter()->toArray();

        // 3. Ensure each company SuperAdmin has a corresponding Member record so they can be assigned / selected as supervisor
        $companySuperAdminMemberIds = [];
        foreach ($companySuperAdmins as $sa) {
            $member = Member::where(function ($q) use ($sa) {
                $hasClause = false;
                if (!empty($sa->email)) {
                    $q->where('email', $sa->email);
                    $hasClause = true;
                }
                if (!empty($sa->phone)) {
                    if ($hasClause) {
                        $q->orWhere('phone', $sa->phone);
                    } else {
                        $q->where('phone', $sa->phone);
                        $hasClause = true;
                    }
                }
                if (!$hasClause) {
                    $q->whereRaw('1 = 0');
                }
            })->first();

            if (!$member) {
                $baseUsername = Str::slug(explode('@', $sa->email ?: 'superadmin')[0], '_') ?: 'superadmin';
                $username = $baseUsername;
                $counter = 1;
                while (Member::where('username', $username)->exists()) {
                    $username = $baseUsername . '_' . $counter;
                    $counter++;
                }

                $member = Member::create([
                    'name' => $sa->name,
                    'email' => $sa->email ?: ($username . '@cadmax.local'),
                    'phone' => $sa->phone ?: null,
                    'username' => $username,
                    'password' => $sa->password ?? Hash::make('SuperAdmin@123'),
                    'status' => 1,
                    'roles' => [2],
                    'designation' => ['Super Admin'],
                    'departments' => ['Administration'],
                    'company_name' => $company?->name,
                    'slug' => Str::slug($sa->name . '-' . Str::random(6)),
                    'created_by' => $sa->id,
                    'registration_source' => 'admin_created',
                ]);
            } else {
                if ((int) $member->status !== 1) {
                    $member->update(['status' => 1]);
                }
                if (empty($member->company_name) && $company) {
                    $member->update(['company_name' => $company->name]);
                }
            }

            $companySuperAdminMemberIds[] = $member->id;
        }

        // 4. Resolve Admins for this company
        $companyAdminMemberIds = [];
        $adminQuery = Member::query()->where(function ($q) {
            $q->whereJsonContains('roles', 'admin')
                ->orWhereJsonContains('roles', 9)
                ->orWhereJsonContains('roles', '9')
                ->orWhereJsonContains('roles', 3)
                ->orWhereJsonContains('roles', '3')
                ->orWhere('slug', 'admin')
                ->orWhere('slug', 'like', 'admin-%')
                ->orWhere('slug', 'like', 'admin_%');
        });

        $adminQuery->where(function ($q) use ($companyNames, $companySuperAdminIds) {
            $has = false;
            if (!empty($companyNames)) {
                $q->whereIn('company_name', $companyNames);
                $has = true;
            }
            if (!empty($companySuperAdminIds)) {
                if ($has) {
                    $q->orWhereIn('created_by', $companySuperAdminIds);
                } else {
                    $q->whereIn('created_by', $companySuperAdminIds);
                    $has = true;
                }
            }
            if (!$has) {
                $q->whereRaw('1 = 0');
            }
        });

        $companyAdminMemberIds = $adminQuery->pluck('id')->toArray();

        // 5. Resolve all Company Employees / Members
        $assignedProjectMemberIds = ProjectTeamMember::where('project_id', $project->id)
            ->pluck('member_id')
            ->toArray();

        $companyMembersQuery = Member::query()
            ->where('status', 1)
            ->where(function ($q) use ($companySuperAdminIds, $companyAdminMemberIds, $companyNames, $assignedProjectMemberIds) {
                $hasCondition = false;
                if (!empty($companyNames)) {
                    $q->whereIn('company_name', $companyNames);
                    $hasCondition = true;
                }
                if (!empty($companySuperAdminIds)) {
                    if ($hasCondition) {
                        $q->orWhereIn('created_by', $companySuperAdminIds);
                    } else {
                        $q->whereIn('created_by', $companySuperAdminIds);
                        $hasCondition = true;
                    }
                }
                if (!empty($companyAdminMemberIds)) {
                    if ($hasCondition) {
                        $q->orWhereIn('created_by', $companyAdminMemberIds);
                    } else {
                        $q->whereIn('created_by', $companyAdminMemberIds);
                        $hasCondition = true;
                    }
                }
                if (!empty($assignedProjectMemberIds)) {
                    if ($hasCondition) {
                        $q->orWhereIn('id', $assignedProjectMemberIds);
                    } else {
                        $q->whereIn('id', $assignedProjectMemberIds);
                        $hasCondition = true;
                    }
                }
                if (!$hasCondition) {
                    $q->whereRaw('1 = 0');
                }
            });

        // Ensure we do NOT include members whose company_name belongs to a DIFFERENT company
        if (!empty($companyNames)) {
            $companyMembersQuery->where(function ($q) use ($companyNames, $assignedProjectMemberIds) {
                $q->whereNull('company_name')
                  ->orWhere('company_name', '')
                  ->orWhereIn('company_name', $companyNames);

                if (!empty($assignedProjectMemberIds)) {
                    $q->orWhereIn('id', $assignedProjectMemberIds);
                }
            });
        }

        // Exclude global platform Super Admin (ID 1) unless the project belongs to company 1
        $isGlobalOwnerCompany = ($companyId && (int)$companyId === 1);
        if (!$isGlobalOwnerCompany) {
            $globalSuperAdminEmails = SuperAdmin::where('id', 1)
                ->pluck('email')
                ->filter()
                ->toArray();

            if (!empty($globalSuperAdminEmails)) {
                $companyMembersQuery->whereNotIn('email', $globalSuperAdminEmails);
            }
        }

        $allTargetMemberIds = collect($companySuperAdminMemberIds)
            ->concat($companyAdminMemberIds)
            ->concat($companyMembersQuery->pluck('id'))
            ->concat($assignedProjectMemberIds)
            ->unique()
            ->filter()
            ->values();

        $members = Member::whereIn('id', $allTargetMemberIds)
            ->where('status', 1)
            ->when(!empty($companyNames), function ($q) use ($companyNames, $assignedProjectMemberIds) {
                $q->where(function ($sq) use ($companyNames, $assignedProjectMemberIds) {
                    $sq->whereNull('company_name')
                      ->orWhere('company_name', '')
                      ->orWhereIn('company_name', $companyNames);

                    if (!empty($assignedProjectMemberIds)) {
                        $sq->orWhereIn('id', $assignedProjectMemberIds);
                    }
                });
            })
            ->when(!$isGlobalOwnerCompany, function ($q) {
                $globalEmails = SuperAdmin::where('id', 1)
                    ->pluck('email')
                    ->filter()
                    ->toArray();
                if (!empty($globalEmails)) {
                    $q->whereNotIn('email', $globalEmails);
                }
            })
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'email',
                'departments',
                'designation',
                'roles',
                'registration_source',
                'created_by',
                'company_name',
            ]);

        // Collect all numeric designation IDs from all members.
        $designationIds = $members
            ->flatMap(function ($member) {
                if (!is_array($member->designation)) {
                    return [];
                }

                return array_values($member->designation);
            })
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        // Fetch all required designations in one query.
        $designationNames = \App\Models\Designation::whereIn(
            'id',
            $designationIds
        )->pluck('name', 'id');

        $memberIds = $members->pluck('id')->all();
        $assignmentsByMember = \App\Models\MemberRoleAssignment::whereIn('member_id', $memberIds)
            ->where('status', 1)
            ->with('role')
            ->get()
            ->groupBy('member_id');

        $systemRoleNames = \App\Models\Role::pluck('name', 'id')->toArray();
        $constructionRoleNames = \App\Models\ConstructionRole::pluck('name', 'id')->toArray();
        $roleSlugNames = [
            'super_admin' => 'Super Admin',
            'superadmin' => 'Super Admin',
            'admin' => 'Admin',
            'project_admin' => 'Project Admin',
            'surveyor' => 'Surveyor',
            'draft_person' => 'Draft Person',
            'draftsman' => 'Draft Person',
            'vehicle_driver' => 'Driver',
            'driver' => 'Driver',
            'review_approver' => 'Review Approver',
            'site_employee' => 'Site Employee',
        ];

        // Build display text and role badges without additional database queries.
        $members->transform(function ($member) use ($designationNames, $companySuperAdminMemberIds, $companyAdminMemberIds, $assignmentsByMember, $systemRoleNames, $constructionRoleNames, $roleSlugNames) {
            $desigStr = '';

            if (!empty($member->designation)) {
                if (is_array($member->designation)) {
                    $desigValues = array_values($member->designation);

                    if (
                        isset($desigValues[0])
                        && is_numeric($desigValues[0])
                    ) {
                        $desigStr = collect($desigValues)
                            ->map(
                                fn ($id) =>
                                    $designationNames[(int) $id] ?? null
                            )
                            ->filter()
                            ->implode(', ');
                    } else {
                        $desigStr = implode(', ', $desigValues);
                    }
                } else {
                    $desigStr = (string) $member->designation;
                }
            }

            $isSuperAdmin = in_array($member->id, $companySuperAdminMemberIds, true)
                || (is_array($member->roles) && (in_array('super_admin', $member->roles, true) || in_array('superadmin', $member->roles, true) || in_array(2, $member->roles, true) || in_array('2', $member->roles, true)))
                || $member->slug === 'super-admin';

            $isAdmin = !$isSuperAdmin && (
                in_array($member->id, $companyAdminMemberIds, true)
                || $member->isAdmin()
                || (is_array($member->roles) && (in_array(9, $member->roles, true) || in_array('9', $member->roles, true) || in_array(3, $member->roles, true) || in_array('3', $member->roles, true) || in_array('admin', $member->roles, true) || in_array('project_admin', $member->roles, true)))
                || $member->slug === 'admin'
                || str_starts_with((string) ($member->slug ?? ''), 'admin-')
                || str_starts_with((string) ($member->slug ?? ''), 'admin_')
            );

            $badges = [];

            if ($isSuperAdmin) {
                $badges[] = 'Super Admin';
            } elseif ($isAdmin) {
                $badges[] = 'Admin';
            }

            // Roles from MemberRoleAssignment
            if ($assignmentsByMember->has($member->id)) {
                foreach ($assignmentsByMember->get($member->id) as $assignment) {
                    if ($assignment->role && !empty($assignment->role->name)) {
                        $roleName = trim($assignment->role->name);
                        $slug = strtolower(trim($assignment->role->slug ?? ''));
                        $formattedRole = match ($slug) {
                            'vehicle_driver', 'driver' => 'Driver',
                            'draft_person', 'draftsman' => 'Draft Person',
                            'surveyor' => 'Surveyor',
                            'super_admin', 'superadmin' => 'Super Admin',
                            'admin' => 'Admin',
                            'project_admin' => 'Project Admin',
                            'review_approver' => 'Review Approver',
                            'site_employee' => 'Site Employee',
                            default => $roleName,
                        };
                        $badges[] = $formattedRole;
                    }
                }
            }

            // Roles from roles column
            if (!empty($member->roles) && is_array($member->roles)) {
                foreach ($member->roles as $r) {
                    if (is_numeric($r)) {
                        $id = (int) $r;
                        if (isset($constructionRoleNames[$id])) {
                            $badges[] = $constructionRoleNames[$id];
                        } elseif (isset($systemRoleNames[$id])) {
                            $badges[] = $systemRoleNames[$id];
                        }
                    } elseif (is_string($r)) {
                        $slug = strtolower(trim($r));
                        if (isset($roleSlugNames[$slug])) {
                            $badges[] = $roleSlugNames[$slug];
                        }
                    }
                }
            }

            // Designation parts
            if (!empty($desigStr)) {
                foreach (explode(',', $desigStr) as $dp) {
                    $trimmed = trim($dp);
                    if ($trimmed !== '') {
                        $badges[] = $trimmed;
                    }
                }
            }

            // Clean & Deduplicate
            $uniqueBadges = [];
            $seenKeys = [];
            foreach ($badges as $b) {
                $cleaned = trim((string) $b);
                if ($cleaned === '' || strcasecmp($cleaned, 'Member') === 0) {
                    continue;
                }
                $norm = strtolower(preg_replace('/[^a-z0-9]/', '', $cleaned));
                if (!isset($seenKeys[$norm])) {
                    $seenKeys[$norm] = true;
                    $uniqueBadges[] = $cleaned;
                }
            }

            $member->roles_list = $uniqueBadges;
            $member->role_badges = $uniqueBadges;
            $member->role_badge = !empty($uniqueBadges) ? implode(', ', $uniqueBadges) : null;
            $member->roles_formatted = !empty($uniqueBadges) ? '[' . implode('] [', $uniqueBadges) . ']' : '';
            $member->designation_text = $desigStr;

            return $member;
        });

        return $members;
    }
}
