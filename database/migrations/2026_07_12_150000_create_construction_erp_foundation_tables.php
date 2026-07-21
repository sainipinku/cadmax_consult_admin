<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('construction_companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('legal_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('gst_number')->nullable();
            $table->text('address')->nullable();
            $table->string('logo_path')->nullable();
            $table->json('settings')->nullable();
            $table->string('status')->default('active');
            $table->nullableMorphs('created_by', 'cc_created_by_idx');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('construction_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained('construction_companies')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_system_role')->default(false);
            $table->string('status')->default('active');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('construction_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('module');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('construction_role_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained('construction_roles')->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('construction_permissions')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['role_id', 'permission_id']);
        });

        Schema::create('construction_clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('construction_companies')->cascadeOnDelete();
            $table->string('client_code')->unique();
            $table->string('client_type')->default('individual');
            $table->string('name');
            $table->string('contact_person')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('alternate_phone')->nullable();
            $table->string('gst_number')->nullable();
            $table->text('billing_address')->nullable();
            $table->text('site_address')->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->default('active');
            $table->nullableMorphs('created_by', 'ccli_created_by_idx');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('construction_projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('construction_companies')->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('construction_clients')->cascadeOnDelete();
            $table->string('project_code')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('category')->nullable();
            $table->text('description')->nullable();
            $table->text('project_address')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->date('start_date')->nullable();
            $table->date('expected_end_date')->nullable();
            $table->string('priority')->default('medium');
            $table->string('status')->default('draft');
            $table->string('current_stage')->default('budget_pending');
            $table->nullableMorphs('created_by', 'cproj_created_by_idx');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('construction_member_role_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('construction_roles')->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('construction_projects')->nullOnDelete();
            $table->timestamps();
            $table->unique(['member_id', 'role_id', 'project_id'], 'construction_member_role_unique');
        });

        Schema::create('construction_project_budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('construction_projects')->cascadeOnDelete();
            $table->unsignedInteger('version_no')->default(1);
            $table->decimal('estimated_amount', 15, 2)->default(0);
            $table->decimal('approved_amount', 15, 2)->nullable();
            $table->string('currency', 3)->default('INR');
            $table->text('notes')->nullable();
            $table->nullableMorphs('submitted_by', 'cpb_submitted_by_idx');
            $table->nullableMorphs('approved_by', 'cpb_approved_by_idx');
            $table->timestamp('approved_at')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();
        });

        Schema::create('construction_project_team_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('construction_projects')->cascadeOnDelete();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->foreignId('role_id')->nullable()->constrained('construction_roles')->nullOnDelete();
            $table->date('assigned_from')->nullable();
            $table->date('assigned_to')->nullable();
            $table->string('assignment_scope')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->string('status')->default('active');
            $table->nullableMorphs('assigned_by', 'cptm_assigned_by_idx');
            $table->timestamps();
            $table->unique(['project_id', 'member_id'], 'construction_project_team_unique');
        });

        Schema::create('construction_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained('construction_companies')->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('construction_projects')->nullOnDelete();
            $table->morphs('documentable', 'cdoc_documentable_idx');
            $table->string('folder')->nullable();
            $table->string('file_name');
            $table->string('original_name');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->default(0);
            $table->string('disk')->default('public');
            $table->string('path');
            $table->unsignedInteger('version_no')->default(1);
            $table->nullableMorphs('uploaded_by', 'cdoc_uploaded_by_idx');
            $table->timestamps();
        });

        Schema::create('construction_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained('construction_companies')->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('construction_projects')->nullOnDelete();
            $table->nullableMorphs('actor', 'cal_actor_idx');
            $table->string('module');
            $table->string('action');
            $table->nullableMorphs('reference', 'cal_reference_idx');
            $table->json('meta')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('construction_survey_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('construction_projects')->cascadeOnDelete();
            $table->string('survey_code')->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('site_address')->nullable();
            $table->decimal('site_latitude', 10, 7)->nullable();
            $table->decimal('site_longitude', 10, 7)->nullable();
            $table->date('planned_date')->nullable();
            $table->time('planned_start_time')->nullable();
            $table->time('planned_end_time')->nullable();
            $table->nullableMorphs('assigned_by', 'csp_assigned_by_idx');
            $table->string('status')->default('planned');
            $table->timestamps();
        });

        Schema::create('construction_survey_plan_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_plan_id')->constrained('construction_survey_plans')->cascadeOnDelete();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->string('role_in_survey')->nullable();
            $table->string('status')->default('assigned');
            $table->timestamps();
            $table->unique(['survey_plan_id', 'member_id'], 'construction_survey_plan_member_unique');
        });

        Schema::create('construction_survey_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('construction_projects')->cascadeOnDelete();
            $table->foreignId('survey_plan_id')->constrained('construction_survey_plans')->cascadeOnDelete();
            $table->foreignId('checked_in_by_member_id')->constrained('members')->cascadeOnDelete();
            $table->timestamp('check_in_at')->nullable();
            $table->decimal('check_in_latitude', 10, 7)->nullable();
            $table->decimal('check_in_longitude', 10, 7)->nullable();
            $table->decimal('gps_distance_meters', 10, 2)->nullable();
            $table->boolean('gps_verified')->default(false);
            $table->string('status')->default('checked_in');
            $table->timestamps();
        });

        Schema::create('construction_survey_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('construction_projects')->cascadeOnDelete();
            $table->foreignId('survey_visit_id')->constrained('construction_survey_visits')->cascadeOnDelete();
            $table->string('entry_type');
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('captured_by_member_id')->nullable()->constrained('members')->nullOnDelete();
            $table->timestamp('captured_at')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('construction_survey_measurements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('construction_projects')->cascadeOnDelete();
            $table->foreignId('survey_visit_id')->constrained('construction_survey_visits')->cascadeOnDelete();
            $table->string('area_name')->nullable();
            $table->string('measurement_type')->default('room');
            $table->decimal('length', 12, 2)->nullable();
            $table->decimal('width', 12, 2)->nullable();
            $table->decimal('height', 12, 2)->nullable();
            $table->string('unit')->default('ft');
            $table->decimal('quantity', 12, 2)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('captured_by_member_id')->nullable()->constrained('members')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('construction_survey_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('construction_projects')->cascadeOnDelete();
            $table->foreignId('survey_visit_id')->constrained('construction_survey_visits')->cascadeOnDelete();
            $table->foreignId('submitted_by_member_id')->nullable()->constrained('members')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->string('status')->default('draft');
            $table->text('review_notes')->nullable();
            $table->foreignId('reviewed_by_member_id')->nullable()->constrained('members')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('construction_drafting_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('construction_projects')->cascadeOnDelete();
            $table->foreignId('survey_submission_id')->constrained('construction_survey_submissions')->cascadeOnDelete();
            $table->foreignId('assigned_to_member_id')->nullable()->constrained('members')->nullOnDelete();
            $table->nullableMorphs('assigned_by', 'cdj_assigned_by_idx');
            $table->timestamp('assigned_at')->nullable();
            $table->date('due_date')->nullable();
            $table->string('status')->default('queued');
            $table->timestamps();
        });

        Schema::create('construction_drawing_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('construction_projects')->cascadeOnDelete();
            $table->foreignId('drafting_job_id')->constrained('construction_drafting_jobs')->cascadeOnDelete();
            $table->unsignedInteger('revision_no')->default(1);
            $table->foreignId('dwg_document_id')->nullable()->constrained('construction_documents')->nullOnDelete();
            $table->foreignId('pdf_document_id')->nullable()->constrained('construction_documents')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('uploaded_by_member_id')->nullable()->constrained('members')->nullOnDelete();
            $table->timestamp('uploaded_at')->nullable();
            $table->string('status')->default('draft');
            $table->timestamps();
        });

        Schema::create('construction_drawing_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('construction_projects')->cascadeOnDelete();
            $table->foreignId('drawing_revision_id')->constrained('construction_drawing_revisions')->cascadeOnDelete();
            $table->nullableMorphs('requested_by', 'cda_requested_by_idx');
            $table->timestamp('requested_at')->nullable();
            $table->nullableMorphs('approved_by', 'cda_approved_by_idx');
            $table->timestamp('approved_at')->nullable();
            $table->string('decision')->default('pending');
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('construction_drawing_approvals');
        Schema::dropIfExists('construction_drawing_revisions');
        Schema::dropIfExists('construction_drafting_jobs');
        Schema::dropIfExists('construction_survey_submissions');
        Schema::dropIfExists('construction_survey_measurements');
        Schema::dropIfExists('construction_survey_entries');
        Schema::dropIfExists('construction_survey_visits');
        Schema::dropIfExists('construction_survey_plan_members');
        Schema::dropIfExists('construction_survey_plans');
        Schema::dropIfExists('construction_activity_logs');
        Schema::dropIfExists('construction_documents');
        Schema::dropIfExists('construction_project_team_members');
        Schema::dropIfExists('construction_project_budgets');
        Schema::dropIfExists('construction_projects');
        Schema::dropIfExists('construction_clients');
        Schema::dropIfExists('construction_member_role_assignments');
        Schema::dropIfExists('construction_role_permissions');
        Schema::dropIfExists('construction_permissions');
        Schema::dropIfExists('construction_roles');
        Schema::dropIfExists('construction_companies');
    }
};
