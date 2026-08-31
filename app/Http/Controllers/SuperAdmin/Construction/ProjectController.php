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
use App\Support\Construction\SurveyStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    use ResolvesConstructionActor;

    public function index(): Response
    {
        $projects = Project::with(['company', 'client', 'latestBudget'])
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
            ->get()
            ->keyBy('project_id');

        $projects->transform(function (Project $project) use (
            $taskChecklistsByProject,
            $tasksCompletedByProject
        ) {
            $taskStats = $tasksCompletedByProject->get($project->id);
            $checklistStats = $taskChecklistsByProject[(int) $project->id] ?? ['total' => 0, 'completed' => 0];
            $project->workflow_counts = [
                'execution_tasks' => [
                    'total' => (int) ($taskStats?->total ?? $project->tasks_total_count ?? 0),
                    'completed' => (int) ($taskStats?->completed ?? 0),
                    'in_progress' => (int) ($taskStats?->in_progress ?? 0),
                    'pending' => max(
                        0,
                        (int) ($taskStats?->total ?? $project->tasks_total_count ?? 0) - (int) ($taskStats?->completed ?? 0) - (int) ($taskStats?->in_progress ?? 0)
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

        return Inertia::render('SuperAdmin/Construction/Projects/Index', [
            'projects' => $projects,
            'companies' => Company::orderBy('name')->get(['id', 'name']),
            'clients' => Client::orderBy('name')->get([
                'id',
                'name',
                'company_id',
            ]),
        ]);
    }

    public function show(Project $project, SurveyDataService $surveyData): Response
    {
        $project->load([
            'company',
            'client',
            'budgets' => fn ($query) => $query->latest('version_no'),
            'teamMembers.member',
            'teamMembers.role',
            'surveyPlans.planMembers.member',
            'surveyPlans.visits.checkedInBy',
            'surveyPlans.visits.entries.capturedBy',
            'surveyPlans.visits.measurements.capturedBy',
            'surveyPlans.visits.submission.submittedBy',
            'surveyPlans.visits.submission.reviewedBy',
            'surveyPlans.checklists.completedBy',
            'surveySubmissions.submittedBy',
            'surveySubmissions.reviewedBy',
            'surveySubmissions.surveyVisit.checkedInBy',
            'draftingJobs.assignedTo',
            'draftingJobs.drawingRevisions.uploadedBy',
            'draftingJobs.drawingRevisions.dwgDocument',
            'draftingJobs.drawingRevisions.pdfDocument',
            'draftingJobs.drawingRevisions.approvals.approvedBy',
            'drawingApprovals.drawingRevision',
            'executionTasks.supervisor',
            'executionTasks.assignees.member',
            'executionTasks.checklists.completedBy',
            'executionTasks.progressReports.submittedBy',
            'executionTasks.progressReports.reviewedBy',
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

        $members = Member::where('status', 1)
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'email',
                'departments',
                'designation',
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

        // Build display text without additional database queries.
        $members->transform(function ($member) use ($designationNames) {
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

            $member->designation_text = $desigStr;

            return $member;
        });

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
                        'total' => $project->executionTasks->count(),
                        'completed' => $project->executionTasks
                            ->where(fn ($t) => $t->status === 'completed')
                            ->count(),
                        'in_progress' => $project->executionTasks
                            ->where(fn ($t) => $t->status === 'in_progress')
                            ->count(),
                        'pending' => $project->executionTasks
                            ->where(fn ($t) => !in_array($t->status, ['completed', 'in_progress']))
                            ->count(),
                    ],
                    'checklist_counts' => [
                        'total' => $project->executionTasks->flatMap(fn ($t) => $t->checklists ?? collect())->count()
                            + $project->surveyPlans->flatMap(fn ($s) => $s->checklists ?? collect())->count(),
                        'completed' => $project->executionTasks
                            ->flatMap(fn ($t) => $t->checklists ?? collect())
                            ->where('is_completed', true)
                            ->count()
                            + $project->surveyPlans
                            ->flatMap(fn ($s) => $s->checklists ?? collect())
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
                Rule::unique('construction_projects', 'project_code'),
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

        $nextId = (Project::max('id') ?? 0) + 1;
        $nameSlug = Str::slug($validated['name']);

        $project = Project::create([
            ...$validated,
            'project_code' => !empty($validated['project_code'])
                ? $validated['project_code']
                : 'PRJ-'
                . str_pad(
                    (string) $nextId,
                    5,
                    '0',
                    STR_PAD_LEFT
                ),
            'slug' => $nameSlug . '-' . $nextId,
            'status' => $validated['status'] ?? 'draft',
            'current_stage' => $validated['current_stage'] ?? 'budget_pending',
            'created_by_type' => $actor
                ? $actor::class
                : null,
            'created_by_id' => $actor?->getKey(),
        ]);

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
            'assignees.member',
            'progressReports',
            'attendanceRecords',
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
            'progressReports',
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

    public function storeTask(
        Project $project,
        Request $request,
        ConstructionActivityService $activityService
    ) {
        $actor = $this->constructionActor();

        $validated = $request->validate([
            'execution_plan_id' => ['nullable', 'integer', 'exists:construction_execution_plans,id'],
            'parent_task_id' => ['nullable', 'integer', 'exists:construction_execution_tasks,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
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

        $nextId = (int) (\App\Models\ExecutionTask::max('id') ?? 0) + 1;

        try {
            $task = \App\Models\ExecutionTask::create([
                'project_id' => $project->id,
                'execution_plan_id' => $validated['execution_plan_id'] ?? null,
                'parent_task_id' => $validated['parent_task_id'] ?? null,
                'task_code' => 'TASK-' . str_pad((string) $nextId, 6, '0', STR_PAD_LEFT),
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'planned_start_date' => $validated['planned_start_date'] ?? null,
                'planned_end_date' => $validated['planned_end_date'] ?? null,
                'actual_start_date' => $validated['actual_start_date'] ?? null,
                'actual_end_date' => $validated['actual_end_date'] ?? null,
                'priority' => $validated['priority'] ?? 'medium',
                'planned_quantity' => $validated['planned_quantity'] ?? null,
                'completed_quantity' => 0,
                'unit' => $validated['unit'] ?? null,
                'progress_percent' => 0,
                'requires_daily_update' => (bool) ($validated['requires_daily_update'] ?? false),
                'requires_gps_verification' => (bool) ($validated['requires_gps_verification'] ?? false),
                'supervisor_member_id' => $validated['supervisor_member_id'] ?? null,
                'status' => $validated['status'] ?? 'draft',
            ]);
        } catch (\Throwable $e) {
            report($e);

            return back()->with(
                'error',
                'Failed to create execution task. ' . $e->getMessage()
            );
        }

        $activityService->log(
            module: 'execution_task',
            action: 'created',
            actor: $actor,
            reference: $task,
            companyId: $project->company_id,
            projectId: $project->id,
            meta: ['task_code' => $task->task_code],
            request: $request
        );

        return back()->with(
            'success',
            "Execution task {$task->task_code} created successfully."
        );
    }

    public function updateTask(
        Project $project,
        \App\Models\ExecutionTask $task,
        Request $request,
        ConstructionActivityService $activityService
    ) {
        if ((int) $task->project_id !== (int) $project->id) {
            return back()->with(
                'error',
                'The selected task does not belong to this project.'
            );
        }

        $actor = $this->constructionActor();

        $validated = $request->validate([
            'execution_plan_id' => ['nullable', 'integer', 'exists:construction_execution_plans,id'],
            'parent_task_id' => ['nullable', 'integer', 'exists:construction_execution_tasks,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
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

        try {
            $task->update($validated);
        } catch (\Throwable $e) {
            report($e);

            return back()->with(
                'error',
                'Failed to update execution task. ' . $e->getMessage()
            );
        }

        $activityService->log(
            module: 'execution_task',
            action: 'updated',
            actor: $actor,
            reference: $task,
            companyId: $project->company_id,
            projectId: $project->id,
            meta: ['task_code' => $task->task_code],
            request: $request
        );

        return back()->with(
            'success',
            "Execution task {$task->task_code} updated successfully."
        );
    }

    public function destroyTask(
        Project $project,
        \App\Models\ExecutionTask $task,
        ConstructionActivityService $activityService
    ) {
        if ((int) $task->project_id !== (int) $project->id) {
            return back()->with(
                'error',
                'The selected task does not belong to this project.'
            );
        }

        $actor = $this->constructionActor();
        $taskCode = $task->task_code;

        try {
            \DB::transaction(function () use ($task) {
                \App\Models\TaskChecklist::where('execution_task_id', $task->id)->delete();
                \App\Models\ExecutionTaskAssignee::where('execution_task_id', $task->id)->delete();
                \App\Models\DailyProgressReport::where('execution_task_id', $task->id)->update([
                    'execution_task_id' => null,
                ]);
                \App\Models\ExecutionTask::where('parent_task_id', $task->id)->update([
                    'parent_task_id' => null,
                ]);
                $task->delete();
            });
        } catch (\Throwable $e) {
            report($e);

            return back()->with(
                'error',
                'Failed to delete execution task. ' . $e->getMessage()
            );
        }

        $activityService->log(
            module: 'execution_task',
            action: 'deleted',
            actor: $actor,
            reference: null,
            companyId: $project->company_id,
            projectId: $project->id,
            meta: ['task_code' => $taskCode],
            request: request()
        );

        return back()->with(
            'success',
            "Execution task {$taskCode} deleted successfully."
        );
    }

    public function storeChecklistItem(
        Project $project,
        Request $request,
        ConstructionActivityService $activityService,
        SurveyDataService $surveyData
    ) {
        $actor = $this->constructionActor();

        $validated = $request->validate([
            'scope' => ['required', 'in:execution_task,survey_plan,project'],
            'execution_task_id' => [
                'nullable',
                'integer',
                'exists:construction_execution_tasks,id',
                Rule::requiredIf(fn () => $request->input('scope') === 'execution_task'),
            ],
            'survey_plan_id' => [
                'nullable',
                'integer',
                'exists:construction_survey_plans,id',
                Rule::requiredIf(fn () => $request->input('scope') === 'survey_plan'),
            ],
            'day_number' => ['required', 'integer', 'min:1', 'max:366'],
            'item_title' => ['required', 'string', 'max:255'],
            'is_completed' => ['nullable', 'boolean'],
        ]);

        if (
            $validated['scope'] !== 'project'
            && isset($validated['execution_task_id'])
            && \App\Models\ExecutionTask::where('id', $validated['execution_task_id'])->value('project_id') !== $project->id
        ) {
            return back()->with(
                'error',
                'The selected execution task does not belong to this project.'
            );
        }

        if (
            $validated['scope'] !== 'project'
            && isset($validated['survey_plan_id'])
            && \App\Models\SurveyPlan::where('id', $validated['survey_plan_id'])->value('project_id') !== $project->id
        ) {
            return back()->with(
                'error',
                'The selected survey plan does not belong to this project.'
            );
        }

        try {
            $taskId = $validated['scope'] === 'execution_task' ? $validated['execution_task_id'] : null;
            $planId = $validated['scope'] === 'survey_plan' ? $validated['survey_plan_id'] : null;

            if ($validated['scope'] === 'project') {
                $firstPlan = $project->surveyPlans->first();
                $planId = $firstPlan?->id;
            }

            $checklist = \App\Models\TaskChecklist::create([
                'execution_task_id' => $taskId,
                'survey_plan_id' => $planId,
                'day_number' => $validated['day_number'],
                'item_title' => $validated['item_title'],
                'is_completed' => (bool) ($validated['is_completed'] ?? false),
                'completed_by_member_id' => !empty($validated['is_completed']) ? $actor?->getKey() : null,
                'completed_at' => !empty($validated['is_completed']) ? now() : null,
            ]);

            if (!$taskId && !$planId) {
                try {
                    $surveyData->ensureDefaultTaskChecklists(
                        $project->executionTasks->first() ?? new \App\Models\ExecutionTask([
                            'project_id' => $project->id,
                            'title' => $project->name,
                        ]),
                        (int) $validated['day_number']
                    );
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        } catch (\Throwable $e) {
            report($e);

            return back()->with(
                'error',
                'Failed to create checklist item. ' . $e->getMessage()
            );
        }

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

        return back()->with(
            'success',
            "Checklist item added for Day {$checklist->day_number}."
        );
    }

    public function updateChecklistItem(
        Project $project,
        \App\Models\TaskChecklist $checklist,
        Request $request,
        ConstructionActivityService $activityService
    ) {
        $actor = $this->constructionActor();

        $taskProjectId = null;
        if ($checklist->execution_task_id) {
            $taskProjectId = \App\Models\ExecutionTask::where('id', $checklist->execution_task_id)->value('project_id');
        }
        $planProjectId = null;
        if ($checklist->survey_plan_id) {
            $planProjectId = \App\Models\SurveyPlan::where('id', $checklist->survey_plan_id)->value('project_id');
        }
        if (
            ($taskProjectId !== null && (int) $taskProjectId !== (int) $project->id)
            || ($planProjectId !== null && (int) $planProjectId !== (int) $project->id)
        ) {
            return back()->with(
                'error',
                'The selected checklist item does not belong to this project.'
            );
        }

        $validated = $request->validate([
            'day_number' => ['nullable', 'integer', 'min:1', 'max:366'],
            'item_title' => ['nullable', 'string', 'max:255'],
            'is_completed' => ['nullable', 'boolean'],
        ]);

        try {
            $attributes = [];
            if (isset($validated['day_number'])) {
                $attributes['day_number'] = $validated['day_number'];
            }
            if (array_key_exists('item_title', $validated)) {
                $attributes['item_title'] = $validated['item_title'];
            }
            if (array_key_exists('is_completed', $validated)) {
                $attributes['is_completed'] = (bool) $validated['is_completed'];
                if ((bool) $validated['is_completed'] && !$checklist->completed_at) {
                    $attributes['completed_by_member_id'] = $actor?->getKey();
                    $attributes['completed_at'] = now();
                }
                if (!(bool) $validated['is_completed']) {
                    $attributes['completed_by_member_id'] = null;
                    $attributes['completed_at'] = null;
                }
            }

            $checklist->update($attributes);
        } catch (\Throwable $e) {
            report($e);

            return back()->with(
                'error',
                'Failed to update checklist item. ' . $e->getMessage()
            );
        }

        $activityService->log(
            module: 'task_checklist',
            action: 'updated',
            actor: $actor,
            reference: $checklist,
            companyId: $project->company_id,
            projectId: $project->id,
            meta: [
                'day_number' => $checklist->day_number,
                'is_completed' => $checklist->is_completed,
            ],
            request: $request
        );

        return back()->with(
            'success',
            'Checklist item updated successfully.'
        );
    }

    public function toggleChecklistItem(
        Project $project,
        \App\Models\TaskChecklist $checklist,
        ConstructionActivityService $activityService
    ) {
        $actor = $this->constructionActor();

        $taskProjectId = null;
        if ($checklist->execution_task_id) {
            $taskProjectId = \App\Models\ExecutionTask::where('id', $checklist->execution_task_id)->value('project_id');
        }
        $planProjectId = null;
        if ($checklist->survey_plan_id) {
            $planProjectId = \App\Models\SurveyPlan::where('id', $checklist->survey_plan_id)->value('project_id');
        }
        if (
            ($taskProjectId !== null && (int) $taskProjectId !== (int) $project->id)
            || ($planProjectId !== null && (int) $planProjectId !== (int) $project->id)
        ) {
            return back()->with(
                'error',
                'The selected checklist item does not belong to this project.'
            );
        }

        $nextCompleted = !$checklist->is_completed;
        try {
            $checklist->update([
                'is_completed' => $nextCompleted,
                'completed_by_member_id' => $nextCompleted ? $actor?->getKey() : null,
                'completed_at' => $nextCompleted ? now() : null,
            ]);
        } catch (\Throwable $e) {
            report($e);

            return back()->with(
                'error',
                'Failed to toggle checklist item. ' . $e->getMessage()
            );
        }

        $activityService->log(
            module: 'task_checklist',
            action: $nextCompleted ? 'completed' : 'reopened',
            actor: $actor,
            reference: $checklist,
            companyId: $project->company_id,
            projectId: $project->id,
            meta: ['day_number' => $checklist->day_number],
            request: request()
        );

        return back()->with(
            'success',
            $nextCompleted ? 'Checklist item marked complete.' : 'Checklist item reopened.'
        );
    }

    public function destroyChecklistItem(
        Project $project,
        \App\Models\TaskChecklist $checklist,
        ConstructionActivityService $activityService
    ) {
        $actor = $this->constructionActor();

        $taskProjectId = null;
        if ($checklist->execution_task_id) {
            $taskProjectId = \App\Models\ExecutionTask::where('id', $checklist->execution_task_id)->value('project_id');
        }
        $planProjectId = null;
        if ($checklist->survey_plan_id) {
            $planProjectId = \App\Models\SurveyPlan::where('id', $checklist->survey_plan_id)->value('project_id');
        }
        if (
            ($taskProjectId !== null && (int) $taskProjectId !== (int) $project->id)
            || ($planProjectId !== null && (int) $planProjectId !== (int) $project->id)
        ) {
            return back()->with(
                'error',
                'The selected checklist item does not belong to this project.'
            );
        }

        $title = $checklist->item_title;
        $dayNumber = $checklist->day_number;

        try {
            $checklist->delete();
        } catch (\Throwable $e) {
            report($e);

            return back()->with(
                'error',
                'Failed to delete checklist item. ' . $e->getMessage()
            );
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

        return back()->with(
            'success',
            "Checklist item removed from Day {$dayNumber}."
        );
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
}
