<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Client;
use App\Models\Company;
use App\Models\Member;
use App\Models\Project;
use App\Models\SuperAdmin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait ResolvesConstructionActor
{
    protected function constructionActor(): ?Model
    {
        foreach (['superadmin', 'admin', 'member', 'callingteam'] as $guard) {
            if (Auth::guard($guard)->check()) {
                return Auth::guard($guard)->user();
            }
        }

        return Auth::user() ?: request()?->user();
    }

    /**
     * Safely resolve the corresponding member ID for the given construction actor.
     * Guaranteed to return a valid members.id or null, strictly preventing foreign key constraint violations.
     */
    protected function resolveActorMemberId(?Model $actor = null): ?int
    {
        $actor = $actor ?? $this->constructionActor();

        if (!$actor) {
            return null;
        }

        if ($actor instanceof \App\Models\Member) {
            return (int) $actor->getKey();
        }

        if (!empty($actor->email)) {
            $memberId = \App\Models\Member::where('email', $actor->email)->value('id');
            if ($memberId) {
                return (int) $memberId;
            }
        }

        return null;
    }

    protected function isActingInNonAdminRole(?Model $actor = null): bool
    {
        $resolved = $this->constructionActor();
        if (
            $actor !== null
            && $resolved !== null
            && ! ($actor->getMorphClass() === $resolved->getMorphClass() && (int) $actor->getKey() === (int) $resolved->getKey())
        ) {
            return false;
        }

        $activeRole = strtolower(trim((string) (session('active_role') ?? request()?->query('role') ?? '')));
        if ($activeRole === '') {
            return false;
        }

        return ! in_array($activeRole, [
            'super_admin',
            'superadmin',
            'crm_super_admin',
            'project_owner',
            'admin',
            'project_admin',
        ], true);
    }

    protected function memberHasCompanyWideAdminRole(\App\Models\Member $member): bool
    {
        if (isset($member->is_project_owner) && (int) $member->is_project_owner === 1) {
            return true;
        }

        $memberSlug = strtolower(trim((string) ($member->slug ?? '')));
        if (in_array($memberSlug, ['admin', 'super-admin', 'super_admin', 'superadmin'], true)) {
            return true;
        }

        $adminSlugs = ['admin', 'project_admin', 'super_admin', 'superadmin', 'super-admin'];

        // Active company-wide (project_id IS NULL) role assignments take precedence cleanly over project-scoped roles
        $hasCompanyWideAdmin = \App\Models\MemberRoleAssignment::query()
            ->where('member_id', $member->getKey())
            ->where('status', 1)
            ->whereNull('project_id')
            ->whereHas('role', function ($q) use ($adminSlugs) {
                $q->where('status', 'active')
                    ->whereNull('deleted_at')
                    ->whereIn('slug', $adminSlugs);
            })
            ->exists();

        if ($hasCompanyWideAdmin) {
            return true;
        }

        $rawRoles = is_array($member->roles)
            ? $member->roles
            : (json_decode((string) $member->getRawOriginal('roles'), true) ?: []);

        if (! empty($rawRoles)) {
            $numericIds = array_values(array_filter(array_map('intval', array_filter($rawRoles, 'is_numeric'))));
            if (! empty($numericIds)) {
                $hasLegacyAdminId = \App\Models\Role::whereIn('id', $numericIds)
                    ->whereIn('slug', $adminSlugs)
                    ->exists();
                if ($hasLegacyAdminId) {
                    return true;
                }
            }

            foreach ($rawRoles as $r) {
                if (is_string($r) && in_array(strtolower(trim($r)), $adminSlugs, true)) {
                    return true;
                }
            }
        }

        return false;
    }

    protected function isCompanyAdminActor(?Model $actor = null): bool
    {
        $actor = $actor ?? $this->constructionActor();

        if (!$actor || $this->isActingInNonAdminRole($actor)) {
            return false;
        }

        if ($actor instanceof \App\Models\Member) {
            return $this->memberHasCompanyWideAdminRole($actor);
        }

        return false;
    }

    protected function isFullAdminActor(?Model $actor = null): bool
    {
        $actor = $actor ?? $this->constructionActor();

        if (!$actor || $this->isActingInNonAdminRole($actor)) {
            return false;
        }

        if (session()->has('impersonator')) {
            if ($actor instanceof \App\Models\SuperAdmin) {
                return true;
            }
            if ($actor instanceof \App\Models\Member) {
                return $this->memberHasCompanyWideAdminRole($actor);
            }
            return false;
        }

        if ($actor instanceof \App\Models\SuperAdmin) {
            return true;
        }

        if ($actor instanceof \App\Models\Member) {
            return $this->memberHasCompanyWideAdminRole($actor);
        }

        return false;
    }

    protected function isProjectOwner(?Model $actor = null): bool
    {
        $actor = $actor ?? $this->constructionActor();

        if (!$actor) {
            return false;
        }

        if (session()->has('impersonator')) {
            if ($actor instanceof \App\Models\SuperAdmin) {
                return empty($actor->company_id) || (!empty($actor->is_project_owner));
            }
            if ($actor instanceof \App\Models\Member) {
                return (isset($actor->is_project_owner) && (int) $actor->is_project_owner === 1);
            }
            return false;
        }

        if ($actor instanceof \App\Models\SuperAdmin) {
            return empty($actor->company_id) || (!empty($actor->is_project_owner));
        }

        if ($actor instanceof \App\Models\Member) {
            return (isset($actor->is_project_owner) && (int) $actor->is_project_owner === 1);
        }

        return false;
    }

    protected function getOtherSuperAdminCompanyIds(SuperAdmin $actor): Collection
    {
        $otherCompanyIds = SuperAdmin::where('id', '!=', $actor->id)
            ->whereNotNull('company_id')
            ->pluck('company_id')
            ->map(fn ($id) => (int) $id);

        $createdByOtherSuperAdminIds = Company::whereIn('created_by_type', [SuperAdmin::class, 'super_admin'])
            ->whereNotNull('created_by_id')
            ->where('created_by_id', '!=', $actor->id)
            ->pluck('id')
            ->map(fn ($id) => (int) $id);

        $otherSuperAdminIds = SuperAdmin::where('id', '!=', $actor->id)->pluck('id');
        $otherDirectMemberIds = $otherSuperAdminIds->isNotEmpty()
            ? Member::whereIn('created_by', $otherSuperAdminIds)->pluck('id')
            : collect();

        $createdByOtherMemberCompanyIds = $otherDirectMemberIds->isNotEmpty()
            ? Company::whereIn('created_by_type', [Member::class, 'member'])
                ->whereIn('created_by_id', $otherDirectMemberIds)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
            : collect();

        $ownAssignedCompanyId = !empty($actor->company_id) ? (int) $actor->company_id : null;

        return $otherCompanyIds
            ->merge($createdByOtherSuperAdminIds)
            ->merge($createdByOtherMemberCompanyIds)
            ->unique()
            ->reject(fn (int $id) => $ownAssignedCompanyId !== null && $id === $ownAssignedCompanyId)
            ->values();
    }

    protected function getOwnedCompanyIds(?Model $actor = null): Collection
    {
        $actor = $actor ?? $this->constructionActor();

        if (!$actor) {
            return collect();
        }

        if ($actor instanceof SuperAdmin) {
            $excludedCompanyIds = $this->getOtherSuperAdminCompanyIds($actor);
            $companyIds = collect();

            if (!empty($actor->company_id)) {
                $companyIds->push((int) $actor->company_id);
            }

            $directCompanyIds = Company::whereIn('created_by_type', [SuperAdmin::class, 'super_admin'])
                ->where('created_by_id', $actor->id)
                ->pluck('id')
                ->map(fn ($id) => (int) $id);

            $directMemberIds = Member::where('created_by', $actor->id)->pluck('id');
            $memberCompanyIds = $directMemberIds->isNotEmpty()
                ? Company::whereIn('created_by_type', [Member::class, 'member'])
                    ->whereIn('created_by_id', $directMemberIds)
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                : collect();

            $ownAssignedCompanyId = !empty($actor->company_id) ? (int) $actor->company_id : null;

            return $companyIds
                ->merge($directCompanyIds)
                ->merge($memberCompanyIds)
                ->unique()
                ->reject(fn (int $id) => $excludedCompanyIds->contains($id) && $id !== $ownAssignedCompanyId)
                ->values();
        }

        if ($actor instanceof Member) {
            if ($this->isCompanyAdminActor($actor)) {
                $companyId = $this->getAccessibleCompanyId($actor);
                return $companyId ? collect([(int) $companyId]) : collect();
            }

            return Project::whereIn('id', $this->getAccessibleProjectIds($actor))
                ->whereNotNull('company_id')
                ->pluck('company_id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();
        }

        return collect();
    }

    protected function getOwnedMemberIds(?Model $actor = null, ?Collection $ownCompanyIds = null): Collection
    {
        $actor = $actor ?? $this->constructionActor();

        if (!$actor) {
            return collect();
        }

        if ($actor instanceof SuperAdmin) {
            $ownCompanyIds = $ownCompanyIds ?? $this->getOwnedCompanyIds($actor);
            $coOwnerSuperAdminIds = collect([(int) $actor->id]);

            if ($ownCompanyIds->isNotEmpty()) {
                $coOwnerSuperAdminIds = $coOwnerSuperAdminIds
                    ->merge(SuperAdmin::whereIn('company_id', $ownCompanyIds)->pluck('id')->map(fn ($id) => (int) $id))
                    ->unique()
                    ->values();
            }

            $companyNames = $ownCompanyIds->isNotEmpty()
                ? Company::whereIn('id', $ownCompanyIds)->pluck('name')->filter()->unique()->values()
                : collect();

            $hasMemberCompanyId = Schema::hasColumn('members', 'company_id');

            $memberQuery = Member::query()->where(function ($q) use ($coOwnerSuperAdminIds, $ownCompanyIds, $companyNames, $hasMemberCompanyId) {
                $q->whereIn('created_by', $coOwnerSuperAdminIds);

                if ($hasMemberCompanyId && $ownCompanyIds->isNotEmpty()) {
                    $q->orWhereIn('company_id', $ownCompanyIds);
                }

                if ($companyNames->isNotEmpty()) {
                    $q->orWhere(function ($cq) use ($companyNames, $coOwnerSuperAdminIds) {
                        $cq->whereIn('company_name', $companyNames)
                           ->where(function ($sub) use ($coOwnerSuperAdminIds) {
                               $sub->whereNull('created_by')
                                   ->orWhereIn('created_by', $coOwnerSuperAdminIds);
                           });
                    });
                }
            });

            return $memberQuery->pluck('id')->map(fn ($id) => (int) $id)->unique()->values();
        }

        if ($actor instanceof Member) {
            if ($this->isCompanyAdminActor($actor)) {
                $companyId = $this->getAccessibleCompanyId($actor);
                $creatorSuperAdminIds = $companyId
                    ? SuperAdmin::where('company_id', $companyId)->pluck('id')->all()
                    : ($actor->created_by ? [(int) $actor->created_by] : []);
                $companyName = $companyId ? Company::where('id', $companyId)->value('name') : $actor->company_name;

                return Member::query()
                    ->where(function ($q) use ($actor, $creatorSuperAdminIds, $companyName) {
                        $q->where('id', $actor->getKey())
                          ->orWhere('created_by', $actor->getKey());
                        if (!empty($creatorSuperAdminIds)) {
                            $q->orWhereIn('created_by', $creatorSuperAdminIds);
                        }
                        if (!empty($companyName)) {
                            $q->orWhere('company_name', $companyName);
                        }
                    })
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->unique()
                    ->values();
            }

            return \App\Models\ProjectTeamMember::whereIn('project_id', $this->getAccessibleProjectIds($actor))
                ->pluck('member_id')
                ->push($actor->getKey())
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();
        }

        return collect();
    }

    protected function getAccessibleClientIds(?Model $actor = null): Collection
    {
        $actor = $actor ?? $this->constructionActor();

        if (!$actor) {
            return collect();
        }

        if ($actor instanceof SuperAdmin) {
            $ownCompanyIds = $this->getOwnedCompanyIds($actor);
            $excludedCompanyIds = $this->getOtherSuperAdminCompanyIds($actor);
            $ownMemberIds = $this->getOwnedMemberIds($actor, $ownCompanyIds);
            $coOwnerSuperAdminIds = $ownCompanyIds->isNotEmpty()
                ? SuperAdmin::whereIn('company_id', $ownCompanyIds)->pluck('id')->push($actor->id)->map(fn ($id) => (int) $id)->unique()->values()
                : collect([(int) $actor->id]);

            $query = Client::query();

            if ($excludedCompanyIds->isNotEmpty()) {
                $query->where(function ($q) use ($excludedCompanyIds) {
                    $q->whereNull('company_id')
                      ->orWhereNotIn('company_id', $excludedCompanyIds);
                });
            }

            $allowLegacyUnowned = empty($actor->company_id);

            $query->where(function ($q) use ($ownCompanyIds, $coOwnerSuperAdminIds, $ownMemberIds, $allowLegacyUnowned) {
                if ($ownCompanyIds->isNotEmpty()) {
                    $q->orWhereIn('company_id', $ownCompanyIds);
                }
                $q->orWhere(function ($sq) use ($coOwnerSuperAdminIds) {
                    $sq->whereIn('created_by_type', [SuperAdmin::class, 'super_admin'])
                       ->whereIn('created_by_id', $coOwnerSuperAdminIds);
                });
                if ($ownMemberIds->isNotEmpty()) {
                    $q->orWhere(function ($mq) use ($ownMemberIds) {
                        $mq->whereIn('created_by_type', [Member::class, 'member'])
                           ->whereIn('created_by_id', $ownMemberIds);
                    });
                }
                if ($allowLegacyUnowned) {
                    $q->orWhere(function ($lq) {
                        $lq->whereNull('created_by_id')
                           ->where(function ($cq) {
                               $cq->whereNull('company_id')
                                  ->orWhereHas('company', fn ($cSub) => $cSub->whereNull('created_by_id'));
                           });
                    });
                }
            });

            return $query->pluck('id')->map(fn ($id) => (int) $id)->values();
        }

        if ($actor instanceof Member) {
            if ($this->isCompanyAdminActor($actor)) {
                $companyId = $this->getAccessibleCompanyId($actor);
                $query = Client::query();
                if ($companyId) {
                    $query->where('company_id', $companyId);
                } else {
                    $query->where('created_by_type', $actor::class)
                          ->where('created_by_id', $actor->getKey());
                }
                return $query->pluck('id')->map(fn ($id) => (int) $id)->values();
            }

            return Project::whereIn('id', $this->getAccessibleProjectIds($actor))
                ->whereNotNull('client_id')
                ->pluck('client_id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();
        }

        return collect();
    }

    protected function ensureProjectAccess(int|Project $project, ?Model $actor = null): Project
    {
        $actor = $actor ?? $this->constructionActor();
        $projectModel = $project instanceof Project ? $project : Project::findOrFail($project);

        abort_unless(
            $this->getAccessibleProjectIds($actor)->contains((int) $projectModel->id),
            403,
            'Unauthorized. You do not have access to this project.'
        );

        return $projectModel;
    }

    protected function ensureCompanyAccess(int|Company $company, ?Model $actor = null): Company
    {
        $actor = $actor ?? $this->constructionActor();
        $companyModel = $company instanceof Company ? $company : Company::findOrFail($company);

        abort_unless(
            $this->getOwnedCompanyIds($actor)->contains((int) $companyModel->id),
            403,
            'Unauthorized. You do not have access to this company.'
        );

        return $companyModel;
    }

    protected function getAccessibleProjectIds(?Model $actor = null): \Illuminate\Support\Collection
    {
        $actor = $actor ?? $this->constructionActor();

        if (!$actor) {
            return collect();
        }

        // 1. Any SuperAdmin (including Root Project Owner and company SuperAdmins): scoped strictly to their own account/companies
        if ($actor instanceof SuperAdmin) {
            $ownCompanyIds = $this->getOwnedCompanyIds($actor);
            $excludedCompanyIds = $this->getOtherSuperAdminCompanyIds($actor);
            $ownMemberIds = $this->getOwnedMemberIds($actor, $ownCompanyIds);
            $coOwnerSuperAdminIds = !empty($actor->company_id) && $ownCompanyIds->isNotEmpty()
                ? SuperAdmin::whereIn('company_id', $ownCompanyIds)->pluck('id')->push($actor->id)->map(fn ($id) => (int) $id)->unique()->values()
                : collect([(int) $actor->id]);

            $query = Project::query();

            if ($excludedCompanyIds->isNotEmpty()) {
                $query->where(function ($q) use ($excludedCompanyIds) {
                    $q->whereNull('company_id')
                      ->orWhereNotIn('company_id', $excludedCompanyIds);
                });
            }

            $allowLegacyUnowned = empty($actor->company_id);

            $query->where(function ($q) use ($ownCompanyIds, $coOwnerSuperAdminIds, $ownMemberIds, $allowLegacyUnowned) {
                if ($ownCompanyIds->isNotEmpty()) {
                    $q->orWhereIn('company_id', $ownCompanyIds);
                }

                $q->orWhere(function ($sq) use ($coOwnerSuperAdminIds) {
                    $sq->whereIn('created_by_type', [SuperAdmin::class, 'super_admin'])
                       ->whereIn('created_by_id', $coOwnerSuperAdminIds);
                });

                if ($ownMemberIds->isNotEmpty()) {
                    $q->orWhere(function ($mq) use ($ownMemberIds) {
                        $mq->whereIn('created_by_type', [Member::class, 'member'])
                           ->whereIn('created_by_id', $ownMemberIds);
                    });
                }

                if ($allowLegacyUnowned) {
                    $q->orWhere(function ($lq) {
                        $lq->whereNull('created_by_id')
                           ->where(function ($cq) {
                               $cq->whereNull('company_id')
                                  ->orWhereHas('company', fn ($cSub) => $cSub->whereNull('created_by_id'));
                           });
                    });
                }
            });

            $companyProjects = $query->pluck('id')->map(fn ($id) => (int) $id)->values();

            if ($this->isActingInNonAdminRole($actor)) {
                $linkedMemberId = $this->resolveActorMemberId($actor);
                if (!$linkedMemberId) {
                    return collect();
                }

                $memberAssignedIds = $this->getAssignedProjectIdsForMemberId($linkedMemberId);

                return $companyProjects->intersect($memberAssignedIds)->values();
            }

            return $companyProjects;
        }

        // 3. Company Admin (Member with company-wide Admin role, not switched to a worker role): sees ALL projects of that company
        if ($this->isCompanyAdminActor($actor)) {
            $companyId = $this->getAccessibleCompanyId($actor);
            $query = \App\Models\Project::query();
            if ($companyId) {
                $query->where('company_id', $companyId);
            } else {
                $query->where('created_by_type', $actor::class)
                      ->where('created_by_id', $actor->getKey());
            }
            return $query->pluck('id');
        }

        // 4. Regular Member / Employee (or Admin switched into a worker role): ONLY sees projects where added to project team / assigned
        return $this->getAssignedProjectIdsForMemberId((int) $actor->getKey());
    }

    protected function getAssignedProjectIdsForMemberId(int $memberId): \Illuminate\Support\Collection
    {
        $assignedProjectIds = \App\Models\ProjectTeamMember::where('member_id', $memberId)
            ->where(function ($q) {
                $q->whereNull('status')->orWhereNotIn('status', ['inactive', 'removed', 'disabled']);
            })
            ->pluck('project_id')
            ->merge(
                \App\Models\MemberRoleAssignment::where('member_id', $memberId)
                    ->where('status', 1)
                    ->whereNotNull('project_id')
                    ->pluck('project_id')
            )
            ->merge(
                \App\Models\ExecutionTask::where('supervisor_member_id', $memberId)
                    ->whereNotNull('project_id')
                    ->pluck('project_id')
            )
            ->merge(
                \Illuminate\Support\Facades\DB::table('construction_execution_task_assignees')
                    ->where('member_id', $memberId)
                    ->whereNotNull('project_id')
                    ->pluck('project_id')
            )
            ->merge(
                \Illuminate\Support\Facades\DB::table('task_assignments')
                    ->where('assigned_to', $memberId)
                    ->whereNotNull('project_id')
                    ->pluck('project_id')
            )
            ->merge(
                \App\Models\Task::where(function ($q) use ($memberId) {
                    $q->where('member_id', $memberId)
                      ->orWhere('assigned_supervisor_member_id', $memberId);
                })
                    ->whereNotNull('project_id')
                    ->pluck('project_id')
            )
            ->merge(
                \Illuminate\Support\Facades\DB::table('construction_survey_plan_members')
                    ->join('construction_survey_plans', 'construction_survey_plans.id', '=', 'construction_survey_plan_members.survey_plan_id')
                    ->where('construction_survey_plan_members.member_id', $memberId)
                    ->whereNotNull('construction_survey_plans.project_id')
                    ->pluck('construction_survey_plans.project_id')
            )
            ->merge(
                \Illuminate\Support\Facades\DB::table('construction_drafting_jobs')
                    ->where('assigned_to_member_id', $memberId)
                    ->whereNotNull('project_id')
                    ->pluck('project_id')
            )
            ->merge(
                \Illuminate\Support\Facades\DB::table('construction_drawing_approvals')
                    ->where('assigned_to_type', \App\Models\Member::class)
                    ->where('assigned_to_id', $memberId)
                    ->whereNotNull('project_id')
                    ->pluck('project_id')
            )
            ->merge(
                \App\Models\VehicleAssignment::where('driver_member_id', $memberId)
                    ->where('status', 'active')
                    ->whereNotNull('project_id')
                    ->pluck('project_id')
            )
            ->merge(
                \Illuminate\Support\Facades\DB::table('construction_equipment_allocations')
                    ->where('assigned_to_member_id', $memberId)
                    ->where('status', 'active')
                    ->whereNotNull('project_id')
                    ->pluck('project_id')
            )
            ->unique()
            ->filter()
            ->values();

        if ($assignedProjectIds->isEmpty()) {
            return collect();
        }

        return \App\Models\Project::whereIn('id', $assignedProjectIds)->pluck('id');
    }

    protected function getAccessibleCompanyId(?Model $actor = null): ?int
    {
        $actor = $actor ?? $this->constructionActor();

        if (!$actor) {
            return null;
        }

        if ($actor instanceof \App\Models\SuperAdmin) {
            return $actor->company_id ? (int) $actor->company_id : null;
        }

        if ($actor instanceof \App\Models\Member) {
            if (!empty($actor->company_id)) {
                return (int) $actor->company_id;
            }
            if ($actor->created_by) {
                $creator = \App\Models\SuperAdmin::find($actor->created_by);
                if ($creator && $creator->company_id) {
                    return (int) $creator->company_id;
                }
            }
            if (!empty($actor->company_name)) {
                $company = \App\Models\Company::where('name', $actor->company_name)
                    ->orWhere('legal_name', $actor->company_name)
                    ->first();
                if ($company) {
                    return (int) $company->id;
                }
            }
        }

        return null;
    }
}
