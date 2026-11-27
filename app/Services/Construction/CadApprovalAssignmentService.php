<?php

namespace App\Services\Construction;

use App\Enums\ClientReviewStatus;
use App\Models\Company;
use App\Models\ConstructionRole;
use App\Models\DraftingJob;
use App\Models\DrawingApproval;
use App\Models\DrawingRevision;
use App\Models\ExecutionTask;
use App\Models\Member;
use App\Models\MemberRoleAssignment;
use App\Models\Project;
use App\Models\Role;
use App\Models\SuperAdmin;
use App\Models\SurveySubmission;
use App\Models\SurveyVisit;
use App\Models\Task;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class CadApprovalAssignmentService
{
    public const ADMIN_ROLE_SLUGS = ['admin', 'project_admin', 'super_admin', 'superadmin'];

    public function __construct(
        private readonly ConstructionAuthorizationService $authorization,
        private readonly ConstructionActivityService $activityService
    ) {
    }

    /**
     * Return all active Admin and Super Admin users belonging strictly to the project's company.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getCompanyAdminApprovers(Project $project): array
    {
        $companyId = (int) ($project->company_id ?? 0);
        if ($companyId <= 0) {
            return [];
        }

        $company = $project->relationLoaded('company') ? $project->company : Company::find($companyId);
        $companyNames = array_values(array_unique(array_filter([
            $company?->name ? trim((string) $company->name) : null,
            $company?->legal_name ? trim((string) $company->legal_name) : null,
        ])));

        $approvers = [];
        $seenKeys = [];
        $seenSuperAdminEmails = [];

        // 1. Active company SuperAdmins
        $superAdmins = SuperAdmin::query()
            ->where('company_id', $companyId)
            ->where(function ($q) {
                $q->where('status', 1)->orWhereNull('status');
            })
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'company_id', 'roles', 'status']);

        $companySuperAdminIds = [];
        foreach ($superAdmins as $sa) {
            $companySuperAdminIds[] = (int) $sa->id;
            $key = 'superadmin:' . $sa->id;
            $emailLower = strtolower(trim((string) ($sa->email ?? '')));
            if ($emailLower !== '') {
                $seenSuperAdminEmails[$emailLower] = true;
            }
            $seenKeys[$key] = true;
            $approvers[] = [
                'key' => $key,
                'type' => SuperAdmin::class,
                'id' => (int) $sa->id,
                'name' => $sa->name,
                'email' => (string) ($sa->email ?? ''),
                'role_label' => 'Super Admin',
                'guard' => 'superadmin',
            ];
        }

        // 2. Members explicitly assigned to a company-scoped ConstructionRole that is an admin role
        $companyAdminScopedAssignments = MemberRoleAssignment::with('role')
            ->where('status', 1)
            ->whereHas('role', function ($q) use ($companyId) {
                $q->where('company_id', $companyId)
                    ->where('status', 'active')
                    ->whereNull('deleted_at')
                    ->whereIn('slug', self::ADMIN_ROLE_SLUGS);
            })
            ->get(['member_id', 'role_id', 'project_id']);

        $scopedMemberRoleMap = [];
        foreach ($companyAdminScopedAssignments as $assignment) {
            $mId = (int) $assignment->member_id;
            $slug = strtolower((string) ($assignment->role?->slug ?? ''));
            if ($mId > 0 && $slug !== '') {
                $scopedMemberRoleMap[$mId][] = $slug;
            }
        }
        $scopedAdminMemberIds = array_keys($scopedMemberRoleMap);

        // 3. Company Members matching company ownership / company_name / scoped admin assignment
        $adminSystemRoleMap = Role::whereIn('slug', self::ADMIN_ROLE_SLUGS)
            ->pluck('slug', 'id')
            ->all();

        $companyMembers = Member::query()
            ->where('status', 1)
            ->where(function ($q) use ($companySuperAdminIds, $companyNames, $scopedAdminMemberIds) {
                $hasClause = false;
                if (! empty($companySuperAdminIds)) {
                    $q->whereIn('created_by', $companySuperAdminIds);
                    $hasClause = true;
                }
                if (! empty($companyNames)) {
                    $hasClause
                        ? $q->orWhereIn('company_name', $companyNames)
                        : $q->whereIn('company_name', $companyNames);
                    $hasClause = true;
                }
                if (! empty($scopedAdminMemberIds)) {
                    $hasClause
                        ? $q->orWhereIn('id', $scopedAdminMemberIds)
                        : $q->whereIn('id', $scopedAdminMemberIds);
                    $hasClause = true;
                }
                if (! $hasClause) {
                    $q->whereRaw('1 = 0');
                }
            })
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'roles', 'company_name', 'created_by']);

        $memberIds = $companyMembers->pluck('id')->all();
        $globalOrCompanyAssignments = ! empty($memberIds)
            ? MemberRoleAssignment::with('role')
                ->whereIn('member_id', $memberIds)
                ->where('status', 1)
                ->whereHas('role', function ($q) use ($companyId) {
                    $q->where('status', 'active')
                        ->whereNull('deleted_at')
                        ->whereIn('slug', self::ADMIN_ROLE_SLUGS)
                        ->where(function ($cq) use ($companyId) {
                            $cq->where('company_id', $companyId)->orWhereNull('company_id');
                        });
                })
                ->get(['member_id', 'role_id', 'project_id'])
                ->groupBy('member_id')
            : collect();

        foreach ($companyMembers as $member) {
            // Skip mirror member rows that duplicate an already-listed company SuperAdmin email
            $emailLower = strtolower(trim((string) ($member->email ?? '')));
            if ($emailLower !== '' && isset($seenSuperAdminEmails[$emailLower])) {
                continue;
            }

            // Ensure company isolation if member explicitly carries another company_name
            $memberCompanyName = trim((string) ($member->company_name ?? ''));
            if ($memberCompanyName !== '' && ! empty($companyNames) && ! in_array($memberCompanyName, $companyNames, true) && ! isset($scopedMemberRoleMap[(int) $member->id])) {
                continue;
            }

            $adminSlugs = $scopedMemberRoleMap[(int) $member->id] ?? [];

            foreach ($globalOrCompanyAssignments->get($member->id, collect()) as $assignment) {
                if ($assignment->project_id && (int) $assignment->project_id !== (int) $project->id) {
                    continue;
                }
                $slug = strtolower((string) ($assignment->role?->slug ?? ''));
                if ($slug !== '') {
                    $adminSlugs[] = $slug;
                }
            }

            $rawRoles = is_array($member->roles)
                ? $member->roles
                : (json_decode((string) $member->getRawOriginal('roles'), true) ?: []);
            foreach ($rawRoles as $r) {
                if (is_numeric($r) && isset($adminSystemRoleMap[(int) $r])) {
                    $adminSlugs[] = strtolower((string) $adminSystemRoleMap[(int) $r]);
                } elseif (is_string($r) && in_array(strtolower(trim($r)), self::ADMIN_ROLE_SLUGS, true)) {
                    $adminSlugs[] = strtolower(trim($r));
                }
            }

            $adminSlugs = array_values(array_unique(array_filter($adminSlugs)));
            if (empty($adminSlugs)) {
                continue;
            }

            $key = 'member:' . $member->id;
            if (isset($seenKeys[$key])) {
                continue;
            }
            $seenKeys[$key] = true;

            $roleLabel = 'Admin';
            if (in_array('super_admin', $adminSlugs, true) || in_array('superadmin', $adminSlugs, true)) {
                $roleLabel = 'Super Admin';
            } elseif (in_array('project_admin', $adminSlugs, true) && ! in_array('admin', $adminSlugs, true)) {
                $roleLabel = 'Project Admin';
            }

            $approvers[] = [
                'key' => $key,
                'type' => Member::class,
                'id' => (int) $member->id,
                'name' => $member->name,
                'email' => (string) ($member->email ?? ''),
                'role_label' => $roleLabel,
                'guard' => 'admin',
            ];
        }

        return $approvers;
    }

    /**
     * Check whether the actor is an Admin or Super Admin of this project's company
     * AND holds the `drawing_approval.assign_admin` permission.
     */
    public function canManageApprovalAssignment(?Model $actor, Project $project): bool
    {
        if (! $actor) {
            return false;
        }

        if ($this->authorization->isProjectOwner($actor)) {
            return true;
        }

        if ($actor instanceof SuperAdmin) {
            $activeRole = strtolower(trim((string) (session('active_role') ?? request()->query('role') ?? '')));
            if ($activeRole !== '' && ! in_array($activeRole, ['super_admin', 'superadmin', 'crm_super_admin', 'project_owner', 'admin', 'project_admin'], true)) {
                return false;
            }

            if ($actor->company_id && (int) $actor->company_id !== (int) $project->company_id) {
                return false;
            }

            return true;
        }

        if (! $actor instanceof Member) {
            return false;
        }

        if (! $this->memberBelongsToProjectCompany($actor, $project)) {
            return false;
        }

        if (! $this->isMemberAdminRole($actor, $project)) {
            return false;
        }

        // Evaluate company-scoped / project-scoped ConstructionRole permissions first so
        // per-company permission grants and revocations take effect deterministically.
        $companyScopedPerms = $this->memberCompanyScopedPermissionSlugs($actor, $project);
        if ($companyScopedPerms !== null) {
            return in_array('drawing_approval.assign_admin', $companyScopedPerms, true);
        }

        return $this->authorization->hasAnyPermission(
            $actor,
            ['drawing_approval.assign_admin'],
            null
        );
    }

    /**
     * @return array<int, string>|null Null when member has no company-scoped ConstructionRole assignments.
     */
    private function memberCompanyScopedPermissionSlugs(Member $member, Project $project): ?array
    {
        $companyId = (int) ($project->company_id ?? 0);
        if ($companyId <= 0) {
            return null;
        }

        $companyRoleIds = MemberRoleAssignment::query()
            ->where('member_id', $member->id)
            ->where('status', 1)
            ->where(function ($pq) use ($project) {
                $pq->whereNull('project_id')->orWhere('project_id', $project->id);
            })
            ->whereHas('role', function ($rq) use ($companyId) {
                $rq->where('company_id', $companyId)
                    ->where('status', 'active')
                    ->whereNull('deleted_at');
            })
            ->pluck('role_id')
            ->all();

        if (empty($companyRoleIds)) {
            return null;
        }

        return DB::table('construction_role_permissions')
            ->join('construction_permissions', 'construction_permissions.id', '=', 'construction_role_permissions.permission_id')
            ->whereIn('construction_role_permissions.role_id', $companyRoleIds)
            ->pluck('construction_permissions.slug')
            ->map(fn ($s) => (string) $s)
            ->values()
            ->all();
    }

    public function assign(
        Project $project,
        Model $actor,
        string $assigneeKey,
        ?int $draftingJobId = null,
        ?int $drawingRevisionId = null,
        ?int $drawingApprovalId = null,
        ?string $remarks = null
    ): DrawingApproval {
        $approvers = collect($this->getCompanyAdminApprovers($project))->keyBy('key');
        $target = $approvers->get(trim($assigneeKey));

        if (! $target) {
            throw ValidationException::withMessages([
                'assignee_key' => 'Selected user must be an active Admin or Super Admin of this project\'s company.',
            ]);
        }

        $approval = null;

        if ($drawingApprovalId) {
            $approval = DrawingApproval::where('project_id', $project->id)
                ->whereKey($drawingApprovalId)
                ->first();
        }

        if (! $approval && $drawingRevisionId) {
            $revision = DrawingRevision::where('project_id', $project->id)
                ->whereKey($drawingRevisionId)
                ->first();

            if ($revision) {
                $approval = DrawingApproval::where('project_id', $project->id)
                    ->where('drawing_revision_id', $revision->id)
                    ->orderByRaw("CASE WHEN decision = 'pending' THEN 0 ELSE 1 END")
                    ->latest('id')
                    ->first();

                if (! $approval) {
                    $approval = DrawingApproval::create([
                        'project_id' => $project->id,
                        'drawing_revision_id' => $revision->id,
                        'requested_by_type' => $actor::class,
                        'requested_by_id' => $actor->getKey(),
                        'requested_at' => $revision->uploaded_at ?? now(),
                        'decision' => 'pending',
                    ]);
                }
            }
        }

        if (! $approval && $draftingJobId) {
            $job = DraftingJob::where('project_id', $project->id)
                ->whereKey($draftingJobId)
                ->first();

            if (! $job) {
                throw ValidationException::withMessages([
                    'drafting_job_id' => 'The selected CAD drafting job does not belong to this project.',
                ]);
            }

            $latestRevision = DrawingRevision::where('project_id', $project->id)
                ->where('drafting_job_id', $job->id)
                ->orderByDesc('revision_no')
                ->latest('id')
                ->first();

            if (! $latestRevision) {
                throw ValidationException::withMessages([
                    'drafting_job_id' => 'No CAD drawing submission was found yet for this task.',
                ]);
            }

            $approval = DrawingApproval::where('project_id', $project->id)
                ->where('drawing_revision_id', $latestRevision->id)
                ->orderByRaw("CASE WHEN decision = 'pending' THEN 0 ELSE 1 END")
                ->latest('id')
                ->first();

            if (! $approval) {
                $approval = DrawingApproval::create([
                    'project_id' => $project->id,
                    'drawing_revision_id' => $latestRevision->id,
                    'requested_by_type' => $actor::class,
                    'requested_by_id' => $actor->getKey(),
                    'requested_at' => $latestRevision->uploaded_at ?? now(),
                    'decision' => 'pending',
                ]);
            }
        }

        if (! $approval) {
            throw ValidationException::withMessages([
                'drafting_job_id' => 'Please submit a CAD drawing before assigning an approval admin.',
            ]);
        }

        if ($approval->decision !== 'pending') {
            throw ValidationException::withMessages([
                'drafting_job_id' => 'This CAD drawing submission has already been reviewed.',
            ]);
        }

        $approval->update([
            'assigned_to_type' => $target['type'],
            'assigned_to_id' => $target['id'],
            'assigned_by_type' => $actor::class,
            'assigned_by_id' => $actor->getKey(),
            'assigned_at' => now(),
            'remarks' => $remarks !== null && trim($remarks) !== '' ? trim($remarks) : $approval->remarks,
        ]);

        $this->activityService->log(
            'drawing_approval',
            'drawing_approval.assigned_admin',
            $actor,
            $approval,
            $project->company_id,
            $project->id,
            [
                'drawing_revision_id' => $approval->drawing_revision_id,
                'assigned_to_type' => $target['type'],
                'assigned_to_id' => $target['id'],
                'assigned_to_name' => $target['name'],
                'assigned_to_role' => $target['role_label'],
            ],
            null,
            "Assigned CAD approval for review to {$target['name']} ({$target['role_label']})"
        );

        return $approval->fresh(['assignedTo', 'assignedBy', 'drawingRevision']);
    }

    /**
     * Return all pending CAD approvals assigned specifically to this logged-in Admin or Super Admin
     * within companies they belong to.
     *
     * @return array<int, array<string, mixed>>
     */
    public function pendingApprovalsForActor(?Model $actor): array
    {
        if (! $actor) {
            return [];
        }

        $actorType = $actor::class;
        $actorId = (int) $actor->getKey();
        $email = strtolower(trim((string) ($actor->email ?? '')));

        $allowedCompanyIds = $this->resolveActorCompanyIds($actor);
        if ($allowedCompanyIds !== null && empty($allowedCompanyIds)) {
            return [];
        }

        $equivalentMemberIds = [];
        $equivalentSuperAdminIds = [];

        if ($actor instanceof Member) {
            $equivalentMemberIds[] = $actorId;
            if ($email !== '' && $allowedCompanyIds !== null) {
                $equivalentSuperAdminIds = SuperAdmin::whereIn('company_id', $allowedCompanyIds)
                    ->whereRaw('LOWER(email) = ?', [$email])
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->all();
            }
        } elseif ($actor instanceof SuperAdmin) {
            $equivalentSuperAdminIds[] = $actorId;
            if ($email !== '' && $allowedCompanyIds !== null && ! empty($allowedCompanyIds)) {
                $companyAdminIds = SuperAdmin::whereIn('company_id', $allowedCompanyIds)->pluck('id')->all();
                if (! empty($companyAdminIds)) {
                    $equivalentMemberIds = Member::whereRaw('LOWER(email) = ?', [$email])
                        ->whereIn('created_by', $companyAdminIds)
                        ->pluck('id')
                        ->map(fn ($id) => (int) $id)
                        ->all();
                }
            }
        }

        $query = DrawingApproval::query()
            ->with([
                'project:id,name,project_code,company_id,current_stage',
                'drawingRevision.pdfDocument',
                'drawingRevision.dwgDocument',
                'assignedBy',
                'requestedBy',
            ])
            ->where('decision', 'pending')
            ->whereNotNull('assigned_to_type')
            ->whereNotNull('assigned_to_id')
            ->whereHas('project', function ($pq) use ($allowedCompanyIds) {
                if ($allowedCompanyIds !== null) {
                    $pq->whereIn('company_id', $allowedCompanyIds);
                }
            })
            ->where(function ($q) use ($actorType, $actorId, $equivalentMemberIds, $equivalentSuperAdminIds) {
                $q->where(function ($sub) use ($actorType, $actorId) {
                    $sub->where('assigned_to_type', $actorType)
                        ->where('assigned_to_id', $actorId);
                });

                if (! empty($equivalentMemberIds)) {
                    $q->orWhere(function ($sub) use ($equivalentMemberIds) {
                        $sub->where('assigned_to_type', Member::class)
                            ->whereIn('assigned_to_id', $equivalentMemberIds);
                    });
                }

                if (! empty($equivalentSuperAdminIds)) {
                    $q->orWhere(function ($sub) use ($equivalentSuperAdminIds) {
                        $sub->where('assigned_to_type', SuperAdmin::class)
                            ->whereIn('assigned_to_id', $equivalentSuperAdminIds);
                    });
                }
            })
            ->orderByDesc('assigned_at')
            ->orderByDesc('id');

        $isSuperAdminActor = $actor instanceof SuperAdmin;

        return $query->get()
            ->filter(function (DrawingApproval $approval) {
                return $approval->project !== null && $approval->drawingRevision !== null;
            })
            ->map(function (DrawingApproval $approval) use ($isSuperAdminActor) {
                $revision = $approval->drawingRevision;
                $project = $approval->project;

                return [
                    'approval_id' => (int) $approval->id,
                    'project_id' => (int) $approval->project_id,
                    'project_name' => $project?->name ?? 'Project',
                    'project_code' => $project?->project_code ?? '',
                    'drawing_revision_id' => (int) $approval->drawing_revision_id,
                    'drafting_job_id' => $revision?->drafting_job_id ? (int) $revision->drafting_job_id : null,
                    'revision_no' => $revision?->revision_no,
                    'drawing_no' => $revision?->drawing_no,
                    'drawing_type' => $revision?->drawing_type,
                    'file_path' => $revision?->pdfDocument?->file_path ?? $revision?->dwgDocument?->file_path ?? null,
                    'assigned_at' => optional($approval->assigned_at)->toISOString(),
                    'assigned_by_name' => $approval->assignedBy?->name ?? null,
                    'requested_by_name' => $approval->requestedBy?->name ?? null,
                    'project_url' => $isSuperAdminActor
                        ? route('super.construction.projects.show', $approval->project_id)
                        : route('admin.construction.projects.show', $approval->project_id),
                ];
            })
            ->values()
            ->all();
    }

    private function memberBelongsToProjectCompany(Member $member, Project $project): bool
    {
        $companyId = (int) ($project->company_id ?? 0);
        if ($companyId <= 0) {
            return false;
        }

        $companySuperAdminIds = SuperAdmin::where('company_id', $companyId)->pluck('id')->all();
        if ($member->created_by && in_array((int) $member->created_by, $companySuperAdminIds, true)) {
            return true;
        }

        $company = $project->relationLoaded('company') ? $project->company : Company::find($companyId);
        $companyNames = array_values(array_unique(array_filter([
            $company?->name ? trim((string) $company->name) : null,
            $company?->legal_name ? trim((string) $company->legal_name) : null,
        ])));
        if (! empty($companyNames) && $member->company_name && in_array(trim((string) $member->company_name), $companyNames, true)) {
            return true;
        }

        return MemberRoleAssignment::where('member_id', $member->id)
            ->where('status', 1)
            ->whereHas('role', fn ($q) => $q->where('company_id', $companyId))
            ->exists();
    }

    private function isMemberAdminRole(Member $member, Project $project): bool
    {
        $companyId = (int) ($project->company_id ?? 0);

        $hasConstructionAdminRole = MemberRoleAssignment::where('member_id', $member->id)
            ->where('status', 1)
            ->where(function ($pq) use ($project) {
                $pq->whereNull('project_id')->orWhere('project_id', $project->id);
            })
            ->whereHas('role', function ($q) use ($companyId) {
                $q->where('status', 'active')
                    ->whereNull('deleted_at')
                    ->whereIn('slug', self::ADMIN_ROLE_SLUGS)
                    ->where(function ($cq) use ($companyId) {
                        $cq->where('company_id', $companyId)->orWhereNull('company_id');
                    });
            })
            ->exists();

        if ($hasConstructionAdminRole) {
            return true;
        }

        $rawRoles = is_array($member->roles)
            ? $member->roles
            : (json_decode((string) $member->getRawOriginal('roles'), true) ?: []);

        if (empty($rawRoles)) {
            return false;
        }

        $adminRoleIds = Role::whereIn('slug', self::ADMIN_ROLE_SLUGS)->pluck('id')->all();
        foreach ($rawRoles as $r) {
            if (is_numeric($r) && in_array((int) $r, $adminRoleIds, true)) {
                return true;
            }
            if (is_string($r) && in_array(strtolower(trim($r)), self::ADMIN_ROLE_SLUGS, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, int>|null Null indicates unrestricted Project Owner scope.
     */
    private function resolveActorCompanyIds(Model $actor): ?array
    {
        if ($this->authorization->isProjectOwner($actor)) {
            return null;
        }

        if ($actor instanceof SuperAdmin) {
            $owned = SuperAdmin::where('id', $actor->id)->whereNotNull('company_id')->pluck('company_id')->all();
            $created = Company::where('created_by_type', SuperAdmin::class)->where('created_by_id', $actor->id)->pluck('id')->all();

            return array_values(array_unique(array_map('intval', array_merge($owned, $created))));
        }

        if (! $actor instanceof Member) {
            return [];
        }

        $companyIds = [];

        if (! empty($actor->company_name)) {
            $companyIds = array_merge(
                $companyIds,
                Company::where('name', trim((string) $actor->company_name))
                    ->orWhere('legal_name', trim((string) $actor->company_name))
                    ->pluck('id')
                    ->all()
            );
        }

        if (! empty($actor->created_by)) {
            $creatorCompanyId = SuperAdmin::where('id', $actor->created_by)->value('company_id');
            if ($creatorCompanyId) {
                $companyIds[] = (int) $creatorCompanyId;
            }
        }

        $assignedRoleCompanyIds = MemberRoleAssignment::query()
            ->where('construction_member_role_assignments.member_id', $actor->id)
            ->where('construction_member_role_assignments.status', 1)
            ->join('construction_roles', 'construction_roles.id', '=', 'construction_member_role_assignments.role_id')
            ->where('construction_roles.status', 'active')
            ->whereNull('construction_roles.deleted_at')
            ->whereNotNull('construction_roles.company_id')
            ->pluck('construction_roles.company_id')
            ->all();

        $teamProjectCompanyIds = DB::table('construction_project_team_members')
            ->join('construction_projects', 'construction_projects.id', '=', 'construction_project_team_members.project_id')
            ->where('construction_project_team_members.member_id', $actor->id)
            ->where('construction_project_team_members.status', 'active')
            ->pluck('construction_projects.company_id')
            ->all();

        return array_values(array_unique(array_filter(array_map(
            'intval',
            array_merge($companyIds, $assignedRoleCompanyIds, $teamProjectCompanyIds)
        ))));
    }

    /**
     * Persist a CAD drawing approval decision and advance revision, job, tasks, and project stage.
     */
    public function processDrawingApprovalDecision(
        DrawingApproval $drawingApproval,
        string $decision,
        ?string $remarks = null,
        ?Model $actor = null
    ): DrawingApproval {
        $drawingApproval->update([
            'approved_by_type' => $actor ? $actor::class : null,
            'approved_by_id' => $actor?->getKey(),
            'approved_at' => now(),
            'decision' => $decision,
            'remarks' => $remarks,
        ]);

        $revision = $drawingApproval->drawingRevision;
        $project = $drawingApproval->project;
        $draftingJob = $revision?->draftingJob;

        if ($revision) {
            $revUpdates = ['status' => $decision === 'approved' ? 'approved' : 'revision_requested'];
            if ($decision === 'approved') {
                $revUpdates['is_locked'] = true;
            }
            $revision->update($revUpdates);

            if ($decision === 'approved') {
                DrawingApproval::where('drawing_revision_id', $revision->id)
                    ->where('decision', 'pending')
                    ->where('id', '!=', $drawingApproval->id)
                    ->update([
                        'decision' => 'approved',
                        'approved_at' => now(),
                        'approved_by_type' => $actor ? $actor::class : null,
                        'approved_by_id' => $actor?->getKey(),
                        'remarks' => $remarks,
                    ]);
            }
        }

        if ($draftingJob) {
            if ($decision === 'approved') {
                $jobUpdates = ['status' => 'approved'];
                if (Schema::hasColumn('construction_drafting_jobs', 'rejection_reason')) {
                    $jobUpdates['rejection_reason'] = null;
                }
                $draftingJob->update($jobUpdates);
            } else {
                $draftingJob->update(['status' => 'revision_requested']);
            }
        }

        if ($project) {
            if ($decision === 'approved') {
                $this->completeTasksOnCadApproval($project, $draftingJob, $actor);
                $project->update(['current_stage' => 'ready_for_construction']);
            } else {
                $project->update(['current_stage' => 'drafting_in_progress']);
            }
        }

        return $drawingApproval->fresh(['drawingRevision.draftingJob', 'project']);
    }

    /**
     * Mark tasks associated with an approved CAD drafting job (across both Task & ExecutionTask) as completed.
     *
     * @return array{unified_ids: array<int, int>, execution_ids: array<int, int>}
     */
    public function completeTasksOnCadApproval(
        Project $project,
        ?DraftingJob $draftingJob = null,
        ?Model $actor = null
    ): array {
        $sync = app(TaskMirrorSyncService::class);
        $ids = ['unified' => [], 'exec' => []];

        if ($draftingJob && $draftingJob->survey_submission_id) {
            $ids = $this->collectTasksFromSubmission($project, (int) $draftingJob->survey_submission_id, $ids);
        }

        // Fallback to active draftsman task links when no submission/visit link resolved tasks
        if (empty($ids['unified']) && empty($ids['exec']) && $draftingJob && $draftingJob->assigned_to_member_id) {
            $ids = $this->collectTasksByDraftsman($project, (int) $draftingJob->assigned_to_member_id, $ids);
        }

        // Always include project tasks explicitly marked in drafting/review-pending states
        $ids = $this->collectTasksByStatuses($project, ['drafting_in_progress', 'drafting', 'drawing_approval_pending'], $ids);

        // Standalone job fallback: if still empty, complete active pre-construction workflow tasks in the project
        if (empty($ids['unified']) && empty($ids['exec'])) {
            $ids = $this->collectTasksByStatuses($project, ['in_progress', 'survey_approved', 'review'], $ids);
        }

        $ids = $this->expandMirroredTaskIds($project, $sync, $ids);

        return [
            'unified_ids' => $this->markUnifiedTasksCompleted($project, $ids['unified'], $actor),
            'execution_ids' => $this->markExecutionTasksCompleted($project, $ids['exec']),
        ];
    }

    private function appendProjectTaskCandidate(Project $project, mixed $candidateId, array $ids, bool $preferExec = false): array
    {
        $id = (int) $candidateId;
        if ($id <= 0) {
            return $ids;
        }

        if ($preferExec) {
            $ids['exec'][] = $id;
            return $ids;
        }

        if (Task::where('project_id', $project->id)->whereKey($id)->exists()) {
            $ids['unified'][] = $id;
        }
        if (ExecutionTask::where('project_id', $project->id)->whereKey($id)->exists()) {
            $ids['exec'][] = $id;
        }

        return $ids;
    }

    private function collectTasksFromSubmission(Project $project, int $submissionId, array $ids): array
    {
        $submission = SurveySubmission::query()->where('project_id', $project->id)->find($submissionId);
        if (! $submission) {
            return $ids;
        }

        if (Schema::hasColumn('construction_survey_submissions', 'execution_task_id')) {
            $ids = $this->appendProjectTaskCandidate($project, $submission->execution_task_id, $ids, true);
        }
        if (Schema::hasColumn('construction_survey_submissions', 'task_id')) {
            $ids = $this->appendProjectTaskCandidate($project, $submission->task_id, $ids);
        }

        if ($submission->survey_visit_id) {
            $visit = SurveyVisit::query()->where('project_id', $project->id)->find($submission->survey_visit_id);
            if ($visit) {
                $ids = $this->appendProjectTaskCandidate($project, $visit->execution_task_id, $ids, true);
                $ids = $this->appendProjectTaskCandidate($project, $visit->task_id, $ids);
            }
        }

        return $ids;
    }

    private function collectTasksByDraftsman(Project $project, int $draftsmanId, array $ids): array
    {
        $exec = ExecutionTask::query()
            ->where('project_id', $project->id)
            ->where(function ($q) use ($draftsmanId) {
                $q->where('supervisor_member_id', $draftsmanId)
                    ->orWhereHas('assignees', fn ($aq) => $aq->where('member_id', $draftsmanId)->where('status', 'active'));
            })
            ->pluck('id')
            ->all();

        $unified = Task::query()
            ->where('project_id', $project->id)
            ->where(function ($q) use ($draftsmanId) {
                $q->where('assigned_supervisor_member_id', $draftsmanId)
                    ->orWhere('member_id', $draftsmanId)
                    ->orWhereHas('assignments', fn ($aq) => $aq->where('assigned_to', $draftsmanId)->whereNull('deleted_at'));
            })
            ->pluck('id')
            ->all();

        $ids['exec'] = array_merge($ids['exec'], $exec);
        $ids['unified'] = array_merge($ids['unified'], $unified);

        return $ids;
    }

    private function collectTasksByStatuses(Project $project, array $statuses, array $ids): array
    {
        $ids['exec'] = array_merge(
            $ids['exec'],
            ExecutionTask::query()->where('project_id', $project->id)->whereIn('status', $statuses)->pluck('id')->all()
        );
        $ids['unified'] = array_merge(
            $ids['unified'],
            Task::query()->where('project_id', $project->id)->whereIn('status', $statuses)->pluck('id')->all()
        );

        return $ids;
    }

    private function expandMirroredTaskIds(Project $project, TaskMirrorSyncService $sync, array $ids): array
    {
        $execIds = array_values(array_unique(array_filter(array_map('intval', $ids['exec']))));
        $unifiedIds = array_values(array_unique(array_filter(array_map('intval', $ids['unified']))));

        foreach (ExecutionTask::where('project_id', $project->id)->whereIn('id', $execIds)->get() as $legacyTask) {
            $partner = $sync->findMatchingUnifiedForLegacy($legacyTask);
            if ($partner) {
                $unifiedIds[] = (int) $partner->id;
            }
        }

        foreach (Task::where('project_id', $project->id)->whereIn('id', $unifiedIds)->get() as $unifiedTask) {
            $partner = $sync->findMatchingLegacyForUnified($unifiedTask);
            if ($partner) {
                $execIds[] = (int) $partner->id;
            }
        }

        return [
            'exec' => array_values(array_unique(array_filter($execIds))),
            'unified' => array_values(array_unique(array_filter($unifiedIds))),
        ];
    }

    private function markExecutionTasksCompleted(Project $project, array $execIds): array
    {
        if (empty($execIds)) {
            return [];
        }

        $today = now()->toDateString();
        $completedIds = [];

        ExecutionTask::query()
            ->where('project_id', $project->id)
            ->whereIn('id', $execIds)
            ->whereNotIn('status', ['cancelled', 'blocked'])
            ->get()
            ->each(function (ExecutionTask $execTask) use ($today, &$completedIds) {
                $execTask->status = 'completed';
                $execTask->progress_percent = 100;
                $execTask->actual_end_date = $execTask->actual_end_date ?? $today;
                $execTask->save();
                $completedIds[] = (int) $execTask->id;
            });

        return $completedIds;
    }

    private function markUnifiedTasksCompleted(Project $project, array $unifiedIds, ?Model $actor): array
    {
        if (empty($unifiedIds)) {
            return [];
        }

        $now = now();
        $completedIds = [];

        Task::query()
            ->where('project_id', $project->id)
            ->whereIn('id', $unifiedIds)
            ->whereNotIn('status', ['cancelled', 'blocked'])
            ->get()
            ->each(function (Task $task) use ($now, $actor, &$completedIds) {
                $task->status = 'completed';
                $task->progress_percent = 100;
                if ($task->planned_qty > 0 && ($task->completed_qty === null || (float) $task->completed_qty < (float) $task->planned_qty)) {
                    $task->completed_qty = $task->planned_qty;
                }
                $task->completed_at = $task->completed_at ?? $now;
                $task->client_review_status = ClientReviewStatus::APPROVED;
                if ($actor && empty($task->approved_by_id)) {
                    $task->approved_by_type = $actor::class;
                    $task->approved_by_id = $actor->getKey();
                }
                $task->save();
                $completedIds[] = (int) $task->id;
            });

        return $completedIds;
    }
}
