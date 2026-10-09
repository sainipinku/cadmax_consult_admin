<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('construction_workflow_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('construction_projects')->cascadeOnDelete();
            $table->string('stage', 50); // survey, drafting, accounts, supervisor, completed
            $table->string('status', 50); // survey_submitted, drafting_in_progress, draft_ready_for_review, accounts_pending, awaiting_supervisor_review, completed, etc.
            $table->string('action', 100); // submitted_survey, accepted_drafting, submitted_draft, sent_to_accounts, submitted_accounts, supervisor_approved, sent_back_to_draft, sent_back_to_survey, sent_back_to_accounts
            $table->nullableMorphs('action_by');
            $table->text('remarks')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'stage'], 'cwl_project_stage_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('construction_workflow_logs');
    }
};
