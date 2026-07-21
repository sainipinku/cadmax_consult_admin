<?php

namespace App\Http\Controllers\SuperAdmin\Construction;

use App\Http\Controllers\Concerns\ResolvesConstructionActor;
use App\Http\Controllers\Controller;
use App\Models\Construction\Document;
use App\Models\Construction\DraftingJob;
use App\Models\Construction\DrawingApproval;
use App\Models\Construction\DrawingRevision;
use App\Models\Construction\SurveySubmission;
use App\Models\Member;
use App\Services\Construction\ConstructionActivityService;
use App\Services\Construction\ConstructionDocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DraftingController extends Controller
{
    use ResolvesConstructionActor;

    public function index(): Response
    {
        return Inertia::render('SuperAdmin/Construction/Drafting/Index', [
            'draftingJobs' => DraftingJob::with([
                'project.company',
                'surveySubmission.submittedBy',
                'assignedTo',
                'drawingRevisions.uploadedBy',
                'drawingRevisions.dwgDocument',
                'drawingRevisions.pdfDocument',
                'drawingRevisions.approvals.approvedBy',
            ])
                ->latest()
                ->get(),
            'approvedSurveySubmissions' => SurveySubmission::with('project')
                ->where('status', 'approved')
                ->orderByDesc('submitted_at')
                ->get(),
            'members' => Member::orderBy('name')->get(['id', 'name', 'email']),
        ]);
    }

    public function createJob(Request $request, ConstructionActivityService $activityService): RedirectResponse
    {
        $actor = $this->constructionActor();

        $validated = $request->validate([
            'survey_submission_id' => ['required', 'exists:construction_survey_submissions,id'],
            'assigned_to_member_id' => ['nullable', 'exists:members,id'],
            'due_date' => ['nullable', 'date'],
        ]);

        $submission = SurveySubmission::with('project')->findOrFail($validated['survey_submission_id']);

        $job = DraftingJob::create([
            'project_id' => $submission->project_id,
            'survey_submission_id' => $submission->id,
            'assigned_to_member_id' => $validated['assigned_to_member_id'] ?? null,
            'assigned_by_type' => $actor ? $actor::class : null,
            'assigned_by_id' => $actor?->getKey(),
            'assigned_at' => now(),
            'due_date' => $validated['due_date'] ?? null,
            'status' => 'queued',
        ]);

        $submission->project->update(['current_stage' => 'drafting_in_progress']);

        $activityService->log(
            module: 'drafting',
            action: 'job_created',
            actor: $actor,
            reference: $job,
            companyId: $submission->project->company_id,
            projectId: $submission->project_id,
            request: $request
        );

        return back()->with('success', 'Drafting job created successfully.');
    }

    public function storeRevision(
        DraftingJob $draftingJob,
        Request $request,
        ConstructionActivityService $activityService,
        ConstructionDocumentService $documentService
    ): RedirectResponse
    {
        $actor = $this->constructionActor();

        $validated = $request->validate([
            'notes' => ['nullable', 'string'],
            'dwg_file' => ['nullable', 'file', 'max:51200'],
            'pdf_file' => ['nullable', 'file', 'max:51200'],
            'dwg_file_name' => ['nullable', 'string', 'max:255'],
            'pdf_file_name' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'in:draft,submitted'],
        ]);

        $dwgDocument = null;
        $pdfDocument = null;
        $project = $draftingJob->project;
        $actorMemberId = $actor instanceof Member ? $actor->getKey() : null;

        if (!empty($validated['dwg_file'])) {
            $dwgDocument = $documentService->storeDocument(
                documentable: $draftingJob,
                actor: $actor,
                folder: 'construction/drawings/dwg',
                file: $validated['dwg_file'],
                companyId: $project->company_id,
                projectId: $project->id
            );
        } elseif (!empty($validated['dwg_file_name'])) {
            $dwgDocument = $documentService->createPlaceholderDocument(
                documentable: $draftingJob,
                actor: $actor,
                folder: 'construction/drawings/dwg',
                originalName: $validated['dwg_file_name'],
                companyId: $project->company_id,
                projectId: $project->id,
                mimeType: 'application/acad'
            );
        }

        if (!empty($validated['pdf_file'])) {
            $pdfDocument = $documentService->storeDocument(
                documentable: $draftingJob,
                actor: $actor,
                folder: 'construction/drawings/pdf',
                file: $validated['pdf_file'],
                companyId: $project->company_id,
                projectId: $project->id
            );
        } elseif (!empty($validated['pdf_file_name'])) {
            $pdfDocument = $documentService->createPlaceholderDocument(
                documentable: $draftingJob,
                actor: $actor,
                folder: 'construction/drawings/pdf',
                originalName: $validated['pdf_file_name'],
                companyId: $project->company_id,
                projectId: $project->id,
                mimeType: 'application/pdf'
            );
        }

        $revision = DrawingRevision::create([
            'project_id' => $project->id,
            'drafting_job_id' => $draftingJob->id,
            'revision_no' => (int) $draftingJob->drawingRevisions()->max('revision_no') + 1,
            'dwg_document_id' => $dwgDocument?->id,
            'pdf_document_id' => $pdfDocument?->id,
            'notes' => $validated['notes'] ?? null,
            'uploaded_by_member_id' => $actorMemberId,
            'uploaded_at' => now(),
            'status' => $validated['status'],
        ]);

        if ($validated['status'] === 'submitted') {
            DrawingApproval::create([
                'project_id' => $project->id,
                'drawing_revision_id' => $revision->id,
                'requested_by_type' => $actor ? $actor::class : null,
                'requested_by_id' => $actor?->getKey(),
                'requested_at' => now(),
                'decision' => 'pending',
            ]);

            $draftingJob->update(['status' => 'submitted']);
            $project->update(['current_stage' => 'drawing_approval_pending']);
        } else {
            $draftingJob->update(['status' => 'in_progress']);
        }

        $activityService->log(
            module: 'drawing_revision',
            action: $validated['status'],
            actor: $actor,
            reference: $revision,
            companyId: $project->company_id,
            projectId: $project->id,
            request: $request
        );

        return back()->with('success', 'Drawing revision saved successfully.');
    }

    public function approveDrawing(DrawingApproval $drawingApproval, Request $request, ConstructionActivityService $activityService): RedirectResponse
    {
        $actor = $this->constructionActor();

        $validated = $request->validate([
            'decision' => ['required', 'in:approved,revision_requested,rejected'],
            'remarks' => ['nullable', 'string'],
        ]);

        $drawingApproval->update([
            'approved_by_type' => $actor ? $actor::class : null,
            'approved_by_id' => $actor?->getKey(),
            'approved_at' => now(),
            'decision' => $validated['decision'],
            'remarks' => $validated['remarks'] ?? null,
        ]);

        $revision = $drawingApproval->drawingRevision;
        $revision->update(['status' => $validated['decision'] === 'approved' ? 'approved' : 'revision_requested']);
        $project = $drawingApproval->project;
        $project->update([
            'current_stage' => $validated['decision'] === 'approved'
                ? 'ready_for_construction'
                : 'drafting_in_progress',
        ]);

        $activityService->log(
            module: 'drawing_approval',
            action: $validated['decision'],
            actor: $actor,
            reference: $drawingApproval,
            companyId: $project->company_id,
            projectId: $project->id,
            request: $request
        );

        return back()->with('success', 'Drawing approval updated successfully.');
    }
}
