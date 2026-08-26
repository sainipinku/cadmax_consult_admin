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
        return Inertia::render('SuperAdmin/Construction/Projects/Index', [
            'projects' => Project::with(['company', 'client', 'latestBudget'])
                ->latest()
                ->get(),
            'companies' => Company::orderBy('name')->get(['id', 'name']),
            'clients' => Client::orderBy('name')->get([
                'id',
                'name',
                'company_id',
            ]),
        ]);
    }

    public function show(Project $project): Response
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
            'surveySubmissions.submittedBy',
            'surveySubmissions.reviewedBy',
            'surveySubmissions.surveyVisit.checkedInBy',
            'draftingJobs.assignedTo',
            'draftingJobs.drawingRevisions.uploadedBy',
            'draftingJobs.drawingRevisions.dwgDocument',
            'draftingJobs.drawingRevisions.pdfDocument',
            'draftingJobs.drawingRevisions.approvals.approvedBy',
            'drawingApprovals.drawingRevision',
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
            'latitude' => [
                'nullable',
                'numeric',
            ],
            'longitude' => [
                'nullable',
                'numeric',
            ],
            'start_date' => [
                'nullable',
                'date',
            ],
            'expected_end_date' => [
                'nullable',
                'date',
            ],
            'priority' => [
                'required',
                'in:low,medium,high,critical',
            ],
        ]);

        $nextId = (Project::max('id') ?? 0) + 1;
        $nameSlug = Str::slug($validated['name']);

        $project = Project::create([
            ...$validated,
            'project_code' => 'PRJ-'
                . str_pad(
                    (string) $nextId,
                    5,
                    '0',
                    STR_PAD_LEFT
                ),
            'slug' => $nameSlug . '-' . $nextId,
            'status' => 'draft',
            'current_stage' => 'budget_pending',
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
            'latitude' => [
                'nullable',
                'numeric',
            ],
            'longitude' => [
                'nullable',
                'numeric',
            ],
            'start_date' => [
                'nullable',
                'date',
            ],
            'expected_end_date' => [
                'nullable',
                'date',
            ],
            'priority' => [
                'required',
                'in:low,medium,high,critical',
            ],
        ]);

        // Only update editable master-data fields.
        // project_code, slug, status, current_stage and all workflow/lifecycle
        // fields are intentionally excluded and remain untouched.
        $project->update([
            'company_id' => $validated['company_id'],
            'client_id' => $validated['client_id'],
            'name' => $validated['name'],
            'category' => $validated['category'] ?? null,
            'description' => $validated['description'] ?? null,
            'project_address' =>
                $validated['project_address'] ?? null,
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'start_date' => $validated['start_date'] ?? null,
            'expected_end_date' =>
                $validated['expected_end_date'] ?? null,
            'priority' => $validated['priority'],
        ]);

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
}