<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\Client;
use App\Models\Company;
use App\Models\ConstructionVehicle;
use App\Models\DailyProgressReport;
use App\Models\DraftingJob;
use App\Models\EquipmentAllocation;
use App\Models\ExecutionTask;
use App\Models\ExecutionTaskAssignee;
use App\Models\Material;
use App\Models\MaterialStock;
use App\Models\Member;
use App\Models\Project;
use App\Models\ProjectBudget;
use App\Models\ProjectTeamMember;
use App\Models\SurveyPlan;
use App\Models\SurveyPlanMember;
use App\Models\SurveySubmission;
use App\Models\SurveyVisit;
use App\Models\SystemSetting;
use App\Models\Task;
use App\Models\TaskChecklist;
use App\Models\TaskAssignment;
use App\Models\VehicleAssignment;
use App\Services\Construction\ConstructionAuthorizationService;
use App\Services\Construction\SurveyDataService;
use App\Support\Construction\SurveyStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class MemberDashboardController extends Controller
{
    public function __construct(
        protected SurveyDataService $surveyData,
        protected readonly ConstructionAuthorizationService $authz,
    ) {}

    private function accessibleProjectIds(Member $member): array
    {
        $team = ProjectTeamMember::query()
            ->where('member_id', $member->getKey())
            ->where('status', 'active')
            ->pluck('project_id')
            ->unique()
            ->all();

        $viaLegacySupervisor = \App\Models\ExecutionTask::query()
            ->where('supervisor_member_id', $member->getKey())
            ->whereNotNull('project_id')
            ->pluck('project_id')
            ->unique()
            ->all();

        $viaUnifiedSupervisor = \App\Models\Task::query()
            ->where('assigned_supervisor_member_id', $member->getKey())
            ->whereNotNull('project_id')
            ->pluck('project_id')
            ->unique()
            ->all();

        $viaAssigneeUnified = \App\Models\TaskAssignment::query()
            ->where('assigned_to', $member->getKey())
            ->where(fn ($q) => $q->whereNull('is_transferred')->orWhere('is_transferred', 0))
            ->whereNull('deleted_at')
            ->whereNotNull('project_id')
            ->pluck('project_id')
            ->unique()
            ->all();

        $merged = array_merge($team, $viaLegacySupervisor, $viaUnifiedSupervisor, $viaAssigneeUnified);
        return array_values(array_unique(array_map('intval', $merged)));
    }

    private function adminTaskProjectIds(Member $member, array $projectIds): array
    {
        if ($projectIds === []) {
            return [];
        }

        $adminIds = [];
        foreach ($projectIds as $pid) {
            if ($this->authz->hasAnyPermission($member, ['execution_task.manage', 'task.manage'], (int) $pid)) {
                $adminIds[] = (int) $pid;
            }
        }

        return array_values(array_unique($adminIds));
    }

    /**
     * Resolve a task ID across both the legacy ExecutionTask table
     * and the unified Task table.
     *
     * Priority: ExecutionTask first (it owns construction_task_checklists.execution_task_id FK).
     * Fallback: If id only exists on unified Task, lazily create a mirror ExecutionTask
     * row (so checklist FKs remain valid) and sync current status forward.
     *
     * @return array{task:ExecutionTask, unifiedTask:Task|null}
     */
    private function resolveTaskAcrossTables(int $taskId): ?array
    {
        /** @var ExecutionTask|null $legacyTask */
        $legacyTask = ExecutionTask::query()
            ->with('project')
            ->find($taskId);

        /** @var Task|null $unifiedTask */
        $unifiedTask = Task::query()
            ->with(['project', 'checklistItems'])
            ->find($taskId);

        if ($legacyTask === null && $unifiedTask === null) {
            return null;
        }

        if ($legacyTask !== null) {
            return [
                'task' => $legacyTask,
                'unifiedTask' => $unifiedTask,
            ];
        }

        // Create a mirror ExecutionTask so construction_task_checklists FK stays valid.
        $planId = $this->resolveOrCreateExecutionPlanIdForProject((int) $unifiedTask->project_id);

        $legacyTask = DB::transaction(function () use ($unifiedTask, $planId, $taskId) {
            $mirror = ExecutionTask::create([
                'id' => $taskId,
                'task_code' => $unifiedTask->task_code
                    ?? ('TSK-' . str_pad((string) $taskId, 5, '0', STR_PAD_LEFT)),
                'title' => $unifiedTask->title,
                'description' => $unifiedTask->description,
                'project_id' => $unifiedTask->project_id,
                'execution_plan_id' => $planId,
                'priority' => in_array($unifiedTask->priority, ['low','medium','high','critical'], true)
                    ? $unifiedTask->priority
                    : 'medium',
                'planned_start_date' => $unifiedTask->start_date ?? now(),
                'planned_end_date' => $unifiedTask->end_date ?? now()->addDays(1),
                'supervisor_member_id' => $unifiedTask->assigned_supervisor_member_id
                    ?? $unifiedTask->member_id
                    ?? null,
                'status' => $this->mapUnifiedStatusToLegacy((string) $unifiedTask->status),
                'progress_percent' => (int) ($unifiedTask->progress_percent ?? 0),
            ]);

            $memberId = $unifiedTask->assigned_supervisor_member_id
                ?? $unifiedTask->member_id
                ?? null;
            if ($memberId !== null && $unifiedTask->project_id !== null) {
                try {
                    ExecutionTaskAssignee::updateOrCreate(
                        [
                            'execution_task_id' => $mirror->id,
                            'project_id' => (int) $unifiedTask->project_id,
                            'member_id' => (int) $memberId,
                        ],
                        [
                            'assigned_at' => now(),
                            'status' => 'active',
                        ]
                    );
                } catch (\Throwable $e) {
                    report($e);
                }
            }

            // Mirror unified Task's TaskChecklistItems into legacy TaskChecklist rows
            // so endpoints using construction_task_checklists continue to work.
            $this->mirrorChecklistItemsToLegacy($unifiedTask, $mirror);

            return $mirror;
        });

        return [
            'task' => $legacyTask->load('project'),
            'unifiedTask' => $unifiedTask,
        ];
    }

    private function mapUnifiedStatusToLegacy(string $status): string
    {
        return match ($status) {
            'pending' => 'planned',
            default => in_array($status, ['planned','in_progress','review','completed','blocked','cancelled'], true)
                ? $status
                : 'planned',
        };
    }

    private function mapLegacyStatusToUnified(string $status): string
    {
        return match ($status) {
            'planned' => 'pending',
            default => in_array($status, ['pending','in_progress','review','completed','blocked','cancelled'], true)
                ? $status
                : 'pending',
        };
    }

    private function resolveOrCreateExecutionPlanIdForProject(int $projectId): ?int
    {
        if ($projectId <= 0) {
            return null;
        }

        $existing = \App\Models\ExecutionPlan::query()
            ->where('project_id', $projectId)
            ->value('id');
        if ($existing !== null) {
            return (int) $existing;
        }

        try {
            return (int) \App\Models\ExecutionPlan::query()->create([
                'project_id' => $projectId,
                'plan_code' => 'EP-' . str_pad(random_int(1, 99999), 5, '0', STR_PAD_LEFT),
                'title' => 'Default Project Plan (auto)',
                'status' => 'approved',
            ])->getKey();
        } catch (\Throwable $e) {
            report($e);
            return null;
        }
    }

    /**
     * Copy unified Task's checklist items -> legacy construction_task_checklists
     * so storeChecklist / taskDetails / toggleChecklist continue to function
     * without a second migration. Idempotent by (execution_task_id, item_title).
     */
    private function mirrorChecklistItemsToLegacy(Task $unifiedTask, ExecutionTask $legacyTask): void
    {
        $items = $unifiedTask->checklistItems;
        if ($items === null || $items->isEmpty()) {
            return;
        }

        foreach ($items as $item) {
            TaskChecklist::query()->firstOrCreate(
                [
                    'execution_task_id' => $legacyTask->id,
                    'item_title' => $item->title ?? $item->item_title ?? 'Checklist item',
                ],
                [
                    'day_number' => $item->sort_order ?? 1,
                    'is_completed' => (bool) ($item->is_completed ?? false),
                    'completed_by_member_id' => $item->completed_by ?? null,
                    'completed_at' => $item->completed_at ?? null,
                ]
            );
        }
    }

    private function syncLegacyStatusToUnified(ExecutionTask $legacyTask, ?Task $unifiedTask, string $legacyStatus): void
    {
        if ($unifiedTask === null) {
            return;
        }

        try {
            $unifiedTask->update([
                'status' => $this->mapLegacyStatusToUnified($legacyStatus),
                'progress_percent' => $legacyStatus === 'completed' ? 100 : (int) ($unifiedTask->progress_percent ?? 0),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function taskNotFoundResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error_code' => 'TASK_NOT_FOUND',
            'message' => 'No task found with the given identifier.',
        ], 404);
    }

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
            $accessibleProjectIds = $this->accessibleProjectIds($member);
            $adminProjectIds = $this->adminTaskProjectIds($member, $accessibleProjectIds);

            $legacyScope = function (Builder $q) use ($memberId, $accessibleProjectIds, $adminProjectIds) {
                $q->where(function (Builder $direct) use ($memberId, $accessibleProjectIds) {
                    $direct->where(function (Builder $assignmentOrSupervisor) use ($memberId) {
                        $assignmentOrSupervisor->whereHas('assignees', function (Builder $sub) use ($memberId) {
                            $sub->where('member_id', $memberId)->where('status', 'active');
                        })->orWhere('supervisor_member_id', $memberId);
                    });
                    if ($accessibleProjectIds !== []) {
                        $direct->where(function (Builder $scope) use ($accessibleProjectIds) {
                            $scope->whereNull('project_id')
                                ->orWhereIn('project_id', $accessibleProjectIds);
                        });
                    } else {
                        $direct->whereNull('project_id');
                    }
                });

                if ($adminProjectIds !== []) {
                    $q->orWhere(function (Builder $admin) use ($adminProjectIds) {
                        $admin->whereIn('project_id', $adminProjectIds);
                    });
                }
            };

            $legacy = ExecutionTask::with([
                'project.company',
                'project.client',
                'executionPlan',
                'supervisor',
            ])->where($legacyScope)->latest()->get()->each->setAttribute('_source', 'legacy');

            $unifiedScope = function (Builder $q) use ($memberId, $accessibleProjectIds, $adminProjectIds) {
                $q->where(function (Builder $direct) use ($memberId, $accessibleProjectIds) {
                    $direct->where(function (Builder $assignmentOrSupervisor) use ($memberId) {
                        $assignmentOrSupervisor->whereHas('assignedMembers', function (Builder $sub) use ($memberId) {
                            $sub->where('assigned_to', $memberId);
                        })->orWhere('assigned_supervisor_member_id', $memberId);
                    });
                    if ($accessibleProjectIds !== []) {
                        $direct->where(function (Builder $scope) use ($accessibleProjectIds) {
                            $scope->whereNull('project_id')
                                ->orWhereIn('project_id', $accessibleProjectIds);
                        });
                    } else {
                        $direct->whereNull('project_id');
                    }
                });

                if ($adminProjectIds !== []) {
                    $q->orWhere(function (Builder $admin) use ($adminProjectIds) {
                        $admin->whereIn('project_id', $adminProjectIds);
                    });
                }
            };

            $unified = Task::with([
                'project.company',
                'project.client',
                'executionPlan',
                'assignedSupervisor',
                'supervisor',
            ])->where($unifiedScope)->latest()->get()->each->setAttribute('_source', 'unified');

            $tasks = $legacy->merge($unified)
                ->sortByDesc(fn ($t) => $t->created_at?->timestamp ?? 0)
                ->values();
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
        /** @var Member $member */
        $member = $request->user();
        $memberId = $member->getKey();

        $accessibleProjectIds = $this->accessibleProjectIds($member);
        $adminProjectIds = $this->adminTaskProjectIds($member, $accessibleProjectIds);

        if ($request->filled('project_id')) {
            $requested = (int) $request->input('project_id');
            if (! in_array($requested, $accessibleProjectIds, true)) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'FORBIDDEN',
                    'message' => 'You do not have access to this project.',
                ], 403);
            }
        }

        $requestedProjectId = $request->filled('project_id') ? (int) $request->input('project_id') : null;
        $search = $request->filled('search') ? trim((string) $request->input('search')) : null;
        $priorityFilter = $request->filled('priority') ? (string) $request->input('priority') : null;

        $normalizedStatusFilter = null;
        if ($request->filled('status') && $request->status !== 'all') {
            $statusMap = [
                'pending' => 'pending',
                'planned' => 'pending',
                'in_progress' => 'in_progress',
                'completed' => 'completed',
                'blocked' => 'blocked',
                'review' => 'review',
                'cancelled' => 'cancelled',
            ];
            $normalizedStatusFilter = $statusMap[strtolower((string) $request->status)] ?? strtolower((string) $request->status);
        }

        $legacyQuery = ExecutionTask::with([
            'project.company',
            'project.client',
            'executionPlan',
            'supervisor',
        ])->where(function (Builder $q) use ($memberId, $accessibleProjectIds, $adminProjectIds) {
            $q->where(function (Builder $direct) use ($memberId, $accessibleProjectIds) {
                $direct->where(function (Builder $assignmentOrSupervisor) use ($memberId) {
                    $assignmentOrSupervisor->whereHas('assignees', function (Builder $sub) use ($memberId) {
                        $sub->where('member_id', $memberId)->where('status', 'active');
                    })->orWhere('supervisor_member_id', $memberId);
                });
                if ($accessibleProjectIds !== []) {
                    $direct->where(function (Builder $scope) use ($accessibleProjectIds) {
                        $scope->whereNull('project_id')
                            ->orWhereIn('project_id', $accessibleProjectIds);
                    });
                } else {
                    $direct->whereNull('project_id');
                }
            });

            if ($adminProjectIds !== []) {
                $q->orWhere(function (Builder $admin) use ($adminProjectIds) {
                    $admin->whereIn('project_id', $adminProjectIds);
                });
            }
        });

        $unifiedQuery = Task::with([
            'project.company',
            'project.client',
            'executionPlan',
            'assignedSupervisor',
            'supervisor',
        ])->where(function (Builder $q) use ($memberId, $accessibleProjectIds, $adminProjectIds) {
            $q->where(function (Builder $direct) use ($memberId, $accessibleProjectIds) {
                $direct->where(function (Builder $assignmentOrSupervisor) use ($memberId) {
                    $assignmentOrSupervisor->whereHas('assignedMembers', function (Builder $sub) use ($memberId) {
                        $sub->where('assigned_to', $memberId);
                    })->orWhere('assigned_supervisor_member_id', $memberId);
                });
                if ($accessibleProjectIds !== []) {
                    $direct->where(function (Builder $scope) use ($accessibleProjectIds) {
                        $scope->whereNull('project_id')
                            ->orWhereIn('project_id', $accessibleProjectIds);
                    });
                } else {
                    $direct->whereNull('project_id');
                }
            });

            if ($adminProjectIds !== []) {
                $q->orWhere(function (Builder $admin) use ($adminProjectIds) {
                    $admin->whereIn('project_id', $adminProjectIds);
                });
            }
        });

        if ($requestedProjectId !== null) {
            $legacyQuery->where('project_id', $requestedProjectId);
            $unifiedQuery->where('project_id', $requestedProjectId);
        }

        if ($search !== null && $search !== '') {
            $legacyQuery->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('task_code', 'like', "%{$search}%");
            });
            $unifiedQuery->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('task_code', 'like', "%{$search}%");
            });
        }

        if ($priorityFilter !== null) {
            $legacyQuery->where('priority', $priorityFilter);
            $unifiedQuery->where('priority', $priorityFilter);
        }

        $legacyRows = $legacyQuery->latest()->get();
        $unifiedRows = $unifiedQuery->latest()->get();

        $merged = collect();
        $seenLegacy = [];

        foreach ($legacyRows as $t) {
            $seenLegacy[(int) $t->id] = true;
            $merged->push($this->normalizeExecutionTaskListItem($t, true));
        }

        foreach ($unifiedRows as $t) {
            if (isset($seenLegacy[(int) $t->id])) {
                continue;
            }
            $merged->push($this->normalizeUnifiedTaskListItem($t, false));
        }

        if ($normalizedStatusFilter !== null) {
            $merged = $merged->filter(function (array $row) use ($normalizedStatusFilter) {
                return $row['normalized_status'] === $normalizedStatusFilter;
            })->values();
        }

        $merged = $merged->sortByDesc('sort_key')->values();

        $counts = [
            'pending' => $merged->where('normalized_status', 'pending')->count(),
            'in_progress' => $merged->where('normalized_status', 'in_progress')->count(),
            'completed' => $merged->where('normalized_status', 'completed')->count(),
            'total' => $merged->count(),
        ];

        $perPage = max(1, (int) ($request->per_page ?? 15));
        $page = max(1, (int) ($request->input('page', 1)));
        $total = $merged->count();
        $pageTasks = $merged->forPage($page, $perPage)->values();

        $rawTaskRows = collect();
        foreach ($pageTasks as $row) {
            if ($row['source'] === 'legacy') {
                $match = $legacyRows->firstWhere('id', $row['id']);
                if ($match !== null) {
                    $rawTaskRows->push($match);
                }
            } else {
                $match = $unifiedRows->firstWhere('id', $row['id']);
                if ($match !== null) {
                    $rawTaskRows->push($match);
                }
            }
        }

        $lastPage = max(1, (int) ceil($total / $perPage));

        return response()->json([
            'success' => true,
            'header_info' => $this->getHeaderInfo($member),
            'counts' => $counts,
            'data' => $pageTasks->map(function (array $row) {
                return [
                    'id' => $row['id'],
                    'title' => $row['title'],
                    'project_name' => $row['project_name'],
                    'location' => $row['location'],
                    'due_time_formatted' => $row['due_time_formatted'],
                    'priority' => $row['priority'],
                    'priority_label' => $row['priority_label'],
                    'is_completed' => $row['is_completed'],
                    'status' => $row['status'],
                ];
            }),
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => $lastPage,
            ],
            'tasks' => $rawTaskRows->all(),
        ]);
    }

    private function normalizeExecutionTaskListItem(ExecutionTask $t, bool $sourceLegacy): array
    {
        $status = (string) $t->status;
        $normalized = match (strtolower($status)) {
            'planned' => 'pending',
            default => in_array(strtolower($status), ['pending','in_progress','review','completed','blocked','cancelled'], true)
                ? strtolower($status)
                : 'pending',
        };

        $createdAt = $t->created_at;
        $sortKey = $createdAt ? $createdAt->getTimestamp() : 0;

        $priority = strtolower((string) ($t->priority ?? 'medium'));
        $dueDate = $t->planned_end_date;

        return [
            'source' => $sourceLegacy ? 'legacy' : 'unified',
            'id' => (int) $t->id,
            'title' => (string) $t->title,
            'project_name' => $t->project->name ?? null,
            'location' => $t->project->project_address ?? null,
            'due_time_formatted' => $dueDate ? $dueDate->format('d M, h:i A') : null,
            'priority' => $priority,
            'priority_label' => ucfirst($priority),
            'is_completed' => strtolower($status) === 'completed',
            'status' => $status,
            'normalized_status' => $normalized,
            'sort_key' => $sortKey,
        ];
    }

    private function normalizeUnifiedTaskListItem(Task $t, bool $sourceLegacy): array
    {
        $status = (string) $t->status;
        $normalized = in_array(strtolower($status), ['pending','in_progress','review','completed','blocked','cancelled'], true)
            ? strtolower($status)
            : 'pending';

        $createdAt = $t->created_at;
        $sortKey = $createdAt ? $createdAt->getTimestamp() : 0;

        $priorityEnum = $t->priority;
        $priority = is_string($priorityEnum) ? strtolower($priorityEnum) : strtolower((string) ($priorityEnum?->value ?? 'medium'));
        $dueDate = $t->end_date;

        return [
            'source' => $sourceLegacy ? 'legacy' : 'unified',
            'id' => (int) $t->id,
            'title' => (string) $t->title,
            'project_name' => $t->project->name ?? null,
            'location' => $t->project->project_address ?? null,
            'due_time_formatted' => $dueDate ? $dueDate->format('d M, h:i A') : null,
            'priority' => in_array($priority, ['low','medium','high','critical','urgent'], true) ? $priority : 'medium',
            'priority_label' => ucfirst(in_array($priority, ['low','medium','high','critical','urgent'], true) ? $priority : 'medium'),
            'is_completed' => strtolower($status) === 'completed',
            'status' => $status,
            'normalized_status' => $normalized,
            'sort_key' => $sortKey,
        ];
    }

    /**
     * Authorize that the authenticated member has access to read/write a task.
     * Defense-in-depth:
     *   (1) Task project_id must be null or within the member's accessible projects.
     *   (2) Member is:
     *       (a) an active assignee on the task,
     *       (b) the task's supervisor, or
     *       (c) an active team member on the parent project (covers PMs, survey leads, etc).
     */
    private function authorizeTaskAccess(Request $request, ExecutionTask $task): bool
    {
        /** @var Member $member */
        $member = $request->user();
        $memberId = $member->getKey();

        $accessibleProjectIds = $this->accessibleProjectIds($member);
        $taskProjectId = $task->project_id ? (int) $task->project_id : null;
        if ($taskProjectId !== null && ! in_array($taskProjectId, $accessibleProjectIds, true)) {
            return false;
        }

        if ((int) $task->supervisor_member_id === (int) $memberId) {
            return true;
        }

        $isAssignee = ExecutionTaskAssignee::query()
            ->where('execution_task_id', $task->id)
            ->where('member_id', $memberId)
            ->where('status', 'active')
            ->exists();
        if ($isAssignee) {
            return true;
        }

        // Also treat unified-task assignment as access for cross-system reads.
        $isUnifiedAssignee = \App\Models\TaskAssignment::query()
            ->where('task_id', $task->id)
            ->where('assigned_to', $memberId)
            ->exists();
        if ($isUnifiedAssignee) {
            return true;
        }

        if ($taskProjectId !== null) {
            return ProjectTeamMember::query()
                ->where('project_id', $taskProjectId)
                ->where('member_id', $memberId)
                ->where('status', 'active')
                ->exists();
        }

        return false;
    }

    private function forbiddenTaskAccessResponse(): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'success' => false,
            'error_code' => 'TASK_ACCESS_DENIED',
            'message' => 'You are not assigned to this task and cannot access it.',
        ], 403);
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

    public function toggleTask(Request $request, int $task)
    {
        $resolved = $this->resolveTaskAcrossTables($task);
        if ($resolved === null) {
            return $this->taskNotFoundResponse();
        }

        [$legacyTask, $unifiedTask] = [$resolved['task'], $resolved['unifiedTask']];

        if (! $this->authorizeTaskAccess($request, $legacyTask)) {
            return $this->forbiddenTaskAccessResponse();
        }

        $newStatus = $legacyTask->status === 'completed' ? 'planned' : 'completed';
        $legacyTask->update([
            'status' => $newStatus,
            'completed_quantity' => $newStatus === 'completed' ? ($legacyTask->planned_quantity ?? 1) : 0,
            'progress_percent' => $newStatus === 'completed' ? 100 : 0,
        ]);

        $this->syncLegacyStatusToUnified($legacyTask, $unifiedTask, $newStatus);

        return response()->json([
            'success' => true,
            'message' => 'Task status toggled successfully.',
            'task' => [
                'id' => $legacyTask->id,
                'status' => $legacyTask->status,
                'is_completed' => $legacyTask->status === 'completed',
            ],
        ]);
    }

    public function updateTaskStatus(Request $request, int $task)
    {
        $resolved = $this->resolveTaskAcrossTables($task);
        if ($resolved === null) {
            return $this->taskNotFoundResponse();
        }

        [$legacyTask, $unifiedTask] = [$resolved['task'], $resolved['unifiedTask']];

        if (! $this->authorizeTaskAccess($request, $legacyTask)) {
            return $this->forbiddenTaskAccessResponse();
        }

        $request->validate([
            'status' => 'required|string|in:planned,in_progress,completed,blocked',
        ]);

        $legacyTask->update(['status' => $request->status]);
        $this->syncLegacyStatusToUnified($legacyTask, $unifiedTask, (string) $request->status);

        return response()->json([
            'success' => true,
            'message' => 'Task status updated.',
            'task' => $legacyTask,
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
        $memberId = $member->getKey();

        $taskCode = 'TSK-' . str_pad(random_int(1, 99999), 5, '0', STR_PAD_LEFT);

        $projectId = $request->project_id;
        if ($projectId) {
            $isOnProjectTeam = ProjectTeamMember::where('project_id', $projectId)
                ->where('member_id', $memberId)
                ->where('status', 'active')
                ->exists();
            $isTaskAssigneeOnProject = ExecutionTaskAssignee::where('project_id', $projectId)
                ->where('member_id', $memberId)
                ->where('status', 'active')
                ->exists();
            $isSurveyMemberOnProject = SurveyPlanMember::where('member_id', $memberId)
                ->whereHas('surveyPlan', function ($q) use ($projectId) {
                    $q->where('project_id', $projectId);
                })
                ->exists();
            if (!$isOnProjectTeam && !$isTaskAssigneeOnProject && !$isSurveyMemberOnProject) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'PROJECT_ACCESS_DENIED',
                    'message' => 'You are not assigned to this project and cannot create tasks for it.',
                ], 403);
            }
        }
        if (!$projectId) {
            $projectId = ProjectTeamMember::where('member_id', $memberId)
                ->where('status', 'active')
                ->orderByDesc('is_primary')
                ->latest()
                ->value('project_id');
        }
        if (!$projectId) {
            $projectId = ExecutionTaskAssignee::where('member_id', $memberId)
                ->where('status', 'active')
                ->latest()
                ->value('project_id');
        }
        if (!$projectId) {
            $projectId = SurveyPlanMember::where('member_id', $memberId)
                ->latest()
                ->whereHas('surveyPlan', function ($q) {
                    $q->whereNotNull('project_id');
                })
                ->join('construction_survey_plans', 'construction_survey_plans.id', '=', 'construction_survey_plan_members.survey_plan_id')
                ->value('construction_survey_plans.project_id');
        }
        if (!$projectId) {
            return response()->json([
                'success' => false,
                'error_code' => 'NO_ASSIGNED_PROJECT',
                'message' => 'You are not assigned to any project. Ask an admin to add you to a project team before creating tasks.',
            ], 403);
        }

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
        $today = today();
        $todayStr = $today->toDateString();

        $parseDate = static function ($value): ?\Illuminate\Support\Carbon {
            if ($value === null || $value === '') {
                return null;
            }
            if ($value instanceof \DateTimeInterface) {
                return \Illuminate\Support\Carbon::instance($value)->startOfDay();
            }
            try {
                return \Illuminate\Support\Carbon::parse($value)->startOfDay();
            } catch (\Throwable) {
                return null;
            }
        };

        return $tasks->filter(function ($t) use ($todayStr, $today, $parseDate) {
            $isLegacy = ($t->_source ?? null) === 'legacy' || $t instanceof \App\Models\ExecutionTask;
            if ($isLegacy) {
                $start = $parseDate($t->planned_start_date ?? null);
                $end = $parseDate($t->planned_end_date ?? null);
            } else {
                $start = $parseDate($t->start_date ?? ($t->planned_start_date ?? null));
                $end = $parseDate($t->end_date ?? ($t->planned_end_date ?? null));
            }
            if (!$start && !$end) {
                return false;
            }
            $rangeStart = $start ?? $end;
            $rangeEnd = $end ?? $start;
            return $today->between($rangeStart, $rangeEnd, true);
        })->map(function ($t) use ($parseDate) {
            $isCompleted = ($t->status === 'completed');
            $priority = strtolower($t->priority ?? 'medium');
            $isLegacy = ($t->_source ?? null) === 'legacy' || $t instanceof \App\Models\ExecutionTask;
            $endVal = $isLegacy
                ? ($t->planned_end_date ?? null)
                : ($t->end_date ?? ($t->planned_end_date ?? null));
            $due = $parseDate($endVal);
            return [
                'id' => $t->id,
                'title' => $t->title,
                'project_name' => $t->project?->name ?? null,
                'location' => $t->project?->project_address ?? ($t->project?->location ?? null),
                'due_time_formatted' => $due ? $due->format('d M, h:i A') : null,
                'priority' => $priority,
                'priority_label' => ucfirst($priority),
                'is_completed' => $isCompleted,
                'status' => $t->status,
                'source' => $isLegacy ? 'legacy' : 'unified',
                'task_code' => $t->task_code ?? null,
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

        try {
            $activeVisit = SurveyVisit::with(['project', 'surveyPlan'])
                ->where('checked_in_by_member_id', $member->id)
                ->whereDate('check_in_at', $today)
                ->whereNull('check_out_at')
                ->latest()
                ->first();
        } catch (\Throwable $e) {
            report($e);
            $activeVisit = null;
        }

        $shift = $this->surveyData->getShiftConfig();

        if (!$activeVisit) {
            try {
                $lastVisit = SurveyVisit::with(['project', 'surveyPlan'])
                    ->where('checked_in_by_member_id', $member->id)
                    ->whereDate('check_in_at', $today)
                    ->latest()
                    ->first();
            } catch (\Throwable $e) {
                report($e);
                $lastVisit = null;
            }

            try {
                $project = $lastVisit?->project ?? Project::first();
            } catch (\Throwable $e) {
                report($e);
                $project = null;
            }
            $plan = $lastVisit?->surveyPlan;

            $totalDays = $this->surveyData->getTotalSurveyDays($plan, $project);
            $currentDay = $this->surveyData->getCurrentDayNumber($lastVisit, $plan, $project);
            $siteAddress = $this->surveyData->resolveSiteAddress($plan, $project);
            $coords = $this->surveyData->resolveSiteCoordinates($plan, $project);

            $checkInTimeFormatted = $lastVisit?->check_in_at
                ? $lastVisit->check_in_at->setTimezone('Asia/Kolkata')->format('h:i A')
                : null;
            $checkInDateFormatted = $lastVisit?->check_in_at
                ? $lastVisit->check_in_at->setTimezone('Asia/Kolkata')->format('d M Y')
                : $nowKolkata->format('d M Y');

            if ($lastVisit) {
                $checkOutFormatted = $lastVisit->check_out_at
                    ? $lastVisit->check_out_at->setTimezone('Asia/Kolkata')->format('h:i A')
                    : 'N/A';
                $checkedInFooter = 'Checked out at ' . $checkOutFormatted;
            } else {
                $checkedInFooter = 'Not Checked In';
            }

            return response()->json([
                'success' => true,
                'is_on_site' => false,
                'location_status_label' => 'Off Site',
                'current_day' => 'Day ' . $currentDay . ' of ' . $totalDays,
                'check_in_time_formatted' => $checkInTimeFormatted,
                'check_in_date_formatted' => $checkInDateFormatted,
                'shift' => [
                    'name' => $shift['name'],
                    'time' => $shift['time'],
                ],
                'site_address' => $siteAddress,
                'site_latitude' => $coords['latitude'],
                'site_longitude' => $coords['longitude'],
                'checked_in_footer' => $checkedInFooter,
                'active_survey_visit_id' => null,
            ]);
        }

        $project = $activeVisit->project;
        $plan = $activeVisit->surveyPlan;
        $totalDays = $this->surveyData->getTotalSurveyDays($plan, $project);
        $currentDay = $this->surveyData->getCurrentDayNumber($activeVisit, $plan, $project);
        $siteAddress = $this->surveyData->resolveSiteAddress($plan, $project);

        $checkInCoords = $this->surveyData->resolveCheckInCoordinates(
            $activeVisit,
            null,
            null,
            $plan,
            $project
        );

        $mins = round($nowKolkata->diffInMinutes($activeVisit->check_in_at->setTimezone('Asia/Kolkata')));
        $h = floor($mins / 60);
        $m = $mins % 60;
        $timeSpentStr = ($h > 0 ? "{$h}h " : '') . "{$m}m";

        return response()->json([
            'success' => true,
            'is_on_site' => true,
            'location_status_label' => 'You are On Site',
            'current_day' => 'Day ' . $currentDay . ' of ' . $totalDays,
            'check_in_time_formatted' => $activeVisit->check_in_at->setTimezone('Asia/Kolkata')->format('h:i A'),
            'check_in_date_formatted' => $activeVisit->check_in_at->setTimezone('Asia/Kolkata')->format('d M Y'),
            'shift' => [
                'name' => $shift['name'],
                'time' => $shift['time'],
            ],
            'site_address' => $siteAddress,
            'site_latitude' => $checkInCoords['latitude'],
            'site_longitude' => $checkInCoords['longitude'],
            'checked_in_footer' => 'Checked in at ' . $activeVisit->check_in_at->setTimezone('Asia/Kolkata')->format('h:i A') . ' • Time spent: ' . $timeSpentStr,
            'active_survey_visit_id' => $activeVisit->id,
        ]);
    }

    public function surveyDutyCheckIn(Request $request)
    {
        $request->validate([
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'survey_plan_id' => 'nullable|exists:construction_survey_plans,id',
            'project_id' => 'nullable|exists:construction_projects,id',
            'day_number' => 'nullable|integer|min:1|max:366',
        ]);

        $member = $request->user();
        $nowKolkata = now()->setTimezone('Asia/Kolkata');

        $projectId = $request->project_id;
        $surveyPlanId = $request->survey_plan_id;

        if (!$projectId && $surveyPlanId) {
            try {
                $projectId = SurveyPlan::where('id', $surveyPlanId)->value('project_id');
            } catch (\Throwable $e) {
                report($e);

                return response()->json([
                    'success' => false,
                    'message' => 'Could not resolve project from survey plan.',
                    'error' => config('app.debug') ? $e->getMessage() : null,
                    'error_code' => 'SURVEY_PLAN_RESOLUTION_FAILED',
                ], 500);
            }
        }

        $project = null;
        $plan = null;
        $lookupErrors = [];

        try {
            if (!$projectId) {
                $memberAssignedProjectId = ProjectTeamMember::where('member_id', $member->id)
                    ->where('status', 'active')
                    ->orderByDesc('id')
                    ->value('project_id');
                $projectId = $memberAssignedProjectId ?? Project::value('id');
                if (!$projectId) {
                    $lookupErrors[] = 'No active project assignment found for this member and no projects exist in the system.';
                }
            }

            if (!$surveyPlanId && $projectId) {
                $surveyPlanId = SurveyPlan::where('project_id', $projectId)
                    ->orderByDesc('id')
                    ->value('id') ?? SurveyPlan::value('id');
            }
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Failed to resolve project and survey plan context for check-in.',
                'error' => config('app.debug') ? $e->getMessage() : null,
                'error_code' => 'PROJECT_CONTEXT_LOOKUP_FAILED',
            ], 500);
        }

        try {
            if ($projectId) {
                $project = Project::find($projectId);
                if (!$project) {
                    $lookupErrors[] = "Project #{$projectId} referenced but not found.";
                }
            }
            if ($surveyPlanId) {
                $plan = SurveyPlan::find($surveyPlanId);
                if (!$plan) {
                    $lookupErrors[] = "Survey Plan #{$surveyPlanId} referenced but not found.";
                }
            }
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load project or survey plan details.',
                'error' => config('app.debug') ? $e->getMessage() : null,
                'error_code' => 'MODEL_LOAD_FAILED',
            ], 500);
        }

        if (!$projectId && !$surveyPlanId) {
            $lookupErrors[] = 'Unable to determine a project or survey plan for this check-in. Please assign a project first.';
        }

        if (!empty($lookupErrors)) {
            return response()->json([
                'success' => false,
                'message' => 'Could not prepare check-in context.',
                'errors' => $lookupErrors,
                'error_code' => 'CHECKIN_CONTEXT_INVALID',
            ], 422);
        }

        $existing = null;
        try {
            $existing = SurveyVisit::where('checked_in_by_member_id', $member->id)
                ->whereDate('check_in_at', $nowKolkata->toDateString())
                ->whereNull('check_out_at')
                ->first();
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Failed to check active visit state.',
                'error' => config('app.debug') ? $e->getMessage() : null,
                'error_code' => 'ACTIVE_VISIT_LOOKUP_FAILED',
            ], 500);
        }

        if ($existing) {
            return response()->json([
                'success' => true,
                'message' => 'Already checked in at survey site for today.',
                'visit' => $existing,
                'check_in_time_formatted' => $existing->check_in_at ? $existing->check_in_at->setTimezone('Asia/Kolkata')->format('h:i A') : null,
            ]);
        }

        try {
            $defaultCoords = $this->surveyData->resolveSiteCoordinates($plan, $project);
        } catch (\Throwable $e) {
            report($e);
            $defaultCoords = ['latitude' => 0.0, 'longitude' => 0.0];
        }
        $lat = $request->latitude !== null ? (float) $request->latitude : $defaultCoords['latitude'];
        $long = $request->longitude !== null ? (float) $request->longitude : $defaultCoords['longitude'];

        $currentDay = $request->filled('day_number')
            ? (int) $request->day_number
            : $this->surveyData->getCurrentDayNumber(null, $plan, $project);

        try {
            $visit = SurveyVisit::create([
                'project_id' => $projectId,
                'survey_plan_id' => $surveyPlanId,
                'checked_in_by_member_id' => $member->id,
                'check_in_at' => now(),
                'check_in_latitude' => $lat,
                'check_in_longitude' => $long,
                'day_number' => $currentDay,
                'gps_verified' => true,
                'status' => SurveyStatus::IN_PROGRESS,
            ]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Failed to record check-in. Server error while saving the visit record.',
                'error' => config('app.debug') ? $e->getMessage() : null,
                'error_file' => config('app.debug') ? $e->getFile() . ':' . $e->getLine() : null,
                'error_code' => 'VISIT_CREATE_FAILED',
                'context' => config('app.debug') ? [
                    'project_id' => $projectId,
                    'survey_plan_id' => $surveyPlanId,
                    'member_id' => $member->id,
                    'latitude' => $lat,
                    'longitude' => $long,
                    'day_number' => $currentDay,
                    'status' => SurveyStatus::IN_PROGRESS,
                ] : null,
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Survey site check-in recorded successfully.',
            'visit' => $visit,
            'check_in_time_formatted' => $nowKolkata->format('h:i A'),
            'resolved_context' => [
                'project_id' => $projectId,
                'survey_plan_id' => $surveyPlanId,
                'day_number' => $currentDay,
                'coordinates' => ['latitude' => $lat, 'longitude' => $long],
            ],
        ]);
    }

    public function surveyDutyCheckOut(Request $request, SurveyVisit $visit)
    {
        $validated = $request->validate([
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'notes' => 'nullable|string|max:5000',
        ]);

        if ($visit->check_out_at !== null) {
            return response()->json([
                'success' => false,
                'message' => 'This visit is already checked out.',
                'error_code' => 'VISIT_ALREADY_CHECKED_OUT',
                'visit_id' => $visit->id,
                'check_out_at' => $visit->check_out_at,
            ], 409);
        }

        $member = $request->user();
        if ($visit->checked_in_by_member_id !== null && (int) $visit->checked_in_by_member_id !== (int) $member->id) {
            return response()->json([
                'success' => false,
                'message' => 'You can only check out visits you checked in.',
                'error_code' => 'OWNERSHIP_MISMATCH',
                'expected_member_id' => $visit->checked_in_by_member_id,
                'provided_member_id' => $member->id,
            ], 403);
        }

        $now = now();
        $nowKolkata = now()->setTimezone('Asia/Kolkata');
        $duration = 0;
        if ($visit->check_in_at) {
            $duration = round($now->diffInMinutes($visit->check_in_at));
            if ($duration <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Check-out cannot be at or before check-in time.',
                    'error_code' => 'INVALID_DURATION',
                    'check_in_at' => $visit->check_in_at->toIso8601String(),
                    'check_out_at' => $now->toIso8601String(),
                    'computed_minutes' => $duration,
                ], 422);
            }
        } else {
            return response()->json([
                'success' => false,
                'message' => 'This visit has no check-in timestamp; check-out is invalid.',
                'error_code' => 'NO_CHECKIN_TIMESTAMP',
                'visit_id' => $visit->id,
            ], 422);
        }

        $h = floor($duration / 60);
        $m = $duration % 60;
        $durationFormatted = ($h > 0 ? "{$h}h " : '') . "{$m}m";

        if (
            $request->has('latitude') !== $request->has('longitude')
            || (isset($validated['latitude']) && !isset($validated['longitude']))
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Provide both latitude and longitude for check-out coordinates, or omit both.',
                'error_code' => 'COORDINATE_PAIR_REQUIRED',
                'provided' => [
                    'latitude' => $request->has('latitude'),
                    'longitude' => $request->has('longitude'),
                ],
            ], 422);
        }

        if (isset($validated['latitude'])) {
            $checkOutLat = (float) $validated['latitude'];
            $checkOutLong = (float) $validated['longitude'];
        } elseif ($visit->check_in_latitude !== null && $visit->check_in_longitude !== null) {
            $checkOutLat = (float) $visit->check_in_latitude;
            $checkOutLong = (float) $visit->check_in_longitude;
        } else {
            try {
                $plan = $visit->survey_plan_id ? SurveyPlan::find($visit->survey_plan_id) : null;
                $project = $visit->project_id ? Project::find($visit->project_id) : null;
                $defaultCoords = $this->surveyData->resolveSiteCoordinates($plan, $project);
                $checkOutLat = $defaultCoords['latitude'];
                $checkOutLong = $defaultCoords['longitude'];
            } catch (\Throwable $e) {
                report($e);
                $checkOutLat = 0.0;
                $checkOutLong = 0.0;
            }
        }

        try {
            $visit->update([
                'check_out_at' => $now,
                'check_out_latitude' => $checkOutLat,
                'check_out_longitude' => $checkOutLong,
                'duration_minutes' => $duration,
                'notes' => $request->input('notes', $visit->notes),
                'status' => SurveyStatus::SUBMITTED,
            ]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Failed to record check-out. Server error while updating the visit record.',
                'error' => config('app.debug') ? $e->getMessage() : null,
                'error_file' => config('app.debug') ? $e->getFile() . ':' . $e->getLine() : null,
                'error_code' => 'VISIT_UPDATE_FAILED',
                'context' => config('app.debug') ? [
                    'visit_id' => $visit->id,
                    'member_id' => $member->id,
                    'duration_minutes' => $duration,
                    'check_out_latitude' => $checkOutLat,
                    'check_out_longitude' => $checkOutLong,
                    'status' => SurveyStatus::SUBMITTED,
                ] : null,
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Survey site check-out recorded successfully.',
            'duration_formatted' => $durationFormatted,
            'duration_minutes' => $duration,
            'check_out_time_formatted' => $nowKolkata->format('h:i A'),
            'visit' => $visit->fresh(),
        ]);
    }

    public function taskDetails(Request $request, $taskId)
    {
        $task = ExecutionTask::query()->find($taskId);
        if (!$task) {
            return response()->json([
                'success' => false,
                'error_code' => 'TASK_NOT_FOUND',
                'message' => 'No task found with the given identifier.',
            ], 404);
        }

        if (!$this->authorizeTaskAccess($request, $task)) {
            return $this->forbiddenTaskAccessResponse();
        }

        $member = $request->user();
        $task->load(['project', 'supervisor', 'checklists']);

        $project = $task->project;

        $totalDays = $this->surveyData->getTotalSurveyDays(null, $project);
        $currentDay = 1;
        try {
            $latestVisit = SurveyVisit::where(function ($q) use ($project, $task) {
                if ($project) {
                    $q->where('project_id', $project->id);
                }
            })
                ->whereNotNull('check_in_at')
                ->latest('check_in_at')
                ->first();
            $currentDay = $this->surveyData->getCurrentDayNumber($latestVisit, null, $project);
        } catch (\Throwable $e) {
            report($e);
        }

        $this->surveyData->ensureDefaultTaskChecklists($task, $currentDay);
        $checklists = TaskChecklist::where('execution_task_id', $task->id)->get();

        $formattedChecklist = $checklists->map(function ($c) {
            return [
                'id' => $c->id,
                'item_title' => $c->item_title,
                'assign_hours' => $c->assign_hours ? (float) $c->assign_hours : null,
                'notes' => $c->notes,
                'status' => $c->status ?? ($c->is_completed ? 'completed' : 'pending'),
                'image_url_1' => $c->image_url_1,
                'image_url_2' => $c->image_url_2,
                'is_completed' => (bool) $c->is_completed,
            ];
        });

        $dayStepper = $this->surveyData->buildTaskDayStepper($currentDay, $totalDays);

        $shift = $this->surveyData->getShiftConfig();
        $shiftEndParts = explode(' ', $shift['end_time']);
        $shiftEndTimestamp = strtotime($shift['end_time']);
        $shiftEndFormatted = $shiftEndTimestamp
            ? date('h:i A', $shiftEndTimestamp)
            : '05:00 PM';

        if ($task->planned_end_date) {
            $dueDateFormatted = $task->planned_end_date->format('d M Y, ') . $shiftEndFormatted;
        } else {
            $dueDateFormatted = now()->setTimezone('Asia/Kolkata')->addDays(max(0, $totalDays - $currentDay))
                ->format('d M Y, ') . $shiftEndFormatted;
        }

        $defaultInstructions = 'Perform detailed topography survey using total station. Capture elevation points, boundaries and key features. Ensure accuracy and complete point coverage.';
        $instructions = $task->description ?? $defaultInstructions;

        $supervisor = $this->surveyData->resolveSupervisorInfo($project, $task);

        return response()->json([
            'success' => true,
            'day_stepper' => $dayStepper,
            'task' => [
                'id' => $task->id,
                'title' => $task->title,
                'status' => $task->status === 'planned' ? 'in_progress' : $task->status,
                'status_label' => ucfirst($task->status === 'planned' ? 'In Progress' : $task->status),
                'due_date_formatted' => $dueDateFormatted,
                'priority' => strtolower($task->priority ?? 'high'),
                'priority_label' => ucfirst($task->priority ?? 'High'),
                'instructions' => $instructions,
            ],
            'assigned_by' => [
                'name' => $supervisor['name'],
                'designation' => $supervisor['designation'],
                'phone' => $supervisor['phone'],
            ],
            'checklist' => $formattedChecklist,
            'button_label' => 'Start Day ' . $currentDay . ' Task',
        ]);
    }

    public function toggleChecklist(Request $request, TaskChecklist $checklist)
    {
        $member = $request->user();
        $task = ExecutionTask::with('project')
            ->where('id', $checklist->execution_task_id)
            ->first();
        if (!$task) {
            return response()->json([
                'success' => false,
                'error_code' => 'CHECKLIST_ORPHAN',
                'message' => 'Checklist is not linked to a valid task.',
            ], 422);
        }
        if (!$this->authorizeTaskAccess($request, $task)) {
            return $this->forbiddenTaskAccessResponse();
        }

        $newStatus = !$checklist->is_completed;

        try {
            $checklist->update([
                'is_completed' => $newStatus,
                'status' => $newStatus ? 'completed' : 'pending',
                'completed_by_member_id' => $newStatus ? $member->id : null,
                'completed_at' => $newStatus ? now() : null,
            ]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update checklist item.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Checklist item updated.',
            'item' => [
                'id' => $checklist->id,
                'item_title' => $checklist->item_title,
                'assign_hours' => $checklist->assign_hours ? (float) $checklist->assign_hours : null,
                'notes' => $checklist->notes,
                'status' => $checklist->status,
                'image_url_1' => $checklist->image_url_1,
                'image_url_2' => $checklist->image_url_2,
                'is_completed' => (bool) $checklist->is_completed,
            ],
        ]);
    }

    public function storeChecklist(Request $request, int $task)
    {
        $resolved = $this->resolveTaskAcrossTables($task);
        if ($resolved === null) {
            return $this->taskNotFoundResponse();
        }

        [$legacyTask, $unifiedTask] = [$resolved['task'], $resolved['unifiedTask']];

        if (! $this->authorizeTaskAccess($request, $legacyTask)) {
            return $this->forbiddenTaskAccessResponse();
        }

        $request->validate([
            'item_title' => 'required|string|max:255',
            'day_number' => 'nullable|integer',
            'assign_hours' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'status' => 'nullable|string|in:pending,in_progress,completed',
            'is_required' => 'nullable|boolean',
            'image_1' => 'nullable|image|max:10240',
            'image_2' => 'nullable|image|max:10240',
        ]);

        $project = $legacyTask->project;
        $defaultDay = 1;
        try {
            $latestVisit = SurveyVisit::where('project_id', $legacyTask->project_id)
                ->whereNotNull('check_in_at')
                ->latest('check_in_at')
                ->first();
            $defaultDay = $this->surveyData->getCurrentDayNumber($latestVisit, null, $project);
        } catch (\Throwable $e) {
            report($e);
        }

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

        try {
            $checklist = TaskChecklist::create([
                'execution_task_id' => $legacyTask->id,
                'day_number' => $request->day_number ?? $defaultDay,
                'item_title' => $request->item_title,
                'assign_hours' => $request->assign_hours ?? null,
                'notes' => $request->notes ?? null,
                'status' => $request->status ?? 'pending',
                'image_url_1' => $imageUrl1,
                'image_url_2' => $imageUrl2,
                'is_completed' => false,
            ]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Failed to add checklist item.',
            ], 500);
        }

        // Mirror back to unified Task's TaskChecklistItem so the Admin panel
        // sees the checklist row without a second query layer (best-effort).
        if ($unifiedTask !== null) {
            try {
                \App\Models\TaskChecklistItem::query()->create([
                    'task_id' => $unifiedTask->id,
                    'project_id' => $project?->id,
                    'item_title' => $request->item_title,
                    'assign_hours' => $request->assign_hours ?? null,
                    'notes' => $request->notes ?? null,
                    'status' => $request->status ?? 'pending',
                    'image_url_1' => $imageUrl1,
                    'image_url_2' => $imageUrl2,
                    'is_completed' => false,
                    'sort_order' => $request->day_number ?? $defaultDay,
                ]);
            } catch (\Throwable $e) {
                report($e);
            }
        }

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

        try {
            if ($request->hasFile('photos')) {
                foreach ($request->file('photos') as $photoFile) {
                    $path = $photoFile->store('surveys/photos', 'public');
                    $photoPaths[] = Storage::url($path);
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }

        $filePath = null;
        try {
            if ($request->hasFile('file')) {
                $uploadedFile = $request->file('file');
                $path = $uploadedFile->store('surveys/documents', 'public');
                $filePath = Storage::url($path);
            }
        } catch (\Throwable $e) {
            report($e);
        }

        $visit = null;
        try {
            if ($request->filled('survey_visit_id')) {
                $visit = SurveyVisit::find($request->survey_visit_id);
            }
        } catch (\Throwable $e) {
            report($e);
        }

        if (!$visit) {
            try {
                $visit = SurveyVisit::where('checked_in_by_member_id', $member->id)
                    ->whereNull('check_out_at')
                    ->latest()
                    ->first();
            } catch (\Throwable $e) {
                report($e);
            }
        }

        $project = null;
        $plan = null;
        try {
            $project = $visit?->project
                ?? ($request->project_id ? Project::find($request->project_id) : null)
                ?? ProjectTeamMember::where('member_id', $member->id)
                    ->where('status', 'active')
                    ->orderByDesc('id')
                    ->first()?->project;

            $plan = $visit?->surveyPlan
                ?? ($request->survey_plan_id ? SurveyPlan::find($request->survey_plan_id) : null)
                ?? ($project ? SurveyPlan::where('project_id', $project->id)->orderByDesc('id')->first() : null);
        } catch (\Throwable $e) {
            report($e);
        }

        $defaultCoords = $this->surveyData->resolveSiteCoordinates($plan, $project);
        $lat = $request->latitude !== null ? (float) $request->latitude : $defaultCoords['latitude'];
        $long = $request->longitude !== null ? (float) $request->longitude : $defaultCoords['longitude'];

        $currentDay = $this->surveyData->getCurrentDayNumber($visit, $plan, $project);

        $gpsAccuracy = match (true) {
            $request->filled('gps_accuracy') => (string) $request->gps_accuracy,
            $visit?->gps_distance_meters => number_format($visit->gps_distance_meters, 1) . ' m',
            default => '2.6 m',
        };
        $gpsStatus = match (true) {
            $request->filled('gps_status') => (string) $request->gps_status,
            $visit?->gps_verified => 'Good',
            default => 'Good',
        };

        $resolvedProjectId = $visit?->project_id
            ?? $project?->id
            ?? ($request->filled('project_id') ? (int) $request->input('project_id') : null)
            ?? ProjectTeamMember::query()
                ->where('member_id', $member->id)
                ->where('status', 'active')
                ->latest()
                ->value('project_id');

        try {
            if ($visit) {
                $visit->update([
                    'project_id' => $visit->project_id ?? $resolvedProjectId,
                    'survey_plan_id' => $visit->survey_plan_id ?? $plan?->id,
                    'checked_in_by_member_id' => $visit->checked_in_by_member_id ?? $member->id,
                    'check_in_latitude' => $lat,
                    'check_in_longitude' => $long,
                    'elevation_m' => $request->elevation_m ?? $visit->elevation_m,
                    'distance_covered_m' => $request->distance_covered_m ?? $visit->distance_covered_m,
                    'total_points_captured' => $request->total_points_captured ?? $visit->total_points_captured,
                    'remarks' => $request->remarks ?? $visit->remarks,
                    'notes' => $request->notes ?? $visit->notes,
                    'photos' => !empty($photoPaths) ? $photoPaths : ($visit->photos ?? []),
                    'file_path' => $filePath ?? $visit->file_path,
                    'day_number' => $visit->day_number ?? $currentDay,
                    'status' => SurveyStatus::SUBMITTED,
                ]);
            } else {
                $projectId = $project?->id ?? $request->project_id ?? Project::value('id');
                $planId = $plan?->id ?? $request->survey_plan_id ?? SurveyPlan::value('id');

                $visit = SurveyVisit::create([
                    'project_id' => $projectId,
                    'survey_plan_id' => $planId,
                    'checked_in_by_member_id' => $member->id,
                    'check_in_at' => now(),
                    'check_in_latitude' => $lat,
                    'check_in_longitude' => $long,
                    'elevation_m' => $request->elevation_m,
                    'distance_covered_m' => $request->distance_covered_m,
                    'total_points_captured' => $request->total_points_captured,
                    'remarks' => $request->remarks,
                    'notes' => $request->notes,
                    'photos' => $photoPaths,
                    'file_path' => $filePath,
                    'day_number' => $currentDay,
                    'gps_verified' => true,
                    'status' => SurveyStatus::SUBMITTED,
                ]);
            }
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Failed to submit survey data. Please try again.',
            ], 500);
        }

        try {
            $needCorrection = false;
            $correction = [];
            if ($visit->project_id === null && $resolvedProjectId !== null) {
                $correction['project_id'] = (int) $resolvedProjectId;
                $needCorrection = true;
            }
            if ($visit->checked_in_by_member_id === null) {
                $correction['checked_in_by_member_id'] = (int) $member->id;
                $needCorrection = true;
            }
            if ($needCorrection) {
                try {
                    $visit->update($correction);
                } catch (\Throwable $e) {
                    report($e);
                }
            }

            $submittedAt = now();
            $projectId = $visit->project_id ?? $resolvedProjectId;

            if ($projectId !== null) {
                try {
                    SurveySubmission::query()->updateOrCreate(
                        [
                            'project_id' => (int) $projectId,
                            'survey_visit_id' => (int) $visit->id,
                        ],
                        [
                            'submitted_by_member_id' => (int) $member->id,
                            'submitted_at' => $submittedAt,
                            'status' => SurveyStatus::SUBMITTED,
                        ]
                    );
                } catch (\Throwable $e) {
                    report($e);
                }
            }

            if ($projectId !== null) {
                $project = Project::query()->find($projectId);
                if ($project) {
                    $legacyTask = ExecutionTask::query()
                        ->where('project_id', (int) $projectId)
                        ->where(function (Builder $q) use ($member) {
                            $q->where('supervisor_member_id', $member->id)
                                ->orWhereHas('assignees', function (Builder $sub) use ($member) {
                                    $sub->where('member_id', $member->id)->where('status', 'active');
                                });
                        })
                        ->orderByDesc('id')
                        ->first()
                        ?? ExecutionTask::query()
                            ->where('project_id', (int) $projectId)
                            ->first();

                    if ($legacyTask && $legacyTask->status !== 'completed') {
                        $legacyTask->update([
                            'status' => 'in_progress',
                            'progress_percent' => min(100, ($legacyTask->progress_percent ?? 0) + 20),
                        ]);
                    }

                    $unifiedTask = Task::query()
                        ->where('project_id', (int) $projectId)
                        ->where(function (Builder $q) use ($member) {
                            $q->where('assigned_supervisor_member_id', $member->id)
                                ->orWhere('member_id', $member->id)
                                ->orWhereHas('assignedMembers', function (Builder $sub) use ($member) {
                                    $sub->where('assigned_to', $member->id);
                                });
                        })
                        ->orderByDesc('id')
                        ->first()
                        ?? Task::query()
                            ->where('project_id', (int) $projectId)
                            ->first();

                    if ($unifiedTask && ! in_array(strtolower((string) $unifiedTask->status), ['completed','cancelled'], true)) {
                        $newProgress = min(100, (int) ($unifiedTask->progress_percent ?? 0) + 20);
                        try {
                            DB::beginTransaction();

                            $unifiedTask->update([
                                'status' => 'in_progress',
                                'progress_percent' => $newProgress,
                            ]);

                            $commentBody = sprintf(
                                'Survey data submitted on Day %d — points: %d, distance: %sm, elevation: %sm.',
                                (int) ($visit->day_number ?? 1),
                                (int) ($visit->total_points_captured ?? 0),
                                (float) ($visit->distance_covered_m ?? 0),
                                (float) ($visit->elevation_m ?? 0)
                            );
                            if (! empty($visit->remarks)) {
                                $commentBody .= ' Remarks: ' . $visit->remarks;
                            }

                            \App\Models\TaskComment::query()->create([
                                'task_id' => (int) $unifiedTask->id,
                                'commented_by' => (int) $member->id,
                                'comment' => $commentBody,
                                'status_from' => (string) $unifiedTask->getOriginal('status') ?? null,
                                'status_to' => 'in_progress',
                                'gps_latitude' => $visit->check_in_latitude,
                                'gps_longitude' => $visit->check_in_longitude,
                            ]);

                            DB::commit();
                        } catch (\Throwable $e) {
                            DB::rollBack();
                            report($e);
                        }
                    }

                    $project->update([
                        'progress_percent' => min(100, ($project->progress_percent ?? 0) + 10),
                    ]);
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return response()->json([
            'success' => true,
            'message' => 'Day ' . $currentDay . ' survey update data, photos, and report submitted successfully.',
            'data' => [
                'visit_id' => $visit->id,
                'gps_location' => [
                    'latitude' => $visit->check_in_latitude,
                    'longitude' => $visit->check_in_longitude,
                    'accuracy' => $gpsAccuracy,
                    'status' => $gpsStatus,
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

        try {
            $roleName = ProjectTeamMember::where('project_id', $project->id)
                ->where('member_id', $memberId)
                ->with('role')
                ->first()?->role?->name ?? 'Survey Team Member';
        } catch (\Throwable $e) {
            report($e);
            $roleName = 'Survey Team Member';
        }

        $totalDays = $this->surveyData->getTotalSurveyDays(null, $project);
        $diffDays = $totalDays;

        if ($project->start_date && $project->expected_end_date) {
            try {
                $diffDays = max(1, $project->start_date->diffInDays($project->expected_end_date));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        $startDateFormatted = $project->start_date
            ? $project->start_date->format('d M Y')
            : now()->setTimezone('Asia/Kolkata')->format('d M Y');

        $endDate = $project->expected_end_date ?? now()->setTimezone('Asia/Kolkata')->addDays(max(0, $totalDays - 1));
        try {
            $daysLeft = now()->setTimezone('Asia/Kolkata')->diffInDays($endDate, false);
            $daysLeftStr = $daysLeft > 0 ? "{$daysLeft} Days Left" : 'Deadline Today';
        } catch (\Throwable $e) {
            report($e);
            $daysLeftStr = "{$totalDays} Days";
        }

        try {
            $avgTaskProgress = $project->executionTasks?->avg('progress_percent');
            $progressPercent = (int) ($project->progress_percent ?? (is_numeric($avgTaskProgress) ? round($avgTaskProgress) : 60));
        } catch (\Throwable $e) {
            report($e);
            $progressPercent = 0;
        }

        try {
            $primaryPlan = $project->surveyPlans->sortByDesc('id')->first();
        } catch (\Throwable $e) {
            report($e);
            $primaryPlan = null;
        }

        $supervisor = $this->surveyData->resolveSupervisorInfo($project);
        $location = $this->surveyData->resolveSiteAddress($primaryPlan, $project);
        $stepper = $this->surveyData->buildProjectSurveyStepper($project, $primaryPlan);
        $description = $this->surveyData->resolveProjectDefaultDescription($project);

        $projectStatus = $project->status === 'active' ? 'in_progress' : strtolower($project->status ?? 'in_progress');
        $projectStatusLabel = $project->status === 'active' ? 'In Progress' : ucfirst($project->status ?? 'In Progress');

        return response()->json([
            'success' => true,
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'location' => $location,
                'status' => $projectStatus,
                'status_label' => $projectStatusLabel,
                'progress_percent' => $progressPercent,
                'start_date_formatted' => $startDateFormatted,
                'duration_formatted' => "{$diffDays} Days",
                'days_left_formatted' => $daysLeftStr,
                'assigned_role' => $roleName,
                'supervisor' => [
                    'name' => $supervisor['name'],
                    'designation' => $supervisor['designation'],
                    'phone' => $supervisor['phone'],
                ],
                'description' => $description,
                'survey_plan_overview' => $stepper,
            ],
        ]);
    }
}
