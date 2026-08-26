<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\ClientInvoice;
use App\Models\ClientPayment;
use App\Models\DailyProgressReport;
use App\Models\DraftingJob;
use App\Models\ConstructionEquipment;
use App\Models\EquipmentAllocation;
use App\Models\EquipmentUsageLog;
use App\Models\ExecutionTask;
use App\Models\ExecutionTaskAssignee;
use App\Models\Project;
use App\Models\ProjectHandover;
use App\Models\ProjectTeamMember;
use App\Models\SurveyEntry;
use App\Models\SurveyMeasurement;
use App\Models\SurveyPlan;
use App\Models\SurveyPlanMember;
use App\Models\SurveySubmission;
use App\Models\SurveyVisit;
use App\Models\ConstructionVehicle;
use App\Models\VehicleAssignment;
use App\Models\VehicleLocationPing;
use App\Models\DrawingRevision;
use App\Models\DrawingApproval;
use App\Models\Member;
use App\Services\Construction\ConstructionActivityService;
use App\Services\Construction\ConstructionAuthorizationService;
use App\Services\Construction\ConstructionDocumentService;
use App\Services\Construction\ConstructionEquipmentService;
use App\Services\Construction\ConstructionExecutionService;
use App\Services\Construction\ConstructionFleetService;
use App\Services\Construction\ConstructionMemberContextService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ConstructionController extends Controller
{
    /**
     * Mobile role/project context.
     *
     * Resolves the authenticated member's accessible projects, project-scoped
     * roles (or global roles when no project), active project/role, and the
     * role-specific permissions.
     *
     * Invalid/unauthorized project or role → HTTP 403.
     */
    public function context(
        Request $request,
        ConstructionMemberContextService $contextService
    ) {
        $member = $request->user();

        abort_unless($member instanceof Member, 403);

        try {
            $context = $contextService->getMobileContext(
                $member,
                $member->getRequestedConstructionRole($request),
                $request->integer('project') ?: null
            );
        } catch (AuthorizationException) {
            return response()->json([
                'success' => false,
                'message' => 'Requested project or role is not accessible.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'member' => $context['member'],
                'roles' => $context['roles'],
                'available_roles' => $context['available_roles'],
                'projects' => $context['projects']->load([
                    'client',
                    'latestBudget',
                ]),
                'permissions' => $context['permissions'],
                'active_role' => $context['active_role'],
                'active_project' => $context['active_project'],
            ],
        ]);
    }

    public function assignedProjects(Request $request)
    {
        $member = $request->user();

        abort_unless($member instanceof Member, 403);

        /** @var ConstructionAuthorizationService $authorization */
        $authorization = app(ConstructionAuthorizationService::class);

        $projects = $authorization->getProjects($member);

        return response()->json([
            'success' => true,
            'data' => $projects->load([
                'client',
                'latestBudget',
            ]),
        ]);
    }

    public function showSurveyPlan(
    Request $request,
    SurveyPlan $surveyPlan
) {
    $member = $request->user();

    $this->ensureSurveyAssignment($surveyPlan, $member);

    return response()->json([
        'success' => true,
        'data' => $surveyPlan->load([
            'project.client',
            'planMembers.member',
            'visits',
        ]),
    ]);
}
    public function checkIn(
        Request $request,
        ConstructionActivityService $activityService
    ) {
        $member = $request->user();

        $validated = $request->validate([
            'survey_plan_id' => [
                'required',
                'exists:construction_survey_plans,id',
            ],
            'check_in_latitude' => ['nullable', 'numeric'],
            'check_in_longitude' => ['nullable', 'numeric'],
            'gps_distance_meters' => ['nullable', 'numeric'],
        ]);

        $surveyPlan = SurveyPlan::findOrFail(
            $validated['survey_plan_id']
        );

        $this->ensureSurveyAssignment($surveyPlan, $member);

        $visit = SurveyVisit::create([
            'project_id' => $surveyPlan->project_id,
            'survey_plan_id' => $surveyPlan->id,
            'checked_in_by_member_id' => $member->getKey(),
            'check_in_at' => now(),
            'check_in_latitude' => $validated['check_in_latitude'] ?? null,
            'check_in_longitude' => $validated['check_in_longitude'] ?? null,
            'gps_distance_meters' => $validated['gps_distance_meters'] ?? null,
            'gps_verified' => (float) (
                $validated['gps_distance_meters'] ?? 99999
            ) <= 150,
            'status' => SurveyVisit::STATUS_IN_PROGRESS,
        ]);

        $surveyPlan->update([
            'status' => SurveyPlan::STATUS_IN_PROGRESS,
        ]);

        $surveyPlan->project->update([
            'current_stage' => 'survey_in_progress',
        ]);

        $activityService->log(
            module: 'survey_visit',
            action: 'check_in',
            actor: $member,
            reference: $visit,
            companyId: $surveyPlan->project->company_id,
            projectId: $surveyPlan->project_id,
            request: $request
        );

        return response()->json([
            'success' => true,
            'data' => $visit,
        ], 201);
    }

    public function storeEntry(
        SurveyVisit $surveyVisit,
        Request $request,
        ConstructionActivityService $activityService,
        ConstructionDocumentService $documentService
    ) {
        $member = $request->user();

        $this->ensureVisitOwnedBy($surveyVisit, $member);

        $validated = $request->validate([
            'entry_type' => [
                'required',
                'in:photo,video,note,voice,observation',
            ],
            'title' => [
                'required',
                'string',
                'max:255',
            ],
            'description' => [
                'nullable',
                'string',
            ],
            'sort_order' => [
                'nullable',
                'integer',
                'min:0',
            ],
            'supporting_document' => [
                'nullable',
                'file',
                'max:20480',
            ],
        ]);

        $supportingDocumentId = null;

        if (!empty($validated['supporting_document'])) {
            $document = $documentService->storeDocument(
                documentable: $surveyVisit,
                actor: $member,
                folder: 'construction/survey/entries',
                file: $validated['supporting_document'],
                companyId: $surveyVisit->project->company_id,
                projectId: $surveyVisit->project_id
            );

            $supportingDocumentId = $document->id;
        }

        $entry = SurveyEntry::create([
            'project_id' => $surveyVisit->project_id,
            'survey_visit_id' => $surveyVisit->id,
            'entry_type' => $validated['entry_type'],
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'supporting_document_id' => $supportingDocumentId,
            'captured_by_member_id' => $member->getKey(),
            'captured_at' => now(),
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        $activityService->log(
            module: 'survey_entry',
            action: 'created',
            actor: $member,
            reference: $entry,
            projectId: $surveyVisit->project_id,
            request: $request
        );

        return response()->json([
            'success' => true,
            'data' => $entry->load('supportingDocument'),
        ], 201);
    }

    public function storeMeasurement(
        SurveyVisit $surveyVisit,
        Request $request,
        ConstructionActivityService $activityService
    ) {
        $member = $request->user();

        $this->ensureVisitOwnedBy($surveyVisit, $member);

        $validated = $request->validate([
            'area_name' => [
                'nullable',
                'string',
                'max:255',
            ],
            'measurement_type' => [
                'required',
                'string',
                'max:100',
            ],
            'length' => [
                'nullable',
                'numeric',
            ],
            'width' => [
                'nullable',
                'numeric',
            ],
            'height' => [
                'nullable',
                'numeric',
            ],
            'unit' => [
                'required',
                'string',
                'max:20',
            ],
            'quantity' => [
                'nullable',
                'numeric',
            ],
            'notes' => [
                'nullable',
                'string',
            ],
        ]);

        $measurement = SurveyMeasurement::create([
            ...$validated,
            'project_id' => $surveyVisit->project_id,
            'survey_visit_id' => $surveyVisit->id,
            'captured_by_member_id' => $member->getKey(),
        ]);

        $activityService->log(
            module: 'survey_measurement',
            action: 'created',
            actor: $member,
            reference: $measurement,
            projectId: $surveyVisit->project_id,
            request: $request
        );

        return response()->json([
            'success' => true,
            'data' => $measurement,
        ], 201);
    }

  public function submitVisit(
    SurveyVisit $surveyVisit,
    Request $request,
    ConstructionActivityService $activityService
) {
    $member = $request->user();

    $this->ensureVisitOwnedBy(
        $surveyVisit,
        $member
    );

    $validated = $request->validate([
        'review_notes' => [
            'nullable',
            'string',
            'max:5000',
        ],
    ]);

    $submission = DB::transaction(
        function () use (
            $activityService,
            $member,
            $request,
            $surveyVisit,
            $validated
        ): SurveySubmission {
            $lockedSubmission =
                SurveySubmission::query()
                    ->where(
                        'survey_visit_id',
                        $surveyVisit->getKey()
                    )
                    ->lockForUpdate()
                    ->first();

            $lockedVisit =
                SurveyVisit::query()
                    ->whereKey(
                        $surveyVisit->getKey()
                    )
                    ->lockForUpdate()
                    ->firstOrFail();

            $this->ensureVisitOwnedBy(
                $lockedVisit,
                $member
            );

            $submissionData = [
                'project_id' =>
                    $lockedVisit->project_id,

                'submitted_by_member_id' =>
                    $member->getKey(),

                'submitted_at' =>
                    now(),

                'status' =>
                    SurveySubmission::STATUS_SUBMITTED,

                'review_notes' =>
                    $validated['review_notes']
                    ?? null,

                'reviewed_by_member_id' =>
                    null,

                'reviewed_at' =>
                    null,
            ];

            if ($lockedSubmission) {
                $lockedSubmission->update(
                    $submissionData
                );

                $submission =
                    $lockedSubmission;
            } else {
                $submission =
                    SurveySubmission::create([
                        'survey_visit_id' =>
                            $lockedVisit->getKey(),

                        ...$submissionData,
                    ]);
            }

            $lockedVisit->update([
                'status' =>
                    SurveyVisit::STATUS_SUBMITTED,
            ]);

            SurveyPlan::query()
                ->whereKey(
                    $lockedVisit->survey_plan_id
                )
                ->lockForUpdate()
                ->firstOrFail()
                ->update([
                    'status' =>
                        SurveyPlan::STATUS_SUBMITTED,
                ]);

            $activityService->log(
                module: 'survey_submission',
                action: 'submitted',
                actor: $member,
                reference: $submission,
                projectId:
                    $lockedVisit->project_id,
                request: $request
            );

            return $submission;
        }
    );

    return response()->json([
        'success' => true,
        'data' => $submission,
    ]);
}

    public function draftingJobs(Request $request)
    {
        $member = $request->user();

        $projectId = (int) $request->integer('project');

        return response()->json([
            'success' => true,
            'data' => DraftingJob::with([
                'project',
                'surveySubmission',
            ])
                ->where('project_id', $projectId)
                ->where(
                    'assigned_to_member_id',
                    $member->getKey()
                )
                ->latest()
                ->get(),
        ]);
    }

    public function submitRevision(
        DraftingJob $draftingJob,
        Request $request,
        ConstructionActivityService $activityService,
        ConstructionDocumentService $documentService
    ) {
        $member = $request->user();

        abort_unless(
            (int) $draftingJob->assigned_to_member_id
                === (int) $member->getKey(),
            403,
            'You are not assigned to this drafting job.'
        );

        $validated = $request->validate([
            'notes' => [
                'nullable',
                'string',
            ],
            'dwg_file' => [
                'nullable',
                'file',
                'max:51200',
            ],
            'pdf_file' => [
                'nullable',
                'file',
                'max:51200',
            ],
            'dwg_file_name' => [
                'nullable',
                'string',
                'max:255',
            ],
            'pdf_file_name' => [
                'nullable',
                'string',
                'max:255',
            ],
        ]);

        $project = $draftingJob->project;

        $dwgDocument = !empty($validated['dwg_file'])
            ? $documentService->storeDocument(
                documentable: $draftingJob,
                actor: $member,
                folder: 'construction/drawings/dwg',
                file: $validated['dwg_file'],
                companyId: $project->company_id,
                projectId: $project->id
            )
            : (
                !empty($validated['dwg_file_name'])
                    ? $documentService->createPlaceholderDocument(
                        documentable: $draftingJob,
                        actor: $member,
                        folder: 'construction/drawings/dwg',
                        originalName: $validated['dwg_file_name'],
                        companyId: $project->company_id,
                        projectId: $project->id,
                        mimeType: 'application/acad'
                    )
                    : null
            );

        $pdfDocument = !empty($validated['pdf_file'])
            ? $documentService->storeDocument(
                documentable: $draftingJob,
                actor: $member,
                folder: 'construction/drawings/pdf',
                file: $validated['pdf_file'],
                companyId: $project->company_id,
                projectId: $project->id
            )
            : (
                !empty($validated['pdf_file_name'])
                    ? $documentService->createPlaceholderDocument(
                        documentable: $draftingJob,
                        actor: $member,
                        folder: 'construction/drawings/pdf',
                        originalName: $validated['pdf_file_name'],
                        companyId: $project->company_id,
                        projectId: $project->id,
                        mimeType: 'application/pdf'
                    )
                    : null
            );

        $revision = DrawingRevision::create([
            'project_id' => $project->id,
            'drafting_job_id' => $draftingJob->id,
            'revision_no' => (int) $draftingJob
                    ->drawingRevisions()
                    ->max('revision_no') + 1,
            'dwg_document_id' => $dwgDocument?->id,
            'pdf_document_id' => $pdfDocument?->id,
            'notes' => $validated['notes'] ?? null,
            'uploaded_by_member_id' => $member->getKey(),
            'uploaded_at' => now(),
            'status' => 'submitted',
        ]);

        $approval = DrawingApproval::create([
            'project_id' => $project->id,
            'drawing_revision_id' => $revision->id,
            'requested_by_type' => $member::class,
            'requested_by_id' => $member->getKey(),
            'requested_at' => now(),
            'decision' => 'pending',
        ]);

        $draftingJob->update([
            'status' => 'submitted',
        ]);

        $project->update([
            'current_stage' => 'drawing_approval_pending',
        ]);

        $activityService->log(
            module: 'drawing_revision',
            action: 'submitted',
            actor: $member,
            reference: $revision,
            projectId: $project->id,
            request: $request
        );

        return response()->json([
            'success' => true,
            'data' => [
                'revision' => $revision,
                'approval' => $approval,
            ],
        ], 201);
    }

    public function assignedTasks(Request $request)
    {
        $member = $request->user();

        $projectId = (int) $request->integer('project');

        return response()->json([
            'success' => true,
            'data' => ExecutionTask::with([
                'project',
                'executionPlan',
                'supervisor',
                'assignees.member',
            ])
                ->where('project_id', $projectId)
                ->whereHas(
                    'assignees',
                    function ($query) use ($member) {
                        $query
                            ->where(
                                'member_id',
                                $member->getKey()
                            )
                            ->where('status', 'active');
                    }
                )
                ->latest()
                ->get(),
        ]);
    }

    public function attendanceCheckIn(
        Request $request,
        ConstructionExecutionService $executionService
    ) {
        $member = $request->user();

        $validated = $request->validate([
            'project_id' => [
                'required',
                'exists:construction_projects,id',
            ],
            'execution_task_id' => [
                'nullable',
                'exists:construction_execution_tasks,id',
            ],
            'attendance_date' => [
                'required',
                'date',
            ],
            'check_in_latitude' => [
                'nullable',
                'numeric',
            ],
            'check_in_longitude' => [
                'nullable',
                'numeric',
            ],
            'gps_accuracy_meters' => [
                'nullable',
                'numeric',
                'min:0',
            ],
            'attendance_type' => [
                'nullable',
                'in:present,half_day,overtime',
            ],
            'notes' => [
                'nullable',
                'string',
            ],
        ]);

        $project = Project::findOrFail(
            $validated['project_id']
        );

        $attendance = $executionService->checkInAttendance(
            $project,
            $validated,
            $member,
            $request
        );

        return response()->json([
            'success' => true,
            'data' => $attendance,
        ], 201);
    }

    public function attendanceCheckOut(
        AttendanceRecord $attendance,
        Request $request,
        ConstructionExecutionService $executionService
    ) {
        $member = $request->user();

        abort_unless(
            (int) $attendance->member_id
                === (int) $member->getKey(),
            403,
            'You can only check out your own attendance record.'
        );

        $validated = $request->validate([
            'check_out_latitude' => [
                'nullable',
                'numeric',
            ],
            'check_out_longitude' => [
                'nullable',
                'numeric',
            ],
            'gps_accuracy_meters' => [
                'nullable',
                'numeric',
                'min:0',
            ],
            'notes' => [
                'nullable',
                'string',
            ],
        ]);

        $attendance = $executionService->checkOutAttendance(
            $attendance,
            $validated,
            $member,
            $request
        );

        return response()->json([
            'success' => true,
            'data' => $attendance,
        ]);
    }

    public function updateTaskProgress(
        ExecutionTask $task,
        Request $request,
        ConstructionExecutionService $executionService
    ) {
        $member = $request->user();

        $isAssigned = ExecutionTaskAssignee::where(
            'execution_task_id',
            $task->id
        )
            ->where('member_id', $member->getKey())
            ->where('status', 'active')
            ->exists();

        abort_unless(
            $isAssigned,
            403,
            'You are not assigned to this task.'
        );

        $validated = $request->validate([
            'progress_percent' => [
                'required',
                'numeric',
                'min:0',
                'max:100',
            ],
            'completed_quantity' => [
                'nullable',
                'numeric',
                'min:0',
            ],
            'status' => [
                'required',
                'in:planned,in_progress,completed,blocked',
            ],
        ]);

        $task = $executionService->updateTaskProgress(
            $task,
            $validated,
            $member,
            $request
        );

        return response()->json([
            'success' => true,
            'data' => $task,
        ]);
    }

    public function submitDailyProgress(
        Request $request,
        ConstructionExecutionService $executionService
    ) {
        $member = $request->user();

        $validated = $request->validate([
            'project_id' => [
                'required',
                'exists:construction_projects,id',
            ],
            'execution_task_id' => [
                'nullable',
                'exists:construction_execution_tasks,id',
            ],
            'report_date' => [
                'required',
                'date',
            ],
            'summary' => [
                'nullable',
                'string',
            ],
            'work_completed' => [
                'nullable',
                'string',
            ],
            'blockers' => [
                'nullable',
                'string',
            ],
            'workforce_count' => [
                'nullable',
                'integer',
                'min:0',
            ],
            'latitude' => [
                'nullable',
                'numeric',
            ],
            'longitude' => [
                'nullable',
                'numeric',
            ],
            'gps_accuracy_meters' => [
                'nullable',
                'numeric',
                'min:0',
            ],
            'weather_summary' => [
                'nullable',
                'string',
                'max:255',
            ],
            'items' => [
                'nullable',
                'array',
            ],
            'items.*.execution_task_id' => [
                'nullable',
                'exists:construction_execution_tasks,id',
            ],
            'items.*.title' => [
                'required_with:items',
                'string',
                'max:255',
            ],
            'items.*.description' => [
                'nullable',
                'string',
            ],
            'items.*.unit' => [
                'nullable',
                'string',
                'max:50',
            ],
            'items.*.planned_quantity' => [
                'nullable',
                'numeric',
                'min:0',
            ],
            'items.*.completed_quantity' => [
                'nullable',
                'numeric',
                'min:0',
            ],
            'items.*.percent_complete' => [
                'nullable',
                'numeric',
                'min:0',
                'max:100',
            ],
            'items.*.remarks' => [
                'nullable',
                'string',
            ],
        ]);

        $project = Project::findOrFail(
            $validated['project_id']
        );

        // Prevent cross-project task IDs in the report header.
        if (!empty($validated['execution_task_id'])) {
            $this->ensureTaskInProject(
                (int) $validated['execution_task_id'],
                $project->id
            );
        }

        // Prevent cross-project task IDs in report items.
        foreach ($validated['items'] ?? [] as $item) {
            if (!empty($item['execution_task_id'])) {
                $this->ensureTaskInProject(
                    (int) $item['execution_task_id'],
                    $project->id
                );
            }
        }

        $report = $executionService->submitDailyProgress(
            $project,
            $validated,
            $member,
            $request
        );

        return response()->json([
            'success' => true,
            'data' => $report,
        ], 201);
    }

    public function vehicles(
        Project $project,
        Request $request
    ) {
        $member = $request->user();

        return response()->json([
            'success' => true,
            'data' => [
                'vehicles' => ConstructionVehicle::where(
                    'project_id',
                    $project->id
                )
                    ->latest()
                    ->get(),
                'pings' => VehicleLocationPing::with([
                    'vehicle',
                    'reportedBy',
                ])
                    ->where('project_id', $project->id)
                    ->latest('recorded_at')
                    ->take(100)
                    ->get(),
            ],
        ]);
    }

    public function vehiclePing(
        Project $project,
        Request $request,
        ConstructionFleetService $fleetService
    ) {
        $member = $request->user();

        $validated = $request->validate([
            'vehicle_id' => [
                'required',
                'exists:construction_vehicles,id',
            ],
            'recorded_at' => [
                'nullable',
                'date',
            ],
            'latitude' => [
                'required',
                'numeric',
            ],
            'longitude' => [
                'required',
                'numeric',
            ],
            'gps_accuracy_meters' => [
                'nullable',
                'numeric',
                'min:0',
            ],
            'speed_kmph' => [
                'nullable',
                'numeric',
                'min:0',
            ],
            'heading_degrees' => [
                'nullable',
                'numeric',
                'min:0',
                'max:360',
            ],
            'odometer_km' => [
                'nullable',
                'numeric',
                'min:0',
            ],
            'source' => [
                'nullable',
                'string',
                'max:30',
            ],
        ]);

        $vehicle = ConstructionVehicle::findOrFail(
            $validated['vehicle_id']
        );

        $this->ensureResourceInProject(
            $vehicle,
            $project->id
        );

        // Driver-specific action: the authenticated member must be the active
        // assigned driver for this vehicle. Never trust a body-supplied
        // reported_by_member_id.
        $this->ensureVehicleDriverAssignment(
            $vehicle,
            $member
        );

        $ping = $fleetService->recordLocationPing(
            $project,
            $validated,
            $member,
            $request
        );

        return response()->json([
            'success' => true,
            'data' => $ping,
        ], 201);
    }

    public function equipment(
        Project $project,
        Request $request
    ) {
        $member = $request->user();

        return response()->json([
            'success' => true,
            'data' => [
                'equipments' => ConstructionEquipment::where(
                    'project_id',
                    $project->id
                )
                    ->latest()
                    ->get(),
                'allocations' => EquipmentAllocation::with([
                    'equipment',
                    'assignedTo',
                ])
                    ->where('project_id', $project->id)
                    ->latest()
                    ->get(),
                'usage_logs' => EquipmentUsageLog::with([
                    'equipment',
                    'member',
                ])
                    ->where('project_id', $project->id)
                    ->latest()
                    ->take(100)
                    ->get(),
            ],
        ]);
    }

    public function equipmentUsage(
        Project $project,
        Request $request,
        ConstructionEquipmentService $equipmentService
    ) {
        $member = $request->user();

        $validated = $request->validate([
            'equipment_id' => [
                'required',
                'exists:construction_equipments,id',
            ],
            'log_date' => [
                'required',
                'date',
            ],
            'hours_used' => [
                'required',
                'numeric',
                'min:0.01',
            ],
            'latitude' => [
                'nullable',
                'numeric',
            ],
            'longitude' => [
                'nullable',
                'numeric',
            ],
            'gps_accuracy_meters' => [
                'nullable',
                'numeric',
                'min:0',
            ],
            'notes' => [
                'nullable',
                'string',
            ],
        ]);

        $equipment = ConstructionEquipment::findOrFail(
            $validated['equipment_id']
        );

        $this->ensureResourceInProject(
            $equipment,
            $project->id
        );

        // Work assignment: the member must have an active allocation for this
        // equipment. Never trust a body-supplied member_id.
        $this->ensureEquipmentAllocationAccess(
            $equipment,
            $member
        );

        $log = $equipmentService->recordUsage(
            $project,
            $validated,
            $member,
            $request
        );

        return response()->json([
            'success' => true,
            'data' => $log,
        ], 201);
    }

    public function equipmentReturn(
        Project $project,
        Request $request,
        ConstructionEquipmentService $equipmentService
    ) {
        $member = $request->user();

        $validated = $request->validate([
            'allocation_id' => [
                'required',
                'exists:construction_equipment_allocations,id',
            ],
            'returned_at' => [
                'nullable',
                'date',
            ],
            'return_latitude' => [
                'nullable',
                'numeric',
            ],
            'return_longitude' => [
                'nullable',
                'numeric',
            ],
            'return_gps_accuracy_meters' => [
                'nullable',
                'numeric',
                'min:0',
            ],
        ]);

        $allocation = EquipmentAllocation::findOrFail(
            $validated['allocation_id']
        );

        $this->ensureResourceInProject(
            $allocation,
            $project->id
        );

        // Only the assigned member (or a manager with equipment_allocation.manage)
        // may return the allocation. Inactive/returned allocations are rejected.
        $this->ensureEquipmentAllocationReturnAccess(
            $allocation,
            $member
        );

        $allocation = $equipmentService->returnEquipment(
            $project,
            $validated,
            $member,
            $request
        );

        return response()->json([
            'success' => true,
            'data' => $allocation,
        ]);
    }

    public function billing(
        Project $project,
        Request $request
    ) {
        $member = $request->user();

        return response()->json([
            'success' => true,
            'data' => [
                'invoices' => ClientInvoice::with([
                    'items',
                    'payments',
                ])
                    ->where('project_id', $project->id)
                    ->latest()
                    ->get(),
                'payments' => ClientPayment::where(
                    'project_id',
                    $project->id
                )
                    ->latest()
                    ->get(),
            ],
        ]);
    }

    public function handover(
        Project $project,
        Request $request
    ) {
        $member = $request->user();

        return response()->json([
            'success' => true,
            'data' => ProjectHandover::with([
                'items',
                'finalDocument',
            ])
                ->where('project_id', $project->id)
                ->latest()
                ->get(),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Private authorization helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Prevent cross-project IDOR for any route/body resource ID.
     */
    private function ensureResourceInProject(
        Model $resource,
        int $projectId
    ): void {
        abort_unless(
            (int) $resource->getAttribute('project_id')
                === $projectId,
            403,
            'Resource does not belong to this project.'
        );
    }

    /**
     * Prevent cross-project execution task IDs in DPR submissions.
     */
    private function ensureTaskInProject(
        int $taskId,
        int $projectId
    ): void {
        abort_unless(
            ExecutionTask::where('id', $taskId)
                ->where('project_id', $projectId)
                ->exists(),
            403,
            'Execution task does not belong to this project.'
        );
    }

    /**
     * Survey work assignment: the member must have an active SurveyPlanMember
     * record for the survey plan.
     *
     * The schema default status is 'assigned'; 'active' is also accepted for
     * backward compatibility with any code that uses the active convention.
     */
    private function ensureSurveyAssignment(
        SurveyPlan $surveyPlan,
        Member $member
    ): void {
        abort_unless(
            SurveyPlanMember::where(
                'survey_plan_id',
                $surveyPlan->id
            )
                ->where('member_id', $member->getKey())
                ->whereIn('status', ['assigned', 'active'])
                ->exists(),
            403,
            'You are not actively assigned to this survey plan.'
        );
    }

    /**
     * Execution work assignment: the member must have an ACTIVE
     * ExecutionTaskAssignee record for the task.
     */
    private function ensureTaskAssignment(
        ExecutionTask $task,
        Member $member
    ): void {
        abort_unless(
            ExecutionTaskAssignee::where(
                'execution_task_id',
                $task->id
            )
                ->where('member_id', $member->getKey())
                ->where('status', 'active')
                ->exists(),
            403,
            'You are not actively assigned to this task.'
        );
    }

    /**
     * Vehicle driver work assignment: the authenticated member must be the
     * active assigned driver for the vehicle.
     */
    private function ensureVehicleDriverAssignment(
        ConstructionVehicle $vehicle,
        Member $member
    ): void {
        abort_unless(
            VehicleAssignment::where(
                'vehicle_id',
                $vehicle->id
            )
                ->where(
                    'driver_member_id',
                    $member->getKey()
                )
                ->where('status', 'active')
                ->exists(),
            403,
            'You are not the active assigned driver for this vehicle.'
        );
    }

    /**
     * Equipment work assignment: the member must have an ACTIVE allocation
     * for the equipment.
     */
    private function ensureEquipmentAllocationAccess(
        ConstructionEquipment $equipment,
        Member $member
    ): void {
        abort_unless(
            EquipmentAllocation::where(
                'equipment_id',
                $equipment->id
            )
                ->where(
                    'assigned_to_member_id',
                    $member->getKey()
                )
                ->where('status', 'active')
                ->exists(),
            403,
            'You do not have an active allocation for this equipment.'
        );
    }

    /**
     * Equipment return access: only the assigned member may return an active
     * allocation. Inactive/returned allocations are rejected.
     */
    private function ensureEquipmentAllocationReturnAccess(
        EquipmentAllocation $allocation,
        Member $member
    ): void {
        abort_unless(
            (int) $allocation->assigned_to_member_id
                === (int) $member->getKey(),
            403,
            'You are not the assigned member for this allocation.'
        );

        abort_unless(
            $allocation->status === 'active',
            403,
            'Only active allocations can be returned.'
        );
    }

    /**
     * Survey visit ownership: the member must own the visit they are mutating.
     */
   private function ensureVisitOwnedBy(
    SurveyVisit $surveyVisit,
    Member $member
): void {
    abort_unless(
        (int) $surveyVisit->checked_in_by_member_id
            === (int) $member->getKey(),
        403,
        'You are not allowed to modify this survey visit.'
    );

    abort_unless(
        $surveyVisit->status
            === SurveyVisit::STATUS_IN_PROGRESS,
        409,
        'Only an in-progress survey visit can be modified or submitted.'
    );
}
}