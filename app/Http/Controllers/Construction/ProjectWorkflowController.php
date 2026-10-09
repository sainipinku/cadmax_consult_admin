<?php

namespace App\Http\Controllers\Construction;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\Construction\ProjectWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProjectWorkflowController extends Controller
{
    protected ProjectWorkflowService $workflowService;

    public function __construct(ProjectWorkflowService $workflowService)
    {
        $this->workflowService = $workflowService;
    }

    protected function getAuthenticatedUser()
    {
        if (Auth::guard('super_admin')->check()) {
            return Auth::guard('super_admin')->user();
        }

        if (Auth::guard('member')->check()) {
            return Auth::guard('member')->user();
        }

        return Auth::user();
    }

    /**
     * Stage 1: Survey Team – Submit Final Survey
     */
    public function submitSurvey(Request $request, Project $project): RedirectResponse
    {
        $validated = $request->validate([
            'remarks' => ['nullable', 'string', 'max:1000'],
            'raw_files' => ['nullable', 'array'],
            'site_photos' => ['nullable', 'array'],
        ]);

        $user = $this->getAuthenticatedUser();
        $this->workflowService->submitSurvey($project, $validated, $user);

        return redirect()->back()->with('success', 'Survey submission completed. Project forwarded to Draft Team queue.');
    }

    /**
     * Stage 2: Draft Team – Accept for Drafting
     */
    public function acceptDrafting(Request $request, Project $project): RedirectResponse
    {
        $user = $this->getAuthenticatedUser();
        $this->workflowService->acceptDrafting($project, $user);

        return redirect()->back()->with('success', 'Survey submission accepted. Drafting is now in progress.');
    }

    /**
     * Stage 3: Draft Team – Submit Draft Drawing
     */
    public function submitDraftDrawing(Request $request, Project $project): RedirectResponse
    {
        $validated = $request->validate([
            'drawing_type' => ['required', 'string', 'max:100'],
            'drawing_no' => ['required', 'string', 'max:100'],
            'revision_no' => ['nullable', 'integer', 'min:1'],
            'dwg_document_id' => ['nullable', 'exists:construction_documents,id'],
            'pdf_document_id' => ['nullable', 'exists:construction_documents,id'],
            'preview_image_path' => ['nullable', 'string'],
            'changes_made' => ['nullable', 'string', 'max:2000'],
            'draft_remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        $user = $this->getAuthenticatedUser();
        $this->workflowService->submitDraftDrawing($project, $validated, $user);

        return redirect()->back()->with('success', 'Map drawing prepared and updated. Ready for draft verification.');
    }

    /**
     * Stage 4: Draft Verification – Send to Accounts
     */
    public function verifyAndSendToAccounts(Request $request, Project $project): RedirectResponse
    {
        $validated = $request->validate([
            'dimensions_checked' => ['required', 'boolean'],
            'survey_data_matched' => ['required', 'boolean'],
            'markup_updated' => ['required', 'boolean'],
            'drawing_checked' => ['required', 'boolean'],
            'final_pdf_dwg_ready' => ['required', 'boolean'],
            'verification_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $user = $this->getAuthenticatedUser();
        $this->workflowService->verifyAndSendToAccounts($project, $validated, $user);

        return redirect()->back()->with('success', 'Draft verification passed. Project forwarded to Accounts Team.');
    }

    /**
     * Stage 5: Accounts Team – Commercial Verification
     */
    public function submitAccountsVerification(Request $request, Project $project): RedirectResponse
    {
        $validated = $request->validate([
            'payment_status' => ['required', 'string', 'in:paid,partial,pending,not_applicable'],
            'commercial_billing_amount' => ['nullable', 'numeric', 'min:0'],
            'commercial_advance_received' => ['nullable', 'numeric', 'min:0'],
            'commercial_pending_amount' => ['nullable', 'numeric', 'min:0'],
            'commercial_expenses' => ['nullable', 'numeric', 'min:0'],
            'accounts_remarks' => ['nullable', 'string', 'max:1000'],
            'accounts_proof_document_id' => ['nullable', 'exists:construction_documents,id'],
        ]);

        $user = $this->getAuthenticatedUser();
        $this->workflowService->submitAccountsVerification($project, $validated, $user);

        return redirect()->back()->with('success', 'Commercial verification completed. Project submitted to Supervisor for review.');
    }

    /**
     * Stage 6: Supervisor – Final Review & Actions
     */
    public function supervisorReview(Request $request, Project $project): RedirectResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'string', 'in:approve,send_back_to_draft,send_back_to_survey,send_back_to_accounts'],
            'remarks' => [
                'nullable',
                'string',
                'max:1000',
                function ($attribute, $value, $fail) use ($request) {
                    if ($request->action !== 'approve' && empty(trim($value ?? ''))) {
                        $fail('Remark is mandatory when sending back a project to any previous team.');
                    }
                }
            ],
        ]);

        $user = $this->getAuthenticatedUser();
        $this->workflowService->supervisorReview($project, $validated['action'], $validated['remarks'] ?? null, $user);

        $messages = [
            'approve' => 'Project drawing and commercial verification approved! Status marked as Completed.',
            'send_back_to_draft' => 'Project sent back to Draft Team with mandatory supervisor remarks.',
            'send_back_to_survey' => 'Project sent back to Survey Team with mandatory supervisor remarks.',
            'send_back_to_accounts' => 'Project sent back to Accounts Team with mandatory supervisor remarks.',
        ];

        return redirect()->back()->with('success', $messages[$validated['action']] ?? 'Supervisor review saved.');
    }
}
