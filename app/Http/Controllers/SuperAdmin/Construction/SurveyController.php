<?php

namespace App\Http\Controllers\SuperAdmin\Construction;

use App\Http\Controllers\Concerns\ResolvesConstructionActor;
use App\Http\Controllers\Controller;
use App\Models\ConstructionDocument;
use App\Models\Designation;
use App\Models\Member;
use App\Models\Project;
use App\Models\ProjectTeamMember;
use App\Models\SuperAdmin;
use App\Models\SurveyPlan;
use App\Models\SurveyPlanMember;
use App\Models\SurveySubmission;
use App\Models\SurveyVisit;
use App\Services\Construction\ConstructionActivityService;
use App\Services\Construction\ConstructionDocumentService;
use App\Support\Construction\SurveyStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class SurveyController extends Controller
{
    use ResolvesConstructionActor;

    private const MAX_DOCUMENTS_PER_PLAN = 25;

    private const DOCUMENT_LIST_COLUMNS = [
        'id',
        'project_id',
        'documentable_type',
        'documentable_id',
        'original_name',
        'mime_type',
        'file_size',
        'created_at',
    ];

    public function index(): Response
    {
        $projectIds = Project::query()
            ->pluck('id')
            ->map(fn ($projectId) => (int) $projectId)
            ->values();

        $permissionsByProject = $projectIds->mapWithKeys(
            fn (int $projectId) => [
                $projectId => [
                    'survey_plan.manage',
                    'survey_submission.review',
                    'document.manage',
                ],
            ]
        );

        $teamMemberships = ProjectTeamMember::query()
            ->whereIn('project_id', $projectIds)
            ->where('status', 'active')
            ->get(['project_id', 'member_id']);

        $memberProjectIds = $teamMemberships
            ->groupBy('member_id')
            ->map(fn ($memberships) => $memberships
                ->pluck('project_id')
                ->map(fn ($projectId) => (int) $projectId)
                ->unique()
                ->values()
                ->all());

        $members = Member::query()
            ->where('status', Member::STATUS_ACTIVE)
            ->whereIn(
                'id',
                $teamMemberships
                    ->pluck('member_id')
                    ->unique()
            )
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'email',
                'departments',
                'designation',
            ]);

        $designationIds = $members
            ->flatMap(
                fn (Member $member) =>
                    array_values($member->designation ?? [])
            )
            ->filter(
                fn ($designation) =>
                    is_numeric($designation)
            )
            ->map(
                fn ($designation) =>
                    (int) $designation
            )
            ->unique()
            ->values();

        $designationNames = Designation::query()
            ->whereIn('id', $designationIds)
            ->pluck('name', 'id');

        $members = $members->map(
            function (
                Member $member
            ) use (
                $designationNames,
                $memberProjectIds
            ): array {
                $designations = array_values(
                    $member->designation ?? []
                );

                $designationText = collect($designations)
                    ->map(
                        fn ($designation) =>
                            is_numeric($designation)
                                ? $designationNames->get(
                                    (int) $designation
                                )
                                : (string) $designation
                    )
                    ->filter()
                    ->implode(', ');

                return [
                    'id' => $member->getKey(),
                    'name' => $member->name,
                    'email' => $member->email,
                    'designation_text' => $designationText,
                    'project_ids' => $memberProjectIds->get(
                        $member->getKey(),
                        []
                    ),
                ];
            }
        );

        $surveyPlans = SurveyPlan::query()
            ->withCount('visits')
            ->with([
                'project.company',
                'planMembers.member:id,name,email',
                'documents' => fn ($query) =>
                    $query
                        ->select(
                            self::DOCUMENT_LIST_COLUMNS
                        )
                        ->latest(),
            ])
            ->latest()
            ->get();

        $surveySubmissions = SurveySubmission::query()
            ->with([
                'project.company',
                'submittedBy:id,name,email',
                'reviewedBy:id,name,email',
                'surveyVisit.checkedInBy:id,name,email',
                'surveyVisit.entries.capturedBy:id,name,email',

                'surveyVisit.entries.supportingDocument' =>
                    fn ($query) =>
                        $query->select(
                            self::DOCUMENT_LIST_COLUMNS
                        ),

                'surveyVisit.measurements.capturedBy:id,name,email',

                'surveyVisit.surveyPlan.documents' =>
                    fn ($query) =>
                        $query
                            ->select(
                                self::DOCUMENT_LIST_COLUMNS
                            )
                            ->latest(),
            ])
            ->latest()
            ->get();

        return Inertia::render(
            'SuperAdmin/Construction/Survey/Index',
            [
                'surveyPlans' => $surveyPlans,

                'surveySubmissions' =>
                    $surveySubmissions,

                'projects' => Project::query()
                    ->orderBy('name')
                    ->get([
                        'id',
                        'name',
                        'project_code',
                    ]),

                'members' =>
                    $members,

                'projectPermissions' =>
                    $permissionsByProject->all(),

                'surveyPlanStatuses' =>
                    SurveyPlan::MANUALLY_MANAGEABLE_STATUSES,

                'surveyStatusCodes' =>
                    SurveyStatus::codes(),

                'documentLimits' => [
                    'max_files_per_upload' => 5,
                    'max_file_size_mb' => 20,
                    'max_files_per_plan' =>
                        self::MAX_DOCUMENTS_PER_PLAN,
                ],
            ]
        );
    }

    public function storePlan(
        Request $request,
        ConstructionActivityService $activityService
    ): RedirectResponse {
        $actor = $this->superAdminActor();

        $projectId = (int) $request->input(
            'project_id'
        );

        $validated = $request->validate(
            $this->planRules(
                $projectId,
                includeProject: true
            )
        );

        $project = Project::findOrFail(
            $validated['project_id']
        );

        DB::transaction(
            function () use (
                $activityService,
                $actor,
                $project,
                $request,
                $validated
            ): void {
                $surveyPlan = SurveyPlan::create([
                    ...collect($validated)
                        ->except(['member_ids'])
                        ->all(),

                    'survey_code' =>
                        'SUR-'
                        . Str::upper(
                            (string) Str::ulid()
                        ),

                    'assigned_by_type' =>
                        $actor::class,

                    'assigned_by_id' =>
                        $actor->getKey(),

                    'status' =>
                        SurveyPlan::STATUS_PLANNED,
                ]);

                $this->syncPlanMembers(
                    $surveyPlan,
                    $validated['member_ids']
                );

                $project->update([
                    'current_stage' =>
                        'survey_planned',
                ]);

                $activityService->log(
                    module: 'survey_plan',
                    action: 'created',
                    actor: $actor,
                    reference: $surveyPlan,
                    companyId: $project->company_id,
                    projectId: $project->getKey(),
                    meta: [
                        'assigned_members' =>
                            array_map(
                                'intval',
                                $validated['member_ids']
                            ),
                    ],
                    request: $request
                );
            }
        );

        return back()->with(
            'success',
            'Survey plan created successfully.'
        );
    }

    public function updatePlan(
        SurveyPlan $surveyPlan,
        Request $request,
        ConstructionActivityService $activityService
    ): RedirectResponse {
        $actor = $this->superAdminActor();

        $validated = $request->validate(
            $this->planRules(
                (int) $surveyPlan->project_id,
                includeProject: false
            )
        );

        DB::transaction(
            function () use (
                $activityService,
                $actor,
                $request,
                $surveyPlan,
                $validated
            ): void {
                $lockedPlan = SurveyPlan::query()
                    ->whereKey(
                        $surveyPlan->getKey()
                    )
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->ensurePlanIsEditable(
                    $lockedPlan
                );

                if (
                    $lockedPlan
                        ->visits()
                        ->exists()
                ) {
                    throw ValidationException::withMessages([
                        'status' =>
                            'Plan details and assignments are locked after field work starts.',
                    ]);
                }

                $lockedPlan->update(
                    collect($validated)
                        ->except(['member_ids'])
                        ->all()
                );

                $this->syncPlanMembers(
                    $lockedPlan,
                    $validated['member_ids']
                );

                $activityService->log(
                    module: 'survey_plan',
                    action: 'updated',
                    actor: $actor,
                    reference: $lockedPlan,
                    companyId:
                        $lockedPlan
                            ->project
                            ->company_id,
                    projectId:
                        (int) $lockedPlan
                            ->project_id,
                    meta: [
                        'assigned_members' =>
                            array_map(
                                'intval',
                                $validated['member_ids']
                            ),
                    ],
                    request: $request
                );
            }
        );

        return back()->with(
            'success',
            'Survey plan updated successfully.'
        );
    }

    public function updatePlanStatus(
        SurveyPlan $surveyPlan,
        Request $request,
        ConstructionActivityService $activityService
    ): RedirectResponse {
        $actor = $this->superAdminActor();

        $request->merge([
            'status' =>
                $request->integer('status'),
        ]);

        $validated = $request->validate([
            'status' => [
                'required',
                'integer',
                Rule::in(
                    SurveyPlan::MANUALLY_MANAGEABLE_STATUSES
                ),
            ],
        ]);

        $statusChanged = DB::transaction(
            function () use (
                $activityService,
                $actor,
                $request,
                $surveyPlan,
                $validated
            ): bool {
                $lockedPlan = SurveyPlan::query()
                    ->whereKey(
                        $surveyPlan->getKey()
                    )
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->ensurePlanIsEditable(
                    $lockedPlan
                );

                $oldStatus =
                    $lockedPlan->status;

                $newStatus =
                    $validated['status'];

                if ($oldStatus === $newStatus) {
                    return false;
                }

                if (
                    $newStatus ===
                        SurveyPlan::STATUS_PLANNED
                    && $lockedPlan
                        ->visits()
                        ->exists()
                ) {
                    throw ValidationException::withMessages([
                        'status' =>
                            'A survey with field visits cannot be moved back to planned.',
                    ]);
                }

                $lockedPlan->update([
                    'status' => $newStatus,
                ]);

                $lockedPlan->project->update([
                    'current_stage' =>
                        $newStatus ===
                        SurveyPlan::STATUS_IN_PROGRESS
                            ? 'survey_in_progress'
                            : 'survey_planned',
                ]);

                $activityService->log(
                    module: 'survey_plan',
                    action: 'status_changed',
                    actor: $actor,
                    reference: $lockedPlan,
                    companyId:
                        $lockedPlan
                            ->project
                            ->company_id,
                    projectId:
                        (int) $lockedPlan
                            ->project_id,
                    meta: [
                        'old_status' =>
                            $oldStatus,
                        'new_status' =>
                            $newStatus,
                        'old_status_key' =>
                            SurveyStatus::key(
                                $oldStatus
                            ),
                        'new_status_key' =>
                            SurveyStatus::key(
                                $newStatus
                            ),
                    ],
                    request: $request
                );

                return true;
            }
        );

        if (!$statusChanged) {
            return back()->with(
                'success',
                'Survey plan status is already up to date.'
            );
        }

        return back()->with(
            'success',
            'Survey plan status updated successfully.'
        );
    }

    public function uploadPlanDocuments(
        SurveyPlan $surveyPlan,
        Request $request,
        ConstructionDocumentService $documentService,
        ConstructionActivityService $activityService
    ): RedirectResponse {
        $actor = $this->superAdminActor();

        $validated = $request->validate([
            'documents' => [
                'required',
                'array',
                'min:1',
                'max:5',
            ],

            'documents.*' => [
                'required',
                'file',
                'mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx,csv,txt,dwg,dxf',
                'max:20480',
            ],
        ]);

        $storedDocuments = [];

        try {
            DB::transaction(
                function () use (
                    $activityService,
                    $actor,
                    $documentService,
                    $request,
                    &$storedDocuments,
                    $surveyPlan,
                    $validated
                ): void {
                    $lockedPlan =
                        SurveyPlan::query()
                            ->whereKey(
                                $surveyPlan
                                    ->getKey()
                            )
                            ->lockForUpdate()
                            ->firstOrFail();

                    $incomingCount = count(
                        $validated['documents']
                    );

                    $existingCount =
                        $lockedPlan
                            ->documents()
                            ->count();

                    if (
                        $existingCount
                        + $incomingCount
                        > self::MAX_DOCUMENTS_PER_PLAN
                    ) {
                        throw ValidationException::withMessages([
                            'documents' =>
                                'A survey plan can contain a maximum of '
                                . self::MAX_DOCUMENTS_PER_PLAN
                                . ' documents.',
                        ]);
                    }

                    $project =
                        $lockedPlan->project;

                    foreach (
                        $validated['documents']
                        as $file
                    ) {
                        $storedDocuments[] =
                            $documentService
                                ->storeDocument(
                                    documentable:
                                        $lockedPlan,
                                    actor:
                                        $actor,
                                    folder:
                                        'construction/survey/plans/'
                                        . $lockedPlan
                                            ->getKey(),
                                    file:
                                        $file,
                                    companyId:
                                        $project
                                            ->company_id,
                                    projectId:
                                        $project
                                            ->getKey(),
                                    disk:
                                        'local'
                                );
                    }

                    $activityService->log(
                        module:
                            'survey_plan',

                        action:
                            'documents_uploaded',

                        actor:
                            $actor,

                        reference:
                            $lockedPlan,

                        companyId:
                            $project->company_id,

                        projectId:
                            $project->getKey(),

                        meta: [
                            'document_count' =>
                                count(
                                    $storedDocuments
                                ),

                            'document_ids' =>
                                collect(
                                    $storedDocuments
                                )
                                    ->pluck('id')
                                    ->all(),
                        ],

                        request:
                            $request
                    );
                }
            );
        } catch (Throwable $exception) {
            foreach (
                array_reverse($storedDocuments)
                as $storedDocument
            ) {
                try {
                    $documentService
                        ->deleteDocument(
                            $storedDocument
                        );
                } catch (
                    Throwable $cleanupException
                ) {
                    report($cleanupException);
                }
            }

            throw $exception;
        }

        return back()->with(
            'success',
            count($storedDocuments)
            . ' document(s) uploaded successfully.'
        );
    }

    public function deletePlanDocument(
        SurveyPlan $surveyPlan,
        ConstructionDocument $document,
        Request $request,
        ConstructionDocumentService $documentService,
        ConstructionActivityService $activityService
    ): RedirectResponse {
        $actor = $this->superAdminActor();

        abort_unless(
            $document->documentable_type
                === SurveyPlan::class
                && (int) $document
                    ->documentable_id
                    === (int) $surveyPlan
                        ->getKey()
                && (int) $document
                    ->project_id
                    === (int) $surveyPlan
                        ->project_id,
            404
        );

        $documentId =
            $document->getKey();

        $project =
            $surveyPlan->project;

        $documentService->deleteDocument(
            $document
        );

        $activityService->log(
            module: 'survey_plan',
            action: 'document_deleted',
            actor: $actor,
            reference: $surveyPlan,
            companyId:
                $project->company_id,
            projectId:
                $project->getKey(),
            meta: [
                'document_id' =>
                    $documentId,
            ],
            request: $request
        );

        return back()->with(
            'success',
            'Survey document deleted successfully.'
        );
    }

    public function reviewSubmission(
        SurveySubmission $submission,
        Request $request,
        ConstructionActivityService $activityService
    ): RedirectResponse {
        $actor = $this->superAdminActor();

        $request->merge([
            'status' =>
                $request->integer('status'),
        ]);

        $validated = $request->validate([
            'status' => [
                'required',
                'integer',
                Rule::in([
                    SurveySubmission::STATUS_APPROVED,
                    SurveySubmission::STATUS_REVISION_REQUESTED,
                    SurveySubmission::STATUS_REJECTED,
                ]),
            ],

            'review_notes' => [
                'nullable',

                Rule::requiredIf(
                    fn (): bool =>
                        in_array(
                            $request->integer(
                                'status'
                            ),
                            [
                                SurveySubmission::STATUS_REVISION_REQUESTED,
                                SurveySubmission::STATUS_REJECTED,
                            ],
                            true
                        )
                ),

                'string',
                'max:5000',
            ],
        ]);

        DB::transaction(
            function () use (
                $activityService,
                $actor,
                $request,
                $submission,
                $validated
            ): void {
                $lockedSubmission =
                    SurveySubmission::query()
                        ->whereKey(
                            $submission
                                ->getKey()
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                if (
                    $lockedSubmission->status
                    !== SurveySubmission::STATUS_SUBMITTED
                ) {
                    throw ValidationException::withMessages([
                        'status' =>
                            'Only a newly submitted survey can be reviewed.',
                    ]);
                }

                $lockedSubmission->update([
                    'status' =>
                        $validated['status'],

                    'review_notes' =>
                        $validated['review_notes']
                        ?? null,

                    /*
                     * This FK targets members only.
                     * SuperAdmin identity is captured
                     * in the construction activity log.
                     */
                    'reviewed_by_member_id' =>
                        null,

                    'reviewed_at' =>
                        now(),
                ]);

                $relatedStatus =
                    match ($validated['status']) {
                        SurveySubmission::STATUS_APPROVED =>
                            SurveyPlan::STATUS_APPROVED,

                        SurveySubmission::STATUS_REVISION_REQUESTED =>
                            SurveyPlan::STATUS_IN_PROGRESS,

                        default =>
                            SurveyPlan::STATUS_REJECTED,
                    };

                $surveyVisit =
                    SurveyVisit::query()
                        ->whereKey(
                            $lockedSubmission
                                ->survey_visit_id
                        )
                        ->lockForUpdate()
                        ->first();

                if ($surveyVisit) {
                    $surveyVisit->update([
                        'status' =>
                            $relatedStatus,
                    ]);

                    SurveyPlan::query()
                        ->whereKey(
                            $surveyVisit
                                ->survey_plan_id
                        )
                        ->lockForUpdate()
                        ->firstOrFail()
                        ->update([
                            'status' =>
                                $relatedStatus,
                        ]);
                }

                $project =
                    Project::query()
                        ->whereKey(
                            $lockedSubmission
                                ->project_id
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                if (
                    $validated['status']
                    === SurveySubmission::STATUS_APPROVED
                ) {
                    $project->update([
                        'current_stage' =>
                            'drafting_in_progress',
                    ]);
                } elseif (
                    $validated['status']
                    === SurveySubmission::STATUS_REVISION_REQUESTED
                ) {
                    $project->update([
                        'current_stage' =>
                            'survey_in_progress',
                    ]);
                }

                $activityService->log(
                    module:
                        'survey_submission',

                    action:
                        SurveyStatus::key(
                            $validated['status']
                        ),

                    actor:
                        $actor,

                    reference:
                        $lockedSubmission,

                    companyId:
                        $project->company_id,

                    projectId:
                        $project->getKey(),

                    meta: [
                        'status' =>
                            $validated['status'],

                        'review_notes' =>
                            $validated['review_notes']
                            ?? null,
                    ],

                    request:
                        $request
                );
            }
        );

        return back()->with(
            'success',
            'Survey submission reviewed successfully.'
        );
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function planRules(
        int $projectId,
        bool $includeProject
    ): array {
        $rules = [
            'title' => [
                'required',
                'string',
                'max:255',
            ],

            'description' => [
                'nullable',
                'string',
                'max:5000',
            ],

            'site_address' => [
                'nullable',
                'string',
                'max:2000',
            ],

            'site_latitude' => [
                'nullable',
                'numeric',
                'between:-90,90',
            ],

            'site_longitude' => [
                'nullable',
                'numeric',
                'between:-180,180',
            ],

            'planned_date' => [
                'nullable',
                'date',
            ],

            'planned_start_time' => [
                'nullable',
                'required_with:planned_end_time',
                'date_format:H:i',
            ],

            'planned_end_time' => [
                'nullable',
                'date_format:H:i',
                'after:planned_start_time',
            ],

            'member_ids' => [
                'required',
                'array',
                'min:1',
                'max:50',
            ],

            'member_ids.*' => [
                'required',
                'integer',
                'distinct',

                Rule::exists(
                    'members',
                    'id'
                )
                    ->where(
                        'status',
                        Member::STATUS_ACTIVE
                    )
                    ->whereNull(
                        'deleted_at'
                    ),

                Rule::exists(
                    'construction_project_team_members',
                    'member_id'
                )->where(
                    fn ($query) =>
                        $query
                            ->where(
                                'project_id',
                                $projectId
                            )
                            ->where(
                                'status',
                                'active'
                            )
                ),
            ],
        ];

        if ($includeProject) {
            $rules = [
                'project_id' => [
                    'required',
                    'integer',
                    Rule::exists(
                        'construction_projects',
                        'id'
                    ),
                ],

                ...$rules,
            ];
        }

        return $rules;
    }

    /**
     * @param array<int, int|string> $memberIds
     */
    private function syncPlanMembers(
        SurveyPlan $surveyPlan,
        array $memberIds
    ): void {
        $newMemberIds = collect(
            $memberIds
        )
            ->map(
                fn ($memberId) =>
                    (int) $memberId
            )
            ->unique()
            ->values();

        $surveyPlan
            ->planMembers()
            ->whereNotIn(
                'member_id',
                $newMemberIds
            )
            ->delete();

        foreach (
            $newMemberIds as $memberId
        ) {
            SurveyPlanMember::updateOrCreate(
                [
                    'survey_plan_id' =>
                        $surveyPlan->getKey(),

                    'member_id' =>
                        $memberId,
                ],
                [
                    'role_in_survey' =>
                        'surveyor',

                    'status' =>
                        'assigned',
                ]
            );
        }
    }

    private function superAdminActor(): SuperAdmin
    {
        $actor =
            $this->constructionActor();

        abort_unless(
            $actor instanceof SuperAdmin,
            403,
            'Unauthorized.'
        );

        return $actor;
    }

    private function ensurePlanIsEditable(
        SurveyPlan $surveyPlan
    ): void {
        if (
            !in_array(
                $surveyPlan->status,
                SurveyPlan::MANUALLY_MANAGEABLE_STATUSES,
                true
            )
        ) {
            throw ValidationException::withMessages([
                'status' =>
                    'Submitted or reviewed survey plans can no longer be edited manually.',
            ]);
        }
    }
}