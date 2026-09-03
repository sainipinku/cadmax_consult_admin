<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Company;
use App\Models\MemberRoleAssignment;
use App\Models\Project;
use App\Models\ProjectBudget;
use App\Models\ProjectTeamMember;
use App\Models\ConstructionRole;
use App\Models\VehicleAssignment;
use App\Models\VehicleLocationPing;
use App\Models\Member;
use App\Services\Construction\ConstructionAuthorizationService;
use App\Services\Construction\ConstructionTeamAssignmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProjectApiController extends Controller
{
    public function __construct(
        protected readonly ConstructionAuthorizationService $authz,
        protected readonly ConstructionTeamAssignmentService $teamService,
    ) {}

    private function denyUnlessCanManageTeam(Project $project, mixed $actor): ?JsonResponse
    {
        if ($actor === null) {
            return response()->json([
                'success' => false,
                'error_code' => 'AUTH_REQUIRED',
                'message' => 'Authentication required.',
            ], 401);
        }

        $isSuperAdmin = $actor instanceof \App\Models\SuperAdmin || $actor instanceof \App\Models\Admin;
        $isProjectAdmin = $actor instanceof Member
            && $this->authz->hasAnyPermission($actor, ['project_team.manage'], $project->id);

        if (! $isSuperAdmin && ! $isProjectAdmin) {
            return response()->json([
                'success' => false,
                'error_code' => 'FORBIDDEN',
                'message' => 'Only project admins can manage team assignments.',
            ], 403);
        }

        return null;
    }

    public function index(Request $request)
    {
        $query = Project::with(['company', 'client', 'latestBudget']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('current_stage')) {
            $query->where('current_stage', $request->current_stage);
        }

        if ($request->has('company_id')) {
            $query->where('company_id', $request->company_id);
        }

        if ($request->has('client_id')) {
            $query->where('client_id', $request->client_id);
        }

        if ($request->has('priority')) {
            $query->where('priority', $request->priority);
        }

        if ($request->has('search')) {
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

    public function show(Project $project)
    {
        $project->load([
            'company',
            'client',
            'budgets' => fn ($q) => $q->latest('version_no'),
            'teamMembers.member',
            'teamMembers.role',
            'surveyPlans',
            'draftingJobs',
            'executionPlans',
            'executionTasks',
            'materials',
            'vehicles',
            'equipments',
            'clientInvoices',
            'clientPayments',
            'handovers',
        ]);

        return response()->json([
            'success' => true,
            'data' => $project,
        ]);
    }

    public function store(Request $request)
    {
        $actor = $request->user();

        $validated = $request->validate([
            'company_id' => ['required', 'exists:construction_companies,id'],
            'client_id' => ['required', 'exists:construction_clients,id'],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'project_address' => ['nullable', 'string'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'start_date' => ['nullable', 'date'],
            'expected_end_date' => ['nullable', 'date'],
            'priority' => ['required', 'in:low,medium,high,critical'],
        ]);

        $nextId = (Project::max('id') ?? 0) + 1;
        $nameSlug = Str::slug($validated['name']);

        $project = Project::create([
            ...$validated,
            'project_code' => 'PRJ-' . str_pad((string) $nextId, 5, '0', STR_PAD_LEFT),
            'slug' => $nameSlug . '-' . $nextId,
            'status' => 'draft',
            'current_stage' => 'budget_pending',
            'created_by_type' => $actor ? $actor::class : null,
            'created_by_id' => $actor?->getKey(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Project created successfully.',
            'data' => $project->load(['company', 'client']),
        ], 201);
    }

    public function update(Request $request, Project $project)
    {
        $validated = $request->validate([
            'company_id' => ['sometimes', 'exists:construction_companies,id'],
            'client_id' => ['sometimes', 'exists:construction_clients,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'category' => ['sometimes', 'nullable', 'string', 'max:100'],
            'description' => ['sometimes', 'nullable', 'string'],
            'project_address' => ['sometimes', 'nullable', 'string'],
            'latitude' => ['sometimes', 'nullable', 'numeric'],
            'longitude' => ['sometimes', 'nullable', 'numeric'],
            'start_date' => ['sometimes', 'nullable', 'date'],
            'expected_end_date' => ['sometimes', 'nullable', 'date'],
            'priority' => ['sometimes', 'in:low,medium,high,critical'],
            'status' => ['sometimes', 'in:draft,active,on_hold,completed,cancelled'],
            'current_stage' => ['sometimes', 'string'],
        ]);

        $project->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Project updated successfully.',
            'data' => $project->load(['company', 'client', 'latestBudget']),
        ]);
    }

    public function destroy(Project $project)
    {
        try {
            DB::transaction(function () use ($project) {
                $projectId = $project->id;

                \App\Models\ConstructionActivityLog::where('project_id', $projectId)->delete();
                MemberRoleAssignment::where('project_id', $projectId)->delete();
                ProjectTeamMember::where('project_id', $projectId)->delete();
                ProjectBudget::where('project_id', $projectId)->delete();
                VehicleAssignment::where('project_id', $projectId)->delete();
                VehicleLocationPing::where('project_id', $projectId)->delete();
                \App\Models\EquipmentAllocation::where('project_id', $projectId)->delete();
                \App\Models\EquipmentUsageLog::where('project_id', $projectId)->delete();
                \App\Models\ProjectHandoverItem::whereIn(
                    'handover_id',
                    \App\Models\ProjectHandover::where('project_id', $projectId)->select('id')
                )->delete();
                \App\Models\ProjectHandover::where('project_id', $projectId)->delete();
                \App\Models\ClientPayment::where('project_id', $projectId)->delete();
                \App\Models\ClientInvoice::where('project_id', $projectId)->delete();
                \App\Models\MaterialIssue::where('project_id', $projectId)->delete();
                \App\Models\MaterialReceipt::where('project_id', $projectId)->delete();
                \App\Models\MaterialStock::where('project_id', $projectId)->delete();
                \App\Models\PurchaseOrder::where('project_id', $projectId)->delete();
                \App\Models\PurchaseRequest::where('project_id', $projectId)->delete();
                \App\Models\DrawingApproval::where('project_id', $projectId)->delete();
                \App\Models\DrawingRevision::where('project_id', $projectId)->delete();
                \App\Models\DraftingJob::where('project_id', $projectId)->delete();
                \App\Models\ConstructionDocument::where('project_id', $projectId)->delete();
                \App\Models\AttendanceRecord::where('project_id', $projectId)->delete();
                \App\Models\DailyProgressItem::whereIn(
                    'daily_progress_report_id',
                    \App\Models\DailyProgressReport::where('project_id', $projectId)->select('id')
                )->delete();
                \App\Models\DailyProgressReport::where('project_id', $projectId)->delete();
                \App\Models\ExecutionTaskAssignee::where('project_id', $projectId)->delete();
                \App\Models\ExecutionTask::where('project_id', $projectId)->delete();
                \App\Models\ExecutionPlan::where('project_id', $projectId)->delete();
                \App\Models\SurveyMeasurement::where('project_id', $projectId)->delete();
                \App\Models\SurveyEntry::where('project_id', $projectId)->delete();
                \App\Models\SurveySubmission::where('project_id', $projectId)->delete();
                \App\Models\SurveyVisit::where('project_id', $projectId)->delete();
                \App\Models\SurveyPlan::where('project_id', $projectId)->delete();

                \App\Models\ConstructionVehicle::where('project_id', $projectId)->update(['project_id' => null]);
                \App\Models\ConstructionEquipment::where('project_id', $projectId)->update(['project_id' => null]);
                \App\Models\Material::where('project_id', $projectId)->update(['project_id' => null]);
                \App\Models\Vendor::where('project_id', $projectId)->update(['project_id' => null]);

                $project->delete();
            });

            return response()->json([
                'success' => true,
                'message' => 'Project and all related data deleted successfully.',
            ]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete project. ' . $e->getMessage(),
            ], 500);
        }
    }

    public function updateStatus(Request $request, Project $project)
    {
        $validated = $request->validate([
            'status' => ['required', 'in:draft,active,on_hold,completed,cancelled'],
            'current_stage' => ['sometimes', 'nullable', 'string'],
        ]);

        $project->update([
            'status' => $validated['status'],
            'current_stage' => $validated['current_stage'] ?? $project->current_stage,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Project status updated successfully.',
            'data' => ['status' => $project->status, 'current_stage' => $project->current_stage],
        ]);
    }

    public function storeBudget(Request $request, Project $project)
    {
        $actor = $request->user();

        $validated = $request->validate([
            'estimated_amount' => ['required', 'numeric', 'min:0'],
            'approved_amount' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'notes' => ['nullable', 'string'],
            'status' => ['required', 'in:pending,approved,rejected'],
        ]);

        $budget = ProjectBudget::create([
            ...$validated,
            'project_id' => $project->id,
            'version_no' => (int) $project->budgets()->max('version_no') + 1,
            'submitted_by_type' => $actor ? $actor::class : null,
            'submitted_by_id' => $actor?->getKey(),
            'approved_by_type' => $validated['status'] === 'approved' && $actor ? $actor::class : null,
            'approved_by_id' => $validated['status'] === 'approved' ? $actor?->getKey() : null,
            'approved_at' => $validated['status'] === 'approved' ? now() : null,
        ]);

        $project->update([
            'status' => $validated['status'] === 'approved' ? 'active' : $project->status,
            'current_stage' => $validated['status'] === 'approved' ? 'budget_approved' : 'budget_pending',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Project budget saved successfully.',
            'data' => $budget,
        ], 201);
    }

    public function budgets(Project $project)
    {
        $budgets = $project->budgets()->latest('version_no')->get();

        return response()->json([
            'success' => true,
            'data' => $budgets,
        ]);
    }

    public function assignTeam(Request $request, Project $project): JsonResponse
    {
        $actor = $request->user();

        $denial = $this->denyUnlessCanManageTeam($project, $actor);
        if ($denial !== null) {
            return $denial;
        }

        $validated = $request->validate([
            'member_id' => ['required', 'exists:members,id'],
            'role_id' => ['nullable', 'exists:construction_roles,id'],
            'assigned_from' => ['nullable', 'date'],
            'assigned_to' => ['nullable', 'date', 'after_or_equal:assigned_from'],
            'assignment_scope' => ['nullable', 'string', 'max:255'],
            'is_primary' => ['nullable', 'boolean'],
            'status' => ['nullable', 'in:active,inactive'],
        ]);

        try {
            $existing = ProjectTeamMember::query()
                ->forAssignment($project->id, (int) $validated['member_id'], isset($validated['role_id']) ? (int) $validated['role_id'] : null)
                ->first();

            if ($existing) {
                $teamMember = $this->teamService->update($project, $existing, $validated, $actor);
            } else {
                $teamMember = $this->teamService->assign($project, $validated, $actor);
            }
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'error_code' => 'VALIDATION_ERROR',
                'message' => 'Assignment rejected.',
                'errors' => $e->errors(),
            ], 422);
        }

        if ($project->current_stage === 'budget_approved') {
            $project->update(['current_stage' => 'team_assigned']);
        }

        return response()->json([
            'success' => true,
            'message' => 'Project team member assigned successfully.',
            'data' => $teamMember->load(['member', 'role']),
        ]);
    }

    public function assignBatch(Request $request): JsonResponse
    {
        $actor = $request->user();

        $validated = $request->validate([
            'project_id' => ['required', 'exists:construction_projects,id'],
            'assignments' => ['required', 'array', 'min:1', 'max:50'],
            'assignments.*.member_id' => ['required', 'exists:members,id'],
            'assignments.*.role_id' => ['nullable', 'exists:construction_roles,id'],
            'assignments.*.assigned_from' => ['nullable', 'date'],
            'assignments.*.assigned_to' => ['nullable', 'date', 'after_or_equal:assigned_from'],
            'assignments.*.assignment_scope' => ['nullable', 'string', 'max:255'],
            'assignments.*.is_primary' => ['nullable', 'boolean'],
            'assignments.*.status' => ['nullable', 'in:active,inactive'],
        ]);

        $project = Project::query()->findOrFail((int) $validated['project_id']);

        $denial = $this->denyUnlessCanManageTeam($project, $actor);
        if ($denial !== null) {
            return $denial;
        }

        $created = [];
        $errors = [];

        DB::beginTransaction();
        try {
            foreach ($validated['assignments'] as $index => $assignment) {
                try {
                    $roleId = isset($assignment['role_id']) ? (int) $assignment['role_id'] : null;
                    $existing = $roleId !== null
                        ? ProjectTeamMember::query()
                            ->forAssignment($project->id, (int) $assignment['member_id'], $roleId)
                            ->first()
                        : ProjectTeamMember::query()
                            ->where('project_id', $project->id)
                            ->where('member_id', (int) $assignment['member_id'])
                            ->whereNull('role_id')
                            ->first();

                    if ($existing) {
                        $row = $this->teamService->update($project, $existing, $assignment, $actor);
                    } else {
                        $row = $this->teamService->assign($project, $assignment, $actor);
                    }

                    $created[] = $row->load(['member', 'role']);
                } catch (ValidationException $e) {
                    $errors[$index] = [
                        'assignment' => $assignment,
                        'errors' => $e->errors(),
                    ];
                }
            }

            if ($errors !== []) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'error_code' => 'VALIDATION_ERROR',
                    'message' => 'Some assignments failed validation. No changes were persisted.',
                    'errors' => $errors,
                    'succeeded_count' => count($created),
                    'failed_count' => count($errors),
                ], 422);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);

            return response()->json([
                'success' => false,
                'error_code' => 'INTERNAL_ERROR',
                'message' => config('app.debug') ? $e->getMessage() : 'Failed to assign team members.',
            ], 500);
        }

        if ($project->current_stage === 'budget_approved') {
            $project->update(['current_stage' => 'team_assigned']);
        }

        return response()->json([
            'success' => true,
            'message' => 'Team assigned successfully.',
            'project_id' => $project->id,
            'data' => $created,
            'assignment_count' => count($created),
        ]);
    }

    public function team(Project $project)
    {
        $team = $project->teamMembers()->with(['member', 'role'])->get();

        return response()->json([
            'success' => true,
            'data' => $team,
        ]);
    }

    public function removeTeamMember(Request $request, Project $project, $teamMemberId): JsonResponse
    {
        $actor = $request->user();

        $denial = $this->denyUnlessCanManageTeam($project, $actor);
        if ($denial !== null) {
            return $denial;
        }

        $teamMember = ProjectTeamMember::where('project_id', $project->id)
            ->where('id', $teamMemberId)
            ->first();

        if (! $teamMember) {
            return response()->json([
                'success' => false,
                'message' => 'Team member not found.',
            ], 404);
        }

        try {
            $this->teamService->remove($project, $teamMember, $actor);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'error_code' => 'INTERNAL_ERROR',
                'message' => config('app.debug') ? $e->getMessage() : 'Failed to remove team member.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Team member removed successfully.',
        ]);
    }

    public function stats()
    {
        $total = Project::count();
        $draft = Project::where('status', 'draft')->count();
        $active = Project::where('status', 'active')->count();
        $onHold = Project::where('status', 'on_hold')->count();
        $completed = Project::where('status', 'completed')->count();
        $cancelled = Project::where('status', 'cancelled')->count();

        $byStage = Project::selectRaw('current_stage, COUNT(*) as count')
            ->groupBy('current_stage')
            ->pluck('count', 'current_stage')
            ->toArray();

        $byPriority = Project::selectRaw('priority, COUNT(*) as count')
            ->groupBy('priority')
            ->pluck('count', 'priority')
            ->toArray();

        $totalBudget = ProjectBudget::where('status', 'approved')->sum('approved_amount') ?? 0;

        return response()->json([
            'success' => true,
            'data' => [
                'total' => $total,
                'by_status' => [
                    'draft' => $draft,
                    'active' => $active,
                    'on_hold' => $onHold,
                    'completed' => $completed,
                    'cancelled' => $cancelled,
                ],
                'by_stage' => $byStage,
                'by_priority' => $byPriority,
                'approved_budget_total' => $totalBudget,
            ],
        ]);
    }
}
