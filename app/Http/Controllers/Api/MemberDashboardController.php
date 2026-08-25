<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\Client;
use App\Models\Company;
use App\Models\DailyProgressReport;
use App\Models\DraftingJob;
use App\Models\EquipmentAllocation;
use App\Models\ExecutionTask;
use App\Models\ExecutionTaskAssignee;
use App\Models\Material;
use App\Models\MaterialStock;
use App\Models\Project;
use App\Models\ProjectBudget;
use App\Models\ProjectTeamMember;
use App\Models\SurveyPlan;
use App\Models\SurveyPlanMember;
use App\Models\SurveyVisit;
use App\Models\ConstructionVehicle;
use App\Models\VehicleAssignment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Support\Construction\SurveyStatus;


class MemberDashboardController extends Controller
{
    public function index(Request $request)
    {
        $member = $request->user();
        $memberId = $member->getKey();

        $teamProjectIds = ProjectTeamMember::where('member_id', $memberId)
            ->where('status', 'active')
            ->pluck('project_id');

        $surveyProjectIds = SurveyPlan::whereHas('planMembers', function ($q) use ($memberId) {
            $q->where('member_id', $memberId);
        })->pluck('project_id');

        $taskProjectIds = ExecutionTaskAssignee::where('member_id', $memberId)
            ->where('status', 'active')
            ->pluck('project_id');

        $projectIds = $teamProjectIds
            ->merge($surveyProjectIds)
            ->merge($taskProjectIds)
            ->unique()
            ->values();

        $companyIds = Project::whereIn('id', $projectIds)->pluck('company_id')->unique()->values();

        try {
            $companies = Company::withCount(['projects', 'clients'])
                ->whereIn('id', $companyIds)
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'legal_name', 'phone', 'logo_path', 'status']);
        } catch (\Throwable $e) {
            report($e);
            $companies = collect([]);
        }

        $projects = Project::with(['company', 'client', 'latestBudget', 'teamMembers.role'])
            ->whereIn('id', $projectIds)
            ->latest()
            ->get();

        $primaryProjectIds = ProjectTeamMember::where('member_id', $memberId)
            ->where('is_primary', true)
            ->where('status', 'active')
            ->pluck('project_id');

        $primaryProjects = Project::with(['company', 'client', 'latestBudget'])
            ->whereIn('id', $primaryProjectIds)
            ->get();

        $surveyPlans = SurveyPlan::with(['project.company', 'project.client', 'planMembers.member'])
            ->whereHas('planMembers', function ($q) use ($memberId) {
                $q->where('member_id', $memberId);
            })
            ->latest()
            ->get();

        $today = now()->toDateString();
        $todayVisits = collect([]);
        try {
            $todayVisits = SurveyVisit::with(['surveyPlan', 'project'])
                ->whereHas('surveyPlan.planMembers', function ($q) use ($memberId) {
                    $q->where('member_id', $memberId);
                })
                ->whereDate('check_in_at', $today)
                ->latest()
                ->get();
        } catch (\Throwable $e) {
            report($e);
        }

        $pendingSurveyPlans = 0;
        try {
$pendingSurveyPlans = SurveyPlan::whereHas(
    'planMembers',
    function ($q) use ($memberId) {
        $q->where('member_id', $memberId);
    }
)
    ->whereIn('status', [
        SurveyPlan::STATUS_PLANNED,
        SurveyPlan::STATUS_IN_PROGRESS,
    ])
    ->count();
        } catch (\Throwable $e) {
            report($e);
        }

        $tasks = collect([]);
        try {
            $tasks = ExecutionTask::with(['project', 'executionPlan', 'supervisor'])
                ->whereHas('assignees', function ($q) use ($memberId) {
                    $q->where('member_id', $memberId)->where('status', 'active');
                })
                ->latest()
                ->get();
        } catch (\Throwable $e) {
            report($e);
        }

        $taskCounts = [
            'total' => $tasks->count(),
            'pending' => $tasks->where('status', 'planned')->count(),
            'in_progress' => $tasks->where('status', 'in_progress')->count(),
            'completed' => $tasks->where('status', 'completed')->count(),
            'blocked' => $tasks->where('status', 'blocked')->count(),
        ];

        $todayAttendance = null;
        try {
            $todayAttendance = AttendanceRecord::where('member_id', $memberId)
                ->where('attendance_date', $today)
                ->latest()
                ->first();
        } catch (\Throwable $e) {
            report($e);
        }

        $attendanceLast30 = collect([]);
        try {
            $attendanceLast30 = AttendanceRecord::where('member_id', $memberId)
                ->where('attendance_date', '>=', now()->subDays(30)->toDateString())
                ->latest('attendance_date')
                ->get();
        } catch (\Throwable $e) {
            report($e);
        }

        $hoursWorked = 0;
        try {
            $hoursWorked = (float) $attendanceLast30->sum('hours_worked');
        } catch (\Throwable $e) {
            $hoursWorked = 0;
        }

        $attendanceSummary = [
            'total_days' => $attendanceLast30->count(),
            'present' => $attendanceLast30->where('attendance_type', 'present')->count(),
            'half_day' => $attendanceLast30->where('attendance_type', 'half_day')->count(),
            'overtime' => $attendanceLast30->where('attendance_type', 'overtime')->count(),
            'check_in_today' => (bool) $todayAttendance,
            'today_record' => $todayAttendance,
            'total_hours_last_30_days' => $hoursWorked,
        ];

        $draftingJobs = collect([]);
        try {
            $draftingJobs = DraftingJob::with(['project'])
                ->where('assigned_to_member_id', $memberId)
                ->latest()
                ->get();
        } catch (\Throwable $e) {
            report($e);
        }

        $recentDPRs = collect([]);
        try {
            $recentDPRs = DailyProgressReport::with(['project'])
                ->where('submitted_by_member_id', $memberId)
                ->latest('report_date')
                ->take(10)
                ->get();
        } catch (\Throwable $e) {
            report($e);
        }

        $vehiclesAssigned = collect([]);
        try {
            $now = now();
            $vehiclesAssigned = VehicleAssignment::with(['vehicle', 'project'])
                ->where('driver_member_id', $memberId)
                ->where('status', 'active')
                ->where(function ($q) use ($now) {
                    $q->whereNull('assigned_to')
                        ->orWhere('assigned_to', '>=', $now);
                })
                ->latest()
                ->get();
        } catch (\Throwable $e) {
            report($e);
        }

        $equipmentAssigned = collect([]);
        try {
            $equipmentAssigned = EquipmentAllocation::with(['equipment', 'project'])
                ->where('assigned_to_member_id', $memberId)
                ->whereNull('returned_at')
                ->where('status', 'active')
                ->latest()
                ->get();
        } catch (\Throwable $e) {
            report($e);
        }

        $projectStageCounts = $projects->countBy('current_stage');

        $projectStatusCounts = [
            'draft' => $projects->where('status', 'draft')->count(),
            'active' => $projects->where('status', 'active')->count(),
            'on_hold' => $projects->where('status', 'on_hold')->count(),
            'completed' => $projects->where('status', 'completed')->count(),
            'cancelled' => $projects->where('status', 'cancelled')->count(),
            'total' => $projects->count(),
        ];

        $totalBudgetApproved = 0;
        try {
            $totalBudgetApproved = (float) ProjectBudget::where('status', 'approved')
                ->whereIn('project_id', $projectIds->all())
                ->sum('approved_amount');
        } catch (\Throwable $e) {
            report($e);
        }

        return response()->json([
            'success' => true,
            'member' => [
                'id' => $member->id,
                'name' => $member->name,
                'email' => $member->email,
                'phone' => $member->phone,
                'role_names' => $member->role_names ?? null,
                'profile_photo_url' => $member->profile_photo_url ?? null,
            ],
            'summary' => [
                'companies_count' => $companies->count(),
                'projects_count' => $projects->count(),
                'primary_projects_count' => $primaryProjects->count(),
                'survey_plans_count' => $surveyPlans->count(),
                'pending_surveys' => $pendingSurveyPlans,
                'tasks_count' => $taskCounts,
                'attendance' => $attendanceSummary,
                'drafting_count' => $draftingJobs->count(),
                'vehicles_assigned_count' => $vehiclesAssigned->count(),
                'equipment_assigned_count' => $equipmentAssigned->count(),
                'total_approved_budget' => $totalBudgetApproved,
            ],
            'companies' => $companies,
            'projects' => $projects,
            'primary_projects' => $primaryProjects,
            'projects_by_status' => $projectStatusCounts,
            'projects_by_stage' => $projectStageCounts,
            'survey_plans' => $surveyPlans,
            'today_survey_visits' => $todayVisits,
            'tasks' => $tasks,
            'task_summary' => $taskCounts,
            'drafting_jobs' => $draftingJobs,
            'recent_dprs' => $recentDPRs,
            'vehicles_assigned' => $vehiclesAssigned,
            'equipment_assigned' => $equipmentAssigned,
            'quick_links' => [
                'assigned_projects' => '/api/member/dashboard/projects',
                'today_attendance' => '/api/member/dashboard/attendance',
                'assigned_tasks' => '/api/construction/mobile/construction/tasks/assigned',
                'survey_plans' => '/api/member/dashboard/surveys',
            ],
        ]);
    }

    public function myProjects(Request $request)
    {
        $member = $request->user();
        $memberId = $member->getKey();

        $teamProjectIds = ProjectTeamMember::where('member_id', $memberId)
            ->where('status', 'active')
            ->pluck('project_id');

       $surveyProjectIds = SurveyPlan::query()->whereHas('planMembers',fn ($query) => $query->where(
            'member_id',
            $memberId
        )
    )->pluck('project_id');
        $taskProjectIds = ExecutionTaskAssignee::where('member_id', $memberId)->where('status', 'active')->pluck('project_id');

        $projectIds = $teamProjectIds->merge($surveyProjectIds)->merge($taskProjectIds)->unique()->values();

        $query = Project::with([
            'company',
            'client',
            'latestBudget',
            'teamMembers.role',
            'surveyPlans' => function ($q) {
                $q->latest()->take(5);
            },
            'executionTasks' => function ($q) use ($memberId) {
                $q->whereHas('assignees', function ($sub) use ($memberId) {
                    $sub->where('member_id', $memberId);
                })->latest()->take(5);
            },
        ])->whereIn('id', $projectIds);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('company_id')) {
            $query->where('company_id', $request->company_id);
        }
        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }
        if ($request->filled('current_stage')) {
            $query->where('current_stage', $request->current_stage);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('project_code', 'like', "%{$search}%")
                    ->orWhere('project_address', 'like', "%{$search}%");
            });
        }

        $perPage = $request->per_page ?? 15;
        $projects = $query->latest()->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $projects->items(),
            'pagination' => [
                'total' => $projects->total(),
                'per_page' => $projects->perPage(),
                'current_page' => $projects->currentPage(),
                'last_page' => $projects->lastPage(),
            ],
        ]);
    }

    public function mySurveys(Request $request)
    {
        $member = $request->user();
        $memberId = $member->getKey();

       $validated = $request->validate([
    'status' => ['nullable', 'integer'],
]);

if (! isset($validated['status'])) {
    $status = null;
} elseif (array_key_exists((int) $validated['status'], SurveyStatus::KEYS)) {
    $status = (int) $validated['status'];
} else {
    return response()->json([
        'success' => false,
        'message' => 'The selected status is invalid.',
        'errors' => [
            'status' => ['The selected status is invalid.'],
        ],
    ], 422);
}

        $query = SurveyPlan::with([
            'project.company',
            'project.client',
            'planMembers.member',
            'visits' => function ($q) {
                $q->latest()->take(5);
            },
        ])->whereHas('planMembers', function ($q) use ($memberId) {
            $q->where('member_id', $memberId);
        });

        if ($request->filled('project_id')) {
            $query->where('project_id', $request->project_id);
        }
      if ($status !== null) {
    $query->where('status', $status);
}

        if ($request->filled('from_date')) {
            $query->whereDate('planned_date', '>=', $request->from_date);
        }
        if ($request->filled('to_date')) {
            $query->whereDate('planned_date', '<=', $request->to_date);
        }

        $perPage = $request->per_page ?? 15;
        $surveys = $query->latest()->paginate($perPage);

        $pending = 0;
        $completed = 0;
        try {
           $pending = SurveyPlan::whereHas(
    'planMembers',
    function ($q) use ($memberId) {
        $q->where('member_id', $memberId);
    }
)
    ->whereIn('status', [
        SurveyPlan::STATUS_PLANNED,
        SurveyPlan::STATUS_IN_PROGRESS,
    ])
    ->count();

$completed = SurveyPlan::whereHas(
    'planMembers',
    function ($q) use ($memberId) {
        $q->where('member_id', $memberId);
    }
)
    ->whereIn('status', [
        SurveyPlan::STATUS_SUBMITTED,
        SurveyPlan::STATUS_APPROVED,
        SurveyPlan::STATUS_REJECTED,
    ])
    ->count();
        } catch (\Throwable $e) {
            report($e);
        }

        return response()->json([
            'success' => true,
            'summary' => [
                'total' => $surveys->total(),
                'pending_inprogress' => $pending,
                'completed' => $completed,
            ],
            'data' => $surveys->items(),
            'pagination' => [
                'total' => $surveys->total(),
                'per_page' => $surveys->perPage(),
                'current_page' => $surveys->currentPage(),
                'last_page' => $surveys->lastPage(),
            ],
        ]);
    }

    public function myTasks(Request $request)
    {
        $member = $request->user();
        $memberId = $member->getKey();

        $query = ExecutionTask::with([
            'project.company',
            'project.client',
            'executionPlan',
            'supervisor',
        ])->whereHas('assignees', function ($q) use ($memberId) {
            $q->where('member_id', $memberId)->where('status', 'active');
        });

        if ($request->filled('project_id')) {
            $query->where('project_id', $request->project_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }

        $perPage = $request->per_page ?? 15;
        $tasks = $query->latest()->paginate($perPage);

        $summary = [];
        try {
            $summary = DB::table('construction_execution_task_assignees')
                ->join('construction_execution_tasks', 'construction_execution_task_assignees.execution_task_id', '=', 'construction_execution_tasks.id')
                ->where('construction_execution_task_assignees.member_id', $memberId)
                ->where('construction_execution_task_assignees.status', 'active')
                ->selectRaw('construction_execution_tasks.status, COUNT(*) as count')
                ->groupBy('construction_execution_tasks.status')
                ->pluck('count', 'status')
                ->toArray();
        } catch (\Throwable $e) {
            report($e);
        }

        return response()->json([
            'success' => true,
            'summary' => $summary,
            'data' => $tasks->items(),
            'pagination' => [
                'total' => $tasks->total(),
                'per_page' => $tasks->perPage(),
                'current_page' => $tasks->currentPage(),
                'last_page' => $tasks->lastPage(),
            ],
        ]);
    }

    public function myAttendance(Request $request)
    {
        $member = $request->user();
        $memberId = $member->getKey();

        $query = AttendanceRecord::with(['project', 'executionTask'])
            ->where('member_id', $memberId);

        if ($request->filled('project_id')) {
            $query->where('project_id', $request->project_id);
        }
        if ($request->filled('from_date')) {
            $query->whereDate('attendance_date', '>=', $request->from_date);
        }
        if ($request->filled('to_date')) {
            $query->whereDate('attendance_date', '<=', $request->to_date);
        }
        if ($request->filled('attendance_type')) {
            $query->where('attendance_type', $request->attendance_type);
        }

        $perPage = $request->per_page ?? 30;
        $records = $query->latest('attendance_date')->paginate($perPage);

        $today = now()->toDateString();
        $todayRecord = null;
        try {
            $todayRecord = AttendanceRecord::where('member_id', $memberId)
                ->where('attendance_date', $today)
                ->latest()
                ->first();
        } catch (\Throwable $e) {
            report($e);
        }

        $last30 = collect([]);
        $hoursWorked30 = 0;
        try {
            $last30 = AttendanceRecord::where('member_id', $memberId)
                ->where('attendance_date', '>=', now()->subDays(30)->toDateString())
                ->get();
            $hoursWorked30 = (float) $last30->sum('hours_worked');
        } catch (\Throwable $e) {
            report($e);
        }

        $summary = [
            'today' => [
                'checked_in' => (bool) $todayRecord,
                'record' => $todayRecord,
            ],
            'last_30_days' => [
                'total_records' => $last30->count(),
                'present' => $last30->where('attendance_type', 'present')->count(),
                'half_day' => $last30->where('attendance_type', 'half_day')->count(),
                'overtime' => $last30->where('attendance_type', 'overtime')->count(),
                'total_hours' => $hoursWorked30,
            ],
        ];

        return response()->json([
            'success' => true,
            'summary' => $summary,
            'data' => $records->items(),
            'pagination' => [
                'total' => $records->total(),
                'per_page' => $records->perPage(),
                'current_page' => $records->currentPage(),
                'last_page' => $records->lastPage(),
            ],
        ]);
    }

    public function projectDetail(Request $request, Project $project)
    {
        $member = $request->user();
        $memberId = $member->getKey();
        $now = now();

        $isAssigned = ProjectTeamMember::where('project_id', $project->id)
            ->where('member_id', $memberId)
            ->where('status', 'active')
            ->exists();

        $isSurveyMember = SurveyPlanMember::where('member_id', $memberId)
            ->whereIn('survey_plan_id', SurveyPlan::where('project_id', $project->id)->pluck('id'))
            ->exists();

        $isTaskAssignee = ExecutionTaskAssignee::where('member_id', $memberId)
            ->where('status', 'active')
            ->whereIn('execution_task_id', ExecutionTask::where('project_id', $project->id)->pluck('id'))
            ->exists();

        if (! ($isAssigned || $isSurveyMember || $isTaskAssignee)) {
            return response()->json([
                'success' => false,
                'message' => 'You are not assigned to this project.',
            ], 403);
        }

        $teamRole = ProjectTeamMember::where('project_id', $project->id)
            ->where('member_id', $memberId)
            ->where('status', 'active')
            ->with('role')
            ->first();

        $project->load([
            'company',
            'client',
            'budgets' => fn ($q) => $q->latest('version_no'),
            'teamMembers.member',
            'teamMembers.role',
        ]);

        $surveyPlans = collect([]);
        try {
            $surveyPlans = SurveyPlan::with([
                'planMembers.member',
                'visits.checkedInBy',
                'visits.entries.capturedBy',
                'visits.measurements.capturedBy',
                'visits.submission',
            ])
                ->where('project_id', $project->id)
                ->latest()
                ->get();
        } catch (\Throwable $e) {
            report($e);
        }

        $mySurveyPlans = $surveyPlans->filter(function ($plan) use ($memberId) {
            return $plan->planMembers->contains('member_id', $memberId);
        })->values();

        $tasks = collect([]);
        try {
            $tasks = ExecutionTask::with(['executionPlan', 'supervisor', 'assignees.member'])
                ->where('project_id', $project->id)
                ->latest()
                ->get();
        } catch (\Throwable $e) {
            report($e);
        }

        $myTasks = $tasks->filter(function ($task) use ($memberId) {
            return $task->assignees->contains(fn ($a) => $a->member_id == $memberId && $a->status === 'active');
        })->values();

        $draftingJobs = collect([]);
        try {
            $draftingJobs = DraftingJob::with(['assignedTo', 'drawingRevisions.uploadedBy'])
                ->where('project_id', $project->id)
                ->latest()
                ->get();
        } catch (\Throwable $e) {
            report($e);
        }
        $myDraftingJobs = $draftingJobs->where('assigned_to_member_id', $memberId)->values();

        $materials = collect([]);
        $stocks = collect([]);
        try {
            $materials = Material::with('stocks')
                ->where('project_id', $project->id)
                ->latest()
                ->get();

            $stocks = MaterialStock::with('material')
                ->where('project_id', $project->id)
                ->latest()
                ->get();
        } catch (\Throwable $e) {
            report($e);
        }

        $vehicles = collect([]);
        $myVehicles = collect([]);
        try {
            $vehicles = ConstructionVehicle::with(['assignments' => fn ($q) => $q->latest()->take(3)])
                ->where('project_id', $project->id)
                ->latest()
                ->get();

            $myVehicles = $vehicles->filter(function ($v) use ($memberId, $now) {
                return $v->assignments->contains(function ($a) use ($memberId, $now) {
                    return $a->driver_member_id == $memberId &&
                           $a->status === 'active' &&
                           (is_null($a->assigned_to) || $a->assigned_to >= $now);
                });
            })->values();
        } catch (\Throwable $e) {
            report($e);
        }

        $equipments = collect([]);
        $myEquipments = collect([]);
        try {
            $equipments = \App\Models\ConstructionEquipment::with([
                'allocations' => fn ($q) => $q->latest()->take(3),
            ])
                ->where('project_id', $project->id)
                ->latest()
                ->get();

            $myEquipments = $equipments->filter(function ($e) use ($memberId) {
                return $e->allocations->contains(function ($a) use ($memberId) {
                    return $a->assigned_to_member_id == $memberId &&
                           is_null($a->returned_at) &&
                           $a->status === 'active';
                });
            })->values();
        } catch (\Throwable $e) {
            report($e);
        }

        $invoices = \App\Models\ClientInvoice::with(['items', 'payments'])
            ->where('project_id', $project->id)
            ->latest()
            ->get();

        $payments = \App\Models\ClientPayment::where('project_id', $project->id)
            ->latest()
            ->get();

        $handovers = \App\Models\ProjectHandover::with(['items', 'finalDocument'])
            ->where('project_id', $project->id)
            ->latest()
            ->get();

        $dprs = collect([]);
        $attendance = collect([]);
        try {
            $dprs = DailyProgressReport::with(['items', 'submittedBy'])
                ->where('project_id', $project->id)
                ->latest('report_date')
                ->take(20)
                ->get();

            $attendance = AttendanceRecord::with(['member'])
                ->where('project_id', $project->id)
                ->latest('attendance_date')
                ->take(50)
                ->get();
        } catch (\Throwable $e) {
            report($e);
        }

        $myAttendance = $attendance->where('member_id', $memberId)->values();

        $taskSummary = [
            'total' => $tasks->count(),
            'planned' => $tasks->where('status', 'planned')->count(),
            'in_progress' => $tasks->where('status', 'in_progress')->count(),
            'completed' => $tasks->where('status', 'completed')->count(),
            'blocked' => $tasks->where('status', 'blocked')->count(),
            'assigned_to_me' => $myTasks->count(),
        ];

       $surveySummary = [
    'total' => $surveyPlans->count(),
     'pending' => $surveyPlans
        ->where(
            'status',
            SurveyPlan::STATUS_PLANNED
        )
        ->count(),

    'in_progress' => $surveyPlans
        ->where(
            'status',
            SurveyPlan::STATUS_IN_PROGRESS
        )
        ->count(),

    'submitted' => $surveyPlans
        ->where(
            'status',
            SurveyPlan::STATUS_SUBMITTED
        )
        ->count(),

    'assigned_to_me' => $mySurveyPlans->count(),
];
        $invoiceTotal = 0;
        $paymentTotal = 0;
        try {
            $invoiceTotal = (float) $invoices->sum('total_amount');
            $paymentTotal = (float) $payments->sum('amount');
        } catch (\Throwable $e) {
            report($e);
        }

        $financeSummary = [
            'budget_total_invoices' => $invoiceTotal,
            'paid_total' => $paymentTotal,
            'pending' => $invoiceTotal - $paymentTotal,
            'invoices_count' => $invoices->count(),
            'payments_count' => $payments->count(),
        ];

        return response()->json([
            'success' => true,
            'my_assignment' => [
                'role_in_project' => $teamRole,
                'is_team_member' => $isAssigned,
                'is_survey_member' => $isSurveyMember,
                'is_task_assignee' => $isTaskAssignee,
            ],
            'project' => $project,
            'summary' => [
                'tasks' => $taskSummary,
                'surveys' => $surveySummary,
                'finance' => $financeSummary,
                'materials_count' => $materials->count(),
                'vehicles_count' => $vehicles->count(),
                'equipment_count' => $equipments->count(),
                'dpr_count' => $dprs->count(),
            ],
            'modules' => [
                'survey_plans' => $surveyPlans,
                'my_survey_plans' => $mySurveyPlans,
                'tasks' => $tasks,
                'my_tasks' => $myTasks,
                'drafting_jobs' => $draftingJobs,
                'my_drafting_jobs' => $myDraftingJobs,
                'materials' => $materials,
                'material_stocks' => $stocks,
                'vehicles' => $vehicles,
                'my_vehicles' => $myVehicles,
                'equipment' => $equipments,
                'my_equipment' => $myEquipments,
                'invoices' => $invoices,
                'payments' => $payments,
                'handovers' => $handovers,
                'daily_progress' => $dprs,
                'attendance' => $attendance,
                'my_attendance' => $myAttendance,
            ],
        ]);
    }
}
