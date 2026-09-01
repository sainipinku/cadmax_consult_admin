<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            if (!Schema::hasColumn('tasks', 'project_id')) {
                $table->foreignId('project_id')->nullable()->constrained('construction_projects')->nullOnDelete()->after('uuid');
            }
            if (!Schema::hasColumn('tasks', 'execution_plan_id')) {
                $table->foreignId('execution_plan_id')->nullable()->constrained('construction_execution_plans')->nullOnDelete()->after('project_id');
            }
            if (!Schema::hasColumn('tasks', 'survey_plan_id')) {
                $table->foreignId('survey_plan_id')->nullable()->constrained('construction_survey_plans')->nullOnDelete()->after('execution_plan_id');
            }
            if (!Schema::hasColumn('tasks', 'parent_task_id')) {
                $table->foreignId('parent_task_id')->nullable()->constrained('tasks')->nullOnDelete()->after('survey_plan_id');
            }
            if (!Schema::hasColumn('tasks', 'task_code')) {
                $table->string('task_code', 60)->nullable()->after('parent_task_id');
            }
            if (!Schema::hasColumn('tasks', 'priority')) {
                $table->enum('priority', ['low','medium','high','critical'])->default('medium')->after('status');
            }
            if (!Schema::hasColumn('tasks', 'category')) {
                $table->string('category', 120)->nullable()->after('priority');
            }
            if (!Schema::hasColumn('tasks', 'progress_percent')) {
                $table->tinyInteger('progress_percent')->unsigned()->default(0)->after('category');
            }
            if (!Schema::hasColumn('tasks', 'requires_gps_verification')) {
                $table->boolean('requires_gps_verification')->default(false)->after('progress_percent');
            }
            if (!Schema::hasColumn('tasks', 'planned_qty')) {
                $table->decimal('planned_qty', 12, 3)->nullable()->after('requires_gps_verification');
                $table->decimal('completed_qty', 12, 3)->nullable()->after('planned_qty');
                $table->string('qty_unit', 30)->nullable()->after('completed_qty');
            }
            if (!Schema::hasColumn('tasks', 'assigned_supervisor_member_id')) {
                $table->foreignId('assigned_supervisor_member_id')->nullable()->constrained('members')->nullOnDelete()->after('qty_unit');
                $table->timestamp('supervisor_approved_at')->nullable()->after('assigned_supervisor_member_id');
                $table->string('approved_by_type', 80)->nullable()->after('supervisor_approved_at');
                $table->unsignedBigInteger('approved_by_id')->nullable()->after('approved_by_type');
            }
            if (!Schema::hasColumn('tasks', 'client_review_status')) {
                $table->enum('client_review_status', ['pending','requested','approved','revision_requested','rejected'])->default('pending')->after('approved_by_id');
            }
            if (!Schema::hasColumn('tasks', 'task_source')) {
                $table->enum('task_source', ['admin_created','member_manual','execution_plan_seed','survey_checklist_seed'])->default('admin_created')->after('client_review_status');
            }
            if (!Schema::hasColumn('tasks', 'created_by_type')) {
                $table->string('created_by_type', 80)->nullable()->after('task_source');
                $table->unsignedBigInteger('created_by_id')->nullable()->after('created_by_type');
            }
            if (!Schema::hasColumn('tasks', 'latitude')) {
                $table->decimal('latitude', 10, 7)->nullable()->after('end_date');
                $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            }
            if (!Schema::hasColumn('tasks', 'sort_order')) {
                $table->unsignedInteger('sort_order')->default(0)->after('longitude');
            }
            if (!Schema::hasColumn('tasks', 'client_reference')) {
                $table->uuid('client_reference')->nullable()->unique()->after('sort_order');
            }
            if (!Schema::hasColumn('tasks', 'deleted_by_type')) {
                $table->string('deleted_by_type', 80)->nullable()->after('client_reference');
                $table->unsignedBigInteger('deleted_by_id')->nullable()->after('deleted_by_type');
            }

            if (!Schema::hasIndex('tasks', 'tasks_project_status_idx')) {
                $table->index(['project_id', 'status', 'deleted_at'], 'tasks_project_status_idx');
            }
            if (!Schema::hasIndex('tasks', 'tasks_source_status_idx')) {
                $table->index(['task_source', 'status', 'created_at'], 'tasks_source_status_idx');
            }
            if (!Schema::hasIndex('tasks', 'tasks_supervisor_idx')) {
                $table->index(['project_id', 'assigned_supervisor_member_id'], 'tasks_supervisor_idx');
            }
            if (!Schema::hasIndex('tasks', 'tasks_survey_plan_idx')) {
                $table->index(['project_id', 'survey_plan_id'], 'tasks_survey_plan_idx');
            }
            if (!Schema::hasIndex('tasks', 'tasks_code_idx')) {
                $table->index(['task_code'], 'tasks_code_idx');
            }
        });

        Schema::table('task_assignments', function (Blueprint $table) {
            if (!Schema::hasColumn('task_assignments', 'uuid')) {
                $table->string('uuid')->nullable()->after('id');
            }
            if (!Schema::hasColumn('task_assignments', 'project_id')) {
                $table->foreignId('project_id')->nullable()->constrained('construction_projects')->nullOnDelete();
            }
            if (!Schema::hasColumn('task_assignments', 'assignment_role')) {
                $table->enum('assignment_role', ['owner','executor','reviewer','checker','verifier'])->default('executor');
            }
            if (!Schema::hasColumn('task_assignments', 'assigned_from')) {
                $table->dateTime('assigned_from')->nullable();
                $table->dateTime('assigned_until')->nullable();
            }
            if (!Schema::hasColumn('task_assignments', 'is_primary')) {
                $table->boolean('is_primary')->default(false);
            }
            if (!Schema::hasColumn('task_assignments', 'status')) {
                $table->enum('status', ['pending_acceptance','active','completed','revoked','rejected'])->default('active');
            }
            $needAssignedBy = !Schema::hasColumn('task_assignments', 'assigned_by_type')
                || !Schema::hasColumn('task_assignments', 'assigned_by_uid');
            $needAccepted = !Schema::hasColumn('task_assignments', 'accepted_at')
                || !Schema::hasColumn('task_assignments', 'rejected_reason');
            if ($needAssignedBy || $needAccepted) {
                if (!Schema::hasColumn('task_assignments', 'assigned_by_type')) {
                    $table->string('assigned_by_type', 80)->nullable();
                }
                if (!Schema::hasColumn('task_assignments', 'assigned_by_uid')) {
                    $table->unsignedBigInteger('assigned_by_uid')->nullable();
                }
                if (!Schema::hasColumn('task_assignments', 'accepted_at')) {
                    $table->timestamp('accepted_at')->nullable();
                }
                if (!Schema::hasColumn('task_assignments', 'rejected_reason')) {
                    $table->text('rejected_reason')->nullable();
                }
            }

            if (!Schema::hasIndex('task_assignments', 'ta_task_status_idx')) {
                $table->index(['task_id', 'status', 'deleted_at'], 'ta_task_status_idx');
            }
            if (!Schema::hasIndex('task_assignments', 'ta_member_status_idx')) {
                $table->index(['assigned_to', 'status'], 'ta_member_status_idx');
            }
        });

        Schema::table('task_comments', function (Blueprint $table) {
            if (!Schema::hasColumn('task_comments', 'project_id')) {
                $table->foreignId('project_id')->nullable()->constrained('construction_projects')->nullOnDelete()->after('uuid');
            }
            if (!Schema::hasColumn('task_comments', 'kind')) {
                $table->enum('kind', ['comment','status_note','progress_note','proof_note','checklist_note','system_note'])->default('comment')->after('project_id');
            }
            if (!Schema::hasColumn('task_comments', 'from_status')) {
                $table->string('from_status', 40)->nullable()->after('kind');
                $table->string('to_status', 40)->nullable()->after('from_status');
            }
            if (!Schema::hasColumn('task_comments', 'latitude')) {
                $table->decimal('latitude', 10, 7)->nullable()->after('to_status');
                $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            }
            if (!Schema::hasColumn('task_comments', 'battery_pct')) {
                $table->tinyInteger('battery_pct')->unsigned()->nullable()->after('longitude');
            }
            if (!Schema::hasColumn('task_comments', 'meta')) {
                $table->json('meta')->nullable()->after('battery_pct');
            }
            if (!Schema::hasColumn('task_comments', 'reply_to_id')) {
                $table->foreignId('reply_to_id')->nullable()->constrained('task_comments')->nullOnDelete()->after('meta');
            }

            if (!Schema::hasIndex('task_comments', 'tc_task_kind_idx')) {
                $table->index(['task_id', 'kind', 'created_at'], 'tc_task_kind_idx');
            }
        });

        Schema::create('task_checklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('construction_projects')->nullOnDelete();
            $table->unsignedTinyInteger('day_number')->nullable();
            $table->string('item_title', 500);
            $table->boolean('is_completed')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('completed_by_member_id')->nullable()->constrained('members')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->enum('source', ['default_seeded','admin_custom','member_added','checklist_seed'])->default('default_seeded');
            $table->uuid('client_reference')->nullable()->unique();
            $table->string('created_by_type', 80)->nullable();
            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->string('updated_by_type', 80)->nullable();
            $table->unsignedBigInteger('updated_by_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['task_id', 'sort_order', 'id'], 'tci_task_sort_idx');
            $table->index(['task_id', 'is_completed'], 'tci_task_done_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_checklist_items');

        Schema::table('task_comments', function (Blueprint $table) {
            $table->dropIndex(['tc_task_kind_idx']);
            foreach ([
                'project_id','kind','from_status','to_status',
                'latitude','longitude','battery_pct','meta','reply_to_id'
            ] as $col) {
                if (Schema::hasColumn('task_comments', $col)) {
                    $table->dropConstrainedForeignId($col === 'reply_to_id' ? 'reply_to_id' : 'project_id');
                    if ($col !== 'project_id' && $col !== 'reply_to_id') {
                        $table->dropColumn($col);
                    }
                    break;
                }
            }
        });

        Schema::table('task_assignments', function (Blueprint $table) {
            $table->dropIndex(['ta_task_status_idx','ta_member_status_idx']);
            foreach ([
                'project_id','assignment_role','assigned_from','assigned_until',
                'is_primary','status','assigned_by_type','assigned_by_uid',
                'accepted_at','rejected_reason'
            ] as $col) {
                if (Schema::hasColumn('task_assignments', $col)) {
                    if ($col === 'project_id') {
                        $table->dropConstrainedForeignId('project_id');
                    } else {
                        $table->dropColumn($col);
                    }
                }
            }
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex(['tasks_project_status_idx','tasks_source_status_idx','tasks_supervisor_idx','tasks_survey_plan_idx','tasks_code_idx']);
            foreach ([
                'project_id','execution_plan_id','survey_plan_id','parent_task_id','task_code','priority','category',
                'progress_percent','requires_gps_verification','planned_qty','completed_qty','qty_unit',
                'assigned_supervisor_member_id','supervisor_approved_at','approved_by_type','approved_by_id',
                'client_review_status','task_source','created_by_type','created_by_id','latitude','longitude',
                'sort_order','client_reference','deleted_by_type','deleted_by_id'
            ] as $col) {
                if (Schema::hasColumn('tasks', $col)) {
                    if (in_array($col, ['project_id','execution_plan_id','survey_plan_id','parent_task_id','assigned_supervisor_member_id'], true)) {
                        $table->dropConstrainedForeignId($col);
                    } else {
                        $table->dropColumn($col);
                    }
                }
            }
        });
    }
};
