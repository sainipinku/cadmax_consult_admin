<?php

namespace App\Services\Construction;

use App\Models\MemberRoleAssignment;
use App\Models\Project;
use App\Models\ProjectTeamMember;
use App\Models\ConstructionRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConstructionTeamAssignmentService
{
    public function __construct(
        protected readonly ConstructionActivityService $activityService
    ) {}

    /**
     * MemberRoleAssignment status:
     * 1 = active
     * 0 = inactive
     *
     * ProjectTeamMember status remains its existing string convention:
     * active / inactive
     */
    private const ROLE_ASSIGNMENT_ACTIVE = 1;
    private const ROLE_ASSIGNMENT_INACTIVE = 0;

    public function assign(
        Project $project,
        array $validated,
        ?Model $actor
    ): ProjectTeamMember {
        return DB::transaction(function () use ($project, $validated, $actor) {
            $roleId = !empty($validated['role_id'])
                ? (int) $validated['role_id']
                : null;

            if ($roleId !== null) {
                $this->ensureRoleAssignable($roleId);

                $this->ensureNoDuplicate(
                    $project->id,
                    (int) $validated['member_id'],
                    $roleId
                );
            }

            $teamStatus = $validated['status'] ?? 'active';

            $teamMember = ProjectTeamMember::create([
                'project_id' => $project->id,
                'member_id' => $validated['member_id'],
                'role_id' => $roleId,
                'assigned_from' => $validated['assigned_from'] ?? null,
                'assigned_to' => $validated['assigned_to'] ?? null,
                'assignment_scope' => $validated['assignment_scope'] ?? null,
                'is_primary' => (bool) ($validated['is_primary'] ?? false),
                'status' => $teamStatus,
                'assigned_by_type' => $actor ? $actor::class : null,
                'assigned_by_id' => $actor?->getKey(),
            ]);

            if ($roleId !== null) {
                $this->syncRoleAssignment(
                    memberId: (int) $validated['member_id'],
                    roleId: $roleId,
                    projectId: $project->id,
                    teamStatus: $teamStatus
                );
            }

            $this->activityService?->log(
                module: 'project_team',
                action: 'assigned',
                actor: $actor,
                reference: $teamMember,
                companyId: $project->company_id,
                projectId: $project->id,
                description: "Assigned member #{$teamMember->member_id} to project {$project->name}",
                meta: [
                    'member_id' => $teamMember->member_id,
                    'role_id' => $roleId,
                    'status' => $teamStatus,
                    'is_primary' => $teamMember->is_primary,
                ]
            );

            return $teamMember;
        });
    }

    public function update(
        Project $project,
        ProjectTeamMember $teamMember,
        array $validated,
        ?Model $actor
    ): ProjectTeamMember {
        return DB::transaction(function () use (
            $project,
            $teamMember,
            $validated,
            $actor
        ) {
            $oldMemberId = (int) $teamMember->member_id;

            $oldRoleId = $teamMember->role_id
                ? (int) $teamMember->role_id
                : null;

            $newMemberId = (int) $validated['member_id'];

            $newRoleId = !empty($validated['role_id'])
                ? (int) $validated['role_id']
                : null;

            if ($newRoleId !== null) {
                $this->ensureRoleAssignable($newRoleId);

                $this->ensureNoDuplicate(
                    $project->id,
                    $newMemberId,
                    $newRoleId,
                    $teamMember->id
                );
            }

            $newTeamStatus = $validated['status'] ?? $teamMember->status;

            $teamMember->update([
                'member_id' => $newMemberId,
                'role_id' => $newRoleId,
                'assigned_from' => $validated['assigned_from'] ?? null,
                'assigned_to' => $validated['assigned_to'] ?? null,
                'assignment_scope' => $validated['assignment_scope'] ?? null,
                'is_primary' => (bool) ($validated['is_primary'] ?? false),
                'status' => $newTeamStatus,
            ]);

            /*
             * If the member or role changed, deactivate the old
             * MemberRoleAssignment without affecting unrelated roles.
             */
            if (
                $oldRoleId !== null
                && (
                    $oldRoleId !== $newRoleId
                    || $oldMemberId !== $newMemberId
                )
            ) {
                $this->deactivateRoleAssignment(
                    $oldMemberId,
                    $oldRoleId,
                    $project->id
                );
            }

            /*
             * Create/reactivate the new authorization assignment.
             * MemberRoleAssignment.status is always stored as integer:
             * 1 = active, 0 = inactive.
             */
            if ($newRoleId !== null) {
                $this->syncRoleAssignment(
                    memberId: $newMemberId,
                    roleId: $newRoleId,
                    projectId: $project->id,
                    teamStatus: $newTeamStatus
                );
            }

            $this->activityService?->log(
                module: 'project_team',
                action: 'updated',
                actor: $actor,
                reference: $teamMember,
                companyId: $project->company_id,
                projectId: $project->id,
                description: "Updated assignment for member #{$newMemberId} on project {$project->name}",
                meta: [
                    'member_id' => $newMemberId,
                    'role_id' => $newRoleId,
                    'status' => $newTeamStatus,
                ]
            );

            return $teamMember->fresh();
        });
    }

    /**
     * Toggle the status of a role-bearing team assignment.
     *
     * ProjectTeamMember continues using its existing string status.
     * MemberRoleAssignment is synchronized using integer status.
     */
    public function toggleStatus(
        Project $project,
        ProjectTeamMember $teamMember,
        ?Model $actor
    ): ProjectTeamMember {
        return DB::transaction(function () use (
            $project,
            $teamMember,
            $actor
        ) {
            $newTeamStatus = $teamMember->status === 'active'
                ? 'inactive'
                : 'active';

            $teamMember->update([
                'status' => $newTeamStatus,
            ]);

            if ($teamMember->role_id !== null) {
                $this->syncRoleAssignment(
                    memberId: (int) $teamMember->member_id,
                    roleId: (int) $teamMember->role_id,
                    projectId: $project->id,
                    teamStatus: $newTeamStatus
                );
            }

            $this->activityService?->log(
                module: 'project_team',
                action: 'status_toggled',
                actor: $actor,
                reference: $teamMember,
                companyId: $project->company_id,
                projectId: $project->id,
                description: "Toggled status of team member #{$teamMember->member_id} to {$newTeamStatus}",
                meta: [
                    'member_id' => $teamMember->member_id,
                    'status' => $newTeamStatus,
                ]
            );

            return $teamMember->fresh();
        });
    }

    /**
     * Remove a team assignment and deactivate the corresponding
     * MemberRoleAssignment.
     *
     * Only the specific assignment is affected.
     */
    public function remove(
        Project $project,
        ProjectTeamMember $teamMember,
        ?Model $actor
    ): void {
        DB::transaction(function () use ($project, $teamMember, $actor) {
            $memberId = (int) $teamMember->member_id;

            $roleId = $teamMember->role_id
                ? (int) $teamMember->role_id
                : null;

            $teamMember->delete();

            if ($roleId !== null) {
                $this->deactivateRoleAssignment(
                    $memberId,
                    $roleId,
                    $project->id
                );
            }

            $this->activityService?->log(
                module: 'project_team',
                action: 'removed',
                actor: $actor,
                reference: $teamMember,
                companyId: $project->company_id,
                projectId: $project->id,
                description: "Removed member #{$memberId} from project {$project->name}",
                meta: [
                    'member_id' => $memberId,
                    'role_id' => $roleId,
                ]
            );
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Private helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Ensure the construction role exists, is active and not soft-deleted.
     */
    private function ensureRoleAssignable(int $roleId): void
    {
        $role = ConstructionRole::withTrashed()->find($roleId);

        if (
            !$role
            || $role->trashed()
            || $role->status !== 'active'
        ) {
            throw ValidationException::withMessages([
                'role_id' => 'The selected role is not active or no longer exists.',
            ]);
        }
    }

    /**
     * Enforce:
     * (project_id, member_id, role_id)
     *
     * Same member + project + different role = allowed.
     * Same member + project + same role = rejected.
     */
    private function ensureNoDuplicate(
        int $projectId,
        int $memberId,
        int $roleId,
        ?int $ignoreId = null
    ): void {
        $query = ProjectTeamMember::query()
            ->forAssignment(
                $projectId,
                $memberId,
                $roleId
            );

        if ($ignoreId !== null) {
            $query->where('id', '!=', $ignoreId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'role_id' => 'This member is already assigned this role on this project.',
            ]);
        }
    }

    /**
     * Synchronize MemberRoleAssignment status with the team assignment.
     *
     * MemberRoleAssignment uses:
     * 1 = active
     * 0 = inactive
     *
     * ProjectTeamMember keeps its existing:
     * active / inactive
     */
    private function syncRoleAssignment(
        int $memberId,
        int $roleId,
        int $projectId,
        string $teamStatus
    ): void {
        $roleAssignmentStatus =
            $this->mapTeamStatusToRoleAssignmentStatus($teamStatus);

        MemberRoleAssignment::updateOrCreate(
            [
                'member_id' => $memberId,
                'role_id' => $roleId,
                'project_id' => $projectId,
            ],
            [
                'status' => $roleAssignmentStatus,
            ]
        );
    }

    /**
     * Convert ProjectTeamMember string status to
     * MemberRoleAssignment integer status.
     */
    private function mapTeamStatusToRoleAssignmentStatus(
        string $teamStatus
    ): int {
        return $teamStatus === 'active'
            ? self::ROLE_ASSIGNMENT_ACTIVE
            : self::ROLE_ASSIGNMENT_INACTIVE;
    }

    /**
     * Preserve the MemberRoleAssignment row for history,
     * but remove its authorization effect.
     */
    private function deactivateRoleAssignment(
        int $memberId,
        int $roleId,
        int $projectId
    ): void {
        MemberRoleAssignment::query()
            ->where('member_id', $memberId)
            ->where('role_id', $roleId)
            ->where('project_id', $projectId)
            ->update([
                'status' => self::ROLE_ASSIGNMENT_INACTIVE,
            ]);
    }
}