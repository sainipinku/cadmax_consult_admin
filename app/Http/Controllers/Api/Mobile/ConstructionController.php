<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Construction\Document;
use App\Models\Construction\AttendanceRecord;
use App\Models\Construction\DailyProgressReport;
use App\Models\Construction\ClientInvoice;
use App\Models\Construction\ClientPayment;
use App\Models\Construction\DraftingJob;
use App\Models\Construction\Equipment;
use App\Models\Construction\EquipmentAllocation;
use App\Models\Construction\EquipmentUsageLog;
use App\Models\Construction\Project;
use App\Models\Construction\ProjectHandover;
use App\Models\Construction\ProjectTeamMember;
use App\Models\Construction\Vehicle;
use App\Models\Construction\VehicleLocationPing;
use App\Models\Construction\ExecutionTask;
use App\Models\Construction\ExecutionTaskAssignee;
use App\Models\Construction\SurveyEntry;
use App\Models\Construction\SurveyMeasurement;
use App\Models\Construction\SurveyPlan;
use App\Models\Construction\SurveyPlanMember;
use App\Models\Construction\SurveySubmission;
use App\Models\Construction\SurveyVisit;
use App\Models\Construction\DrawingRevision;
use App\Models\Construction\DrawingApproval;
use App\Services\Construction\ConstructionActivityService;
use App\Services\Construction\ConstructionBillingService;
use App\Services\Construction\ConstructionDocumentService;
use App\Services\Construction\ConstructionEquipmentService;
use App\Services\Construction\ConstructionExecutionService;
use App\Services\Construction\ConstructionFleetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConstructionController extends Controller
{
    private function ensureProjectMembership(Project $project, object $member): void
    {
        abort_unless(
            ProjectTeamMember::where('project_id', $project->id)
                ->where('member_id', $member->getKey())
                ->where('status', 'active')
                ->exists(),
            403,
            'You are not assigned to this project.'
        );
    }

    public function assignedProjects(Request $request): JsonResponse
    {
        $member = $request->user();

        $teamProjectIds = ProjectTeamMember::where('member_id', $member->getKey())->pluck('project_id');
        $surveyProjectIds = SurveyPlan::whereHas('planMembers', function ($query) use ($member) {
            $query->where('member_id', $member->getKey());
        })->pluck('project_id');

        $projectIds = $teamProjectIds->merge($surveyProjectIds)->unique()->values();

        return response()->json([
            'success' => true,
            'data' => Project::with(['client', 'latestBudget'])
                ->whereIn('id', $projectIds)
                ->get(),
        ]);
    }

    public function showSurveyPlan(SurveyPlan $surveyPlan): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $surveyPlan->load(['project.client', 'planMembers.member', 'visits']),
        ]);
    }

    public function checkIn(Request $request, ConstructionActivityService $activityService): JsonResponse
    {
        $member = $request->user();

        $validated = $request->validate([
            'survey_plan_id' => ['required', 'exists:construction_survey_plans,id'],
            'check_in_latitude' => ['nullable', 'numeric'],
            'check_in_longitude' => ['nullable', 'numeric'],
            'gps_distance_meters' => ['nullable', 'numeric'],
        ]);

        $surveyPlan = SurveyPlan::findOrFail($validated['survey_plan_id']);

        $visit = SurveyVisit::create([
            'project_id' => $surveyPlan->project_id,
            'survey_plan_id' => $surveyPlan->id,
            'checked_in_by_member_id' => $member->getKey(),
            'check_in_at' => now(),
            'check_in_latitude' => $validated['check_in_latitude'] ?? null,
            'check_in_longitude' => $validated['check_in_longitude'] ?? null,
            'gps_distance_meters' => $validated['gps_distance_meters'] ?? null,
            'gps_verified' => (float) ($validated['gps_distance_meters'] ?? 99999) <= 150,
            'status' => 'in_progress',
        ]);

        $surveyPlan->update(['status' => 'in_progress']);
        $surveyPlan->project->update(['current_stage' => 'survey_in_progress']);

        $activityService->log(
            module: 'survey_visit',
            action: 'check_in',
            actor: $member,
            reference: $visit,
            companyId: $surveyPlan->project->company_id,
            projectId: $surveyPlan->project_id,
            request: $request
        );

        return response()->json(['success' => true, 'data' => $visit], 201);
    }

    public function storeEntry(
        SurveyVisit $surveyVisit,
        Request $request,
        ConstructionActivityService $activityService,
        ConstructionDocumentService $documentService
    ): JsonResponse
    {
        $member = $request->user();

        $validated = $request->validate([
            'entry_type' => ['required', 'in:photo,video,note,voice,observation'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'supporting_document' => ['nullable', 'file', 'max:20480'],
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

        return response()->json(['success' => true, 'data' => $entry->load('supportingDocument')], 201);
    }

    public function storeMeasurement(SurveyVisit $surveyVisit, Request $request, ConstructionActivityService $activityService): JsonResponse
    {
        $member = $request->user();

        $validated = $request->validate([
            'area_name' => ['nullable', 'string', 'max:255'],
            'measurement_type' => ['required', 'string', 'max:100'],
            'length' => ['nullable', 'numeric'],
            'width' => ['nullable', 'numeric'],
            'height' => ['nullable', 'numeric'],
            'unit' => ['required', 'string', 'max:20'],
            'quantity' => ['nullable', 'numeric'],
            'notes' => ['nullable', 'string'],
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

        return response()->json(['success' => true, 'data' => $measurement], 201);
    }

    public function submitVisit(SurveyVisit $surveyVisit, Request $request, ConstructionActivityService $activityService): JsonResponse
    {
        $member = $request->user();

        $validated = $request->validate([
            'review_notes' => ['nullable', 'string'],
        ]);

        $submission = SurveySubmission::updateOrCreate(
            ['survey_visit_id' => $surveyVisit->id],
            [
                'project_id' => $surveyVisit->project_id,
                'submitted_by_member_id' => $member->getKey(),
                'submitted_at' => now(),
                'status' => 'submitted',
                'review_notes' => $validated['review_notes'] ?? null,
            ]
        );

        $surveyVisit->update(['status' => 'submitted']);
        $surveyVisit->surveyPlan->update(['status' => 'submitted']);

        $activityService->log(
            module: 'survey_submission',
            action: 'submitted',
            actor: $member,
            reference: $submission,
            projectId: $surveyVisit->project_id,
            request: $request
        );

        return response()->json(['success' => true, 'data' => $submission]);
    }

    public function draftingJobs(Request $request): JsonResponse
    {
        $member = $request->user();

        return response()->json([
            'success' => true,
            'data' => DraftingJob::with(['project', 'surveySubmission'])
                ->where('assigned_to_member_id', $member->getKey())
                ->latest()
                ->get(),
        ]);
    }

    public function submitRevision(
        DraftingJob $draftingJob,
        Request $request,
        ConstructionActivityService $activityService,
        ConstructionDocumentService $documentService
    ): JsonResponse
    {
        $member = $request->user();

        $validated = $request->validate([
            'notes' => ['nullable', 'string'],
            'dwg_file' => ['nullable', 'file', 'max:51200'],
            'pdf_file' => ['nullable', 'file', 'max:51200'],
            'dwg_file_name' => ['nullable', 'string', 'max:255'],
            'pdf_file_name' => ['nullable', 'string', 'max:255'],
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
            : (!empty($validated['dwg_file_name'])
            ? $documentService->createPlaceholderDocument(
                documentable: $draftingJob,
                actor: $member,
                folder: 'construction/drawings/dwg',
                originalName: $validated['dwg_file_name'],
                companyId: $project->company_id,
                projectId: $project->id,
                mimeType: 'application/acad'
            )
            : null);

        $pdfDocument = !empty($validated['pdf_file'])
            ? $documentService->storeDocument(
                documentable: $draftingJob,
                actor: $member,
                folder: 'construction/drawings/pdf',
                file: $validated['pdf_file'],
                companyId: $project->company_id,
                projectId: $project->id
            )
            : (!empty($validated['pdf_file_name'])
            ? $documentService->createPlaceholderDocument(
                documentable: $draftingJob,
                actor: $member,
                folder: 'construction/drawings/pdf',
                originalName: $validated['pdf_file_name'],
                companyId: $project->company_id,
                projectId: $project->id,
                mimeType: 'application/pdf'
            )
            : null);

        $revision = DrawingRevision::create([
            'project_id' => $project->id,
            'drafting_job_id' => $draftingJob->id,
            'revision_no' => (int) $draftingJob->drawingRevisions()->max('revision_no') + 1,
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

        $draftingJob->update(['status' => 'submitted']);
        $project->update(['current_stage' => 'drawing_approval_pending']);

        $activityService->log(
            module: 'drawing_revision',
            action: 'submitted',
            actor: $member,
            reference: $revision,
            projectId: $project->id,
            request: $request
        );

        return response()->json(['success' => true, 'data' => ['revision' => $revision, 'approval' => $approval]], 201);
    }

    public function assignedTasks(Request $request): JsonResponse
    {
        $member = $request->user();

        return response()->json([
            'success' => true,
            'data' => ExecutionTask::with(['project', 'executionPlan', 'supervisor', 'assignees.member'])
                ->whereHas('assignees', function ($query) use ($member) {
                    $query->where('member_id', $member->getKey())->where('status', 'active');
                })
                ->latest()
                ->get(),
        ]);
    }

    public function attendanceCheckIn(
        Request $request,
        ConstructionExecutionService $executionService
    ): JsonResponse {
        $member = $request->user();

        $validated = $request->validate([
            'project_id' => ['required', 'exists:construction_projects,id'],
            'execution_task_id' => ['nullable', 'exists:construction_execution_tasks,id'],
            'attendance_date' => ['required', 'date'],
            'check_in_latitude' => ['nullable', 'numeric'],
            'check_in_longitude' => ['nullable', 'numeric'],
            'gps_accuracy_meters' => ['nullable', 'numeric', 'min:0'],
            'attendance_type' => ['nullable', 'in:present,half_day,overtime'],
            'notes' => ['nullable', 'string'],
        ]);

        $project = Project::findOrFail($validated['project_id']);
        $this->ensureProjectMembership($project, $member);
        $attendance = $executionService->checkInAttendance($project, $validated, $member, $request);

        return response()->json(['success' => true, 'data' => $attendance], 201);
    }

    public function attendanceCheckOut(
        AttendanceRecord $attendance,
        Request $request,
        ConstructionExecutionService $executionService
    ): JsonResponse {
        $member = $request->user();

        abort_unless((int) $attendance->member_id === (int) $member->getKey(), 403, 'You can only check out your own attendance record.');

        $validated = $request->validate([
            'check_out_latitude' => ['nullable', 'numeric'],
            'check_out_longitude' => ['nullable', 'numeric'],
            'gps_accuracy_meters' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $attendance = $executionService->checkOutAttendance($attendance, $validated, $member, $request);

        return response()->json(['success' => true, 'data' => $attendance]);
    }

    public function updateTaskProgress(
        ExecutionTask $task,
        Request $request,
        ConstructionExecutionService $executionService
    ): JsonResponse {
        $member = $request->user();

        $isAssigned = ExecutionTaskAssignee::where('execution_task_id', $task->id)
            ->where('member_id', $member->getKey())
            ->where('status', 'active')
            ->exists();

        abort_unless($isAssigned, 403, 'You are not assigned to this task.');

        $validated = $request->validate([
            'progress_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'completed_quantity' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', 'in:planned,in_progress,completed,blocked'],
        ]);

        $task = $executionService->updateTaskProgress($task, $validated, $member, $request);

        return response()->json(['success' => true, 'data' => $task]);
    }

    public function submitDailyProgress(
        Request $request,
        ConstructionExecutionService $executionService
    ): JsonResponse {
        $member = $request->user();

        $validated = $request->validate([
            'project_id' => ['required', 'exists:construction_projects,id'],
            'execution_task_id' => ['nullable', 'exists:construction_execution_tasks,id'],
            'report_date' => ['required', 'date'],
            'summary' => ['nullable', 'string'],
            'work_completed' => ['nullable', 'string'],
            'blockers' => ['nullable', 'string'],
            'workforce_count' => ['nullable', 'integer', 'min:0'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'gps_accuracy_meters' => ['nullable', 'numeric', 'min:0'],
            'weather_summary' => ['nullable', 'string', 'max:255'],
            'items' => ['nullable', 'array'],
            'items.*.execution_task_id' => ['nullable', 'exists:construction_execution_tasks,id'],
            'items.*.title' => ['required_with:items', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string'],
            'items.*.unit' => ['nullable', 'string', 'max:50'],
            'items.*.planned_quantity' => ['nullable', 'numeric', 'min:0'],
            'items.*.completed_quantity' => ['nullable', 'numeric', 'min:0'],
            'items.*.percent_complete' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.remarks' => ['nullable', 'string'],
        ]);

        $project = Project::findOrFail($validated['project_id']);
        $this->ensureProjectMembership($project, $member);
        $report = $executionService->submitDailyProgress($project, $validated, $member, $request);

        return response()->json(['success' => true, 'data' => $report], 201);
    }

    public function vehicles(Project $project, Request $request): JsonResponse
    {
        $member = $request->user();
        $this->ensureProjectMembership($project, $member);

        return response()->json([
            'success' => true,
            'data' => [
                'vehicles' => Vehicle::where('project_id', $project->id)->latest()->get(),
                'pings' => VehicleLocationPing::with(['vehicle', 'reportedBy'])
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
    ): JsonResponse {
        $member = $request->user();
        $this->ensureProjectMembership($project, $member);

        $validated = $request->validate([
            'vehicle_id' => ['required', 'exists:construction_vehicles,id'],
            'recorded_at' => ['nullable', 'date'],
            'latitude' => ['required', 'numeric'],
            'longitude' => ['required', 'numeric'],
            'gps_accuracy_meters' => ['nullable', 'numeric', 'min:0'],
            'speed_kmph' => ['nullable', 'numeric', 'min:0'],
            'heading_degrees' => ['nullable', 'numeric', 'min:0', 'max:360'],
            'odometer_km' => ['nullable', 'numeric', 'min:0'],
            'source' => ['nullable', 'string', 'max:30'],
        ]);

        $ping = $fleetService->recordLocationPing($project, $validated, $member, $request);

        return response()->json(['success' => true, 'data' => $ping], 201);
    }

    public function equipment(Project $project, Request $request): JsonResponse
    {
        $member = $request->user();
        $this->ensureProjectMembership($project, $member);

        return response()->json([
            'success' => true,
            'data' => [
                'equipments' => Equipment::where('project_id', $project->id)->latest()->get(),
                'allocations' => EquipmentAllocation::with(['equipment', 'assignedTo'])
                    ->where('project_id', $project->id)
                    ->latest()
                    ->get(),
                'usage_logs' => EquipmentUsageLog::with(['equipment', 'member'])
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
    ): JsonResponse {
        $member = $request->user();
        $this->ensureProjectMembership($project, $member);

        $validated = $request->validate([
            'equipment_id' => ['required', 'exists:construction_equipments,id'],
            'log_date' => ['required', 'date'],
            'hours_used' => ['required', 'numeric', 'min:0.01'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'gps_accuracy_meters' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $log = $equipmentService->recordUsage($project, $validated, $member, $request);

        return response()->json(['success' => true, 'data' => $log], 201);
    }

    public function equipmentReturn(
        Project $project,
        Request $request,
        ConstructionEquipmentService $equipmentService
    ): JsonResponse {
        $member = $request->user();
        $this->ensureProjectMembership($project, $member);

        $validated = $request->validate([
            'allocation_id' => ['required', 'exists:construction_equipment_allocations,id'],
            'returned_at' => ['nullable', 'date'],
            'return_latitude' => ['nullable', 'numeric'],
            'return_longitude' => ['nullable', 'numeric'],
            'return_gps_accuracy_meters' => ['nullable', 'numeric', 'min:0'],
        ]);

        $allocation = $equipmentService->returnEquipment($project, $validated, $member, $request);

        return response()->json(['success' => true, 'data' => $allocation]);
    }

    public function billing(Project $project, Request $request): JsonResponse
    {
        $member = $request->user();
        $this->ensureProjectMembership($project, $member);

        return response()->json([
            'success' => true,
            'data' => [
                'invoices' => ClientInvoice::with(['items', 'payments'])
                    ->where('project_id', $project->id)
                    ->latest()
                    ->get(),
                'payments' => ClientPayment::where('project_id', $project->id)
                    ->latest()
                    ->get(),
            ],
        ]);
    }

    public function handover(Project $project, Request $request): JsonResponse
    {
        $member = $request->user();
        $this->ensureProjectMembership($project, $member);

        return response()->json([
            'success' => true,
            'data' => ProjectHandover::with(['items', 'finalDocument'])
                ->where('project_id', $project->id)
                ->latest()
                ->get(),
        ]);
    }
}
