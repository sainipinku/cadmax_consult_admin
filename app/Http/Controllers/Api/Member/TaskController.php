<?php

namespace App\Http\Controllers\Api\Member;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\ProjectTeamMember;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\TaskComment;
use App\Services\Construction\ConstructionAuthorizationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TaskController extends Controller
{
    public function __construct(
        protected readonly ConstructionAuthorizationService $authz,
    ) {}

    private function accessibleProjectIds(Member $member): array
    {
        return ProjectTeamMember::query()
            ->where('member_id', $member->getKey())
            ->where('status', 'active')
            ->pluck('project_id')
            ->unique()
            ->all();
    }

    private function projectAdminIds(Member $member, array $projectIds): array
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

    public function index(Request $request)
    {
        /** @var Member $member */
        $member = $request->user();
        $memberId = $member->getKey();

        $accessibleProjectIds = $this->accessibleProjectIds($member);
        $adminProjectIds = $this->projectAdminIds($member, $accessibleProjectIds);

        $query = Task::query()
            ->with([
                'creator',
                'assignedMembers' => function ($q) use ($member) {
                    $q->select('members.id', 'members.name', 'members.email', 'members.phone')
                        ->where('assigned_to', $member->id)
                        ->withPivot(['uuid', 'assigned_by', 'start_date', 'end_date', 'is_transferred']);
                },
            ])
            ->where(function (Builder $q) use ($memberId, $accessibleProjectIds, $adminProjectIds) {
                $q->where(function (Builder $direct) use ($memberId, $accessibleProjectIds, $adminProjectIds) {
                    $direct->whereHas('assignedMembers', function ($sub) use ($memberId) {
                        $sub->where('assigned_to', $memberId);
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

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where('title', 'like', "%{$search}%");
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('project_id')) {
            $requested = (int) $request->input('project_id');
            if (! in_array($requested, $accessibleProjectIds, true)) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'FORBIDDEN',
                    'message' => 'You do not have access to this project.',
                ], 403);
            }
            $query->where('project_id', $requested);
        }

        $perPage = (int) $request->input('per_page', 10);
        $tasks = $query->latest()->paginate(max(1, min($perPage, 50)))->withQueryString();

        return response()->json([
            'success' => true,
            'tasks' => $tasks,
        ]);
    }

    public function show(Request $request, Task $task)
    {
        /** @var Member $member */
        $member = $request->user();
        $accessibleProjectIds = $this->accessibleProjectIds($member);

        $taskProjectId = $task->project_id ? (int) $task->project_id : null;
        if ($taskProjectId !== null && ! in_array($taskProjectId, $accessibleProjectIds, true)) {
            return response()->json([
                'success' => false,
                'error_code' => 'FORBIDDEN',
                'message' => 'Task belongs to a project you are not assigned to.',
            ], 403);
        }

        $assigned = TaskAssignment::query()
            ->where('task_id', $task->id)
            ->where('assigned_to', $member->id)
            ->exists();

        $isProjectAdmin = $taskProjectId !== null
            && $this->authz->hasAnyPermission($member, ['execution_task.manage', 'task.manage'], $taskProjectId);

        if (! $assigned && ! $isProjectAdmin) {
            return response()->json([
                'success' => false,
                'error_code' => 'FORBIDDEN',
                'message' => 'You are not assigned to this task.',
            ], 403);
        }

        $task->load(['creator', 'instances' => function ($q) use ($member) {
            $q->where('assigned_to', $member->id)->latest();
        }]);

        return response()->json([
            'success' => true,
            'task' => $task,
        ]);
    }

    public function notesIndex(Request $request, Task $task)
    {
        /** @var Member $member */
        $member = $request->user();
        $accessibleProjectIds = $this->accessibleProjectIds($member);
        $taskProjectId = $task->project_id ? (int) $task->project_id : null;

        if ($taskProjectId !== null && ! in_array($taskProjectId, $accessibleProjectIds, true)) {
            return response()->json([
                'success' => false,
                'error_code' => 'FORBIDDEN',
                'message' => 'Task belongs to a project you are not assigned to.',
            ], 403);
        }

        $assigned = TaskAssignment::query()
            ->where('task_id', $task->id)
            ->where('assigned_to', $member->id)
            ->exists();

        $isProjectAdmin = $taskProjectId !== null
            && $this->authz->hasAnyPermission($member, ['execution_task.manage', 'task.manage'], $taskProjectId);

        if (! $assigned && ! $isProjectAdmin) {
            return response()->json([
                'success' => false,
                'error_code' => 'FORBIDDEN',
                'message' => 'You are not assigned to this task.',
            ], 403);
        }

        $notes = TaskComment::with(['task', 'commenter', 'replies.commenter'])
            ->where('task_id', $task->id)
            ->whereNull('reply_note_id')
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'notes' => $notes,
        ]);
    }

    public function notesStore(Request $request, Task $task)
    {
        /** @var Member $member */
        $member = $request->user();
        $accessibleProjectIds = $this->accessibleProjectIds($member);
        $taskProjectId = $task->project_id ? (int) $task->project_id : null;

        if ($taskProjectId !== null && ! in_array($taskProjectId, $accessibleProjectIds, true)) {
            return response()->json([
                'success' => false,
                'error_code' => 'FORBIDDEN',
                'message' => 'Task belongs to a project you are not assigned to.',
            ], 403);
        }

        $assigned = TaskAssignment::query()
            ->where('task_id', $task->id)
            ->where('assigned_to', $member->id)
            ->exists();

        $isProjectAdmin = $taskProjectId !== null
            && $this->authz->hasAnyPermission($member, ['execution_task.manage', 'task.manage'], $taskProjectId);

        if (! $assigned && ! $isProjectAdmin) {
            return response()->json([
                'success' => false,
                'error_code' => 'FORBIDDEN',
                'message' => 'You are not assigned to this task.',
            ], 403);
        }

        $validated = $request->validate([
            'note' => ['required', 'string', 'max:2000'],
            'reply_note_id' => ['nullable', 'exists:task_comments,id'],
        ]);

        if (! empty($validated['reply_note_id'])) {
            $parent = TaskComment::query()->where('id', $validated['reply_note_id'])->first();
            if (! $parent || (int) $parent->task_id !== (int) $task->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid reply note.',
                ], 422);
            }
        }

        $note = TaskComment::create([
            'uuid' => (string) Str::uuid(),
            'task_id' => $task->id,
            'reply_note_id' => $validated['reply_note_id'] ?? null,
            'commented_by' => $member->id,
            'comment' => $validated['note'],
        ]);

        return response()->json([
            'success' => true,
            'note' => $note->load(['commenter', 'parent']),
        ]);
    }

    public function notesDestroy(Request $request, TaskComment $note)
    {
        $member = $request->user();

        if ((int) $note->commented_by !== (int) $member->id) {
            return response()->json([
                'success' => false,
                'error_code' => 'FORBIDDEN',
                'message' => 'You can only delete your own notes.',
            ], 403);
        }

        $note->delete();

        return response()->json([
            'success' => true,
            'message' => 'Note deleted.',
        ]);
    }
}
