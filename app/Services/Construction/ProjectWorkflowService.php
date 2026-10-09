<?php

namespace App\Services\Construction;

use App\Models\ConstructionWorkflowLog;
use App\Models\DrawingRevision;
use App\Models\DraftingJob;
use App\Models\Project;
use App\Models\SurveySubmission;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProjectWorkflowService
{
    /**
     * Helper to resolve actor morph attributes for logs
     */
    protected function getActorAttributes($user): array
    {
        if (!$user) {
            return [
                'action_by_type' => null,
                'action_by_id' => null,
            ];
        }

        return [
            'action_by_type' => get_class($user),
            'action_by_id' => $user->id,
        ];
    }

    /**
     * Record audit log for workflow stage transition
     */
    public function logTransition(
        Project $project,
        string $stage,
        string $status,
        string $action,
        $user = null,
        ?string $remarks = null,
        ?array $metadata = null
    ): ConstructionWorkflowLog {
        $actor = $this->getActorAttributes($user);

        return ConstructionWorkflowLog::create(array_merge([
            'project_id' => $project->id,
            'stage' => $stage,
            'status' => $status,
            'action' => $action,
            'remarks' => $remarks,
            'metadata' => $metadata,
        ], $actor));
    }

    /**
     * 1. Survey Team – Final Survey Submission
     */
    public function submitSurvey(Project $project, array $data, $user): Project
    {
        return DB::transaction(function () use ($project, $data, $user) {
            if ($project->is_locked) {
                throw ValidationException::withMessages(['workflow' => 'This project is locked and completed.']);
            }

            $project->update([
                'current_stage' => 'survey_submitted',
                'status' => 'survey_submitted',
            ]);

            // Ensure a drafting job queue entry exists
            $submission = SurveySubmission::where('project_id', $project->id)->latest()->first();
            if ($submission) {
                $submission->update(['status' => 20]); // Submitted

                DraftingJob::firstOrCreate(
                    [
                        'project_id' => $project->id,
                        'survey_submission_id' => $submission->id,
                    ],
                    [
                        'status' => 'queued',
                        'assigned_at' => now(),
                    ]
                );
            }

            $this->logTransition(
                $project,
                'survey',
                'survey_submitted',
                'submitted_survey',
                $user,
                $data['remarks'] ?? 'Survey site work complete and data submitted.',
                $data
            );

            return $project->fresh(['workflowLogs', 'surveySubmissions']);
        });
    }

    /**
     * 2. Draft Team – Accept for Drafting
     */
    public function acceptDrafting(Project $project, $user): Project
    {
        return DB::transaction(function () use ($project, $user) {
            if ($project->is_locked) {
                throw ValidationException::withMessages(['workflow' => 'This project is locked and completed.']);
            }

            $project->update([
                'current_stage' => 'drafting_in_progress',
            ]);

            $draftingJob = DraftingJob::where('project_id', $project->id)->latest()->first();
            if ($draftingJob) {
                $draftingJob->update([
                    'status' => 'in_progress',
                    'assigned_to_member_id' => $user->id ?? $draftingJob->assigned_to_member_id,
                ]);
            }

            $this->logTransition(
                $project,
                'drafting',
                'drafting_in_progress',
                'accepted_drafting',
                $user,
                'Accepted project survey data for drafting work.'
            );

            return $project->fresh(['workflowLogs', 'draftingJobs']);
        });
    }

    /**
     * 3. Draft Team – Prepare / Update Map & Submit Drawing
     */
    public function submitDraftDrawing(Project $project, array $data, $user): Project
    {
        return DB::transaction(function () use ($project, $data, $user) {
            if ($project->is_locked) {
                throw ValidationException::withMessages(['workflow' => 'This project is locked and completed.']);
            }

            $draftingJob = DraftingJob::where('project_id', $project->id)->latest()->first();
            $nextRevisionNo = (DrawingRevision::where('project_id', $project->id)->max('revision_no') ?? 0) + 1;

            $revision = DrawingRevision::create([
                'project_id' => $project->id,
                'drafting_job_id' => $draftingJob ? $draftingJob->id : null,
                'revision_no' => $data['revision_no'] ?? $nextRevisionNo,
                'drawing_type' => $data['drawing_type'] ?? null,
                'drawing_no' => $data['drawing_no'] ?? null,
                'dwg_document_id' => $data['dwg_document_id'] ?? null,
                'pdf_document_id' => $data['pdf_document_id'] ?? null,
                'preview_image_path' => $data['preview_image_path'] ?? null,
                'changes_made' => $data['changes_made'] ?? null,
                'notes' => $data['notes'] ?? $data['draft_remarks'] ?? null,
                'uploaded_by_member_id' => $user->id ?? null,
                'uploaded_at' => now(),
                'status' => 'ready_for_review',
            ]);

            $project->update([
                'current_stage' => 'draft_ready_for_review',
            ]);

            $this->logTransition(
                $project,
                'drafting',
                'draft_ready_for_review',
                'submitted_draft',
                $user,
                $data['draft_remarks'] ?? 'Draft map drawing prepared and submitted for verification.',
                ['drawing_revision_id' => $revision->id]
            );

            return $project->fresh(['workflowLogs', 'draftingJobs.drawingRevisions']);
        });
    }

    /**
     * 4. Draft Verification – Checklists & Send to Accounts
     */
    public function verifyAndSendToAccounts(Project $project, array $checklist, $user): Project
    {
        return DB::transaction(function () use ($project, $checklist, $user) {
            if ($project->is_locked) {
                throw ValidationException::withMessages(['workflow' => 'This project is locked and completed.']);
            }

            $project->update([
                'current_stage' => 'accounts_pending',
                'draft_verification_checklist' => $checklist,
            ]);

            $this->logTransition(
                $project,
                'drafting',
                'accounts_pending',
                'sent_to_accounts',
                $user,
                'Draft verification complete. Sent drawing to Accounts Team.',
                ['checklist' => $checklist]
            );

            return $project->fresh(['workflowLogs']);
        });
    }

    /**
     * 5. Accounts Team – Commercial Verification & Submit to Supervisor
     */
    public function submitAccountsVerification(Project $project, array $data, $user): Project
    {
        return DB::transaction(function () use ($project, $data, $user) {
            if ($project->is_locked) {
                throw ValidationException::withMessages(['workflow' => 'This project is locked and completed.']);
            }

            $project->update([
                'payment_status' => $data['payment_status'] ?? 'pending',
                'commercial_billing_amount' => $data['commercial_billing_amount'] ?? 0,
                'commercial_advance_received' => $data['commercial_advance_received'] ?? 0,
                'commercial_pending_amount' => $data['commercial_pending_amount'] ?? 0,
                'commercial_expenses' => $data['commercial_expenses'] ?? 0,
                'accounts_remarks' => $data['accounts_remarks'] ?? null,
                'accounts_proof_document_id' => $data['accounts_proof_document_id'] ?? null,
                'current_stage' => 'awaiting_supervisor_review',
            ]);

            $this->logTransition(
                $project,
                'accounts',
                'awaiting_supervisor_review',
                'submitted_accounts',
                $user,
                $data['accounts_remarks'] ?? 'Commercial verification completed by Accounts Team.',
                $data
            );

            return $project->fresh(['workflowLogs', 'accountsProofDocument']);
        });
    }

    /**
     * 6. Supervisor – Final Review (Approve or Send Back to 3 stages)
     */
    public function supervisorReview(Project $project, string $action, ?string $remarks, $user): Project
    {
        return DB::transaction(function () use ($project, $action, $remarks, $user) {
            if ($project->is_locked && $action !== 'unlock') {
                throw ValidationException::withMessages(['workflow' => 'Project is already completed and locked.']);
            }

            switch ($action) {
                case 'approve':
                    $project->update([
                        'current_stage' => 'completed',
                        'status' => 'completed',
                        'is_locked' => true,
                    ]);

                    DrawingRevision::where('project_id', $project->id)->update(['is_locked' => true]);

                    $this->logTransition(
                        $project,
                        'completed',
                        'completed',
                        'supervisor_approved',
                        $user,
                        $remarks ?? 'Supervisor approved final drawing & commercial verification. Project completed and locked.'
                    );
                    break;

                case 'send_back_to_draft':
                    if (empty(trim($remarks ?? ''))) {
                        throw ValidationException::withMessages(['remarks' => 'Remark is mandatory when sending back to Draft Team.']);
                    }

                    $project->update([
                        'current_stage' => 'drafting_in_progress',
                    ]);

                    $this->logTransition(
                        $project,
                        'supervisor',
                        'drafting_in_progress',
                        'sent_back_to_draft',
                        $user,
                        $remarks
                    );
                    break;

                case 'send_back_to_survey':
                    if (empty(trim($remarks ?? ''))) {
                        throw ValidationException::withMessages(['remarks' => 'Remark is mandatory when sending back to Survey Team.']);
                    }

                    $project->update([
                        'current_stage' => 'survey_submitted',
                    ]);

                    $this->logTransition(
                        $project,
                        'supervisor',
                        'survey_submitted',
                        'sent_back_to_survey',
                        $user,
                        $remarks
                    );
                    break;

                case 'send_back_to_accounts':
                    if (empty(trim($remarks ?? ''))) {
                        throw ValidationException::withMessages(['remarks' => 'Remark is mandatory when sending back to Accounts Team.']);
                    }

                    $project->update([
                        'current_stage' => 'accounts_pending',
                    ]);

                    $this->logTransition(
                        $project,
                        'supervisor',
                        'accounts_pending',
                        'sent_back_to_accounts',
                        $user,
                        $remarks
                    );
                    break;

                default:
                    throw ValidationException::withMessages(['action' => 'Invalid supervisor review action provided.']);
            }

            return $project->fresh(['workflowLogs']);
        });
    }
}
