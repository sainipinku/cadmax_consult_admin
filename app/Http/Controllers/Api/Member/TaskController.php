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

/**
 * @tags Member Tasks
 */
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

    /**
     * List Member Assigned Tasks
     *
     * Returns all unified tasks assigned to the authenticated member across accessible projects.
     */
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
                'checklistItems.completedBy',
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

        $rawDateInput = null;
        foreach (['date', 'assigned_date', 'target_date', 'task_date'] as $dateParamKey) {
            if ($request->filled($dateParamKey)) {
                $rawDateInput = trim((string) $request->input($dateParamKey));
                break;
            }
        }

        $isExplicitToday = in_array(strtolower((string) $request->input('filter')), ['today', 'current'], true)
            || in_array(strtolower((string) $request->input('filter_by')), ['today', 'current'], true)
            || $request->boolean('today')
            || (is_string($rawDateInput) && in_array(strtolower(trim($rawDateInput)), ['today', 'current'], true));

        $targetDateStr = null;
        if ($rawDateInput !== null && $rawDateInput !== '' && !$isExplicitToday) {
            try {
                if (preg_match('/^\d{1,2}\/\d{1,2}\/\d{4}$/', $rawDateInput)) {
                    $targetDateStr = \Illuminate\Support\Carbon::createFromFormat('d/m/Y', $rawDateInput)->toDateString();
                } elseif (preg_match('/^\d{1,2}-\d{1,2}-\d{4}$/', $rawDateInput)) {
                    $targetDateStr = \Illuminate\Support\Carbon::createFromFormat('d-m-Y', $rawDateInput)->toDateString();
                } else {
                    $targetDateStr = \Illuminate\Support\Carbon::parse($rawDateInput)->toDateString();
                }
            } catch (\Throwable) {
                $targetDateStr = null;
            }
        } elseif ($isExplicitToday) {
            $targetDateStr = now()->setTimezone('Asia/Kolkata')->toDateString();
        }

        if ($targetDateStr !== null) {
            $query->where(function ($q) use ($targetDateStr) {
                $q->where(function ($dateQ) use ($targetDateStr) {
                    $dateQ->whereDate('start_date', '<=', $targetDateStr)
                          ->whereDate('end_date', '>=', $targetDateStr);
                })
                ->orWhereDate('start_date', $targetDateStr)
                ->orWhereDate('end_date', $targetDateStr)
                ->orWhereDate('specific_date', $targetDateStr)
                ->orWhere(function ($createdQ) use ($targetDateStr) {
                    $createdQ->whereNull('start_date')
                             ->whereNull('end_date')
                             ->whereDate('created_at', $targetDateStr);
                });
            });
        }

        $perPage = (int) $request->input('per_page', 10);
        $tasks = $query->latest()->paginate(max(1, min($perPage, 50)))->withQueryString();

        return response()->json([
            'success' => true,
            'date' => $targetDateStr,
            'filter_date' => $targetDateStr,
            'target_date' => $targetDateStr,
            'tasks' => $tasks,
        ]);
    }

    /**
     * Get Task Detail
     *
     * Retrieves specific task info including creator, assigned dates, priority, and instance tracking.
     */
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

        $task->load([
            'creator',
            'checklistItems.completedBy',
            'surveyVisits.submission.reviewedBy',
            'instances' => function ($q) use ($member) {
                $q->where('assigned_to', $member->id)->latest();
            }
        ]);

        $latestVisit = $task->surveyVisits->sortByDesc('id')->first();
        $sub = $latestVisit?->submission;
        $visitNotes = is_array($latestVisit?->notes) ? $latestVisit->notes : (json_decode($latestVisit?->notes ?? '', true) ?: []);
        $isRevisionRequested = (
            $sub?->status === 5
            || ($latestVisit && (int) $latestVisit->status === 5)
            || $task->client_review_status === 'revision_requested'
            || !empty($visitNotes['revision_request'])
        );
        $memberReply = $sub?->member_reply ?? ($visitNotes['member_reply']['message'] ?? null);
        $isReplied = !empty($memberReply) || $task->client_review_status === 'revision_submitted';

        $revInfo = null;
        if ($isRevisionRequested || $latestVisit) {
            $managerName = $sub?->reviewedBy?->name ?? ($visitNotes['revision_request']['requested_by_name'] ?? 'Project Manager');
            $requestedAt = $sub?->reviewed_at?->toIso8601String() ?? ($visitNotes['revision_request']['requested_at'] ?? null);
            $requestedAtFormatted = $sub?->reviewed_at
                ? $sub->reviewed_at->setTimezone('Asia/Kolkata')->format('d M Y · h:i A')
                : ($visitNotes['revision_request']['requested_at_formatted'] ?? ($requestedAt ? date('d M Y · h:i A', strtotime($requestedAt)) : null));
            $repliedAt = $sub?->member_replied_at?->toIso8601String() ?? ($visitNotes['member_reply']['replied_at'] ?? null);
            $repliedAtFormatted = $sub?->member_replied_at
                ? $sub->member_replied_at->setTimezone('Asia/Kolkata')->format('d M Y · h:i A')
                : ($visitNotes['member_reply']['replied_at_formatted'] ?? ($repliedAt ? date('d M Y · h:i A', strtotime($repliedAt)) : null));

            $reviewStatus = 'pending';
            $statusLabel = 'Planned';
            if ($isRevisionRequested) {
                $reviewStatus = $isReplied ? 'revision_replied' : 'revision_requested';
                $statusLabel = $isReplied ? 'Revision Submitted' : 'Revision Requested';
            } elseif ($latestVisit && (int) $latestVisit->status === 4) {
                $reviewStatus = 'approved';
                $statusLabel = 'Approved';
            } elseif ($latestVisit && (int) $latestVisit->status === 3) {
                $reviewStatus = 'submitted';
                $statusLabel = 'Under Review';
            }

            $revInfo = [
                'has_revision' => $isRevisionRequested,
                'is_revision_requested' => $isRevisionRequested,
                'is_replied' => $isReplied,
                'review_status' => $reviewStatus,
                'status' => $reviewStatus,
                'status_label' => $statusLabel,
                'remarks' => $sub?->review_notes ?? ($visitNotes['revision_request']['remarks'] ?? null),
                'revision_remarks' => $sub?->review_notes ?? ($visitNotes['revision_request']['remarks'] ?? null),
                'manager_name' => $managerName,
                'requested_by' => $managerName,
                'requested_at' => $requestedAtFormatted,
                'member_reply' => $memberReply,
                'member_replied_at' => $repliedAtFormatted,
                'history' => $visitNotes['revision_history'] ?? [],
            ];
        }

        $taskData = $task->toArray();
        if ($revInfo) {
            $taskData['review_status'] = $revInfo['review_status'];
            $taskData['is_revision_requested'] = $revInfo['is_revision_requested'];
            $taskData['revision_remarks'] = $revInfo['revision_remarks'];
            $taskData['revision_requested_at'] = $revInfo['requested_at'];
            $taskData['revision_requested_by'] = $revInfo['requested_by'];
            $taskData['revision_reply'] = $revInfo['member_reply'];
            $taskData['revision_replied_at'] = $revInfo['member_replied_at'];
            $taskData['revision'] = $revInfo;
        }

        return response()->json([
            'success' => true,
            'task' => $taskData,
            'revision' => $revInfo,
        ]);
    }

    /**
     * List Task Notes & Comments
     *
     * Retrieves comment thread and activity notes for a specific task.
     */
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

    /**
     * Add Task Note / Comment
     *
     * Posts a new note or threaded reply on the specified task.
     */
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

    /**
     * Delete Task Note
     *
     * Deletes a note posted by the authenticated member.
     */
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
