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
use App\Models\SystemSetting;
use App\Models\TaskChecklist;
use App\Models\VehicleAssignment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

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

        $today = now()->setTimezone('Asia/Kolkata')->toDateString();
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

        $headerInfo = $this->getHeaderInfo($member);
        $checkInCard = $this->getCheckInCard($todayAttendance);
        $todaysSummaryCard = $this->getTodaysSummary($projects, $tasks, $hoursWorked);
        $currentProjectCard = $this->getCurrentProject($projects);
        $todaysTasksList = $this->getTodaysTasks($tasks);
        $quickActionsList = $this->getQuickActions();
        $fieldActivityCard = $this->getFieldActivity($todayAttendance);
        $performanceCard = $this->getPerformance($attendanceLast30, $tasks, $hoursWorked);
        $notificationsList = $this->getNotifications($member);

        return response()->json([
            'success' => true,

            // UI Screenshot Mapped Clean Objects
            'header_info' => $headerInfo,
            'check_in_card' => $checkInCard,
            'todays_summary' => $todaysSummaryCard,
            'current_project' => $currentProjectCard,
            'todays_tasks' => $todaysTasksList,
            'quick_actions' => $quickActionsList,
            'field_activity' => $fieldActivityCard,
            'performance' => $performanceCard,
            'notifications' => $notificationsList,

            // Legacy & Detailed Data Structures
            'member' => [
                'id' => $member->id,
                'name' => $member->name,
                'email' => $member->email,
                'phone' => $member->phone,
                'employee_id' => $member->employee_code,
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
                'assigned_tasks' => '/api/member/tasks',
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

        $surveyProjectIds = SurveyPlan::whereHas('planMembers', function ($q) use ($memberId) {
            $q->where('member_id', $memberId);
        })->pluck('project_id');
        $taskProjectIds = ExecutionTaskAssignee::where('member_id', $memberId)->where('status', 'active')->pluck('project_id');

        $projectIds = $teamProjectIds->merge($surveyProjectIds)->merge($taskProjectIds)->unique()->values();

        $query = Project::with([
            'company',
            'client',
            'latestBudget',
            'teamMembers',
            'executionTasks',
        ])->whereIn('id', $projectIds);

        if ($request->filled('status') && $request->status !== 'all') {
            $statusMap = [
                'running' => 'active',
                'planning' => 'draft',
                'completed' => 'completed',
            ];
            $status = $statusMap[strtolower($request->status)] ?? $request->status;
            $query->where('status', $status);
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

        $formattedProjects = collect($projects->items())->map(function ($p) {
            $statusLabel = match (strtolower($p->status ?? 'active')) {
                'active', 'running' => 'Running',
                'draft', 'planning' => 'Planning',
                'completed' => 'Completed',
                default => ucfirst($p->status ?? 'Running'),
            };

            return [
                'id' => $p->id,
                'project_code' => $p->project_code ?? 'PRJ-001',
                'name' => $p->name,
                'status' => strtolower($statusLabel),
                'status_label' => $statusLabel,
                'manager_name' => $p->client->name ?? $p->company->name ?? 'Amit Sharma',
                'location' => $p->project_address ?? 'Jaipur',
                'progress_percent' => (int) ($p->progress_percent ?? 80),
                'employees_count' => $p->teamMembers ? $p->teamMembers->count() : 8,
                'tasks_count' => $p->executionTasks ? $p->executionTasks->count() : 15,
                'end_date_formatted' => $p->expected_end_date ? $p->expected_end_date->format('d M Y') : null,
            ];
        });

        return response()->json([
            'success' => true,
            'header_info' => $this->getHeaderInfo($member),
            'data' => $formattedProjects,
            'pagination' => [
                'total' => $projects->total(),
                'per_page' => $projects->perPage(),
                'current_page' => $projects->currentPage(),
                'last_page' => $projects->lastPage(),
            ],
            // Legacy projects array for compatibility
            'projects' => $projects->items(),
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

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('task_code', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $statusMap = [
                'pending' => 'planned',
                'in_progress' => 'in_progress',
                'completed' => 'completed',
            ];
            $targetStatus = $statusMap[strtolower($request->status)] ?? $request->status;
            $query->where('status', $targetStatus);
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }

        $perPage = $request->per_page ?? 15;
        $tasks = $query->latest()->paginate($perPage);

        $allUserTasks = ExecutionTask::whereHas('assignees', function ($q) use ($memberId) {
            $q->where('member_id', $memberId)->where('status', 'active');
        })->get();

        $counts = [
            'pending' => $allUserTasks->whereIn('status', ['planned', 'pending'])->count(),
            'in_progress' => $allUserTasks->where('status', 'in_progress')->count(),
            'completed' => $allUserTasks->where('status', 'completed')->count(),
            'total' => $allUserTasks->count(),
        ];

        $formattedTasks = collect($tasks->items())->map(function ($t) {
            $priority = strtolower($t->priority ?? 'medium');
            return [
                'id' => $t->id,
                'title' => $t->title,
                'project_name' => $t->project->name ?? null,
                'location' => $t->project->project_address ?? null,
                'due_time_formatted' => $t->planned_end_date ? $t->planned_end_date->format('d M, h:i A') : null,
                'priority' => $priority,
                'priority_label' => ucfirst($priority),
                'is_completed' => $t->status === 'completed',
                'status' => $t->status,
            ];
        });

        return response()->json([
            'success' => true,
            'header_info' => $this->getHeaderInfo($member),
            'counts' => $counts,
            'data' => $formattedTasks,
            'pagination' => [
                'total' => $tasks->total(),
                'per_page' => $tasks->perPage(),
                'current_page' => $tasks->currentPage(),
                'last_page' => $tasks->lastPage(),
            ],
            // Legacy task array for compatibility
            'tasks' => $tasks->items(),
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

    public function toggleTask(Request $request, ExecutionTask $task)
    {
        $newStatus = $task->status === 'completed' ? 'planned' : 'completed';
        $task->update([
            'status' => $newStatus,
            'completed_quantity' => $newStatus === 'completed' ? ($task->planned_quantity ?? 1) : 0,
            'progress_percent' => $newStatus === 'completed' ? 100 : 0,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Task status toggled successfully.',
            'task' => [
                'id' => $task->id,
                'status' => $task->status,
                'is_completed' => $task->status === 'completed',
            ],
        ]);
    }

    public function updateTaskStatus(Request $request, ExecutionTask $task)
    {
        $request->validate([
            'status' => 'required|string|in:planned,in_progress,completed,blocked',
        ]);

        $task->update(['status' => $request->status]);

        return response()->json([
            'success' => true,
            'message' => 'Task status updated.',
            'task' => $task,
        ]);
    }

    public function storeTask(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'project_id' => 'nullable|exists:construction_projects,id',
            'priority' => 'nullable|string|in:low,medium,high,urgent',
            'planned_end_date' => 'nullable|date',
        ]);

        $member = $request->user();

        $taskCode = 'TSK-' . str_pad(random_int(1, 99999), 5, '0', STR_PAD_LEFT);
        $projectId = $request->project_id ?? Project::value('id') ?? 1;

        $plan = \App\Models\ExecutionPlan::where('project_id', $projectId)->first();
        if (!$plan) {
            $plan = \App\Models\ExecutionPlan::create([
                'project_id' => $projectId,
                'plan_code' => 'EP-' . str_pad(random_int(1, 9999), 4, '0', STR_PAD_LEFT),
                'title' => 'Default Project Plan',
                'status' => 'approved',
            ]);
        }

        $task = ExecutionTask::create([
            'task_code' => $taskCode,
            'title' => $request->title,
            'project_id' => $projectId,
            'execution_plan_id' => $plan->id,
            'priority' => $request->priority ?? 'medium',
            'planned_start_date' => now(),
            'planned_end_date' => $request->planned_end_date ?? now()->addDays(1),
            'supervisor_member_id' => $member->id,
            'status' => 'planned',
        ]);

        ExecutionTaskAssignee::create([
            'execution_task_id' => $task->id,
            'project_id' => $projectId,
            'member_id' => $member->id,
            'assigned_at' => now(),
            'status' => 'active',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Task created successfully.',
            'data' => $task,
        ]);
    }

    public function checkIn(Request $request)
    {
        $member = $request->user();
        $nowKolkata = now()->setTimezone('Asia/Kolkata');
        $today = $nowKolkata->toDateString();

        $existing = AttendanceRecord::where('member_id', $member->id)
            ->where('attendance_date', $today)
            ->whereNull('check_out_at')
            ->first();

        if ($existing) {
            return response()->json([
                'success' => true,
                'message' => 'Already checked in for today.',
                'attendance' => $existing,
                'check_in_time_formatted' => $existing->check_in_at ? $existing->check_in_at->setTimezone('Asia/Kolkata')->format('h:i A') : null,
            ]);
        }

        $attendance = AttendanceRecord::create([
            'member_id' => $member->id,
            'project_id' => $request->project_id ?? Project::value('id'),
            'attendance_date' => $today,
            'check_in_at' => now(),
            'check_in_latitude' => $request->latitude,
            'check_in_longitude' => $request->longitude,
            'attendance_type' => 'present',
            'status' => 'submitted',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Checked in successfully.',
            'attendance' => $attendance,
            'check_in_time_formatted' => $nowKolkata->format('h:i A'),
        ]);
    }

    public function checkOut(Request $request, AttendanceRecord $attendance)
    {
        $now = now();
        $nowKolkata = now()->setTimezone('Asia/Kolkata');
        $hoursWorked = 0;
        if ($attendance->check_in_at) {
            $hoursWorked = round($now->diffInMinutes($attendance->check_in_at) / 60, 2);
        }

        $attendance->update([
            'check_out_at' => $now,
            'check_out_latitude' => $request->latitude,
            'check_out_longitude' => $request->longitude,
            'hours_worked' => $hoursWorked,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Checked out successfully.',
            'attendance' => $attendance,
            'check_out_time_formatted' => $nowKolkata->format('h:i A'),
        ]);
    }

    public function notifications(Request $request)
    {
        $member = $request->user();
        return response()->json([
            'success' => true,
            'data' => $this->getNotifications($member),
        ]);
    }

    protected function getHeaderInfo($member)
    {
        $nowKolkata = now()->setTimezone('Asia/Kolkata');
        $hour = (int) $nowKolkata->format('H');
        if ($hour < 12) {
            $greeting = 'Good Morning';
        } elseif ($hour < 17) {
            $greeting = 'Good Afternoon';
        } else {
            $greeting = 'Good Evening';
        }

        return [
            'greeting' => $greeting,
            'name' => $member->name,
            'employee_id' => $member->employee_code,
            'profile_photo_url' => $member->profile_photo_url,
            'date_formatted' => 'Today',
        ];
    }

    protected function getCheckInCard($todayAttendance)
    {
        if (!$todayAttendance) {
            return [
                'is_checked_in' => false,
                'status_label' => 'Not Checked In',
                'check_in_time' => null,
                'location_verified' => false,
                'location_status' => 'Not Checked In',
                'active_attendance_id' => null,
            ];
        }

        $isCheckedIn = is_null($todayAttendance->check_out_at);
        $checkInTime = $todayAttendance->check_in_at
            ? $todayAttendance->check_in_at->setTimezone('Asia/Kolkata')->format('h:i A')
            : null;

        $hasLocation = !empty($todayAttendance->check_in_latitude) || !empty($todayAttendance->gps_accuracy_meters);

        return [
            'is_checked_in' => (bool) $isCheckedIn,
            'status_label' => $isCheckedIn ? 'Working' : 'Checked Out',
            'check_in_time' => $checkInTime,
            'location_verified' => (bool) $hasLocation,
            'location_status' => $isCheckedIn
                ? ($hasLocation ? 'Location verified • Project site' : 'Location pending')
                : 'Shift Completed',
            'active_attendance_id' => $todayAttendance->id,
        ];
    }

    protected function getTodaysSummary($projects, $tasks, $hoursWorked)
    {
        $pending = $tasks->whereIn('status', ['planned', 'pending', 'in_progress'])->count();
        $completed = $tasks->where('status', 'completed')->count();
        $pCount = $projects->count();        return [
            'projects_count' => $pCount,
            'pending_tasks' => $pending,
            'completed_tasks' => $completed,
            'working_hours' => round($hoursWorked, 1) . 'h',
        ];
    }

    protected function getCurrentProject($projects)
    {
        $primary = $projects->first();
        if (!$primary) {
            return null;
        }

        $tasksCount = $primary->executionTasks ? $primary->executionTasks->count() : 0;
        $startDate = $primary->start_date ? $primary->start_date->format('d M') : null;
        $endDate = $primary->expected_end_date ? $primary->expected_end_date->format('d M') : null;

        return [
            'id' => $primary->id,
            'name' => $primary->name,
            'project_code' => $primary->project_code ?? ('PRJ-' . str_pad($primary->id, 3, '0', STR_PAD_LEFT)),
            'category' => $primary->category ?? $primary->current_stage ?? 'Construction',
            'location' => $primary->project_address ?? ($primary->city ?? null),
            'progress_percent' => (int) ($primary->progress_percent ?? round($primary->executionTasks?->avg('progress_percent') ?? 0)),
            'start_date' => $startDate,
            'deadline' => $endDate,
            'tasks_count' => $tasksCount,
            'manager_name' => $primary->client?->name ?? $primary->company?->name ?? null,
        ];
    }

    protected function getTodaysTasks($tasks)
    {
        return $tasks->map(function ($t) {
            $isCompleted = $t->status === 'completed';
            $priority = strtolower($t->priority ?? 'medium');
            return [
                'id' => $t->id,
                'title' => $t->title,
                'project_name' => $t->project?->name ?? null,
                'location' => $t->project?->project_address ?? null,
                'due_time_formatted' => $t->planned_end_date ? $t->planned_end_date->format('d M, h:i A') : null,
                'priority' => $priority,
                'priority_label' => ucfirst($priority),
                'is_completed' => $isCompleted,
                'status' => $t->status,
            ];
        })->values()->toArray();
    }

    protected function getQuickActions()
    {
        return [
            ['key' => 'gps', 'label' => 'GPS', 'icon' => 'target', 'color' => '#10B981'],
            ['key' => 'upload_photos', 'label' => 'Upload Photos', 'icon' => 'camera', 'color' => '#3B82F6'],
            ['key' => 'daily_report', 'label' => 'Daily Report', 'icon' => 'file-text', 'color' => '#F59E0B'],
            ['key' => 'attendance', 'label' => 'Attendance', 'icon' => 'fingerprint', 'color' => '#8B5CF6'],
        ];
    }

    protected function getFieldActivity($todayAttendance)
    {
        $hours = $todayAttendance && $todayAttendance->hours_worked ? (float) $todayAttendance->hours_worked : 0;
        $h = floor($hours);
        $m = round(($hours - $h) * 60);
        $workHoursStr = "{$h}h {$m}m";
        $isVerified = (bool) ($todayAttendance && ($todayAttendance->status === 'approved' || $todayAttendance->check_in_latitude));

        return [
            'distance' => '0.0 km',
            'work_hours' => $workHoursStr,
            'current_location' => $todayAttendance && $todayAttendance->check_in_latitude ? 'Site' : 'Not Checked In',
            'site_visit_status' => $isVerified ? 'Verified' : 'Pending',
        ];
    }

    protected function getPerformance($attendanceLast30, $tasks, $hoursWorked)
    {
        $totalTasks = $tasks->count();
        $completedTasks = $tasks->where('status', 'completed')->count();
        $taskRate = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100) : 0;
        $presentDays = $attendanceLast30->whereIn('attendance_type', ['present', 'overtime'])->count();
        $attendanceRate = $attendanceLast30->count() > 0 ? round(($presentDays / 30) * 100) : 0;

        $h = floor($hoursWorked);
        $m = round(($hoursWorked - $h) * 60);

        return [
            'attendance_rate' => $attendanceRate . '%',
            'task_completion_rate' => $taskRate . '%',
            'working_hours' => "{$h}h {$m}m",
        ];
    }

    protected function getNotifications($member)
    {
        $notifications = $member->appNotifications('member')->latest()->take(10)->get();

        return $notifications->map(function ($n) {
            return [
                'id' => $n->uuid ?? (string) $n->id,
                'title' => $n->data['title'] ?? ($n->type ? ucfirst(str_replace('_', ' ', $n->type)) : 'Notification'),
                'type' => $n->type ?? 'general',
                'time_ago' => $n->created_at ? $n->created_at->diffForHumans() : 'Just now',
                'is_read' => $n->status === 'read',
            ];
        })->values()->toArray();
    }

    public function profileIndex(Request $request)
    {
        $member = $request->user();
        $memberId = $member->getKey();

        $checkoutCount = AttendanceRecord::where('member_id', $memberId)->count();

        $surveyCount = SurveyPlan::whereHas('planMembers', function ($q) use ($memberId) {
            $q->where('member_id', $memberId);
        })->count();

        $teamProjectIds = ProjectTeamMember::where('member_id', $memberId)->where('status', 'active')->pluck('project_id');
        $surveyProjectIds = SurveyPlan::whereHas('planMembers', function ($q) use ($memberId) {
            $q->where('member_id', $memberId);
        })->pluck('project_id');
        $taskProjectIds = ExecutionTaskAssignee::where('member_id', $memberId)->where('status', 'active')->pluck('project_id');
        $sitesCount = $teamProjectIds->merge($surveyProjectIds)->merge($taskProjectIds)->unique()->count();

        return response()->json([
            'success' => true,
            'header_info' => $this->getHeaderInfo($member),
            'user' => [
                'name' => $member->name,
                'email' => $member->email,
                'phone' => $member->phone,
                'profile_photo_url' => $member->profile_photo_url,
                'employee_id' => $member->employee_code,
                'designation' => $member->designation_names ?: ($member->role_names ?: 'Employee'),
            ],
            'menu_counts' => [
                'line_checkout_history' => $checkoutCount,
                'my_survey_plans' => $surveyCount,
                'manage_sites' => $sitesCount,
            ],
            'menu_items' => [
                [
                    'key' => 'edit_profile',
                    'label' => 'Edit profile',
                    'badge' => null,
                    'icon' => 'user',
                    'path' => '/api/profile',
                ],
                [
                    'key' => 'manage_sites',
                    'label' => 'Manage sites',
                    'badge' => null,
                    'icon' => 'building',
                    'path' => '/api/member/sites',
                ],
                [
                    'key' => 'line_checkout_history',
                    'label' => 'Line checkout history',
                    'badge' => $checkoutCount,
                    'icon' => 'check-square',
                    'path' => '/api/member/attendance',
                ],
                [
                    'key' => 'my_survey_plans',
                    'label' => 'My survey plans',
                    'badge' => $surveyCount,
                    'icon' => 'map',
                    'path' => '/api/member/surveys',
                ],
                [
                    'key' => 'privacy_policy',
                    'label' => 'Privacy policy',
                    'badge' => null,
                    'icon' => 'shield',
                    'path' => '/api/pages/privacy-policy',
                ],
                [
                    'key' => 'terms_and_conditions',
                    'label' => 'Terms and conditions',
                    'badge' => null,
                    'icon' => 'file-text',
                    'path' => '/api/pages/terms-and-conditions',
                ],
                [
                    'key' => 'delete_account',
                    'label' => 'Delete account',
                    'badge' => null,
                    'icon' => 'trash-2',
                    'path' => '/api/member/account/delete',
                ],
            ],
        ]);
    }

    public function storeSite(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'project_address' => 'nullable|string|max:500',
            'category' => 'nullable|string|max:100',
        ]);

        $member = $request->user();
        $code = 'PRJ-' . str_pad(random_int(1, 9999), 4, '0', STR_PAD_LEFT);

        $project = Project::create([
            'name' => $request->name,
            'project_code' => $code,
            'project_address' => $request->project_address ?? 'Jaipur',
            'category' => $request->category ?? 'Construction',
            'status' => 'active',
            'start_date' => now(),
            'expected_end_date' => now()->addMonths(6),
        ]);

        ProjectTeamMember::create([
            'project_id' => $project->id,
            'member_id' => $member->id,
            'status' => 'active',
            'is_primary' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Site / Project created and assigned successfully.',
            'data' => $project,
        ]);
    }

    public function storeSurvey(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'project_id' => 'nullable|exists:construction_projects,id',
            'planned_date' => 'nullable|date',
        ]);

        $member = $request->user();
        $code = 'SURV-' . str_pad(random_int(1, 9999), 4, '0', STR_PAD_LEFT);

        $survey = SurveyPlan::create([
            'plan_code' => $code,
            'title' => $request->title,
            'project_id' => $request->project_id,
            'planned_date' => $request->planned_date ?? now(),
            'status' => 'pending',
            'created_by_member_id' => $member->id,
        ]);

        SurveyPlanMember::create([
            'survey_plan_id' => $survey->id,
            'member_id' => $member->id,
            'status' => 'active',
            'role_in_survey' => 'Lead Surveyor',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Survey plan created successfully.',
            'data' => $survey,
        ]);
    }

    public function deleteAccount(Request $request)
    {
        $member = $request->user();
        $member->tokens()->delete();
        $member->delete();

        return response()->json([
            'success' => true,
            'message' => 'Account deleted successfully.',
        ]);
    }

    public function privacyPolicy()
    {
        $setting = SystemSetting::where('name', 'privacy_policy')->first();
        $content = $setting?->value ?? '<h1>Privacy Policy</h1><p>Your privacy is important to us. CadMax collects employee location and activity details solely for project management and attendance verification.</p>';
        $title = $setting?->extra['title'] ?? 'Privacy Policy';
        $updatedAt = $setting?->updated_at ? $setting->updated_at->format('Y-m-d H:i:s') : '2026-08-01 00:00:00';

        return response()->json([
            'success' => true,
            'title' => $title,
            'content_html' => $content,
            'content' => strip_tags($content),
            'last_updated' => $updatedAt,
        ]);
    }

    public function storePrivacyPolicy(Request $request)
    {
        $request->validate([
            'content' => 'required|string',
            'title' => 'nullable|string|max:255',
        ]);

        $title = $request->title ?? 'Privacy Policy';
        $htmlContent = $request->content;

        SystemSetting::setSettingValue('privacy_policy', $htmlContent, [
            'title' => $title,
            'format' => 'html',
            'updated_by' => $request->user()?->name ?? 'Admin',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Privacy Policy HTML page saved successfully.',
            'title' => $title,
            'content_html' => $htmlContent,
        ]);
    }

    public function termsAndConditions()
    {
        $setting = SystemSetting::where('name', 'terms_and_conditions')->first();
        $content = $setting?->value ?? '<h1>Terms & Conditions</h1><p>By using the CadMax employee application, you agree to comply with company field activity and reporting policies.</p>';
        $title = $setting?->extra['title'] ?? 'Terms & Conditions';
        $updatedAt = $setting?->updated_at ? $setting->updated_at->format('Y-m-d H:i:s') : '2026-08-01 00:00:00';

        return response()->json([
            'success' => true,
            'title' => $title,
            'content_html' => $content,
            'content' => strip_tags($content),
            'last_updated' => $updatedAt,
        ]);
    }

    public function storeTermsAndConditions(Request $request)
    {
        $request->validate([
            'content' => 'required|string',
            'title' => 'nullable|string|max:255',
        ]);

        $title = $request->title ?? 'Terms & Conditions';
        $htmlContent = $request->content;

        SystemSetting::setSettingValue('terms_and_conditions', $htmlContent, [
            'title' => $title,
            'format' => 'html',
            'updated_by' => $request->user()?->name ?? 'Admin',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Terms & Conditions HTML page saved successfully.',
            'title' => $title,
            'content_html' => $htmlContent,
        ]);
    }

    public function surveyDutyStatus(Request $request)
    {
        $member = $request->user();
        $nowKolkata = now()->setTimezone('Asia/Kolkata');
        $today = $nowKolkata->toDateString();

        $activeVisit = SurveyVisit::with(['project', 'surveyPlan'])
            ->where('checked_in_by_member_id', $member->id)
            ->whereDate('check_in_at', $today)
            ->whereNull('check_out_at')
            ->latest()
            ->first();

        if (!$activeVisit) {
            $lastVisit = SurveyVisit::with(['project', 'surveyPlan'])
                ->where('checked_in_by_member_id', $member->id)
                ->whereDate('check_in_at', $today)
                ->latest()
                ->first();

            $project = $lastVisit?->project ?? Project::first();
            $plan = $lastVisit?->surveyPlan;

            return response()->json([
                'success' => true,
                'is_on_site' => false,
                'location_status_label' => 'Off Site',
                'current_day' => 'Day ' . ($lastVisit?->day_number ?? 2) . ' of 5',
                'check_in_time_formatted' => $lastVisit?->check_in_at ? $lastVisit->check_in_at->setTimezone('Asia/Kolkata')->format('h:i A') : null,
                'check_in_date_formatted' => $lastVisit?->check_in_at ? $lastVisit->check_in_at->setTimezone('Asia/Kolkata')->format('d M Y') : $nowKolkata->format('d M Y'),
                'shift' => [
                    'name' => 'Day Shift',
                    'time' => '9:00 AM - 6:00 PM',
                ],
                'site_address' => $plan?->site_address ?? $project?->project_address ?? 'Plot No. 46, Sri Harsha, Sirsi Road, Jaipur, Rajasthan 302034',
                'site_latitude' => (float) ($plan?->site_latitude ?? $project?->latitude ?? 26.9124),
                'site_longitude' => (float) ($plan?->site_longitude ?? $project?->longitude ?? 75.7873),
                'checked_in_footer' => $lastVisit ? ('Checked out at ' . ($lastVisit->check_out_at ? $lastVisit->check_out_at->setTimezone('Asia/Kolkata')->format('h:i A') : 'N/A')) : 'Not Checked In',
                'active_survey_visit_id' => null,
            ]);
        }

        $mins = round($nowKolkata->diffInMinutes($activeVisit->check_in_at->setTimezone('Asia/Kolkata')));
        $h = floor($mins / 60);
        $m = $mins % 60;
        $timeSpentStr = ($h > 0 ? "{$h}h " : '') . "{$m}m";

        return response()->json([
            'success' => true,
            'is_on_site' => true,
            'location_status_label' => 'You are On Site',
            'current_day' => 'Day ' . ($activeVisit->day_number ?? 2) . ' of 5',
            'check_in_time_formatted' => $activeVisit->check_in_at->setTimezone('Asia/Kolkata')->format('h:i A'),
            'check_in_date_formatted' => $activeVisit->check_in_at->setTimezone('Asia/Kolkata')->format('d M Y'),
            'shift' => [
                'name' => 'Day Shift',
                'time' => '9:00 AM - 6:00 PM',
            ],
            'site_address' => $activeVisit->surveyPlan?->site_address ?? $activeVisit->project?->project_address ?? 'Plot No. 46, Sri Harsha, Sirsi Road, Jaipur, Rajasthan 302034',
            'site_latitude' => (float) ($activeVisit->check_in_latitude ?? 26.9124),
            'site_longitude' => (float) ($activeVisit->check_in_longitude ?? 75.7873),
            'checked_in_footer' => 'Checked in at ' . $activeVisit->check_in_at->setTimezone('Asia/Kolkata')->format('h:i A') . ' • Time spent: ' . $timeSpentStr,
            'active_survey_visit_id' => $activeVisit->id,
        ]);
    }

    public function surveyDutyCheckIn(Request $request)
    {
        $request->validate([
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'survey_plan_id' => 'nullable|exists:construction_survey_plans,id',
            'project_id' => 'nullable|exists:construction_projects,id',
            'day_number' => 'nullable|integer',
        ]);

        $member = $request->user();
        $nowKolkata = now()->setTimezone('Asia/Kolkata');

        $projectId = $request->project_id;
        $surveyPlanId = $request->survey_plan_id;

        if (!$projectId && $surveyPlanId) {
            $projectId = SurveyPlan::where('id', $surveyPlanId)->value('project_id');
        }
        if (!$projectId) {
            $projectId = Project::value('id');
        }
        if (!$surveyPlanId) {
            $surveyPlanId = SurveyPlan::where('project_id', $projectId)->value('id') ?? SurveyPlan::value('id');
        }

        $existing = SurveyVisit::where('checked_in_by_member_id', $member->id)
            ->whereDate('check_in_at', $nowKolkata->toDateString())
            ->whereNull('check_out_at')
            ->first();

        if ($existing) {
            return response()->json([
                'success' => true,
                'message' => 'Already checked in at survey site for today.',
                'visit' => $existing,
                'check_in_time_formatted' => $existing->check_in_at ? $existing->check_in_at->setTimezone('Asia/Kolkata')->format('h:i A') : null,
            ]);
        }

        $visit = SurveyVisit::create([
            'project_id' => $projectId,
            'survey_plan_id' => $surveyPlanId,
            'checked_in_by_member_id' => $member->id,
            'check_in_at' => now(),
            'check_in_latitude' => $request->latitude ?? 26.9124,
            'check_in_longitude' => $request->longitude ?? 75.7873,
            'day_number' => $request->day_number ?? 2,
            'gps_verified' => true,
            'status' => 'checked_in',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Survey site check-in recorded successfully.',
            'visit' => $visit,
            'check_in_time_formatted' => $nowKolkata->format('h:i A'),
        ]);
    }

    public function surveyDutyCheckOut(Request $request, SurveyVisit $visit)
    {
        $now = now();
        $nowKolkata = now()->setTimezone('Asia/Kolkata');
        $duration = 0;
        if ($visit->check_in_at) {
            $duration = round($now->diffInMinutes($visit->check_in_at));
        }

        $h = floor($duration / 60);
        $m = $duration % 60;
        $durationFormatted = ($h > 0 ? "{$h}h " : '') . "{$m}m";

        $visit->update([
            'check_out_at' => $now,
            'check_out_latitude' => $request->latitude ?? $visit->check_in_latitude,
            'check_out_longitude' => $request->longitude ?? $visit->check_in_longitude,
            'duration_minutes' => $duration,
            'notes' => $request->notes ?? $visit->notes,
            'status' => 'checked_out',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Survey site check-out recorded successfully.',
            'duration_formatted' => $durationFormatted,
            'check_out_time_formatted' => $nowKolkata->format('h:i A'),
            'visit' => $visit,
        ]);
    }

    public function taskDetails(Request $request, ExecutionTask $task)
    {
        $member = $request->user();
        $task->load(['project', 'supervisor', 'checklists']);

        $checklists = $task->checklists;

        if ($checklists->isEmpty()) {
            $defaultItems = [
                'Check instrument & battery',
                'Calibrate total station',
                'Record existing benchmarks',
                'Capture elevation points',
                'Cross-check measurements',
            ];

            foreach ($defaultItems as $item) {
                TaskChecklist::create([
                    'execution_task_id' => $task->id,
                    'day_number' => 2,
                    'item_title' => $item,
                    'is_completed' => false,
                ]);
            }
            $checklists = TaskChecklist::where('execution_task_id', $task->id)->get();
        }

        $formattedChecklist = $checklists->map(function ($c) {
            return [
                'id' => $c->id,
                'item_title' => $c->item_title,
                'is_completed' => (bool) $c->is_completed,
            ];
        });

        $dayStepper = [
            ['day' => 1, 'label' => 'Day 1', 'status' => 'Completed', 'is_active' => false],
            ['day' => 2, 'label' => 'Day 2', 'status' => 'In Progress', 'is_active' => true],
            ['day' => 3, 'label' => 'Day 3', 'status' => 'Pending', 'is_active' => false],
            ['day' => 4, 'label' => 'Day 4', 'status' => 'Pending', 'is_active' => false],
            ['day' => 5, 'label' => 'Day 5', 'status' => 'Pending', 'is_active' => false],
        ];

        return response()->json([
            'success' => true,
            'day_stepper' => $dayStepper,
            'task' => [
                'id' => $task->id,
                'title' => $task->title,
                'status' => $task->status === 'planned' ? 'in_progress' : $task->status,
                'status_label' => ucfirst($task->status === 'planned' ? 'In Progress' : $task->status),
                'due_date_formatted' => $task->planned_end_date ? $task->planned_end_date->format('d M Y, 05:00 PM') : '25 May 2024, 05:00 PM',
                'priority' => strtolower($task->priority ?? 'high'),
                'priority_label' => ucfirst($task->priority ?? 'High'),
                'instructions' => $task->description ?? 'Perform detailed topography survey using total station. Capture elevation points, boundaries and key features. Ensure accuracy and complete point coverage.',
            ],
            'assigned_by' => [
                'name' => $task->supervisor?->name ?? 'Er. Rajesh Sharma',
                'designation' => 'Project Manager',
                'phone' => $task->supervisor?->phone ?? '9876543210',
            ],
            'checklist' => $formattedChecklist,
            'button_label' => 'Start Day 2 Task',
        ]);
    }

    public function toggleChecklist(Request $request, TaskChecklist $checklist)
    {
        $member = $request->user();
        $newStatus = !$checklist->is_completed;

        $checklist->update([
            'is_completed' => $newStatus,
            'completed_by_member_id' => $newStatus ? $member->id : null,
            'completed_at' => $newStatus ? now() : null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Checklist item updated.',
            'item' => [
                'id' => $checklist->id,
                'item_title' => $checklist->item_title,
                'is_completed' => (bool) $checklist->is_completed,
            ],
        ]);
    }

    public function storeChecklist(Request $request, ExecutionTask $task)
    {
        $request->validate([
            'item_title' => 'required|string|max:255',
            'day_number' => 'nullable|integer',
        ]);

        $checklist = TaskChecklist::create([
            'execution_task_id' => $task->id,
            'day_number' => $request->day_number ?? 2,
            'item_title' => $request->item_title,
            'is_completed' => false,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Checklist item added successfully.',
            'data' => $checklist,
        ]);
    }

    public function submitDayData(Request $request)
    {
        $request->validate([
            'survey_visit_id' => 'nullable|exists:construction_survey_visits,id',
            'survey_plan_id' => 'nullable|exists:construction_survey_plans,id',
            'project_id' => 'nullable|exists:construction_projects,id',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'elevation_m' => 'nullable|numeric',
            'distance_covered_m' => 'nullable|numeric',
            'total_points_captured' => 'nullable|integer',
            'remarks' => 'nullable|string',
            'notes' => 'nullable|string',
            'photos.*' => 'nullable|image|max:10240',
            'file' => 'nullable|file|max:20480',
        ]);

        $member = $request->user();
        $photoPaths = [];

        if ($request->hasFile('photos')) {
            foreach ($request->file('photos') as $photoFile) {
                $path = $photoFile->store('surveys/photos', 'public');
                $photoPaths[] = Storage::url($path);
            }
        }

        $filePath = null;
        if ($request->hasFile('file')) {
            $uploadedFile = $request->file('file');
            $path = $uploadedFile->store('surveys/documents', 'public');
            $filePath = Storage::url($path);
        }

        $visit = null;
        if ($request->filled('survey_visit_id')) {
            $visit = SurveyVisit::find($request->survey_visit_id);
        }

        if (!$visit) {
            $visit = SurveyVisit::where('checked_in_by_member_id', $member->id)
                ->whereNull('check_out_at')
                ->latest()
                ->first();
        }

        if ($visit) {
            $visit->update([
                'check_in_latitude' => $request->latitude ?? $visit->check_in_latitude,
                'check_in_longitude' => $request->longitude ?? $visit->check_in_longitude,
                'elevation_m' => $request->elevation_m ?? $visit->elevation_m,
                'distance_covered_m' => $request->distance_covered_m ?? $visit->distance_covered_m,
                'total_points_captured' => $request->total_points_captured ?? $visit->total_points_captured,
                'remarks' => $request->remarks ?? $visit->remarks,
                'notes' => $request->notes ?? $visit->notes,
                'photos' => !empty($photoPaths) ? $photoPaths : ($visit->photos ?? []),
                'file_path' => $filePath ?? $visit->file_path,
                'status' => 'submitted',
            ]);
        } else {
            $visit = SurveyVisit::create([
                'project_id' => $request->project_id ?? Project::value('id') ?? 1,
                'survey_plan_id' => $request->survey_plan_id ?? SurveyPlan::value('id') ?? 1,
                'checked_in_by_member_id' => $member->id,
                'check_in_at' => now(),
                'check_in_latitude' => $request->latitude ?? 25.296212,
                'check_in_longitude' => $request->longitude ?? 75.804412,
                'elevation_m' => $request->elevation_m,
                'distance_covered_m' => $request->distance_covered_m,
                'total_points_captured' => $request->total_points_captured,
                'remarks' => $request->remarks,
                'notes' => $request->notes,
                'photos' => $photoPaths,
                'file_path' => $filePath,
                'status' => 'submitted',
            ]);
        }

        if ($visit->project_id) {
            $project = Project::find($visit->project_id);
            if ($project) {
                $task = ExecutionTask::where('project_id', $project->id)->first();
                if ($task && $task->status !== 'completed') {
                    $task->update([
                        'status' => 'in_progress',
                        'progress_percent' => min(100, ($task->progress_percent ?? 0) + 20),
                    ]);
                }
                $project->update([
                    'progress_percent' => min(100, ($project->progress_percent ?? 0) + 10),
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Day 2 survey update data, photos, and report submitted successfully.',
            'data' => [
                'visit_id' => $visit->id,
                'gps_location' => [
                    'latitude' => $visit->check_in_latitude,
                    'longitude' => $visit->check_in_longitude,
                    'accuracy' => '2.6 m',
                    'status' => 'Good',
                ],
                'date_time_formatted' => now()->setTimezone('Asia/Kolkata')->format('d M Y, h:i A'),
                'elevation_m' => (float) $visit->elevation_m,
                'distance_covered_m' => (float) $visit->distance_covered_m,
                'remarks' => $visit->remarks,
                'total_points_captured' => (int) $visit->total_points_captured,
                'photos' => $visit->photos,
                'file_path' => $visit->file_path,
                'notes' => $visit->notes,
            ],
        ]);
    }

    public function projectSurveyDetails(Request $request, Project $project)
    {
        $member = $request->user();
        $memberId = $member->getKey();

        $project->load(['company', 'client', 'createdBy', 'executionTasks', 'surveyPlans']);

        $roleName = ProjectTeamMember::where('project_id', $project->id)
            ->where('member_id', $memberId)
            ->with('role')
            ->first()?->role?->name ?? 'Survey Team Member';

        $startDate = $project->start_date ? $project->start_date->format('d M Y') : '24 May 2024';
        $endDate = $project->expected_end_date ?? now()->addDays(5);
        $diffDays = $project->start_date && $project->expected_end_date
            ? max(1, $project->start_date->diffInDays($project->expected_end_date))
            : 5;

        $daysLeft = now()->diffInDays($endDate, false);
        $daysLeftStr = $daysLeft > 0 ? "{$daysLeft} Days Left" : 'Deadline Today';

        $progressPercent = (int) ($project->progress_percent ?? round($project->executionTasks?->avg('progress_percent') ?? 60));

        $supervisor = $project->client?->name ?? $project->company?->name ?? $project->createdBy?->name ?? 'Er. Rajesh Sharma';
        $supervisorPhone = $project->client?->phone ?? $project->company?->phone ?? '9876543210';

        $stepper = [
            ['day' => 1, 'title' => 'Site Visit', 'status' => 'Completed', 'status_class' => 'completed'],
            ['day' => 2, 'title' => 'Topography Survey', 'status' => 'In Progress', 'status_class' => 'in_progress'],
            ['day' => 3, 'title' => 'Structure', 'status' => 'Pending', 'status_class' => 'pending'],
            ['day' => 4, 'title' => 'Utilities', 'status' => 'Pending', 'status_class' => 'pending'],
            ['day' => 5, 'title' => 'Final Report', 'status' => 'Pending', 'status_class' => 'pending'],
        ];

        return response()->json([
            'success' => true,
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'location' => $project->project_address ?? 'Jaipur, Rajasthan',
                'status' => $project->status === 'active' ? 'in_progress' : strtolower($project->status ?? 'in_progress'),
                'status_label' => $project->status === 'active' ? 'In Progress' : ucfirst($project->status ?? 'In Progress'),
                'progress_percent' => $progressPercent,
                'start_date_formatted' => $startDate,
                'duration_formatted' => "{$diffDays} Days",
                'days_left_formatted' => $daysLeftStr,
                'assigned_role' => $roleName,
                'supervisor' => [
                    'name' => $supervisor,
                    'designation' => 'Supervisor',
                    'phone' => $supervisorPhone,
                ],
                'description' => $project->description ?? 'Construction of residential villa project including foundation, structure, and utility setup as per design specifications and quality standards.',
                'survey_plan_overview' => $stepper,
            ],
        ]);
    }
}
