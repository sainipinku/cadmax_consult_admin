<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('construction_execution_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('construction_projects')->cascadeOnDelete();
            $table->string('plan_code')->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('planned_start_date')->nullable();
            $table->date('planned_end_date')->nullable();
            $table->decimal('planned_progress_percent', 5, 2)->default(0);
            $table->decimal('actual_progress_percent', 5, 2)->default(0);
            $table->nullableMorphs('created_by', 'cep_created_by_idx');
            $table->nullableMorphs('approved_by', 'cep_approved_by_idx');
            $table->timestamp('approved_at')->nullable();
            $table->string('status')->default('planned');
            $table->timestamps();
        });

        Schema::create('construction_execution_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('construction_projects')->cascadeOnDelete();
            $table->foreignId('execution_plan_id')->constrained('construction_execution_plans')->cascadeOnDelete();
            $table->foreignId('parent_task_id')->nullable()->constrained('construction_execution_tasks')->nullOnDelete();
            $table->string('task_code')->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('planned_start_date')->nullable();
            $table->date('planned_end_date')->nullable();
            $table->date('actual_start_date')->nullable();
            $table->date('actual_end_date')->nullable();
            $table->string('priority')->default('medium');
            $table->decimal('planned_quantity', 12, 2)->nullable();
            $table->decimal('completed_quantity', 12, 2)->default(0);
            $table->string('unit')->nullable();
            $table->decimal('progress_percent', 5, 2)->default(0);
            $table->boolean('requires_daily_update')->default(true);
            $table->boolean('requires_gps_verification')->default(true);
            $table->foreignId('supervisor_member_id')->nullable()->constrained('members')->nullOnDelete();
            $table->string('status')->default('planned');
            $table->timestamps();

            $table->index(['project_id', 'status']);
            $table->index(['execution_plan_id', 'status']);
        });

        Schema::create('construction_execution_task_assignees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('construction_projects')->cascadeOnDelete();
            $table->foreignId('execution_task_id')->constrained('construction_execution_tasks')->cascadeOnDelete();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->string('assignment_role')->default('worker');
            $table->date('assigned_from')->nullable();
            $table->date('assigned_to')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->nullableMorphs('assigned_by', 'ceta_assigned_by_idx');
            $table->string('status')->default('active');
            $table->timestamps();

            $table->unique(['execution_task_id', 'member_id'], 'construction_execution_task_member_unique');
        });

        Schema::create('construction_daily_progress_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('construction_projects')->cascadeOnDelete();
            $table->foreignId('execution_task_id')->nullable()->constrained('construction_execution_tasks')->nullOnDelete();
            $table->date('report_date');
            $table->foreignId('submitted_by_member_id')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->text('summary')->nullable();
            $table->text('work_completed')->nullable();
            $table->text('blockers')->nullable();
            $table->unsignedInteger('workforce_count')->default(0);
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('gps_accuracy_meters', 10, 2)->nullable();
            $table->string('weather_summary')->nullable();
            $table->foreignId('reviewed_by_member_id')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->string('status')->default('draft');
            $table->timestamps();

            $table->foreign('submitted_by_member_id', 'cdpr_submitted_by_fk')->references('id')->on('members')->nullOnDelete();
            $table->foreign('reviewed_by_member_id', 'cdpr_reviewed_by_fk')->references('id')->on('members')->nullOnDelete();

            $table->index(['project_id', 'report_date']);
            $table->index(['project_id', 'status']);
        });

        Schema::create('construction_daily_progress_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('construction_projects')->cascadeOnDelete();
            $table->foreignId('daily_progress_report_id');
            $table->foreignId('execution_task_id')->nullable()->constrained('construction_execution_tasks')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('unit')->nullable();
            $table->decimal('planned_quantity', 12, 2)->nullable();
            $table->decimal('completed_quantity', 12, 2)->default(0);
            $table->decimal('percent_complete', 5, 2)->default(0);
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->foreign('daily_progress_report_id', 'cdpi_report_fk')
                ->references('id')
                ->on('construction_daily_progress_reports')
                ->cascadeOnDelete();
        });

        Schema::create('construction_attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('construction_projects')->cascadeOnDelete();
            $table->foreignId('execution_task_id')->nullable()->constrained('construction_execution_tasks')->nullOnDelete();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->date('attendance_date');
            $table->timestamp('check_in_at')->nullable();
            $table->timestamp('check_out_at')->nullable();
            $table->decimal('check_in_latitude', 10, 7)->nullable();
            $table->decimal('check_in_longitude', 10, 7)->nullable();
            $table->decimal('check_out_latitude', 10, 7)->nullable();
            $table->decimal('check_out_longitude', 10, 7)->nullable();
            $table->decimal('gps_accuracy_meters', 10, 2)->nullable();
            $table->string('attendance_type')->default('present');
            $table->text('notes')->nullable();
            $table->foreignId('reviewed_by_member_id')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();

            $table->foreign('reviewed_by_member_id', 'car_reviewed_by_fk')->references('id')->on('members')->nullOnDelete();

            $table->unique(['project_id', 'member_id', 'attendance_date'], 'construction_attendance_daily_unique');
            $table->index(['project_id', 'attendance_date']);
            $table->index(['project_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('construction_attendance_records');
        Schema::dropIfExists('construction_daily_progress_items');
        Schema::dropIfExists('construction_daily_progress_reports');
        Schema::dropIfExists('construction_execution_task_assignees');
        Schema::dropIfExists('construction_execution_tasks');
        Schema::dropIfExists('construction_execution_plans');
    }
};
