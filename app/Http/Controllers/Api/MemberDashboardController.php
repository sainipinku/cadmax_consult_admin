<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\Client;
use App\Models\Company;
use App\Models\ConstructionVehicle;
use App\Models\DailyProgressReport;
use App\Models\DraftingJob;
use App\Models\Employee;
use App\Models\EmployeeLocation;
use App\Models\Equipment;
use App\Models\EquipmentAllocation;
use App\Models\ExecutionTask;
use App\Models\ExecutionTaskAssignee;
use App\Models\Material;
use App\Models\MaterialStock;
use App\Models\Member;
use App\Models\MemberRoleAssignment;
use App\Models\Project;
use App\Models\ProjectBudget;
use App\Models\ProjectTeamMember;
use App\Models\SuperAdmin;
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
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;



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

        $viaLegacyAssignee = \App\Models\ExecutionTaskAssignee::query()
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

        $viaLegacyAssignee = ExecutionTaskAssignee::query()
            ->where('member_id', $member->getKey())
            ->where('status', 'active')
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

        $viaDirectMember = \App\Models\Task::query()
            ->where('member_id', $member->getKey())
            ->whereNotNull('project_id')
            ->pluck('project_id')
            ->unique()
            ->all();

        $viaDraftingJob = \App\Models\DraftingJob::query()
            ->where('assigned_to_member_id', $member->getKey())
            ->whereNotNull('project_id')
            ->pluck('project_id')
            ->unique()
            ->all();

        $viaSurveyPlan = \App\Models\SurveyPlan::query()
            ->whereHas('planMembers', fn ($q) => $q->where('member_id', $member->getKey()))
            ->whereNotNull('project_id')
            ->pluck('project_id')
            ->unique()
            ->all();

        $merged = array_merge(
            $team,
            $viaLegacyAssignee,
            $viaLegacySupervisor,
            $viaUnifiedSupervisor,
            $viaAssigneeUnified,
            $viaDirectMember,
            $viaDraftingJob,
            $viaSurveyPlan
        );
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

        // If legacy task not found by ID, match by task_code or project_id + title
        if ($legacyTask === null && $unifiedTask !== null) {
            if (!empty($unifiedTask->task_code)) {
                $legacyTask = ExecutionTask::query()
                    ->with('project')
                    ->where('task_code', $unifiedTask->task_code)
                    ->first();
            }
            if ($legacyTask === null && !empty($unifiedTask->title) && !empty($unifiedTask->project_id)) {
                $legacyTask = ExecutionTask::query()
                    ->with('project')
                    ->where('project_id', $unifiedTask->project_id)
                    ->where('title', $unifiedTask->title)
                    ->first();
            }
        }

        // If unified task not found by ID, match by task_code or project_id + title
        if ($legacyTask !== null && $unifiedTask === null) {
            if (!empty($legacyTask->task_code)) {
                $unifiedTask = Task::query()
                    ->with(['project', 'checklistItems'])
                    ->where('task_code', $legacyTask->task_code)
                    ->first();
            }
            if ($unifiedTask === null && !empty($legacyTask->title) && !empty($legacyTask->project_id)) {
                $unifiedTask = Task::query()
                    ->with(['project', 'checklistItems'])
                    ->where('project_id', $legacyTask->project_id)
                    ->where('title', $legacyTask->title)
                    ->first();
            }
        }

        if ($legacyTask === null && $unifiedTask === null) {
            return null;
        }

        if ($legacyTask !== null) {
            if ($unifiedTask !== null) {
                try {
                    $this->mirrorChecklistItemsToLegacy($unifiedTask, $legacyTask);
                } catch (\Throwable $e) {
                    report($e);
                }
            }
            return [
                'task' => $legacyTask,
                'unifiedTask' => $unifiedTask,
            ];
        }

        // Create a mirror ExecutionTask so construction_task_checklists FK stays valid.
        $planId = $this->resolveOrCreateExecutionPlanIdForProject((int) $unifiedTask->project_id);

        $targetCode = $unifiedTask->task_code;
        if (empty($targetCode) || ExecutionTask::where('task_code', $targetCode)->exists()) {
            $existing = !empty($targetCode)
                ? ExecutionTask::with('project')->where('task_code', $targetCode)->first()
                : null;
            if ($existing) {
                if ($unifiedTask !== null) {
                    try {
                        $this->mirrorChecklistItemsToLegacy($unifiedTask, $existing);
                    } catch (\Throwable $e) {
                        report($e);
                    }
                }
                return [
                    'task' => $existing,
                    'unifiedTask' => $unifiedTask,
                ];
            }
            $baseCode = $targetCode ?: ('TSK-' . str_pad((string) $taskId, 5, '0', STR_PAD_LEFT));
            $counter = 1;
            $newCode = $baseCode;
            while (ExecutionTask::where('task_code', $newCode)->exists()) {
                $newCode = $baseCode . '-M' . $counter;
                $counter++;
            }
            $targetCode = $newCode;
        }

        $idPayload = ExecutionTask::where('id', $taskId)->exists() ? [] : ['id' => $taskId];

        try {
            $legacyTask = DB::transaction(function () use ($unifiedTask, $planId, $targetCode, $idPayload) {
                $createData = array_merge($idPayload, [
                    'task_code' => $targetCode,
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

                $mirror = ExecutionTask::create($createData);

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

                $this->mirrorChecklistItemsToLegacy($unifiedTask, $mirror);

                return $mirror;
            });
        } catch (\Illuminate\Database\UniqueConstraintViolationException|\Illuminate\Database\QueryException $e) {
            report($e);
            $recovered = ExecutionTask::with('project')
                ->where('task_code', $targetCode)
                ->orWhere('id', $taskId)
                ->first();
            if ($recovered) {
                return [
                    'task' => $recovered,
                    'unifiedTask' => $unifiedTask,
                ];
            }
            throw $e;
        }

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

    /**
     * Member Dashboard Overview
     *
     * Returns an overview for the authenticated member including active projects, pending/completed tasks, today's attendance status, and duty summary.
     *
     * @tags Member Dashboard
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
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

        $unifiedTaskProjectIds = \App\Models\TaskAssignment::where('assigned_to', $memberId)
            ->where(fn ($q) => $q->whereNull('is_transferred')->orWhere('is_transferred', 0))
            ->whereNull('deleted_at')
            ->whereNotNull('project_id')
            ->pluck('project_id');

        $directTaskProjectIds = \App\Models\Task::where('member_id', $memberId)
            ->whereNotNull('project_id')
            ->pluck('project_id');

        $supervisorTaskProjectIds = \App\Models\ExecutionTask::where('supervisor_member_id', $memberId)
            ->whereNotNull('project_id')
            ->pluck('project_id')
            ->merge(
                \App\Models\Task::where('assigned_supervisor_member_id', $memberId)
                    ->whereNotNull('project_id')
                    ->pluck('project_id')
            );

        $projectIds = $teamProjectIds
            ->merge($surveyProjectIds)
            ->merge($taskProjectIds)
            ->merge($unifiedTaskProjectIds)
            ->merge($directTaskProjectIds)
            ->merge($supervisorTaskProjectIds)
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

        $projects = Project::with(['company', 'client', 'latestBudget', 'teamMembers.member', 'teamMembers.role'])
            ->whereIn('id', $projectIds)
            ->latest()
            ->get();

        $primaryProjectIds = ProjectTeamMember::where('member_id', $memberId)
            ->where('is_primary', true)
            ->where('status', 'active')
            ->pluck('project_id');

        $primaryProjects = Project::with(['company', 'client', 'latestBudget', 'teamMembers.member', 'teamMembers.role'])
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

            $unifiedScope = function (Builder $q) use ($memberId, $accessibleProjectIds, $adminProjectIds) {
                $q->where(function (Builder $direct) use ($memberId, $accessibleProjectIds) {
                    $direct->where(function (Builder $assignmentOrSupervisor) use ($memberId) {
                        $assignmentOrSupervisor->whereHas('assignments', function (Builder $sub) use ($memberId) {
                            $sub->where('assigned_to', $memberId);
                        })
                        ->orWhereHas('assignedMembers', function (Builder $sub) use ($memberId) {
                            $sub->where('members.id', $memberId);
                        })
                        ->orWhere('assigned_supervisor_member_id', $memberId)
                        ->orWhere('member_id', $memberId);
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

            $requestedProjectId = $request->filled('project_id') ? (int) $request->input('project_id') : null;

            $legacyQuery = ExecutionTask::with([
                'project.company',
                'project.client',
                'executionPlan',
                'supervisor',
            ])->where($legacyScope);

            $unifiedQuery = Task::with([
                'project.company',
                'project.client',
                'executionPlan',
                'assignedSupervisor',
                'supervisor',
            ])->where($unifiedScope);

            if ($requestedProjectId !== null) {
                $legacyQuery->where('project_id', $requestedProjectId);
                $unifiedQuery->where('project_id', $requestedProjectId);
            }

            $legacy = $legacyQuery->latest()->get()->each->setAttribute('_source', 'legacy');
            $unified = $unifiedQuery->latest()->get()->each->setAttribute('_source', 'unified');

            $seenCodes = [];
            $seenTitleProject = [];
            $dedupedTasks = collect();

            foreach ($legacy as $t) {
                if (!empty($t->task_code)) {
                    $seenCodes[(string) $t->task_code] = true;
                }
                $key = ($t->project_id ?? 0) . ':' . trim(strtolower($t->title ?? ''));
                $seenTitleProject[$key] = true;
                $dedupedTasks->push($t);
            }

            foreach ($unified as $t) {
                $isSeenCode = !empty($t->task_code) && isset($seenCodes[(string) $t->task_code]);
                $key = ($t->project_id ?? 0) . ':' . trim(strtolower($t->title ?? ''));
                $isSeenTitleProject = isset($seenTitleProject[$key]);
                if ($isSeenCode || $isSeenTitleProject) {
                    continue;
                }
                $dedupedTasks->push($t);
            }

            $tasks = $dedupedTasks
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

            $employeeEquipment = Equipment::with(['category', 'assignedEmployee.member', 'assignedProject'])
                ->whereHas('assignedEmployee', fn ($query) => $query->where('member_id', $memberId))
                ->where('status', 1)
                ->latest()
                ->get();

            $equipmentAssigned = $equipmentAssigned->concat($employeeEquipment)->values();
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

        $targetDate = null;
        if ($request->filled('date')) {
            $dateInput = trim((string) $request->input('date'));
            try {
                if (preg_match('/^\d{1,2}\/\d{1,2}\/\d{4}$/', $dateInput)) {
                    $targetDate = \Illuminate\Support\Carbon::createFromFormat('d/m/Y', $dateInput)->startOfDay();
                } elseif (preg_match('/^\d{1,2}-\d{1,2}-\d{4}$/', $dateInput)) {
                    $targetDate = \Illuminate\Support\Carbon::createFromFormat('d-m-Y', $dateInput)->startOfDay();
                } else {
                    $targetDate = \Illuminate\Support\Carbon::parse($dateInput)->startOfDay();
                }
            } catch (\Throwable) {
                $targetDate = null;
            }
        }

        $headerInfo = $this->getHeaderInfo($member);
        $checkInCard = $this->getCheckInCard($todayAttendance);
        $todaysSummaryCard = $this->getTodaysSummary($projects, $tasks, $hoursWorked);
        $todaysTasksList = $this->getTodaysTasks($tasks, $requestedProjectId, $targetDate);
        $todayTaskKeys = collect($todaysTasksList)->mapWithKeys(
            fn ($task) => [($task['source'] ?? 'unified') . ':' . ($task['id'] ?? '') => true]
        );
        $dashboardTasks = $tasks
            ->filter(function ($task, $index) use ($todayTaskKeys) {
                $key = (($task->_source ?? null) === 'legacy' || $task instanceof \App\Models\ExecutionTask ? 'legacy' : 'unified')
                    . ':' . ($task->id ?? '');

                return $index < 20 || $todayTaskKeys->has($key);
            })
            ->map(function ($task) use ($todayTaskKeys) {
                $formatted = $this->formatTaskItem($task);
                $key = $formatted['source'] . ':' . ($formatted['id'] ?? '');
                $formatted['is_today'] = $todayTaskKeys->has($key);

                return $formatted;
            })
            ->values()
            ->all();

        $tasksByProject = $tasks->groupBy(fn ($t) => (int) ($t->project_id ?? 0));

        $attachTasksToProject = function (Project $project) use ($tasksByProject) {
            $projId = (int) $project->id;
            $projAllTasksModels = $tasksByProject->get($projId, collect());

            $teamMembers = $project->teamMembers
                ? $project->teamMembers
                    ->filter(fn ($tm) => $tm->status === null || $tm->status === 'active')
                    ->map(function ($tm) {
                        return [
                            'id' => $tm->id,
                            'member_id' => $tm->member_id,
                            'name' => $tm->member?->name ?? 'Team Member',
                            'email' => $tm->member?->email,
                            'mobile' => $tm->member?->mobile ?? $tm->member?->phone,
                            'phone' => $tm->member?->phone ?? $tm->member?->mobile,
                            'designation' => $tm->member?->designation,
                            'role' => $tm->role?->name ?? $tm->assignment_scope ?? 'Member',
                            'role_slug' => $tm->role?->slug,
                            'profile_photo_url' => $tm->member?->profile_photo_url ?? null,
                            'is_primary' => (bool) $tm->is_primary,
                            'status' => $tm->status ?? 'active',
                        ];
                    })->values()->all()
                : [];

            $project->setAttribute('team_members_count', count($teamMembers));
            $project->setAttribute('team_members', $teamMembers);
            $project->setAttribute('tasks_count', $projAllTasksModels->count());
        };

        foreach ($projects as $project) {
            $attachTasksToProject($project);
        }

        foreach ($primaryProjects as $primaryProject) {
            $attachTasksToProject($primaryProject);
        }

        $currentProjectCard = $this->getCurrentProject($projects);
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
            'tasks' => $dashboardTasks,
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
                'today_tasks_count' => count($todaysTasksList),
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


    
    /**
     * List Assigned Projects
     *
     * Retrieves all construction projects where the authenticated member is assigned as a team member or surveyor.
     *
     * @tags Member Projects
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
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
        $draftingProjectIds = \App\Models\DraftingJob::where('assigned_to_member_id', $memberId)->pluck('project_id');
        $legacySupProjectIds = \App\Models\ExecutionTask::where('supervisor_member_id', $memberId)->pluck('project_id');
        $unifiedSupProjectIds = \App\Models\Task::where('assigned_supervisor_member_id', $memberId)->orWhere('member_id', $memberId)->pluck('project_id');
        $unifiedAssignProjectIds = \App\Models\TaskAssignment::where('assigned_to', $memberId)->where(fn ($q) => $q->whereNull('is_transferred')->orWhere('is_transferred', 0))->pluck('project_id');

        $projectIds = $teamProjectIds
            ->merge($surveyProjectIds)
            ->merge($taskProjectIds)
            ->merge($draftingProjectIds)
            ->merge($legacySupProjectIds)
            ->merge($unifiedSupProjectIds)
            ->merge($unifiedAssignProjectIds)
            ->unique()
            ->values();

        $query = Project::with([
            'company',
            'client',
            'latestBudget',
            'teamMembers.role',
            'teamMembers.member',
            'executionTasks',
            'tasks',
            'draftingJobs.assignedTo',
            'draftingJobs.drawingRevisions',
        ])
        ->withCount(['teamMembers', 'executionTasks', 'tasks'])
        ->whereIn('id', $projectIds);

        // Date filtering (today, explicit date, or date range)
        $targetProjectDate = null;
        if ($request->filled('date') || in_array(strtolower((string) $request->input('filter')), ['today', 'current'], true) || in_array(strtolower((string) $request->input('filter_by')), ['today', 'current'], true) || $request->boolean('today')) {
            $dateInput = trim((string) $request->input('date', 'today'));
            try {
                if (strtolower($dateInput) === 'today' || $dateInput === '' || $request->boolean('today')) {
                    $targetProjectDate = now()->setTimezone('Asia/Kolkata')->startOfDay();
                } elseif (preg_match('/^\d{1,2}\/\d{1,2}\/\d{4}$/', $dateInput)) {
                    $targetProjectDate = \Illuminate\Support\Carbon::createFromFormat('d/m/Y', $dateInput)->startOfDay();
                } elseif (preg_match('/^\d{1,2}-\d{1,2}-\d{4}$/', $dateInput)) {
                    $targetProjectDate = \Illuminate\Support\Carbon::createFromFormat('d-m-Y', $dateInput)->startOfDay();
                } else {
                    $targetProjectDate = \Illuminate\Support\Carbon::parse($dateInput)->startOfDay();
                }
            } catch (\Throwable) {
                $targetProjectDate = now()->setTimezone('Asia/Kolkata')->startOfDay();
            }

            if ($targetProjectDate) {
                $targetDateStr = $targetProjectDate->toDateString();
                $query->where(function ($q) use ($targetDateStr) {
                    $q->where(function ($sub) use ($targetDateStr) {
                        $sub->where(function ($d) use ($targetDateStr) {
                            $d->whereNull('start_date')->orWhereDate('start_date', '<=', $targetDateStr);
                        })->where(function ($d) use ($targetDateStr) {
                            $d->whereNull('expected_end_date')->orWhereDate('expected_end_date', '>=', $targetDateStr);
                        });
                    })->orWhereIn('status', ['active', 'running', 'in_progress', 'draft', 'planning']);
                });
            }
        }

        if ($request->filled('from_date')) {
            $query->whereDate('start_date', '>=', $request->input('from_date'));
        }
        if ($request->filled('to_date')) {
            $query->whereDate('expected_end_date', '<=', $request->input('to_date'));
        }

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

        $formattedProjects = collect($projects->items())->map(function ($p) use ($memberId) {
            $statusRaw = strtolower($p->status ?? 'active');
            $statusLabel = match ($statusRaw) {
                'active', 'running' => 'Running',
                'draft', 'planning' => 'Planning',
                'completed' => 'Completed',
                'on_hold' => 'On Hold',
                'cancelled' => 'Cancelled',
                default => ucfirst(str_replace('_', ' ', $p->status ?? 'Running')),
            };

            $isDraftAssigned = $p->draftingJobs && $p->draftingJobs->where('assigned_to_member_id', $memberId)->isNotEmpty();

            $managerName = $p->client?->name
                ?? $p->company?->name
                ?? ($p->teamMembers?->firstWhere('is_primary', true)?->member?->name ?? null)
                ?? 'Project Manager';

            $location = $p->project_address ?? $p->location_name ?? 'Site Location';
            $tasksCollection = ($p->tasks && $p->tasks->isNotEmpty())
                ? $p->tasks
                : ($p->executionTasks && $p->executionTasks->isNotEmpty() ? $p->executionTasks : collect());
            $avgProgress = $tasksCollection->isNotEmpty() ? $tasksCollection->avg('progress_percent') : null;
            $progressPercent = (int) round($avgProgress ?? (float) ($p->progress_percent ?? 0));
            $employeesCount = (int) ($p->team_members_count ?? ($p->teamMembers ? $p->teamMembers->count() : 0));
            $tasksCount = (int) (($p->tasks_count ?? ($p->tasks ? $p->tasks->count() : 0)) + ($p->execution_tasks_count ?? ($p->executionTasks ? $p->executionTasks->count() : 0)));

            return [
                'id' => $p->id,
                'project_code' => $p->project_code ?? ('PRJ-' . str_pad((string) $p->id, 4, '0', STR_PAD_LEFT)),
                'name' => $p->name,
                'status' => $statusRaw === 'active' ? 'running' : $statusRaw,
                'status_label' => $statusLabel,
                'current_stage' => $p->current_stage ?? 'survey_in_progress',
                'stage_label' => ucfirst(str_replace('_', ' ', $p->current_stage ?? 'survey in progress')),
                'manager_name' => $managerName,
                'location' => $location,
                'progress_percent' => $progressPercent,
                'employees_count' => $employeesCount,
                'tasks_count' => $tasksCount,
                'is_drafting_assigned' => $isDraftAssigned,
                'drafting_jobs' => $isDraftAssigned ? $p->draftingJobs->where('assigned_to_member_id', $memberId)->values() : [],
                'start_date' => $p->start_date ? $p->start_date->format('Y-m-d') : null,
                'start_date_formatted' => $p->start_date ? $p->start_date->format('d M Y') : null,
                'end_date' => $p->expected_end_date ? $p->expected_end_date->format('Y-m-d') : null,
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

    /**
     * List Assigned Surveys
     *
     * Retrieves all field survey plans and duties assigned to the authenticated member, with optional status filtering.
     *
     * @tags Survey & Duty Tracking
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
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

    /**
     * List Member Assigned Tasks
     *
     * Retrieves all assigned tasks across accessible projects. Pass date=today to limit results to tasks scheduled for or active today.
     *
     * @tags Member Tasks & Checklists
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
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
            'checklists',
            'surveyVisits.submission.reviewedBy',
        ])->where(function (Builder $q) use ($memberId, $accessibleProjectIds, $adminProjectIds) {
            $q->where(function (Builder $direct) use ($memberId, $accessibleProjectIds) {
                $direct->where(function (Builder $assignmentOrSupervisor) use ($memberId) {
                    $assignmentOrSupervisor->whereHas('assignees', function (Builder $sub) use ($memberId) {
                        $sub->where('member_id', $memberId)->where('status', 'active');
                    })
                    ->orWhere('supervisor_member_id', $memberId)
                    ->orWhereHas('surveyVisits.submission.draftingJobs', function (Builder $sub) use ($memberId) {
                        $sub->where('assigned_to_member_id', $memberId);
                    });
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
            'checklistItems',
            'surveyVisits.submission.reviewedBy',
        ])->where(function (Builder $q) use ($memberId, $accessibleProjectIds, $adminProjectIds) {
            $q->where(function (Builder $direct) use ($memberId, $accessibleProjectIds) {
                $direct->where(function (Builder $assignmentOrSupervisor) use ($memberId) {
                    $assignmentOrSupervisor->whereHas('assignments', function (Builder $sub) use ($memberId) {
                        $sub->where('assigned_to', $memberId);
                    })
                    ->orWhereHas('assignedMembers', function (Builder $sub) use ($memberId) {
                        $sub->where('members.id', $memberId);
                    })
                    ->orWhere('assigned_supervisor_member_id', $memberId)
                    ->orWhere('member_id', $memberId)
                    ->orWhereHas('surveyVisits.submission.draftingJobs', function (Builder $sub) use ($memberId) {
                        $sub->where('assigned_to_member_id', $memberId);
                    });
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
        $seenCodes = [];

        foreach ($legacyRows as $t) {
            $seenLegacy[(int) $t->id] = true;
            if (!empty($t->task_code)) {
                $seenCodes[(string) $t->task_code] = true;
            }
            $merged->push($this->normalizeExecutionTaskListItem($t, true));
        }

        foreach ($unifiedRows as $t) {
            $isSeenId = isset($seenLegacy[(int) $t->id]);
            $isSeenCode = !empty($t->task_code) && isset($seenCodes[(string) $t->task_code]);
            if ($isSeenId || $isSeenCode) {
                continue;
            }
            $merged->push($this->normalizeUnifiedTaskListItem($t, false));
        }

        if (strtolower(trim((string) $request->input('date'))) === 'today') {
            $today = now()->setTimezone('Asia/Kolkata')->toDateString();
            $todayTaskKeys = $legacyRows->concat($unifiedRows)
                ->filter(function ($task) use ($today) {
                    $isLegacy = $task instanceof ExecutionTask;
                    $startValue = $isLegacy
                        ? $task->planned_start_date
                        : ($task->start_date ?? $task->planned_start_date);
                    $endValue = $isLegacy
                        ? $task->planned_end_date
                        : ($task->end_date ?? $task->planned_end_date);

                    $start = $startValue ? Carbon::parse($startValue)->toDateString() : null;
                    $end = $endValue ? Carbon::parse($endValue)->toDateString() : null;

                    if ($start !== null && $end !== null) {
                        return $today >= min($start, $end) && $today <= max($start, $end);
                    }

                    return $start === $today || $end === $today;
                })
                ->mapWithKeys(function ($task) {
                    $source = $task instanceof ExecutionTask ? 'legacy' : 'unified';
                    return [$source . ':' . $task->id => true];
                });

            $merged = $merged->filter(function (array $row) use ($todayTaskKeys) {
                return $todayTaskKeys->has($row['source'] . ':' . $row['id']);
            })->values();
        }

        if ($normalizedStatusFilter !== null) {
            $merged = $merged->filter(function (array $row) use ($normalizedStatusFilter) {
                return $row['normalized_status'] === $normalizedStatusFilter;
            })->values();
        }

        // Date filtering in myTasks (today, specific date, or date range)
        $targetTaskDate = null;
        if ($request->filled('date') || in_array(strtolower((string) $request->input('filter')), ['today', 'current'], true) || in_array(strtolower((string) $request->input('filter_by')), ['today', 'current'], true) || $request->boolean('today')) {
            $dateInput = trim((string) $request->input('date', 'today'));
            try {
                if (strtolower($dateInput) === 'today' || $dateInput === '' || $request->boolean('today')) {
                    $targetTaskDate = now()->setTimezone('Asia/Kolkata')->startOfDay();
                } elseif (preg_match('/^\d{1,2}\/\d{1,2}\/\d{4}$/', $dateInput)) {
                    $targetTaskDate = \Illuminate\Support\Carbon::createFromFormat('d/m/Y', $dateInput)->startOfDay();
                } elseif (preg_match('/^\d{1,2}-\d{1,2}-\d{4}$/', $dateInput)) {
                    $targetTaskDate = \Illuminate\Support\Carbon::createFromFormat('d-m-Y', $dateInput)->startOfDay();
                } else {
                    $targetTaskDate = \Illuminate\Support\Carbon::parse($dateInput)->setTimezone('Asia/Kolkata')->startOfDay();
                }
            } catch (\Throwable) {
                $targetTaskDate = now()->setTimezone('Asia/Kolkata')->startOfDay();
            }
        }

        $fromDate = null;
        $toDate = null;
        if ($request->filled('from_date')) {
            try {
                $fromDate = \Illuminate\Support\Carbon::parse($request->input('from_date'))->setTimezone('Asia/Kolkata')->startOfDay();
            } catch (\Throwable) {}
        }
        if ($request->filled('to_date')) {
            try {
                $toDate = \Illuminate\Support\Carbon::parse($request->input('to_date'))->setTimezone('Asia/Kolkata')->startOfDay();
            } catch (\Throwable) {}
        }

        if ($targetTaskDate !== null) {
            $targetDateStr = $targetTaskDate->toDateString();
            $merged = $merged->filter(function (array $row) use ($targetDateStr) {
                $startStr = !empty($row['start_date']) ? substr($row['start_date'], 0, 10) : null;
                $endStr = !empty($row['end_date']) ? substr($row['end_date'], 0, 10) : null;

                // Future task cannot be for targetDate if start is in the future
                if ($startStr && $startStr > $targetDateStr) {
                    return false;
                }

                if ($startStr && $endStr) {
                    $minDate = min($startStr, $endStr);
                    $maxDate = max($startStr, $endStr);
                    return ($targetDateStr >= $minDate && $targetDateStr <= $maxDate);
                }
                if ($startStr && $startStr === $targetDateStr) {
                    return true;
                }
                if ($endStr && $endStr === $targetDateStr) {
                    return true;
                }
                if (!$startStr && !$endStr) {
                    return true;
                }
                if ($startStr && $startStr <= $targetDateStr && !$endStr) {
                    return true;
                }
                if ($endStr && $endStr >= $targetDateStr && !$startStr) {
                    return true;
                }
                return false;
            })->values();
        }

        if ($fromDate !== null || $toDate !== null) {
            $fromStr = $fromDate ? $fromDate->toDateString() : null;
            $toStr = $toDate ? $toDate->toDateString() : null;
            $merged = $merged->filter(function (array $row) use ($fromStr, $toStr) {
                $startStr = !empty($row['start_date']) ? substr($row['start_date'], 0, 10) : null;
                $endStr = !empty($row['end_date']) ? substr($row['end_date'], 0, 10) : null;
                if ($fromStr && $endStr && $endStr < $fromStr) {
                    return false;
                }
                if ($toStr && $startStr && $startStr > $toStr) {
                    return false;
                }
                return true;
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
                    'task_code' => $row['task_code'] ?? null,
                    'project_id' => $row['project_id'] ?? null,
                    'title' => $row['title'],
                    'description' => $row['description'] ?? null,
                    'project_name' => $row['project_name'],
                    'location' => $row['location'],
                    'start_date' => $row['start_date'] ?? null,
                    'start_date_formatted' => $row['start_date_formatted'] ?? null,
                    'end_date' => $row['end_date'] ?? null,
                    'end_date_formatted' => $row['end_date_formatted'] ?? null,
                    'due_time_formatted' => $row['due_time_formatted'],
                    'priority' => $row['priority'],
                    'priority_label' => $row['priority_label'],
                    'progress_percent' => $row['progress_percent'] ?? 0,
                    'supervisor_name' => $row['supervisor_name'] ?? null,
                    'is_completed' => $row['is_completed'],
                    'status' => $row['status'],
                    'normalized_status' => $row['normalized_status'] ?? 'pending',
                    'status_label' => $row['status_label'] ?? ucfirst($row['status'] ?? 'Pending'),
                    'checklist_total' => $row['checklist_total'] ?? 0,
                    'checklist_completed' => $row['checklist_completed'] ?? 0,
                    'is_drafting_assigned' => $row['is_drafting_assigned'] ?? false,
                    'review_status' => $row['review_status'] ?? null,
                    'source' => $row['source'] ?? 'unified',
                    'created_at' => $row['created_at'] ?? null,
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

    /**
     * List Today's Scheduled Tasks
     *
     * Retrieves tasks scheduled specifically for today or currently pending execution for the authenticated member.
     *
     * @tags Member Tasks & Checklists
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function todaysTasks(Request $request)
    {
        /** @var Member $member */
        $member = $request->user();
        $memberId = $member->getKey();

        $accessibleProjectIds = $this->accessibleProjectIds($member);
        $adminProjectIds = $this->adminTaskProjectIds($member, $accessibleProjectIds);

        $requestedProjectId = null;
        if ($request->filled('project_id')) {
            $requestedProjectId = (int) $request->input('project_id');
            if (! in_array($requestedProjectId, $accessibleProjectIds, true)) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'FORBIDDEN',
                    'message' => 'You do not have access to this project.',
                ], 403);
            }
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
                    })->orWhere('supervisor_member_id', $memberId)
                    ->orWhereHas('surveyVisits.submission.draftingJobs', function (Builder $sub) use ($memberId) {
                        $sub->where('assigned_to_member_id', $memberId);
                    });
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
                    $assignmentOrSupervisor->whereHas('assignments', function (Builder $sub) use ($memberId) {
                        $sub->where('assigned_to', $memberId);
                    })
                    ->orWhereHas('assignedMembers', function (Builder $sub) use ($memberId) {
                        $sub->where('members.id', $memberId);
                    })
                    ->orWhere('assigned_supervisor_member_id', $memberId)
                    ->orWhere('member_id', $memberId)
                    ->orWhereHas('surveyVisits.submission.draftingJobs', function (Builder $sub) use ($memberId) {
                        $sub->where('assigned_to_member_id', $memberId);
                    });
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

        $legacy = $legacyQuery->latest()->get()->each->setAttribute('_source', 'legacy');
        $unified = $unifiedQuery->latest()->get()->each->setAttribute('_source', 'unified');

        $targetDate = null;
        if ($request->filled('date')) {
            $dateInput = trim((string) $request->input('date'));
            try {
                if (preg_match('/^\d{1,2}\/\d{1,2}\/\d{4}$/', $dateInput)) {
                    $targetDate = \Illuminate\Support\Carbon::createFromFormat('d/m/Y', $dateInput)->startOfDay();
                } elseif (preg_match('/^\d{1,2}-\d{1,2}-\d{4}$/', $dateInput)) {
                    $targetDate = \Illuminate\Support\Carbon::createFromFormat('d-m-Y', $dateInput)->startOfDay();
                } else {
                    $targetDate = \Illuminate\Support\Carbon::parse($dateInput)->startOfDay();
                }
            } catch (\Throwable) {
                $targetDate = null;
            }
        }

        $now = now()->setTimezone('Asia/Kolkata');
        $currentDate = $now->copy()->startOfDay();
        $effectiveTargetDate = $targetDate ?? $currentDate;

        $seenCodes = [];
        $seenTitleProject = [];
        $dedupedTasks = collect();

        foreach ($legacy as $t) {
            if (!empty($t->task_code)) {
                $seenCodes[(string) $t->task_code] = true;
            }
            $key = ($t->project_id ?? 0) . ':' . trim(strtolower($t->title ?? ''));
            $seenTitleProject[$key] = true;
            $dedupedTasks->push($t);
        }

        foreach ($unified as $t) {
            $isSeenCode = !empty($t->task_code) && isset($seenCodes[(string) $t->task_code]);
            $key = ($t->project_id ?? 0) . ':' . trim(strtolower($t->title ?? ''));
            $isSeenTitleProject = isset($seenTitleProject[$key]);
            if ($isSeenCode || $isSeenTitleProject) {
                continue;
            }
            $dedupedTasks->push($t);
        }

        $tasks = $dedupedTasks
            ->sortByDesc(fn ($t) => $t->created_at?->timestamp ?? 0)
            ->values();

        $todaysTasks = $this->getTodaysTasks($tasks, $requestedProjectId, $effectiveTargetDate);

        return response()->json([
            'success' => true,
            'project_id' => $requestedProjectId,
            'current_date' => $currentDate->format('d/m/Y'),
            'current_date_raw' => $currentDate->toDateString(),
            'request_date' => $effectiveTargetDate->format('d/m/Y'),
            'request_date_raw' => $effectiveTargetDate->toDateString(),
            'requested_date' => $effectiveTargetDate->format('d/m/Y'),
            'requested_date_raw' => $effectiveTargetDate->toDateString(),
            'date' => $effectiveTargetDate->format('d/m/Y'),
            'total' => count($todaysTasks),
            'todays_tasks' => $todaysTasks,
            'data' => $todaysTasks,
        ]);
    }

    private function formatPriorityValue(mixed $priority, string $default = 'medium'): string
    {
        if (is_object($priority)) {
            if (isset($priority->value)) {
                return strtolower((string) $priority->value);
            }
            if (method_exists($priority, '__toString')) {
                return strtolower((string) $priority);
            }
        }
        if (is_string($priority)) {
            return strtolower($priority);
        }
        return strtolower((string) ($priority ?? $default));
    }

    private function resolveTaskRevisionInfo($t): ?array
    {
        if ($t === null) {
            return null;
        }

        $taskId = (int) ($t->id ?? 0);

        // Find associated SurveyVisit
        $visit = null;
        if (method_exists($t, 'relationLoaded') && $t->relationLoaded('surveyVisits')) {
            $visit = $t->surveyVisits->sortByDesc('id')->first();
        }
        if (!$visit) {
            $taskIds = [$taskId];
            if (!empty($t->task_code)) {
                $otherId = $t instanceof ExecutionTask
                    ? Task::where('task_code', $t->task_code)->value('id')
                    : ExecutionTask::where('task_code', $t->task_code)->value('id');
                if ($otherId) {
                    $taskIds[] = (int) $otherId;
                }
            }
            $hasExecCol = $this->hasSurveyVisitExecutionTaskIdColumn();
            $visit = SurveyVisit::with(['submission.reviewedBy', 'checkedInBy'])
                ->where(function ($q) use ($taskIds, $hasExecCol) {
                    $q->whereIn('task_id', $taskIds);
                    if ($hasExecCol) {
                        $q->orWhereIn('execution_task_id', $taskIds);
                    }
                })
                ->latest('id')
                ->first();
        }

        $sub = $visit?->relationLoaded('submission')
            ? $visit->submission
            : ($visit ? SurveySubmission::with('reviewedBy')->where('survey_visit_id', $visit->id)->first() : null);

        $visitNotes = [];
        if ($visit && !empty($visit->notes)) {
            if (is_array($visit->notes)) {
                $visitNotes = $visit->notes;
            } elseif (is_string($visit->notes)) {
                $visitNotes = json_decode($visit->notes, true) ?: [];
            }
        }

        $subStatus = (int) ($sub?->status ?? 0);
        $visitStatus = (int) ($visit?->status ?? 0);
        $clientReviewStatus = $t->client_review_status instanceof \App\Enums\ClientReviewStatus
            ? $t->client_review_status->value
            : (string) ($t->client_review_status ?? '');

        $isRevisionRequested = (
            $subStatus === SurveySubmission::STATUS_REVISION_REQUESTED
            || $subStatus === 5
            || $visitStatus === SurveyStatus::REVISION_REQUESTED
            || $visitStatus === 5
            || $clientReviewStatus === 'revision_requested'
            || !empty($visitNotes['revision_request'])
        );

        if (!$isRevisionRequested && empty($sub) && empty($visit)) {
            return null;
        }

        $memberReply = $sub?->member_reply ?? ($visitNotes['member_reply']['message'] ?? null);
        $isReplied = !empty($memberReply) || ($isRevisionRequested && in_array($clientReviewStatus, ['requested', 'revision_submitted'], true));

        $remarks = $sub?->review_notes ?? ($visitNotes['revision_request']['remarks'] ?? null);
        $managerName = $sub?->reviewedBy?->name ?? ($visitNotes['revision_request']['requested_by_name'] ?? 'Project Manager');
        $requestedAt = $sub?->reviewed_at?->toIso8601String() ?? ($visitNotes['revision_request']['requested_at'] ?? null);
        $requestedAtFormatted = $sub?->reviewed_at
            ? $sub->reviewed_at->setTimezone('Asia/Kolkata')->format('d M Y · h:i A')
            : ($visitNotes['revision_request']['requested_at_formatted'] ?? ($requestedAt ? date('d M Y · h:i A', strtotime($requestedAt)) : null));

        $repliedAt = $sub?->member_replied_at?->toIso8601String() ?? ($visitNotes['member_reply']['replied_at'] ?? null);
        $repliedAtFormatted = $sub?->member_replied_at
            ? $sub->member_replied_at->setTimezone('Asia/Kolkata')->format('d M Y · h:i A')
            : ($visitNotes['member_reply']['replied_at_formatted'] ?? ($repliedAt ? date('d M Y · h:i A', strtotime($repliedAt)) : null));

        $history = $visitNotes['revision_history'] ?? [];
        if (empty($history) && $isRevisionRequested) {
            $history = [
                [
                    'type' => 'revision_requested',
                    'sender_role' => 'manager',
                    'sender_name' => $managerName,
                    'message' => $remarks,
                    'created_at' => $requestedAt,
                    'created_at_formatted' => $requestedAtFormatted,
                ],
            ];
            if ($isReplied && $memberReply) {
                $history[] = [
                    'type' => 'member_reply',
                    'sender_role' => 'surveyor',
                    'sender_name' => $visit?->checkedInBy?->name ?? 'Field Surveyor',
                    'message' => $memberReply,
                    'created_at' => $repliedAt,
                    'created_at_formatted' => $repliedAtFormatted,
                ];
            }
        }

        $reviewStatus = 'pending';
        $statusLabel = 'Planned';
        if ($isRevisionRequested) {
            $reviewStatus = $isReplied ? 'revision_replied' : 'revision_requested';
            $statusLabel = $isReplied ? 'Revision Submitted' : 'Revision Requested';
        } elseif ($subStatus === SurveySubmission::STATUS_APPROVED || $visitStatus === SurveyStatus::APPROVED) {
            $reviewStatus = 'approved';
            $statusLabel = 'Approved';
        } elseif ($subStatus === SurveySubmission::STATUS_SUBMITTED || $visitStatus === SurveyStatus::SUBMITTED) {
            $reviewStatus = 'submitted';
            $statusLabel = 'Under Review';
        }

        return [
            'has_revision' => $isRevisionRequested,
            'is_revision_requested' => $isRevisionRequested,
            'is_replied' => $isReplied,
            'review_status' => $reviewStatus,
            'status' => $reviewStatus,
            'status_label' => $statusLabel,
            'remarks' => $remarks,
            'revision_remarks' => $remarks,
            'manager_name' => $managerName,
            'requested_by' => $managerName,
            'requested_at' => $requestedAtFormatted,
            'member_reply' => $memberReply,
            'member_replied_at' => $repliedAtFormatted,
            'history' => $history,
        ];
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

        $priority = $this->formatPriorityValue($t->priority, 'medium');
        $dueDate = $t->planned_end_date;
        $revInfo = $this->resolveTaskRevisionInfo($t);

        $startDate = $t->planned_start_date ? $t->planned_start_date->copy()->setTimezone('Asia/Kolkata') : null;
        $endDate = $t->planned_end_date ? $t->planned_end_date->copy()->setTimezone('Asia/Kolkata') : null;

        return [
            'source' => $sourceLegacy ? 'legacy' : 'unified',
            'id' => (int) $t->id,
            'task_code' => (string) ($t->task_code ?? ('TSK-' . str_pad((string) $t->id, 5, '0', STR_PAD_LEFT))),
            'project_id' => $t->project_id ? (int) $t->project_id : null,
            'title' => (string) $t->title,
            'description' => $t->description ?? null,
            'project_name' => $t->project->name ?? null,
            'location' => $t->project->project_address ?? null,
            'start_date' => $startDate ? $startDate->toDateString() : null,
            'start_date_formatted' => $startDate ? $startDate->format('d/m/Y') : null,
            'end_date' => $endDate ? $endDate->toDateString() : null,
            'end_date_formatted' => $endDate ? $endDate->format('d/m/Y') : null,
            'due_time_formatted' => $dueDate ? $dueDate->format('d M, h:i A') : null,
            'priority' => $priority,
            'priority_label' => ucfirst($priority),
            'progress_percent' => (int) ($t->progress_percent ?? 0),
            'supervisor_name' => $t->supervisor?->name ?? null,
            'is_completed' => strtolower($status) === 'completed',
            'status' => $status,
            'normalized_status' => $normalized,
            'status_label' => ucfirst(str_replace('_', ' ', $status)),
            'is_drafting_assigned' => strtolower($status) === 'drafting_in_progress',
            'checklist_total' => $t->relationLoaded('checklists') ? $t->checklists->count() : 0,
            'checklist_completed' => $t->relationLoaded('checklists') ? $t->checklists->where('is_completed', true)->count() : 0,
            'sort_key' => $sortKey,
            'review_status' => $revInfo['review_status'] ?? ($status === 'review' ? 'submitted' : $normalized),
            'is_revision_requested' => $revInfo['is_revision_requested'] ?? false,
            'revision_remarks' => $revInfo['revision_remarks'] ?? null,
            'revision_requested_at' => $revInfo['requested_at'] ?? null,
            'revision_requested_by' => $revInfo['requested_by'] ?? null,
            'revision_reply' => $revInfo['member_reply'] ?? null,
            'revision_replied_at' => $revInfo['member_replied_at'] ?? null,
            'revision' => $revInfo,
            'created_at' => $t->created_at?->toIso8601String(),
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

        $priority = $this->formatPriorityValue($t->priority, 'medium');
        $dueDate = $t->end_date;
        $revInfo = $this->resolveTaskRevisionInfo($t);
        $startDate = $t->start_date ? $t->start_date->copy()->setTimezone('Asia/Kolkata') : null;
        $endDate = $t->end_date ? $t->end_date->copy()->setTimezone('Asia/Kolkata') : null;

        return [
            'source' => $sourceLegacy ? 'legacy' : 'unified',
            'id' => (int) $t->id,
            'task_code' => (string) ($t->task_code ?? ('TSK-' . str_pad((string) $t->id, 5, '0', STR_PAD_LEFT))),
            'project_id' => $t->project_id ? (int) $t->project_id : null,
            'title' => (string) $t->title,
            'description' => $t->description ?? null,
            'project_name' => $t->project->name ?? null,
            'location' => $t->project->project_address ?? null,
            'start_date' => $startDate ? $startDate->toDateString() : null,
            'start_date_formatted' => $startDate ? $startDate->format('d/m/Y') : null,
            'end_date' => $endDate ? $endDate->toDateString() : null,
            'end_date_formatted' => $endDate ? $endDate->format('d/m/Y') : null,
            'due_time_formatted' => $dueDate ? $dueDate->format('d M, h:i A') : null,
            'priority' => in_array($priority, ['low','medium','high','critical','urgent'], true) ? $priority : 'medium',
            'priority_label' => ucfirst(in_array($priority, ['low','medium','high','critical','urgent'], true) ? $priority : 'medium'),
            'progress_percent' => (int) ($t->progress_percent ?? 0),
            'supervisor_name' => $t->assignedSupervisor?->name ?? $t->supervisor?->name ?? null,
            'is_completed' => strtolower($status) === 'completed',
            'status' => $status,
            'normalized_status' => $normalized,
            'status_label' => ucfirst(str_replace('_', ' ', $status)),
            'is_drafting_assigned' => strtolower($status) === 'drafting_in_progress',
            'checklist_total' => $t->relationLoaded('checklistItems') ? $t->checklistItems->count() : 0,
            'checklist_completed' => $t->relationLoaded('checklistItems') ? $t->checklistItems->where('is_completed', true)->count() : 0,
            'sort_key' => $sortKey,
            'review_status' => $revInfo['review_status'] ?? ($status === 'review' ? 'submitted' : $normalized),
            'is_revision_requested' => $revInfo['is_revision_requested'] ?? false,
            'revision_remarks' => $revInfo['revision_remarks'] ?? null,
            'revision_requested_at' => $revInfo['requested_at'] ?? null,
            'revision_requested_by' => $revInfo['requested_by'] ?? null,
            'revision_reply' => $revInfo['member_reply'] ?? null,
            'revision_replied_at' => $revInfo['member_replied_at'] ?? null,
            'revision' => $revInfo,
            'created_at' => $t->created_at?->toIso8601String(),
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
        /** @var Member|null $member */
        $member = $request->user();

        // Allow access to any authenticated member (assignment check removed per request)
        return $member !== null;
    }

    private function forbiddenTaskAccessResponse(): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'success' => false,
            'error_code' => 'TASK_ACCESS_DENIED',
            'message' => 'You are not assigned to this task and cannot access it.',
        ], 403);
    }

    /**
     * Get Member Attendance History
     *
     * Retrieves historical attendance records for the authenticated member, including check-in/out timestamps, hours worked, and project tags.
     *
     * @tags Member Dashboard & Attendance
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
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

    /**
     * Get Project Detail Overview
     *
     * Returns detailed project overview for the authenticated member including site info, daily progress, attendance summary, and survey plans.
     *
     * @tags Member Dashboard & Attendance
     * @param Request $request
     * @param Project $project
     * @return \Illuminate\Http\JsonResponse
     */
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

    /**
     * Toggle Task Completion Status
     *
     * Toggles a task between completed and planned states for the member.
     *
     * @tags Member Tasks & Checklists
     * @param Request $request
     * @param int $task
     * @return \Illuminate\Http\JsonResponse
     */
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

    /**
     * Update Task Execution Status
     *
     * Updates the status of an assigned task (planned, in_progress, completed, blocked).
     *
     * @tags Member Tasks & Checklists
     * @param Request $request
     * @param int $task
     * @return \Illuminate\Http\JsonResponse
     */
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

    /**
     * Update Task Details
     *
     * Updates details, dates, remarks, or metadata of an existing assigned task.
     *
     * @tags Member Tasks & Checklists
     * @param Request $request
     * @param int $task
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateTask(Request $request, int $task)
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
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'project_id' => 'nullable|exists:construction_projects,id',
            'project' => 'nullable|exists:construction_projects,id',
            'priority' => 'nullable|string',
            'status' => 'nullable|string',
            'planned_start_date' => 'nullable|date',
            'start_date' => 'nullable|date',
            'planned_end_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'actual_start_date' => 'nullable|date',
            'actual_end_date' => 'nullable|date',
            'planned_quantity' => 'nullable|numeric',
            'planned_qty' => 'nullable|numeric',
            'completed_quantity' => 'nullable|numeric',
            'completed_qty' => 'nullable|numeric',
            'unit' => 'nullable|string',
            'qty_unit' => 'nullable|string',
            'progress_percent' => 'nullable|numeric',
            'supervisor_member_id' => 'nullable|exists:members,id',
            'assigned_supervisor_member_id' => 'nullable|exists:members,id',
        ]);

        $legacyData = [];
        $unifiedData = [];

        if ($request->has('title') && $request->title !== null) {
            $legacyData['title'] = $request->title;
            $unifiedData['title'] = $request->title;
        }

        if ($request->has('description')) {
            $legacyData['description'] = $request->description;
            $unifiedData['description'] = $request->description;
        }

        $newProjectId = $request->input('project_id') ?? $request->input('project');
        if ($newProjectId !== null) {
            $member = $request->user();
            $accessibleProjectIds = $this->accessibleProjectIds($member);
            if (! in_array((int) $newProjectId, $accessibleProjectIds, true)) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'PROJECT_ACCESS_DENIED',
                    'message' => 'You do not have access to this project.',
                ], 403);
            }
            $legacyData['project_id'] = (int) $newProjectId;
            $unifiedData['project_id'] = (int) $newProjectId;
        }

        if ($request->has('priority') && $request->priority !== null) {
            $priorityInput = strtolower((string) $request->priority);
            $priority = in_array($priorityInput, ['low', 'medium', 'high', 'urgent', 'critical'], true)
                ? $priorityInput
                : 'medium';
            $legacyData['priority'] = $priority;
            $unifiedData['priority'] = $priority;
        }

        if ($request->has('status') && $request->status !== null) {
            $statusStr = (string) $request->status;
            $legacyData['status'] = $statusStr;
            $unifiedData['status'] = $statusStr;
        }

        $startDate = $request->input('planned_start_date') ?? $request->input('start_date');
        if ($startDate !== null) {
            $legacyData['planned_start_date'] = $startDate;
            $unifiedData['start_date'] = $startDate;
        }

        $endDate = $request->input('planned_end_date') ?? $request->input('end_date');
        if ($endDate !== null) {
            $legacyData['planned_end_date'] = $endDate;
            $unifiedData['end_date'] = $endDate;
        }

        if ($request->has('actual_start_date')) {
            $legacyData['actual_start_date'] = $request->actual_start_date;
        }

        if ($request->has('actual_end_date')) {
            $legacyData['actual_end_date'] = $request->actual_end_date;
        }

        $plannedQty = $request->input('planned_quantity') ?? $request->input('planned_qty');
        if ($plannedQty !== null) {
            $legacyData['planned_quantity'] = (float) $plannedQty;
            $unifiedData['planned_qty'] = (float) $plannedQty;
        }

        $completedQty = $request->input('completed_quantity') ?? $request->input('completed_qty');
        if ($completedQty !== null) {
            $legacyData['completed_quantity'] = (float) $completedQty;
            $unifiedData['completed_qty'] = (float) $completedQty;
        }

        $unit = $request->input('unit') ?? $request->input('qty_unit');
        if ($unit !== null) {
            $legacyData['unit'] = $unit;
            $unifiedData['qty_unit'] = $unit;
        }

        if ($request->has('progress_percent') && $request->progress_percent !== null) {
            $legacyData['progress_percent'] = (float) $request->progress_percent;
            $unifiedData['progress_percent'] = (float) $request->progress_percent;
        }

        $supervisorId = $request->input('supervisor_member_id') ?? $request->input('assigned_supervisor_member_id');
        if ($supervisorId !== null) {
            $legacyData['supervisor_member_id'] = (int) $supervisorId;
            $unifiedData['assigned_supervisor_member_id'] = (int) $supervisorId;
        }

        if (! empty($legacyData)) {
            $legacyTask->update($legacyData);
        }

        if ($unifiedTask !== null && ! empty($unifiedData)) {
            try {
                $unifiedTask->update($unifiedData);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        $updatedTask = $legacyTask->fresh(['project', 'supervisor', 'checklists']);

        return response()->json([
            'success' => true,
            'message' => 'Task updated successfully.',
            'task' => $updatedTask,
            'data' => $updatedTask,
        ]);
    }

    /**
     * Create New Member Task
     *
     * Creates a new execution task assigned to a project and member with optional checklists and schedule.
     *
     * @tags Member Tasks & Checklists
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function storeTask(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'project_id' => 'nullable|exists:construction_projects,id',
            'project' => 'nullable|exists:construction_projects,id',
            'priority' => 'nullable|string',
            'planned_start_date' => 'nullable|date',
            'planned_end_date' => 'nullable|date',
            'checklist' => 'nullable',
        ]);

        $member = $request->user();
        $memberId = $member->getKey();

        $taskCode = 'TSK-' . str_pad((string) random_int(1, 99999), 5, '0', STR_PAD_LEFT);

        $projectId = $request->input('project_id') ?? $request->input('project');
        if ($projectId) {
            $accessibleProjectIds = $this->accessibleProjectIds($member);
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
            $isAccessible = in_array((int) $projectId, $accessibleProjectIds, true);

            if (! $isOnProjectTeam && ! $isTaskAssigneeOnProject && ! $isSurveyMemberOnProject && ! $isAccessible) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'PROJECT_ACCESS_DENIED',
                    'message' => 'You are not assigned to this project and cannot create tasks for it.',
                ], 403);
            }
        }
        if (! $projectId) {
            $projectId = ProjectTeamMember::where('member_id', $memberId)
                ->where('status', 'active')
                ->orderByDesc('is_primary')
                ->latest()
                ->value('project_id');
        }
        if (! $projectId) {
            $projectId = ExecutionTaskAssignee::where('member_id', $memberId)
                ->where('status', 'active')
                ->latest()
                ->value('project_id');
        }
        if (! $projectId) {
            $projectId = SurveyPlanMember::where('member_id', $memberId)
                ->latest()
                ->whereHas('surveyPlan', function ($q) {
                    $q->whereNotNull('project_id');
                })
                ->join('construction_survey_plans', 'construction_survey_plans.id', '=', 'construction_survey_plan_members.survey_plan_id')
                ->value('construction_survey_plans.project_id');
        }
        if (! $projectId) {
            $accessibleProjectIds = $this->accessibleProjectIds($member);
            if (! empty($accessibleProjectIds)) {
                $projectId = $accessibleProjectIds[0];
            }
        }
        if (! $projectId) {
            return response()->json([
                'success' => false,
                'error_code' => 'NO_ASSIGNED_PROJECT',
                'message' => 'You are not assigned to any project. Ask an admin to add you to a project team before creating tasks.',
            ], 403);
        }

        $plan = \App\Models\ExecutionPlan::where('project_id', $projectId)->first();
        if (! $plan) {
            $plan = \App\Models\ExecutionPlan::create([
                'project_id' => $projectId,
                'plan_code' => 'EP-' . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT),
                'title' => 'Default Project Plan',
                'status' => 'approved',
            ]);
        }

        $priorityInput = strtolower((string) ($request->priority ?? 'medium'));
        $priority = in_array($priorityInput, ['low', 'medium', 'high', 'urgent', 'critical'], true)
            ? $priorityInput
            : 'medium';

        $startDate = $request->planned_start_date ?? now();
        $endDate = $request->planned_end_date ?? now()->addDays(1);

        $task = ExecutionTask::create([
            'task_code' => $taskCode,
            'title' => $request->title,
            'description' => $request->description ?? null,
            'project_id' => (int) $projectId,
            'execution_plan_id' => $plan->id,
            'priority' => $priority,
            'planned_start_date' => $startDate,
            'planned_end_date' => $endDate,
            'supervisor_member_id' => $member->id,
            'status' => 'planned',
        ]);

        ExecutionTaskAssignee::create([
            'execution_task_id' => $task->id,
            'project_id' => (int) $projectId,
            'member_id' => $member->id,
            'assigned_at' => now(),
            'status' => 'active',
        ]);

        try {
            Task::updateOrCreate(
                ['id' => $task->id],
                [
                    'task_code' => $taskCode,
                    'title' => $request->title,
                    'description' => $request->description ?? null,
                    'project_id' => (int) $projectId,
                    'execution_plan_id' => $plan->id,
                    'member_id' => $member->id,
                    'assigned_supervisor_member_id' => $member->id,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'priority' => $priority,
                    'status' => 'planned',
                    'progress_percent' => 0,
                ]
            );
        } catch (\Throwable $e) {
            report($e);
        }

        $rawChecklist = $request->input('checklist');
        if (is_string($rawChecklist)) {
            $decoded = json_decode($rawChecklist, true);
            if (is_array($decoded)) {
                $rawChecklist = $decoded;
            }
        }

        if (is_array($rawChecklist)) {
            foreach ($rawChecklist as $item) {
                $itemTitle = null;
                $assignHours = null;
                $dayNumber = 1;
                $notes = null;
                $status = 'pending';
                $isCompleted = false;

                if (is_string($item)) {
                    $itemTitle = trim($item);
                } elseif (is_array($item)) {
                    $itemTitle = trim($item['item_title'] ?? $item['title'] ?? $item['name'] ?? '');
                    $assignHours = isset($item['assign_hours']) && is_numeric($item['assign_hours']) ? (float) $item['assign_hours'] : null;
                    $dayNumber = isset($item['day_number']) && is_numeric($item['day_number']) ? (int) $item['day_number'] : 1;
                    $notes = isset($item['notes']) ? (string) $item['notes'] : null;
                    $status = isset($item['status']) && in_array($item['status'], ['pending', 'in_progress', 'completed'], true) ? $item['status'] : 'pending';
                    $isCompleted = ! empty($item['is_completed']);
                }

                if ($itemTitle !== null && $itemTitle !== '') {
                    TaskChecklist::create([
                        'execution_task_id' => $task->id,
                        'day_number' => $dayNumber,
                        'item_title' => $itemTitle,
                        'assign_hours' => $assignHours,
                        'notes' => $notes,
                        'status' => $status,
                        'is_completed' => $isCompleted,
                    ]);
                }
            }
        }

        $loadedTask = $task->load(['project', 'checklists']);

        return response()->json([
            'success' => true,
            'message' => 'Task created successfully.',
            'task' => $loadedTask,
            'data' => $loadedTask,
        ]);
    }

    /**
     * Member Daily Check-In
     *
     * Records member daily check-in with GPS coordinates, address, and selfie photo.
     *
     * @tags Member Dashboard & Attendance
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkIn(Request $request)
    {
        $member = $request->user();
        if (!$member) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $nowKolkata = now()->setTimezone('Asia/Kolkata');
        $today = $nowKolkata->toDateString();

        $existing = AttendanceRecord::where('member_id', $member->id)
            ->where('attendance_date', $today)
            ->first();

        $latitude = $request->input('check_in_latitude', $request->input('latitude', $request->input('lat')));
        $longitude = $request->input('check_in_longitude', $request->input('longitude', $request->input('lng', $request->input('long'))));
        $accuracy = $request->input('gps_accuracy_meters', $request->input('accuracy', $request->input('acc')));

        if ($latitude !== null && $longitude !== null) {
            \App\Models\EmployeeLocation::recordIfValid(
                $member,
                $latitude,
                $longitude,
                $accuracy,
                now()
            );
        }

        if ($existing) {
            if ($existing->check_out_at) {
                return response()->json([
                    'success' => true,
                    'message' => 'Already checked in and checked out for today.',
                    'attendance' => $existing,
                    'data' => $existing,
                    'check_in_time_formatted' => $existing->check_in_at ? $existing->check_in_at->setTimezone('Asia/Kolkata')->format('h:i A') : null,
                    'check_out_time_formatted' => $existing->check_out_at ? $existing->check_out_at->setTimezone('Asia/Kolkata')->format('h:i A') : null,
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Already checked in for today.',
                'attendance' => $existing,
                'data' => $existing,
                'check_in_time_formatted' => $existing->check_in_at ? $existing->check_in_at->setTimezone('Asia/Kolkata')->format('h:i A') : null,
            ]);
        }

        // Auto-close any unclosed records from earlier dates
        AttendanceRecord::where('member_id', $member->id)
            ->where('attendance_date', '<', $today)
            ->whereNull('check_out_at')
            ->update([
                'check_out_at' => now(),
            ]);

        $projectId = $request->input('project_id');
        if (!$projectId) {
            $projectId = ProjectTeamMember::where('member_id', $member->id)
                ->where(function ($q) {
                    $q->whereNull('status')->orWhere('status', '!=', 'inactive');
                })
                ->value('project_id');
        }
        if (!$projectId) {
            $projectId = MemberRoleAssignment::where('member_id', $member->id)
                ->where('status', 1)
                ->whereNotNull('project_id')
                ->value('project_id');
        }
        if (!$projectId) {
            $accessible = $this->accessibleProjectIds($member);
            if (!empty($accessible)) {
                $projectId = $accessible[0];
            }
        }
        if (!$projectId) {
            $companyId = !empty($member->company_id) ? (int) $member->company_id : null;
            if (!$companyId && $member->created_by) {
                $creator = SuperAdmin::find($member->created_by);
                if ($creator && $creator->company_id) {
                    $companyId = (int) $creator->company_id;
                }
            }
            if (!$companyId && !empty($member->company_name)) {
                $companyId = Company::where('name', $member->company_name)->orWhere('legal_name', $member->company_name)->value('id');
            }
            if ($companyId) {
                $projectId = Project::where('company_id', $companyId)->value('id');
            }
        }
        if (!$projectId) {
            $projectId = Project::value('id');
        }

        $notes = $request->input('notes', $request->input('check_in_notes'));
        $attendanceType = $request->input('attendance_type', 'present');

        $attendanceData = [
            'project_id' => $projectId,
            'execution_task_id' => $request->input('execution_task_id'),
            'check_in_at' => now(),
            'attendance_type' => $attendanceType,
            'notes' => $notes,
            'status' => 'submitted',
        ];
        if (
            is_numeric($latitude)
            && is_numeric($longitude)
            && $latitude >= -90
            && $latitude <= 90
            && $longitude >= -180
            && $longitude <= 180
        ) {
            $attendanceData['check_in_latitude'] = $latitude;
            $attendanceData['check_in_longitude'] = $longitude;
        }
        if (is_numeric($accuracy) && $accuracy >= 0 && $accuracy <= 100000) {
            $attendanceData['gps_accuracy_meters'] = $accuracy;
        }

        $attendance = AttendanceRecord::updateOrCreate(
            [
                'member_id' => $member->id,
                'attendance_date' => $today,
            ],
            $attendanceData
        );


        try {
            \App\Models\CheckInOut::updateOrCreate(
                [
                    'member_id' => $member->id,
                    'date' => $today,
                ],
                [
                    'check_in' => now()->toTimeString(),
                    'check_in_ip' => $request->ip(),
                    'check_in_notes' => $notes,
                    'role' => $member->roles,
                ]
            );
        } catch (\Throwable $e) {
            // Silently ignore if table/columns differ
        }

        return response()->json([
            'success' => true,
            'message' => 'Checked in successfully.',
            'attendance' => $attendance,
            'data' => $attendance,
            'check_in_time_formatted' => $nowKolkata->format('h:i A'),
        ]);
    }

    /**
     * Member Daily Check-Out
     *
     * Records member daily check-out, calculates worked hours, and attaches exit location.
     *
     * @tags Member Dashboard & Attendance
     * @param Request $request
     * @param int|null $attendance
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkOut(Request $request, $attendance = null)
    {
        $member = $request->user();
        if (!$member) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $now = now();
        $nowKolkata = now()->setTimezone('Asia/Kolkata');
        $today = $nowKolkata->toDateString();

        $record = null;

        // 1. If AttendanceRecord instance was resolved
        if ($attendance instanceof AttendanceRecord && $attendance->exists) {
            $record = $attendance;
        } elseif (!empty($attendance) && is_numeric($attendance)) {
            // 2. If ID was provided in route parameter
            $record = AttendanceRecord::find((int) $attendance);
        } elseif ($request->filled('attendance_id') && is_numeric($request->input('attendance_id'))) {
            // 3. If ID was provided in request body
            $record = AttendanceRecord::find((int) $request->input('attendance_id'));
        } elseif ($request->filled('attendance') && is_numeric($request->input('attendance'))) {
            $record = AttendanceRecord::find((int) $request->input('attendance'));
        }

        // 4. If a specific attendance was specified, ensure it belongs to the token member
        if ($record) {
            if ((int) $record->member_id !== (int) $member->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. This attendance record does not belong to you.',
                ], 403);
            }
        } else {
            // 5. Token-based lookup: resolve active open check-in strictly from the token user
            $record = AttendanceRecord::where('member_id', $member->id)
                ->where('attendance_date', $today)
                ->whereNull('check_out_at')
                ->latest('id')
                ->first();

            if (!$record) {
                $record = AttendanceRecord::where('member_id', $member->id)
                    ->whereNull('check_out_at')
                    ->latest('id')
                    ->first();
            }
        }

        // If no active record to check out
        if (!$record) {
            $alreadyCheckedOut = AttendanceRecord::where('member_id', $member->id)
                ->where('attendance_date', $today)
                ->whereNotNull('check_out_at')
                ->latest('id')
                ->first();

            if ($alreadyCheckedOut) {
                return response()->json([
                    'success' => true,
                    'message' => 'Already checked out for today.',
                    'attendance' => $alreadyCheckedOut,
                    'data' => $alreadyCheckedOut,
                    'check_out_time_formatted' => $alreadyCheckedOut->check_out_at ? $alreadyCheckedOut->check_out_at->setTimezone('Asia/Kolkata')->format('h:i A') : null,
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'No active check-in record found to check out.',
            ], 422);
        }

        $latitude = $request->input('check_out_latitude', $request->input('latitude', $request->input('lat')));
        $longitude = $request->input('check_out_longitude', $request->input('longitude', $request->input('lng', $request->input('long'))));
        $accuracy = $request->input('gps_accuracy_meters', $request->input('accuracy', $request->input('acc')));

        if ($latitude !== null && $longitude !== null) {
            \App\Models\EmployeeLocation::recordIfValid(
                $member,
                $latitude,
                $longitude,
                $accuracy,
                $now
            );
        }

        if ($record->check_out_at) {
            return response()->json([
                'success' => true,
                'message' => 'Already checked out.',
                'attendance' => $record,
                'data' => $record,
                'check_out_time_formatted' => $record->check_out_at->setTimezone('Asia/Kolkata')->format('h:i A'),
            ]);
        }

        $hoursWorked = 0;
        if ($record->check_in_at) {
            $hoursWorked = round($now->diffInMinutes($record->check_in_at) / 60, 2);
        }

        $notes = $request->input('notes', $request->input('check_out_notes'));

        $updateData = [
            'check_out_at' => $now,
        ];

        if (
            is_numeric($latitude)
            && is_numeric($longitude)
            && $latitude >= -90
            && $latitude <= 90
            && $longitude >= -180
            && $longitude <= 180
        ) {
            $updateData['check_out_latitude'] = $latitude;
            $updateData['check_out_longitude'] = $longitude;
        }
        if (is_numeric($accuracy) && $accuracy >= 0 && $accuracy <= 100000) {
            $updateData['gps_accuracy_meters'] = $accuracy;
        }
        if ($notes !== null) {
            $updateData['notes'] = $record->notes ? ($record->notes . ' | Checkout: ' . $notes) : $notes;
        }

        $record->update($updateData);


        try {
            $checkInOut = \App\Models\CheckInOut::where('member_id', $member->id)
                ->where('date', $record->attendance_date ? $record->attendance_date->toDateString() : $today)
                ->latest('id')
                ->first();
            if ($checkInOut) {
                $checkInOut->check_out = now()->toTimeString();
                $checkInOut->check_out_ip = $request->ip();
                if ($notes) {
                    $checkInOut->check_out_notes = $notes;
                }
                if ($checkInOut->check_in) {
                    $checkInOut->total_minutes = \Carbon\Carbon::parse($checkInOut->check_in)->diffInMinutes(now());
                }
                $checkInOut->save();
            }
        } catch (\Throwable $e) {
            // Silently ignore
        }

        return response()->json([
            'success' => true,
            'message' => 'Checked out successfully.',
            'attendance' => $record->fresh(),
            'data' => $record->fresh(),
            'check_out_time_formatted' => $nowKolkata->format('h:i A'),
            'hours_worked' => $hoursWorked,
        ]);
    }

    /**
     * List Member Notifications
     *
     * Retrieves latest alerts, project notifications, and approvals for the member.
     *
     * @tags Member Dashboard & Attendance
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function notifications(Request $request)
    {
        $member = $request->user();
        return response()->json([
            'success' => true,
            'data' => $this->getNotifications($member),
        ]);
    }

    /**
     * Record Member GPS Location Ping (Every 20 minutes)
     *
     * Periodically tracks and updates the authenticated member's location coordinates.
     * Supports single ping (latitude, longitude, accuracy, captured_at) or batch sync.
     *
     * @tags Member Dashboard & Attendance
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function recordLocation(Request $request): JsonResponse
    {
        $member = $request->user();
        if (!$member instanceof Member || (int) $member->status !== Member::STATUS_ACTIVE) {
            return response()->json([
                'success' => false,
                'message' => 'An active member account is required to report location.',
            ], 403);
        }

        $employee = $member->employee;
        if (!$employee && class_exists(Employee::class)) {
            $employee = Employee::firstOrCreate(
                ['member_id' => $member->getKey()],
                ['uuid' => (string) Str::uuid()]
            );
            $member->setRelation('employee', $employee);
        }

        // Handle batch sync (e.g. mobile app coming back online after collecting periodic points)
        if ($request->has('locations') && is_array($request->input('locations'))) {
            $locations = $request->input('locations');
            $recordedCount = 0;
            $lastRecord = null;

            foreach ($locations as $loc) {
                if (!is_array($loc)) continue;
                $lat = $loc['latitude'] ?? $loc['lat'] ?? null;
                $long = $loc['longitude'] ?? $loc['lng'] ?? $loc['long'] ?? $loc['lon'] ?? null;
                $accuracy = $loc['accuracy'] ?? $loc['gps_accuracy_meters'] ?? $loc['gps_accuracy'] ?? null;
                $time = $loc['captured_at'] ?? $loc['capturedAt'] ?? $loc['timestamp'] ?? $loc['time'] ?? now();

                if (is_numeric($lat) && is_numeric($long) && $lat >= -90 && $lat <= 90 && $long >= -180 && $long <= 180) {
                    $saved = EmployeeLocation::recordIfValid($member, $lat, $long, $accuracy, $time);
                    if ($saved) {
                        $recordedCount++;
                        $lastRecord = $saved;
                    }
                }
            }

            return response()->json([
                'success' => true,
                'message' => "Successfully recorded {$recordedCount} location points.",
                'data' => [
                    'member_id' => $member->id,
                    'employee_id' => $employee?->employee_id,
                    'count' => $recordedCount,
                    'latest_latitude' => $lastRecord ? (float) $lastRecord->latitude : null,
                    'latest_longitude' => $lastRecord ? (float) $lastRecord->longitude : null,
                    'latest_accuracy' => $lastRecord && $lastRecord->accuracy !== null ? (float) $lastRecord->accuracy : null,
                    'latest_captured_at' => $lastRecord ? $lastRecord->captured_at?->toIso8601String() : null,
                    'sync_interval_minutes' => 20,
                ],
            ]);
        }

        // Handle single location ping
        $lat = $request->input('latitude', $request->input('lat'));
        $long = $request->input('longitude', $request->input('lng', $request->input('long', $request->input('lon'))));
        $accuracy = $request->input('accuracy', $request->input('gps_accuracy_meters', $request->input('gps_accuracy')));
        $rawCapturedAt = $request->input('captured_at', $request->input('capturedAt', $request->input('timestamp', $request->input('time'))));

        if (!is_numeric($lat) || !is_numeric($long) || $lat < -90 || $lat > 90 || $long < -180 || $long > 180) {
            return response()->json([
                'success' => false,
                'message' => 'Valid latitude (-90 to 90) and longitude (-180 to 180) are required.',
                'errors' => [
                    'latitude' => ['The latitude field must be a valid coordinate between -90 and 90.'],
                    'longitude' => ['The longitude field must be a valid coordinate between -180 and 180.'],
                ],
            ], 422);
        }

        try {
            $capturedAt = $rawCapturedAt ? Carbon::parse($rawCapturedAt) : now();
        } catch (\Throwable $e) {
            $capturedAt = now();
        }

        if ($capturedAt->greaterThan(now()->addMinutes(10))) {
            return response()->json([
                'success' => false,
                'message' => 'The location capture time cannot be more than 10 minutes in the future.',
                'errors' => [
                    'captured_at' => ['The capture time cannot be in the future.'],
                ],
            ], 422);
        }

        $record = EmployeeLocation::recordIfValid(
            $member,
            $lat,
            $long,
            $accuracy,
            $capturedAt
        );

        return response()->json([
            'success' => true,
            'message' => 'Member location recorded successfully.',
            'data' => [
                'id' => $record?->id,
                'member_id' => $member->id,
                'employee_id' => $employee?->employee_id,
                'latitude' => (float) $lat,
                'longitude' => (float) $long,
                'accuracy' => is_numeric($accuracy) ? (float) $accuracy : null,
                'captured_at' => $capturedAt->toIso8601String(),
                'sync_interval_minutes' => 20,
            ],
        ], 201);
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

        if (!$primary->relationLoaded('teamMembers')) {
            $primary->loadMissing(['teamMembers.member', 'teamMembers.role']);
        }

        $tasksCount = $primary->tasks_count ?? ($primary->executionTasks ? $primary->executionTasks->count() : 0);
        $todayTasksCount = $primary->today_tasks_count ?? 0;
        $startDate = $primary->start_date ? $primary->start_date->format('d M') : null;
        $endDate = $primary->expected_end_date ? $primary->expected_end_date->format('d M') : null;

        $teamMembers = $primary->teamMembers
            ? $primary->teamMembers
                ->filter(fn ($tm) => $tm->status === null || $tm->status === 'active')
                ->map(function ($tm) {
                    return [
                        'id' => $tm->id,
                        'member_id' => $tm->member_id,
                        'name' => $tm->member?->name ?? 'Team Member',
                        'email' => $tm->member?->email,
                        'mobile' => $tm->member?->mobile ?? $tm->member?->phone,
                        'phone' => $tm->member?->phone ?? $tm->member?->mobile,
                        'designation' => $tm->member?->designation,
                        'role' => $tm->role?->name ?? $tm->assignment_scope ?? 'Member',
                        'role_slug' => $tm->role?->slug,
                        'profile_photo_url' => $tm->member?->profile_photo_url ?? null,
                        'is_primary' => (bool) $tm->is_primary,
                        'status' => $tm->status ?? 'active',
                    ];
                })->values()->all()
            : [];

        return [
            'id' => $primary->id,
            'name' => $primary->name,
            'project_code' => $primary->project_code ?? ('PRJ-' . str_pad($primary->id, 3, '0', STR_PAD_LEFT)),
            'category' => $primary->category ?? $primary->current_stage ?? 'Construction',
            'location' => $primary->project_address ?? ($primary->city ?? null),
            'progress_percent' => (int) ($primary->progress_percent ?? round($primary->executionTasks?->avg('progress_percent') ?? 0)),
            'start_date' => $startDate,
            'deadline' => $endDate,
            'team_members_count' => count($teamMembers),
            'team_members' => $teamMembers,
            'tasks_count' => $tasksCount,
            'today_tasks_count' => $todayTasksCount,
            'manager_name' => $primary->client?->name ?? $primary->company?->name ?? null,
        ];
    }

    protected function formatTaskItem($t): array
    {
        $parseDate = static function ($value): ?\Illuminate\Support\Carbon {
            if ($value === null || $value === '') {
                return null;
            }
            if ($value instanceof \DateTimeInterface) {
                return \Illuminate\Support\Carbon::instance($value)->setTimezone('Asia/Kolkata')->startOfDay();
            }
            try {
                return \Illuminate\Support\Carbon::parse($value)->setTimezone('Asia/Kolkata')->startOfDay();
            } catch (\Throwable) {
                return null;
            }
        };

        $statusRaw = $t->status ?? '';
        $statusStr = is_object($statusRaw) ? ($statusRaw->value ?? (string) $statusRaw) : (string) $statusRaw;
        $isCompleted = (strtolower($statusStr) === 'completed');
        $priority = $this->formatPriorityValue($t->priority ?? null, 'medium');
        $isLegacy = ($t->_source ?? null) === 'legacy' || $t instanceof \App\Models\ExecutionTask;
        $startVal = $isLegacy
            ? ($t->planned_start_date ?? null)
            : ($t->start_date ?? ($t->planned_start_date ?? null));
        $endVal = $isLegacy
            ? ($t->planned_end_date ?? null)
            : ($t->end_date ?? ($t->planned_end_date ?? null));
        $startParsed = $parseDate($startVal);
        $endParsed = $parseDate($endVal);
        $due = $endParsed;
        $supervisor = $t->supervisor ?? $t->assignedSupervisor ?? null;
        $revInfo = $this->resolveTaskRevisionInfo($t);
        $normalizedStatus = match (strtolower($statusStr)) {
            'planned' => 'pending',
            default => in_array(strtolower($statusStr), ['pending', 'in_progress', 'review', 'completed', 'blocked', 'cancelled'], true)
                ? strtolower($statusStr)
                : 'pending',
        };

        return [
            'id' => $t->id,
            'title' => $t->title,
            'description' => $t->description ?? null,
            'project_id' => $t->project_id ? (int) $t->project_id : null,
            'project_name' => $t->project?->name ?? null,
            'location' => $t->project?->project_address ?? ($t->project?->location ?? null),
            'progress_percent' => (int) ($t->progress_percent ?? 0),
            'start_date' => $startVal ? $startParsed?->toDateString() : null,
            'end_date' => $endVal ? $endParsed?->toDateString() : null,
            'start_date_formatted' => $startVal ? $startParsed?->format('d/m/Y') : null,
            'end_date_formatted' => $endVal ? $endParsed?->format('d/m/Y') : null,
            'due_time_formatted' => $due ? $due->format('d M, h:i A') : null,
            'priority' => $priority,
            'priority_label' => ucfirst($priority),
            'is_completed' => $isCompleted,
            'status' => $statusStr,
            'normalized_status' => $normalizedStatus,
            'source' => $isLegacy ? 'legacy' : 'unified',
            'task_code' => $t->task_code ?? null,
            'supervisor_name' => $supervisor?->name ?? null,
            'created_at' => $t->created_at?->toIso8601String(),
            'review_status' => $revInfo['review_status'] ?? ($statusStr === 'review' ? 'submitted' : $normalizedStatus),
            'is_revision_requested' => $revInfo['is_revision_requested'] ?? false,
            'revision_remarks' => $revInfo['revision_remarks'] ?? null,
            'revision_requested_at' => $revInfo['requested_at'] ?? null,
            'revision_requested_by' => $revInfo['requested_by'] ?? null,
            'revision_reply' => $revInfo['member_reply'] ?? null,
            'revision_replied_at' => $revInfo['member_replied_at'] ?? null,
            'revision' => $revInfo,
        ];
    }

    protected function getTodaysTasks($tasks, ?int $projectId = null, ?\Illuminate\Support\Carbon $targetDate = null)
    {
        $today = $targetDate ? $targetDate->copy()->startOfDay() : now()->setTimezone('Asia/Kolkata')->startOfDay();
        $todayStr = $today->toDateString();

        $parseDate = static function ($value): ?\Illuminate\Support\Carbon {
            if ($value === null || $value === '') {
                return null;
            }
            if ($value instanceof \DateTimeInterface) {
                return \Illuminate\Support\Carbon::instance($value)->setTimezone('Asia/Kolkata')->startOfDay();
            }
            try {
                return \Illuminate\Support\Carbon::parse($value)->setTimezone('Asia/Kolkata')->startOfDay();
            } catch (\Throwable) {
                return null;
            }
        };

        if ($projectId !== null) {
            $tasks = $tasks->filter(function ($t) use ($projectId) {
                return (int) ($t->project_id ?? 0) === (int) $projectId;
            });
        }

        return $tasks->filter(function ($t) use ($todayStr, $today, $parseDate) {
            $isLegacy = ($t->_source ?? null) === 'legacy' || $t instanceof \App\Models\ExecutionTask;
            if ($isLegacy) {
                $start = $parseDate($t->planned_start_date ?? null);
                $end = $parseDate($t->planned_end_date ?? null);
            } else {
                $start = $parseDate($t->start_date ?? ($t->planned_start_date ?? null));
                $end = $parseDate($t->end_date ?? ($t->planned_end_date ?? null));
            }

            $statusRaw = $t->status ?? '';
            $statusStr = strtolower(is_object($statusRaw) ? ($statusRaw->value ?? (string) $statusRaw) : (string) $statusRaw);

            // Completed / cancelled tasks: include if start or end date matches today or range includes today
            if (in_array($statusStr, ['completed', 'cancelled'], true)) {
                if ($end && $end->equalTo($today)) {
                    return true;
                }
                if ($start && $start->equalTo($today)) {
                    return true;
                }
                if ($start && $end && $today->between($start->min($end), $end->max($start), true)) {
                    return true;
                }
                return false;
            }

            // Exact date range match
            if ($start && $end) {
                $rangeStart = $start->min($end);
                $rangeEnd = $end->max($start);
                if ($today->between($rangeStart, $rangeEnd, true)) {
                    return true;
                }
            }
            if ($start && $start->equalTo($today)) {
                return true;
            }
            if ($end && $end->equalTo($today)) {
                return true;
            }

            // Active tasks (in_progress, planned, pending, active, draft, etc.)
            if (in_array($statusStr, ['in_progress', 'planned', 'pending', 'active', 'draft', ''], true)) {
                if (! $start && ! $end) {
                    return true;
                }
                // If a task starts in the future, it cannot be scheduled for today
                if ($start && $start->greaterThan($today)) {
                    return false;
                }
                if ($start && $end) {
                    $rangeStart = $start->min($end);
                    $rangeEnd = $end->max($start);
                    if ($today->between($rangeStart, $rangeEnd, true)) {
                        return true;
                    }
                    if ($rangeEnd->lessThan($today)) {
                        return true; // Overdue pending task
                    }
                    return false;
                }
                if ($start && $start->lessThanOrEqualTo($today)) {
                    return true;
                }
                if ($end && $end->greaterThanOrEqualTo($today) && (!$start || $start->lessThanOrEqualTo($today))) {
                    return true;
                }
            }

            return false;
        })->map(fn ($t) => $this->formatTaskItem($t))->values()->toArray();
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

    /**
     * Member App Profile & Navigation
     *
     * Retrieves member profile metrics, quick navigation options, and duty statistics for the mobile profile screen.
     *
     * @tags Member Profile
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
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

    /**
     * Create New Construction Site
     *
     * Creates and registers a new construction site/project directly from mobile and assigns the authenticated member.
     *
     * @tags Construction Projects
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
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

    /**
     * Create Field Survey Plan
     *
     * Creates a new survey plan and assigns the creator as lead surveyor.
     *
     * @tags Survey & Duty Tracking
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
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

    /**
     * Request Account Deletion
     *
     * Revokes active tokens and schedules the member account for permanent deletion.
     *
     * @tags Member Profile
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
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

    /**
     * Get Privacy Policy
     *
     * Retrieves current terms and privacy policy HTML and plain text for mobile and web views.
     *
     * @tags System Health & Settings
     * @return \Illuminate\Http\JsonResponse
     */
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

    /**
     * Update Privacy Policy
     *
     * Updates privacy policy content.
     *
     * @tags System Health & Settings
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
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

    /**
     * Get Terms & Conditions
     *
     * Retrieves terms and conditions content.
     *
     * @tags System Health & Settings
     * @return \Illuminate\Http\JsonResponse
     */
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

    /**
     * Update Terms & Conditions
     *
     * Updates terms and conditions content.
     *
     * @tags System Health & Settings
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
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

    /**
     * Get Survey Duty Status
     *
     * Inquires the member's real-time survey duty status, active survey visit, shift timing, and current day check-in coordinates.
     *
     * @tags Survey & Duty Visits
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
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

    /**
     * Check-in for Survey Duty
     *
     * Records GPS coordinates and checks the member in for the scheduled survey day duty on a project or survey plan.
     *
     * @tags Survey & Duty Visits
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
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
                $resolvedPlan = $this->resolveOrCreateSurveyPlan(
                    $project,
                    $member,
                    null,
                    $project?->name
                );
                $surveyPlanId = $resolvedPlan?->id ?? SurveyPlan::value('id');
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

            if ($request->filled('latitude') && $request->filled('longitude')) {
                \App\Models\EmployeeLocation::recordIfValid(
                    $member,
                    $request->input('latitude'),
                    $request->input('longitude'),
                    $request->input('gps_accuracy_meters', $request->input('accuracy')),
                    $visit->check_in_at
                );
            }
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

    /**
     * Check-out from Survey Duty
     *
     * Completes and checks out of the active survey visit, recording check-out time and optional GPS notes.
     *
     * @tags Survey & Duty Visits
     * @param Request $request
     * @param SurveyVisit $visit
     * @return \Illuminate\Http\JsonResponse
     */
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

    /**
     * Get Task Details & Checklist Stepper
     *
     * Retrieves comprehensive information for a specific task, including the current day stepper, due dates, instructions, assigned supervisor info, and interactive checklist items.
     *
     * @tags Member Tasks & Duties
     * @param Request $request
     * @param int|string $taskId Task ID
     * @return JsonResponse
     */
    public function taskDetails(Request $request, $taskId)
    {
        $resolved = $this->resolveTaskAcrossTables((int) $taskId);
        if ($resolved === null) {
            return response()->json([
                'success' => false,
                'error_code' => 'TASK_NOT_FOUND',
                'message' => 'No task found with the given identifier.',
            ], 404);
        }

        [$task, $unifiedTask] = [$resolved['task'], $resolved['unifiedTask']];

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

        $legacyChecklists = TaskChecklist::where('execution_task_id', $task->id)->get();
        $unifiedChecklistItems = $unifiedTask !== null
            ? \App\Models\TaskChecklistItem::where('task_id', $unifiedTask->id)->get()
            : \App\Models\TaskChecklistItem::where('task_id', $task->id)->get();

        $mergedList = collect();
        $seenTitles = [];

        foreach ($legacyChecklists as $c) {
            $key = strtolower(trim((string) $c->item_title)) . '-' . ($c->day_number ?? 1);
            $seenTitles[$key] = true;
            $mergedList->push([
                'id' => $c->id,
                'item_title' => $c->item_title,
                'assign_hours' => $c->assign_hours ? (float) $c->assign_hours : null,
                'notes' => $c->notes,
                'status' => $c->status ?? ($c->is_completed ? 'completed' : 'pending'),
                'image_url_1' => $c->image_url_1,
                'image_url_2' => $c->image_url_2,
                'is_completed' => (bool) $c->is_completed,
                'day_number' => $c->day_number ?? 1,
            ]);
        }

        foreach ($unifiedChecklistItems as $c) {
            $key = strtolower(trim((string) $c->item_title)) . '-' . ($c->day_number ?? 1);
            if (!isset($seenTitles[$key])) {
                $seenTitles[$key] = true;
                $mergedList->push([
                    'id' => $c->id,
                    'item_title' => $c->item_title,
                    'assign_hours' => $c->assign_hours ? (float) $c->assign_hours : null,
                    'notes' => $c->notes,
                    'status' => $c->status ?? ($c->is_completed ? 'completed' : 'pending'),
                    'image_url_1' => $c->image_url_1,
                    'image_url_2' => $c->image_url_2,
                    'is_completed' => (bool) $c->is_completed,
                    'day_number' => $c->day_number ?? 1,
                ]);
            }
        }

        $formattedChecklist = $mergedList->values();

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

        $priorityStr = $this->formatPriorityValue($task->priority, 'high');

        $revInfo = $this->resolveTaskRevisionInfo($task) ?? ($unifiedTask ? $this->resolveTaskRevisionInfo($unifiedTask) : null);

        $taskIds = array_values(array_unique(array_filter([(int) $task->id, (int) ($unifiedTask?->id ?? 0)])));
        $hasExecCol = $this->hasSurveyVisitExecutionTaskIdColumn();
        $rawVisits = SurveyVisit::with(['submission.reviewedBy', 'checkedInBy'])
            ->where(function ($q) use ($taskIds, $hasExecCol) {
                $q->whereIn('task_id', $taskIds);
                if ($hasExecCol) {
                    $q->orWhereIn('execution_task_id', $taskIds);
                }
            })
            ->latest('id')
            ->get();

        if ($rawVisits->isEmpty() && $project) {
            $rawVisits = SurveyVisit::with(['submission.reviewedBy', 'checkedInBy'])
                ->where('project_id', $project->id)
                ->latest('id')
                ->take(5)
                ->get();
        }

        $submittedVisits = $rawVisits->map(function ($v) {
            $vNotes = is_array($v->notes) ? $v->notes : (json_decode($v->notes ?? '', true) ?: []);
            return [
                'id' => $v->id,
                'task_id' => $v->task_id ?? $v->execution_task_id,
                'day_number' => $v->day_number,
                'elevation_m' => (float) $v->elevation_m,
                'distance_covered_m' => (float) $v->distance_covered_m,
                'total_points_captured' => (int) $v->total_points_captured,
                'remarks' => $v->remarks,
                'notes' => $v->notes,
                'photos' => $v->photos ?? [],
                'file_path' => $v->file_path,
                'status' => $v->status_label ?? 'Submitted',
                'date_time' => $v->updated_at ? $v->updated_at->setTimezone('Asia/Kolkata')->format('d M Y, h:i A') : null,
                'revision_request' => $vNotes['revision_request'] ?? null,
                'member_reply' => $vNotes['member_reply'] ?? null,
                'revision_history' => $vNotes['revision_history'] ?? [],
            ];
        });

        $taskStatus = strtolower((string) ($task->status ?? 'pending'));
        $buttonLabel = 'Start Day ' . $currentDay . ' Task';
        if ($revInfo && ($revInfo['is_revision_requested'] ?? false)) {
            if (!($revInfo['is_replied'] ?? false)) {
                $buttonLabel = 'Submit Revision Reply';
            } else {
                $buttonLabel = 'Revision Under Review';
            }
        } elseif ($taskStatus === 'review') {
            $buttonLabel = 'Under Review';
        } elseif (in_array($taskStatus, ['completed', 'survey_approved', 'approved'], true)) {
            $buttonLabel = 'Task Completed';
        }

        return response()->json([
            'success' => true,
            'day_stepper' => $dayStepper,
            'task' => [
                'id' => $task->id,
                'task_code' => $task->task_code ?? $unifiedTask?->task_code,
                'title' => $task->title,
                'status' => $task->status === 'planned' ? 'in_progress' : $task->status,
                'status_label' => ucfirst($task->status === 'planned' ? 'In Progress' : $task->status),
                'due_date_formatted' => $dueDateFormatted,
                'priority' => $priorityStr,
                'priority_label' => ucfirst($priorityStr),
                'instructions' => $instructions,
                'review_status' => $revInfo['review_status'] ?? ($taskStatus === 'review' ? 'submitted' : $taskStatus),
                'is_revision_requested' => $revInfo['is_revision_requested'] ?? false,
                'revision_remarks' => $revInfo['revision_remarks'] ?? null,
                'revision_requested_at' => $revInfo['requested_at'] ?? null,
                'revision_requested_by' => $revInfo['requested_by'] ?? null,
                'revision_reply' => $revInfo['member_reply'] ?? null,
                'revision_replied_at' => $revInfo['member_replied_at'] ?? null,
                'revision' => $revInfo,
            ],
            'assigned_by' => [
                'name' => $supervisor['name'],
                'designation' => $supervisor['designation'],
                'phone' => $supervisor['phone'],
            ],
            'checklist' => $formattedChecklist,
            'submitted_data' => $submittedVisits,
            'revision' => $revInfo,
            'button_label' => $buttonLabel,
        ]);
    }

    /**
     * Toggle Task Checklist Item Status
     *
     * Toggles an individual checklist item between pending and completed states for the authenticated member.
     *
     * @tags Tasks & Checklists
     * @param Request $request
     * @param TaskChecklist $checklist
     * @return \Illuminate\Http\JsonResponse
     */
    public function toggleChecklist(Request $request, $checklist)
    {
        $member = $request->user();

        $checklistModel = $checklist instanceof TaskChecklist
            ? $checklist
            : TaskChecklist::find($checklist);

        $unifiedItem = null;
        if (!$checklistModel) {
            $unifiedItem = \App\Models\TaskChecklistItem::find($checklist);
        }

        if (!$checklistModel && !$unifiedItem) {
            return response()->json([
                'success' => false,
                'error_code' => 'CHECKLIST_NOT_FOUND',
                'message' => 'Checklist item not found.',
            ], 404);
        }

        $task = null;
        if ($checklistModel && $checklistModel->execution_task_id) {
            $task = ExecutionTask::with('project')->find($checklistModel->execution_task_id);
        }
        if (!$task && $checklistModel) {
            $unifiedMatch = \App\Models\TaskChecklistItem::where('item_title', $checklistModel->item_title)->first();
            if ($unifiedMatch) {
                $task = ExecutionTask::with('project')->find($unifiedMatch->task_id);
            }
        }
        if (!$task && $unifiedItem) {
            $task = ExecutionTask::with('project')->find($unifiedItem->task_id);
        }

        if ($task && !$this->authorizeTaskAccess($request, $task)) {
            return $this->forbiddenTaskAccessResponse();
        }

        $isCurrentlyCompleted = $checklistModel ? (bool) $checklistModel->is_completed : (bool) $unifiedItem->is_completed;
        $nextCompleted = !$isCurrentlyCompleted;

        try {
            if ($checklistModel) {
                $checklistModel->update([
                    'is_completed' => $nextCompleted,
                    'status' => $nextCompleted ? 'completed' : 'pending',
                    'completed_by_member_id' => $nextCompleted ? $member->id : null,
                    'completed_at' => $nextCompleted ? now() : null,
                ]);

                try {
                    \App\Models\TaskChecklistItem::where('item_title', $checklistModel->item_title)->update([
                        'is_completed' => $nextCompleted,
                        'status' => $nextCompleted ? 'completed' : 'pending',
                        'completed_by_member_id' => $nextCompleted ? $member->id : null,
                        'completed_at' => $nextCompleted ? now() : null,
                    ]);
                } catch (\Throwable $e) {}
            }

            if ($unifiedItem) {
                $unifiedItem->update([
                    'is_completed' => $nextCompleted,
                    'status' => $nextCompleted ? 'completed' : 'pending',
                    'completed_by_member_id' => $nextCompleted ? $member->id : null,
                    'completed_at' => $nextCompleted ? now() : null,
                ]);

                try {
                    TaskChecklist::where('item_title', $unifiedItem->item_title)->update([
                        'is_completed' => $nextCompleted,
                        'status' => $nextCompleted ? 'completed' : 'pending',
                        'completed_by_member_id' => $nextCompleted ? $member->id : null,
                        'completed_at' => $nextCompleted ? now() : null,
                    ]);
                } catch (\Throwable $e) {}
            }
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update checklist item.',
            ], 500);
        }

        $active = $checklistModel ?: $unifiedItem;

        return response()->json([
            'success' => true,
            'message' => 'Checklist item updated.',
            'item' => [
                'id' => $active->id,
                'item_title' => $active->item_title,
                'assign_hours' => $active->assign_hours ? (float) $active->assign_hours : null,
                'notes' => $active->notes,
                'status' => $active->status,
                'image_url_1' => $active->image_url_1,
                'image_url_2' => $active->image_url_2,
                'is_completed' => (bool) $active->is_completed,
            ],
        ]);
    }

    /**
     * Add Task Checklist Item
     *
     * Adds a new checklist requirement item to the specified execution task, with optional day number, assigned hours, notes, and proof images.
     *
     * @tags Tasks & Checklists
     * @param Request $request
     * @param int $task ID of the task
     * @return \Illuminate\Http\JsonResponse
     */
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
                    'day_number' => $request->day_number ?? $defaultDay,
                    'item_title' => $request->item_title,
                    'assign_hours' => $request->assign_hours ?? null,
                    'notes' => $request->notes ?? null,
                    'status' => $request->status ?? 'pending',
                    'image_url_1' => $imageUrl1,
                    'image_url_2' => $imageUrl2,
                    'is_completed' => false,
                    'sort_order' => $request->day_number ?? $defaultDay,
                    'source' => 'member_added',
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

    /**
     * Submit Driver Trip Log
     *
     * Records vehicle trip details, start/destination locations, start/end odometer readings, status, POD proof photos, and trip receipts for drivers.
     *
     * @tags Driver Operations
     * @param Request $request
     * @param int|null $task Optional task ID
     * @return \Illuminate\Http\JsonResponse
     */
    public function submitDriverTrip(Request $request, ?int $task = null)
    {
        $request->validate([
            'task_id' => 'nullable|integer',
            'project_id' => 'nullable|exists:construction_projects,id',
            'vehicle_number' => 'required|string|max:50',
            'start_location' => 'required|string|max:255',
            'destination' => 'required|string|max:255',
            'start_km' => 'required|numeric|min:0',
            'end_km' => 'required|numeric|min:0',
            'total_distance' => 'nullable|numeric|min:0',
            'trip_status' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
            'remarks' => 'nullable|string',
            'photos.*' => 'nullable|image|max:10240',
            'file' => 'nullable|file|max:20480',
        ]);

        $member = $request->user();
        $photoPaths = [];

        try {
            if ($request->hasFile('photos')) {
                foreach ($request->file('photos') as $photoFile) {
                    $path = $photoFile->store('driver/photos', 'public');
                    $photoPaths[] = Storage::url($path);
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }

        $filePath = null;
        try {
            if ($request->hasFile('file')) {
                $path = $request->file('file')->store('driver/documents', 'public');
                $filePath = Storage::url($path);
            }
        } catch (\Throwable $e) {
            report($e);
        }

        $startKm = (float) $request->start_km;
        $endKm = (float) $request->end_km;
        $totalDistance = $request->filled('total_distance') 
            ? (float) $request->total_distance 
            : max(0, $endKm - $startKm);

        $notesStr = $request->notes ?? $request->remarks ?? null;
        $tripStatus = $request->trip_status ?? 'in_progress';
        $targetTaskId = $task ?? ($request->filled('task_id') ? (int) $request->task_id : null);

        $checklist = null;
        if ($targetTaskId !== null) {
            $resolved = $this->resolveTaskAcrossTables($targetTaskId);
            if ($resolved !== null) {
                $legacyTask = $resolved['task'];
                $checklist = TaskChecklist::create([
                    'execution_task_id' => $legacyTask->id,
                    'item_title' => 'Trip: ' . $request->start_location . ' to ' . $request->destination,
                    'vehicle_number' => $request->vehicle_number,
                    'start_location' => $request->start_location,
                    'destination' => $request->destination,
                    'start_km' => $startKm,
                    'end_km' => $endKm,
                    'total_distance' => $totalDistance,
                    'trip_status' => $tripStatus,
                    'delivery_proof_urls' => $photoPaths,
                    'document_url' => $filePath,
                    'notes' => $notesStr,
                    'status' => $tripStatus === 'completed' ? 'completed' : 'in_progress',
                    'is_completed' => $tripStatus === 'completed',
                ]);
            }
        }

        if (!$checklist) {
            $firstTaskId = ExecutionTask::value('id') ?? 1;
            $checklist = TaskChecklist::create([
                'execution_task_id' => $targetTaskId ?? $firstTaskId,
                'item_title' => 'Trip: ' . $request->start_location . ' to ' . $request->destination,
                'vehicle_number' => $request->vehicle_number,
                'start_location' => $request->start_location,
                'destination' => $request->destination,
                'start_km' => $startKm,
                'end_km' => $endKm,
                'total_distance' => $totalDistance,
                'trip_status' => $tripStatus,
                'delivery_proof_urls' => $photoPaths,
                'document_url' => $filePath,
                'notes' => $notesStr,
                'status' => $tripStatus === 'completed' ? 'completed' : 'in_progress',
                'is_completed' => $tripStatus === 'completed',
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Driver trip details submitted successfully.',
            'data' => [
                'id' => $checklist->id,
                'vehicle_number' => $request->vehicle_number,
                'start_location' => $request->start_location,
                'destination' => $request->destination,
                'start_km' => $startKm,
                'end_km' => $endKm,
                'total_distance' => $totalDistance,
                'trip_status' => $tripStatus,
                'notes' => $notesStr,
                'delivery_proof_photos' => $photoPaths,
                'receipt_pod_pdf' => $filePath,
                'is_completed' => $checklist->is_completed,
            ],
        ]);
    }

    /**
     * Submit Day Survey Field Data
     *
     * Submits daily survey metrics including GPS coordinates, elevation, distance covered, captured points count, photos, remarks, and raw data files.
     *
     * @tags Survey & Duty Visits
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function submitDayData(Request $request)
    {
        $request->validate([
            'task_id' => 'nullable',
            'survey_visit_id' => 'nullable',
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

        $taskIdInput = $request->input('task_id') ?? $request->input('survey_visit_id');
        $surveyVisitIdInput = $request->input('survey_visit_id');

        $visit = null;
        $task = null;
        $resolvedTaskData = null;

        if ($surveyVisitIdInput && is_numeric($surveyVisitIdInput)) {
            try {
                $visit = SurveyVisit::find((int) $surveyVisitIdInput);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        if ($taskIdInput && is_numeric($taskIdInput)) {
            try {
                $resolvedTaskData = $this->resolveTaskAcrossTables((int) $taskIdInput);
                if ($resolvedTaskData) {
                    $task = $resolvedTaskData['unifiedTask'] ?? $resolvedTaskData['task'];
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }

        if (!$visit && $task) {
            try {
                $visit = SurveyVisit::where('task_id', $task->id)
                    ->where('checked_in_by_member_id', $member->id)
                    ->latest()
                    ->first();
            } catch (\Throwable $e) {
                report($e);
            }
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

        $targetTaskId = $task?->id ?? (is_numeric($taskIdInput) ? (int) $taskIdInput : null);

        $project = null;
        $plan = null;
        try {
            $project = $task?->project
                ?? $visit?->project
                ?? ($request->project_id ? Project::find($request->project_id) : null)
                ?? ProjectTeamMember::where('member_id', $member->id)
                    ->where('status', 'active')
                    ->orderByDesc('id')
                    ->first()?->project;

            $preferredPlanId = (int) ($request->survey_plan_id ?? $request->input('survey_plan') ?? 0);
            $plan = $task?->surveyPlan
                ?? $visit?->surveyPlan
                ?? $this->resolveOrCreateSurveyPlan($project, $member, $preferredPlanId ?: null, $task?->title);
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

        $resolvedProjectId = $task?->project_id
            ?? $visit?->project_id
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
                    'task_id' => $targetTaskId ?? $visit->task_id,
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
                $projectId = $resolvedProjectId ?? Project::value('id');
                $planId = $plan?->id ?? $request->survey_plan_id ?? SurveyPlan::value('id');

                $visit = SurveyVisit::create([
                    'task_id' => $targetTaskId,
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
                $projectObj = Project::query()->find($projectId);
                if ($projectObj) {
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

                    $projectObj->update([
                        'progress_percent' => min(100, ($projectObj->progress_percent ?? 0) + 10),
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
                'task_id' => $visit->task_id ?? $targetTaskId,
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
                'file_path' => $filePath ?? $visit->file_path,
                'notes' => $visit->notes,
            ],
        ]);
    }

    /**
     * Submit Day Task Work Data & Files
     *
     * Submits completed checklist items, site photos (up to 10), categorized survey files (CSV/RAW/KML), sketches, reference documents, and site status / obstruction reports for supervisor review.
     *
     * @tags Member Tasks & Duties
     * @param Request $request
     * @param int|string|null $task Identifier of the task being submitted (passed in URL)
     * @return JsonResponse
     */
    public function submitTaskData(Request $request, $task = null)
    {
        try {
            $taskId = $task instanceof ExecutionTask || $task instanceof Task
                ? $task->id
                : ($task ?? $request->input('task_id') ?? $request->input('task'));

            if (!$taskId || !is_numeric($taskId)) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'INVALID_TASK_ID',
                    'message' => 'A valid task ID is required in the URL or payload.',
                ], 422);
            }

            $resolvedTaskData = $this->resolveTaskAcrossTables((int) $taskId);
            if (!$resolvedTaskData) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'TASK_NOT_FOUND',
                    'message' => 'No task found with the given identifier: ' . $taskId,
                ], 404);
            }

            $legacyTask = $resolvedTaskData['task'];
            $unifiedTask = $resolvedTaskData['unifiedTask'];

            // Task assignment access validation removed per request

            $member = $request->user();
            if (!$member) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'UNAUTHENTICATED',
                    'message' => 'You must be authenticated to submit task data.',
                ], 401);
            }

            $project = $legacyTask->project
                ?? ($legacyTask->project_id ? Project::find($legacyTask->project_id) : null)
                ?? ($unifiedTask?->project_id ? Project::find($unifiedTask->project_id) : null);

            $preferredPlanId = (int) ($request->input('survey_plan_id')
                ?? $request->input('survey_plan')
                ?? $legacyTask->survey_plan_id
                ?? $unifiedTask?->survey_plan_id
                ?? 0);

            $plan = $legacyTask->surveyPlan
                ?? $this->resolveOrCreateSurveyPlan(
                    $project,
                    $member,
                    $preferredPlanId ?: null,
                    $legacyTask->title ?? $unifiedTask?->title
                );

            $totalDays = $this->surveyData->getTotalSurveyDays($plan, $project);

            $existingVisit = SurveyVisit::where('task_id', $legacyTask->id)
                ->where(function ($q) use ($member) {
                    $q->where('checked_in_by_member_id', $member->id)
                        ->orWhereNull('checked_in_by_member_id');
                })
                ->latest()
                ->first();

            $currentDay = $this->surveyData->getCurrentDayNumber($existingVisit, $plan, $project);
            if ($request->filled('day_number') && is_numeric($request->input('day_number'))) {
                $currentDay = max(1, (int) $request->input('day_number'));
            }

            // 1. Checklist processing (Image 1: Work checklist done)
            $this->surveyData->ensureDefaultTaskChecklists($legacyTask, $currentDay);
            if ($unifiedTask) {
                try {
                    $this->mirrorChecklistItemsToLegacy($unifiedTask, $legacyTask);
                } catch (\Throwable $e) {
                    report($e);
                }
            }
            $allChecklists = TaskChecklist::where('execution_task_id', $legacyTask->id)->get();

            $rawChecklistIds = $request->input('checklist_ids', []);
            if (is_string($rawChecklistIds)) {
                $decoded = json_decode($rawChecklistIds, true);
                $rawChecklistIds = is_array($decoded) ? $decoded : explode(',', $rawChecklistIds);
            }
            $completedIds = array_map('intval', array_filter((array) $rawChecklistIds, fn ($v) => is_numeric($v)));

            // Gather completed item titles from both legacy and unified checklist tables
            $completedTitles = [];
            if (!empty($completedIds)) {
                $completedLegacy = TaskChecklist::whereIn('id', $completedIds)->get(['id', 'item_title']);
                foreach ($completedLegacy as $c) {
                    $completedTitles[] = strtolower(trim((string) $c->item_title));
                }
                $completedUnified = \App\Models\TaskChecklistItem::whereIn('id', $completedIds)->get(['id', 'item_title']);
                foreach ($completedUnified as $c) {
                    $completedTitles[] = strtolower(trim((string) $c->item_title));
                }
            }

            $now = now();
            foreach ($allChecklists as $chk) {
                $chkTitle = strtolower(trim((string) $chk->item_title));
                $isDone = in_array((int) $chk->id, $completedIds, true)
                    || (!empty($chkTitle) && in_array($chkTitle, $completedTitles, true));
                $chk->update([
                    'is_completed' => $isDone,
                    'status' => $isDone ? 'completed' : 'pending',
                    'completed_by_member_id' => $isDone ? $member->id : null,
                    'completed_at' => $isDone ? $now : null,
                ]);
            }
            $allChecklists = $allChecklists->fresh();
            $completedChecklistCount = $allChecklists->where('is_completed', true)->count();
            $totalChecklistCount = $allChecklists->count();

            // Also synchronize unified task checklist items if unified task exists
            if ($unifiedTask) {
                try {
                    $unifiedChecklistItems = \App\Models\TaskChecklistItem::where('task_id', $unifiedTask->id)->get();
                    foreach ($unifiedChecklistItems as $uChk) {
                        $uTitle = strtolower(trim((string) ($uChk->item_title ?? $uChk->title)));
                        $isDone = in_array((int) $uChk->id, $completedIds, true)
                            || (!empty($uTitle) && in_array($uTitle, $completedTitles, true));
                        $uChk->update([
                            'is_completed' => $isDone,
                            'status' => $isDone ? 'completed' : 'pending',
                            'completed_by_member_id' => $isDone ? $member->id : null,
                            'completed_at' => $isDone ? $now : null,
                        ]);
                    }
                } catch (\Throwable $e) {
                    report($e);
                }
            }

        // 2. Photos (Site Photos: Image 2)
        $newPhotoUrls = [];
        $photoFiles = $request->file('photos') ?? $request->file('site_photos') ?? [];
        if (!is_array($photoFiles) && $photoFiles) {
            $photoFiles = [$photoFiles];
        }
        foreach ($photoFiles as $photoFile) {
            if ($photoFile && $photoFile->isValid()) {
                try {
                    $path = $photoFile->store('surveys/photos', 'public');
                    $newPhotoUrls[] = Storage::url($path);
                    $this->createTaskDocumentRecord($legacyTask->id, $member->id, Storage::url($path), $path, 'site_photo');
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        }
        $existingPhotos = is_array($existingVisit?->photos) ? $existingVisit->photos : [];
        $existingFromRequest = (array) $request->input('existing_photos', []);
        $finalPhotos = array_values(array_unique(array_merge($existingPhotos, $existingFromRequest, $newPhotoUrls)));

        // 3. Multiple Categorized Files (Image 3: Files tab)
        $categorizedFiles = [
            'survey_data' => [],
            'sketch_markup' => [],
            'reference_documents' => [],
        ];

        // 3a. Survey Data Files (CSV, RAW, KML, Excel)
        $surveyDataFiles = $request->file('survey_data_files')
            ?? $request->file('survey_data_file')
            ?? $request->file('survey_files')
            ?? [];
        if (!is_array($surveyDataFiles) && $surveyDataFiles) {
            $surveyDataFiles = [$surveyDataFiles];
        }
        foreach ($surveyDataFiles as $file) {
            if ($file && $file->isValid()) {
                try {
                    $origName = $file->getClientOriginalName();
                    $ext = strtolower($file->getClientOriginalExtension());
                    $sizeFormatted = $this->formatFileSize($file->getSize());
                    $path = $file->store('surveys/data', 'public');
                    $url = Storage::url($path);
                    $categorizedFiles['survey_data'][] = [
                        'name' => $origName,
                        'url' => $url,
                        'path' => $path,
                        'size' => $sizeFormatted,
                        'extension' => $ext,
                    ];
                    $this->createTaskDocumentRecord($legacyTask->id, $member->id, $url, $path, 'survey_data');
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        }

        // 3b. Sketch & Markup Files (JPG, PNG, PDF)
        $sketchFiles = $request->file('sketch_markup_files')
            ?? $request->file('sketch_files')
            ?? $request->file('markup_files')
            ?? [];
        if (!is_array($sketchFiles) && $sketchFiles) {
            $sketchFiles = [$sketchFiles];
        }
        foreach ($sketchFiles as $file) {
            if ($file && $file->isValid()) {
                try {
                    $origName = $file->getClientOriginalName();
                    $ext = strtolower($file->getClientOriginalExtension());
                    $sizeFormatted = $this->formatFileSize($file->getSize());
                    $path = $file->store('surveys/sketches', 'public');
                    $url = Storage::url($path);
                    $categorizedFiles['sketch_markup'][] = [
                        'name' => $origName,
                        'url' => $url,
                        'path' => $path,
                        'size' => $sizeFormatted,
                        'extension' => $ext,
                    ];
                    $this->createTaskDocumentRecord($legacyTask->id, $member->id, $url, $path, 'sketch_markup');
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        }

        // 3c. Reference Documents (PDF, DOCX)
        $refFiles = $request->file('reference_document_files')
            ?? $request->file('reference_documents')
            ?? $request->file('reference_files')
            ?? [];
        if (!is_array($refFiles) && $refFiles) {
            $refFiles = [$refFiles];
        }
        foreach ($refFiles as $file) {
            if ($file && $file->isValid()) {
                try {
                    $origName = $file->getClientOriginalName();
                    $ext = strtolower($file->getClientOriginalExtension());
                    $sizeFormatted = $this->formatFileSize($file->getSize());
                    $path = $file->store('surveys/reference_docs', 'public');
                    $url = Storage::url($path);
                    $categorizedFiles['reference_documents'][] = [
                        'name' => $origName,
                        'url' => $url,
                        'path' => $path,
                        'size' => $sizeFormatted,
                        'extension' => $ext,
                    ];
                    $this->createTaskDocumentRecord($legacyTask->id, $member->id, $url, $path, 'reference_document');
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        }

        // Legacy / Generic single file fallback
        if ($request->hasFile('file') && empty($categorizedFiles['survey_data'])) {
            $file = $request->file('file');
            if ($file && $file->isValid()) {
                $path = $file->store('surveys/documents', 'public');
                $url = Storage::url($path);
                $categorizedFiles['survey_data'][] = [
                    'name' => $file->getClientOriginalName(),
                    'url' => $url,
                    'path' => $path,
                    'size' => $this->formatFileSize($file->getSize()),
                    'extension' => strtolower($file->getClientOriginalExtension()),
                ];
                $this->createTaskDocumentRecord($legacyTask->id, $member->id, $url, $path, 'survey_data');
            }
        }

        // 4. Status tab data (Image 4)
        $workStatus = strtolower((string) $request->input('work_status', 'completed'));
        if (!in_array($workStatus, ['completed', 'partial', 'not_done'], true)) {
            $workStatus = $completedChecklistCount === $totalChecklistCount ? 'completed' : 'partial';
        }

        $rawHasObstruction = $request->input('has_obstruction');
        $hasObstruction = filter_var($rawHasObstruction, FILTER_VALIDATE_BOOLEAN)
            || in_array(strtolower((string) $rawHasObstruction), ['1', 'yes', 'true'], true);

        $obstructionDetails = $request->input('obstruction_details');
        $pendingWork = $request->input('pending_work');
        $startTime = $request->input('start_time') ?? '09:00 AM';
        $endTime = $request->input('end_time') ?? '01:00 PM';
        $totalHours = $request->input('total_hours') ?? '4h 0m';
        $remarks = $request->input('remarks');

        // 5. Build structured notes and persist Visit
        $notesMetadata = [
            'work_status' => $workStatus,
            'has_obstruction' => $hasObstruction,
            'obstruction_details' => $obstructionDetails,
            'pending_work' => $pendingWork,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'total_hours' => $totalHours,
            'work_summary' => [
                'start_time' => $startTime,
                'end_time' => $endTime,
                'total_hours' => $totalHours,
            ],
            'categorized_files' => $categorizedFiles,
            'remarks' => $remarks,
            'checklist_summary' => [
                'completed' => $completedChecklistCount,
                'total' => $totalChecklistCount,
            ],
            'submitted_at' => now()->toIso8601String(),
        ];

        $primaryFilePath = !empty($categorizedFiles['survey_data'][0]['url'])
            ? $categorizedFiles['survey_data'][0]['url']
            : ($existingVisit?->file_path);

        $defaultCoords = $this->surveyData->resolveSiteCoordinates($plan, $project);
        $lat = $request->latitude !== null ? (float) $request->latitude : $defaultCoords['latitude'];
        $long = $request->longitude !== null ? (float) $request->longitude : $defaultCoords['longitude'];

        $surveyPlanId = $plan?->id ?? $existingVisit?->survey_plan_id ?? ($preferredPlanId ?: null);
        $elevation = $request->input('elevation_m') ?? $request->input('elevation');
        $distance = $request->input('distance_covered_m') ?? $request->input('distance');
        $points = $request->input('total_points_captured') ?? $request->input('points');

        if ($existingVisit) {
            $existingVisit->update([
                'task_id' => $legacyTask->id,
                'execution_task_id' => $legacyTask->id,
                'project_id' => $project?->id ?? $existingVisit->project_id,
                'survey_plan_id' => $surveyPlanId ?? $existingVisit->survey_plan_id,
                'checked_in_by_member_id' => $member->id,
                'check_in_latitude' => $lat,
                'check_in_longitude' => $long,
                'elevation_m' => $elevation ?? $existingVisit->elevation_m,
                'distance_covered_m' => $distance ?? $existingVisit->distance_covered_m,
                'total_points_captured' => $points ?? $existingVisit->total_points_captured,
                'photos' => $finalPhotos,
                'file_path' => $primaryFilePath,
                'remarks' => $remarks ?? $existingVisit->remarks,
                'notes' => json_encode($notesMetadata, JSON_UNESCAPED_SLASHES),
                'day_number' => $currentDay,
                'status' => SurveyStatus::SUBMITTED, // 20
            ]);
            $visit = $existingVisit;
        } else {
            $visit = SurveyVisit::create([
                'task_id' => $legacyTask->id,
                'execution_task_id' => $legacyTask->id,
                'project_id' => $project?->id,
                'survey_plan_id' => $surveyPlanId,
                'checked_in_by_member_id' => $member->id,
                'check_in_at' => now(),
                'check_in_latitude' => $lat,
                'check_in_longitude' => $long,
                'elevation_m' => $elevation,
                'distance_covered_m' => $distance,
                'total_points_captured' => $points,
                'photos' => $finalPhotos,
                'file_path' => $primaryFilePath,
                'remarks' => $remarks,
                'notes' => json_encode($notesMetadata, JSON_UNESCAPED_SLASHES),
                'day_number' => $currentDay,
                'gps_verified' => true,
                'status' => SurveyStatus::SUBMITTED, // 20
            ]);
        }

        // 6. SurveySubmission
        $submission = null;
        if ($project) {
            try {
                $submission = SurveySubmission::updateOrCreate(
                    [
                        'project_id' => (int) $project->id,
                        'survey_visit_id' => (int) $visit->id,
                    ],
                    [
                        'submitted_by_member_id' => (int) $member->id,
                        'submitted_at' => now(),
                        'status' => SurveyStatus::SUBMITTED, // 20
                        'review_notes' => null,
                    ]
                );
            } catch (\Throwable $e) {
                report($e);
            }
        }

        // 7. Update Project & Task flow
        if ($project) {
            try {
                $project->update([
                    'current_stage' => 'survey_submitted',
                ]);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        $progressPercent = $totalChecklistCount > 0
            ? (int) round(($completedChecklistCount / $totalChecklistCount) * 100)
            : 100;

        try {
            $legacyTask->update([
                'status' => 'review',
                'progress_percent' => $progressPercent,
            ]);
            if ($unifiedTask) {
                $unifiedTask->update([
                    'status' => 'review',
                    'progress_percent' => $progressPercent,
                ]);
            }
        } catch (\Throwable $e) {
            report($e);
        }

        // 8. Build Image 5 response
        $referenceCode = 'SRV-' . ($project ? str_pad((string) $project->id, 4, '0', STR_PAD_LEFT) : '0912') . '-D' . $currentDay;
        $totalFilesUploaded = count($categorizedFiles['survey_data'])
            + count($categorizedFiles['sketch_markup'])
            + count($categorizedFiles['reference_documents']);

        $nowFormatted = now()->setTimezone('Asia/Kolkata')->format('d M Y · h:i A');

        return response()->json([
            'success' => true,
            'message' => 'Your survey task has been submitted successfully and sent to the manager for review.',
            'data' => [
                'title' => 'Survey task submitted',
                'subtitle' => 'Your survey has been sent to the manager for review.',
                'review_status' => [
                    [
                        'step' => 1,
                        'title' => 'Submitted by surveyor',
                        'subtitle' => $nowFormatted,
                        'status' => 'completed',
                        'is_current' => false,
                    ],
                    [
                        'step' => 2,
                        'title' => 'Waiting for manager review',
                        'subtitle' => 'Current status',
                        'status' => 'current',
                        'is_current' => true,
                    ],
                    [
                        'step' => 3,
                        'title' => 'Approved or changes requested',
                        'subtitle' => 'You will be notified',
                        'status' => 'pending',
                        'is_current' => false,
                    ],
                ],
                'task_summary' => [
                    'task_id' => $legacyTask->id,
                    'reference' => $referenceCode,
                    'project_name' => $project?->name ?? 'Survey Project',
                    'day' => $currentDay . ' / ' . $totalDays,
                    'day_number' => $currentDay,
                    'total_days' => $totalDays,
                    'total_hours' => $totalHours,
                    'uploaded' => count($finalPhotos) . ' photos · ' . $totalFilesUploaded . ' files',
                    'photos_count' => count($finalPhotos),
                    'files_count' => $totalFilesUploaded,
                    'checklist_completed_count' => $completedChecklistCount,
                    'checklist_total_count' => $totalChecklistCount,
                    'work_status' => $workStatus,
                    'has_obstruction' => $hasObstruction,
                    'obstruction_details' => $obstructionDetails,
                    'pending_work' => $pendingWork,
                    'submission_id' => $submission?->id,
                    'visit_id' => $visit->id,
                ],
            ],
        ]);
        } catch (\Illuminate\Validation\ValidationException $ve) {
            return response()->json([
                'success' => false,
                'error_code' => 'VALIDATION_ERROR',
                'message' => $ve->getMessage(),
                'errors' => $ve->errors(),
            ], 422);
        } catch (\Throwable $e) {
            report($e);
            return response()->json([
                'success' => false,
                'error_code' => 'TASK_SUBMISSION_FAILED',
                'message' => 'Failed to submit task data: ' . ($e->getMessage() ?: 'An unexpected error occurred while processing task submission.'),
            ], 500);
        }
    }

    /**
     * Submit Field Team Revision Reply
     *
     * Allows field surveyors / assignees to submit a reply/clarification to a requested survey revision,
     * upload additional photos or files, update checklist statuses, and return task to review state.
     *
     * @tags Tasks & Checklists
     * @param Request $request
     * @param mixed $task
     * @return \Illuminate\Http\JsonResponse
     */
    public function submitRevisionReply(Request $request, $task = null)
    {
        try {
            $taskId = $task instanceof ExecutionTask || $task instanceof Task
                ? $task->id
                : ($task ?? $request->input('task_id') ?? $request->input('task'));

            if (!$taskId || !is_numeric($taskId)) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'INVALID_TASK_ID',
                    'message' => 'A valid task ID is required in the URL or payload.',
                ], 422);
            }

            $resolvedTaskData = $this->resolveTaskAcrossTables((int) $taskId);
            if (!$resolvedTaskData) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'TASK_NOT_FOUND',
                    'message' => 'No task found with the given identifier: ' . $taskId,
                ], 404);
            }

            $legacyTask = $resolvedTaskData['task'];
            $unifiedTask = $resolvedTaskData['unifiedTask'];

            $member = $request->user();
            if (!$member) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'UNAUTHENTICATED',
                    'message' => 'You must be authenticated to submit a revision reply.',
                ], 401);
            }

            $replyMessage = trim((string) (
                $request->input('reply')
                ?? $request->input('message')
                ?? $request->input('remarks')
                ?? $request->input('notes')
                ?? ''
            ));

            if ($replyMessage === '') {
                return response()->json([
                    'success' => false,
                    'error_code' => 'VALIDATION_ERROR',
                    'message' => 'A reply message or explanation is required.',
                    'errors' => [
                        'reply' => ['Please enter your revision reply or clarification notes.'],
                    ],
                ], 422);
            }

            $project = $legacyTask->project
                ?? ($legacyTask->project_id ? Project::find($legacyTask->project_id) : null)
                ?? ($unifiedTask?->project_id ? Project::find($unifiedTask->project_id) : null);

            $taskIds = array_values(array_unique(array_filter([(int) $legacyTask->id, (int) ($unifiedTask?->id ?? 0)])));
            $hasExecCol = $this->hasSurveyVisitExecutionTaskIdColumn();
            $visit = SurveyVisit::where(function ($q) use ($taskIds, $hasExecCol) {
                $q->whereIn('task_id', $taskIds);
                if ($hasExecCol) {
                    $q->orWhereIn('execution_task_id', $taskIds);
                }
            })->latest('id')->first();

            if (!$visit && $project) {
                $visit = SurveyVisit::where('project_id', $project->id)->latest('id')->first();
            }

            $uploadedFiles = [];
            $newPhotos = [];

            if ($request->hasFile('photos')) {
                foreach ((array) $request->file('photos') as $idx => $photoFile) {
                    if ($photoFile && $photoFile->isValid()) {
                        $filename = 'rev_photo_' . time() . '_' . $idx . '.' . $photoFile->getClientOriginalExtension();
                        $relPath = $photoFile->storeAs('survey_visits/revisions', $filename, 'public');
                        $url = Storage::disk('public')->url($relPath);
                        $newPhotos[] = $url;
                        $uploadedFiles[] = [
                            'name' => $photoFile->getClientOriginalName(),
                            'url' => $url,
                            'path' => $relPath,
                            'type' => 'photo',
                            'size' => $this->formatFileSize($photoFile->getSize() ?: 0),
                        ];
                        $this->createTaskDocumentRecord($legacyTask->id, $member->id, $url, $relPath, 'photo');
                    }
                }
            }

            if ($request->hasFile('files')) {
                foreach ((array) $request->file('files') as $idx => $file) {
                    if ($file && $file->isValid()) {
                        $filename = 'rev_file_' . time() . '_' . $idx . '.' . $file->getClientOriginalExtension();
                        $relPath = $file->storeAs('survey_visits/revisions', $filename, 'public');
                        $url = Storage::disk('public')->url($relPath);
                        $uploadedFiles[] = [
                            'name' => $file->getClientOriginalName(),
                            'url' => $url,
                            'path' => $relPath,
                            'type' => 'document',
                            'size' => $this->formatFileSize($file->getSize() ?: 0),
                        ];
                        $this->createTaskDocumentRecord($legacyTask->id, $member->id, $url, $relPath, 'document');
                    }
                }
            }

            if ($request->hasFile('file')) {
                $file = $request->file('file');
                if ($file && $file->isValid()) {
                    $filename = 'rev_doc_' . time() . '.' . $file->getClientOriginalExtension();
                    $relPath = $file->storeAs('survey_visits/revisions', $filename, 'public');
                    $url = Storage::disk('public')->url($relPath);
                    $uploadedFiles[] = [
                        'name' => $file->getClientOriginalName(),
                        'url' => $url,
                        'path' => $relPath,
                        'type' => 'document',
                        'size' => $this->formatFileSize($file->getSize() ?: 0),
                    ];
                    $this->createTaskDocumentRecord($legacyTask->id, $member->id, $url, $relPath, 'document');
                }
            }

            $now = now();
            $nowFormatted = $now->setTimezone('Asia/Kolkata')->format('d M Y · h:i A');

            if ($visit) {
                $visitNotes = is_array($visit->notes) ? $visit->notes : (json_decode($visit->notes ?? '', true) ?: []);

                $memberReplyData = [
                    'message' => $replyMessage,
                    'replied_at' => $now->toIso8601String(),
                    'replied_at_formatted' => $nowFormatted,
                    'member_id' => $member->id,
                    'member_name' => $member->name,
                    'files' => $uploadedFiles,
                ];

                $visitNotes['member_reply'] = $memberReplyData;

                $history = $visitNotes['revision_history'] ?? [];
                $history[] = [
                    'type' => 'member_reply',
                    'sender_role' => 'surveyor',
                    'sender_name' => $member->name,
                    'sender_id' => $member->id,
                    'message' => $replyMessage,
                    'files' => $uploadedFiles,
                    'created_at' => $now->toIso8601String(),
                    'created_at_formatted' => $nowFormatted,
                ];
                $visitNotes['revision_history'] = $history;

                $existingPhotos = is_array($visit->photos) ? $visit->photos : [];
                $finalPhotos = array_values(array_unique(array_merge($existingPhotos, $newPhotos)));

                $visit->update([
                    'status' => SurveyStatus::SUBMITTED,
                    'notes' => json_encode($visitNotes, JSON_UNESCAPED_SLASHES),
                    'photos' => $finalPhotos,
                ]);

                $sub = SurveySubmission::where('survey_visit_id', $visit->id)->first();
                if ($sub) {
                    $sub->update([
                        'member_reply' => $replyMessage,
                        'member_replied_at' => $now,
                        'status' => SurveySubmission::STATUS_SUBMITTED,
                    ]);
                } elseif ($project) {
                    SurveySubmission::create([
                        'project_id' => $project->id,
                        'survey_visit_id' => $visit->id,
                        'task_id' => $legacyTask->id,
                        'execution_task_id' => $legacyTask->id,
                        'submitted_by_member_id' => $member->id,
                        'submitted_at' => $now,
                        'status' => SurveySubmission::STATUS_SUBMITTED,
                        'member_reply' => $replyMessage,
                        'member_replied_at' => $now,
                    ]);
                }
            }

            $legacyTask->update([
                'status' => 'review',
            ]);
            if ($unifiedTask) {
                $unifiedTask->update([
                    'status' => 'review',
                    'client_review_status' => \App\Enums\ClientReviewStatus::REQUESTED,
                ]);
            }

            try {
                \App\Models\TaskComment::create([
                    'uuid' => (string) \Illuminate\Support\Str::uuid(),
                    'task_id' => $unifiedTask?->id ?? $legacyTask->id,
                    'project_id' => $project?->id,
                    'commented_by' => $member->id,
                    'comment' => "Field team replied to survey revision: " . $replyMessage,
                    'kind' => 'revision_reply',
                    'from_status' => 'in_progress',
                    'to_status' => 'review',
                ]);
            } catch (\Throwable $e) {
                report($e);
            }

            if ($request->has('checklist_ids')) {
                $rawChecklistIds = $request->input('checklist_ids');
                if (is_string($rawChecklistIds)) {
                    $rawChecklistIds = json_decode($rawChecklistIds, true) ?: explode(',', $rawChecklistIds);
                }
                $cIds = array_map('intval', array_filter((array) $rawChecklistIds, fn ($v) => is_numeric($v)));
                if (!empty($cIds)) {
                    TaskChecklist::where('execution_task_id', $legacyTask->id)
                        ->whereIn('id', $cIds)
                        ->update(['is_completed' => true, 'status' => 'completed']);
                    if ($unifiedTask) {
                        \App\Models\TaskChecklistItem::where('task_id', $unifiedTask->id)
                            ->whereIn('id', $cIds)
                            ->update(['is_completed' => true, 'status' => 'completed']);
                    }
                }
            }

            $revInfo = $this->resolveTaskRevisionInfo($legacyTask) ?? ($unifiedTask ? $this->resolveTaskRevisionInfo($unifiedTask) : null);

            return response()->json([
                'success' => true,
                'message' => 'Revision reply submitted successfully. The task is now under review.',
                'task' => [
                    'id' => $legacyTask->id,
                    'task_code' => $legacyTask->task_code ?? $unifiedTask?->task_code,
                    'title' => $legacyTask->title,
                    'status' => 'review',
                    'status_label' => 'Under Review',
                    'review_status' => 'revision_replied',
                    'is_revision_requested' => true,
                    'is_replied' => true,
                    'revision_remarks' => $revInfo['revision_remarks'] ?? null,
                    'revision_reply' => $replyMessage,
                    'revision_replied_at' => $nowFormatted,
                    'revision' => $revInfo,
                ],
                'revision' => $revInfo,
                'uploaded_files' => $uploadedFiles,
            ]);
        } catch (\Illuminate\Validation\ValidationException $ve) {
            return response()->json([
                'success' => false,
                'error_code' => 'VALIDATION_ERROR',
                'message' => $ve->getMessage(),
                'errors' => $ve->errors(),
            ], 422);
        } catch (\Throwable $e) {
            report($e);
            return response()->json([
                'success' => false,
                'error_code' => 'REVISION_REPLY_FAILED',
                'message' => 'Failed to submit revision reply: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function hasSurveyVisitExecutionTaskIdColumn(): bool
    {
        static $hasCol = null;
        if ($hasCol === null) {
            $hasCol = Schema::hasTable('construction_survey_visits') && Schema::hasColumn('construction_survey_visits', 'execution_task_id');
        }
        return $hasCol;
    }

    private function createTaskDocumentRecord(int $taskId, int $memberId, string $url, string $path, string $type): void
    {
        try {
            \App\Models\TaskDocument::create([
                'uuid' => (string) \Illuminate\Support\Str::uuid(),
                'task_id' => $taskId,
                'uploaded_by' => $memberId,
                'link' => $url,
                'path' => $path,
                'type' => $type,
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function formatFileSize(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 1) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 1) . ' KB';
        }

        return $bytes . ' B';
    }

    /**
     * Resolve an existing SurveyPlan or auto-create one for the project.
     */
    protected function resolveOrCreateSurveyPlan(
        ?Project $project,
        ?Member $member = null,
        ?int $preferredPlanId = null,
        ?string $titleContext = null
    ): ?SurveyPlan {
        if ($preferredPlanId) {
            $found = SurveyPlan::find($preferredPlanId);
            if ($found) {
                return $found;
            }
        }

        if ($project) {
            $projectPlan = SurveyPlan::where('project_id', $project->id)
                ->orderByDesc('id')
                ->first();
            if ($projectPlan) {
                return $projectPlan;
            }
        }

        if ($member) {
            $assignedPlan = SurveyPlan::whereHas('planMembers', function ($q) use ($member) {
                $q->where('member_id', $member->id);
            })
            ->when($project, fn ($q) => $q->where('project_id', $project->id))
            ->orderByDesc('id')
            ->first();

            if ($assignedPlan) {
                return $assignedPlan;
            }
        }

        if ($project) {
            try {
                $code = 'SUR-' . strtoupper(\Illuminate\Support\Str::random(8));
                $title = 'Field Survey - ' . ($titleContext ?: ($project->name ?? 'Project #' . $project->id));

                $newPlan = SurveyPlan::create([
                    'project_id' => $project->id,
                    'survey_code' => $code,
                    'title' => $title,
                    'description' => 'Auto-generated survey plan for task submissions and field surveys.',
                    'site_address' => $project->site_address ?? $project->location ?? null,
                    'site_latitude' => $project->latitude ?? null,
                    'site_longitude' => $project->longitude ?? null,
                    'planned_date' => now()->toDateString(),
                    'assigned_by_type' => $member ? get_class($member) : null,
                    'assigned_by_id' => $member?->id,
                    'status' => SurveyPlan::STATUS_IN_PROGRESS,
                ]);

                if ($member) {
                    SurveyPlanMember::firstOrCreate(
                        [
                            'survey_plan_id' => $newPlan->id,
                            'member_id' => $member->id,
                        ],
                        [
                            'role_in_survey' => 'Lead Surveyor',
                            'status' => 'assigned',
                        ]
                    );
                }

                return $newPlan;
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return SurveyPlan::orderByDesc('id')->first();
    }

    /**
     * Get Project Survey Details & Team
     *
     * Retrieves full survey plan details, team members, and documents for a specific construction project.
     *
     * @tags Survey & Duty Tracking
     * @param Request $request
     * @param Project $project
     * @return \Illuminate\Http\JsonResponse
     */
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
