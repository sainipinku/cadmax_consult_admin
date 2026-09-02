<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = 'construction_projects';

        if (!Schema::hasTable($table)) {
            return;
        }

        Schema::table($table, function (Blueprint $table) {
            $this->ensureColumn($table, 'created_by_type', fn (Blueprint $t) => $t->string('created_by_type', 80)->nullable()->after('current_stage'));
            $this->ensureColumn($table, 'created_by_id', fn (Blueprint $t) => $t->unsignedBigInteger('created_by_id')->nullable()->after('created_by_type'));

            $this->ensureColumn($table, 'client_review_status', function (Blueprint $t) {
                $t->enum('client_review_status', [
                    'pending', 'requested', 'approved', 'revision_requested', 'rejected',
                ])->default('pending')->after('current_stage');
            });

            $this->ensureColumn($table, 'client_review_requested_at', fn (Blueprint $t) => $t->timestamp('client_review_requested_at')->nullable()->after('client_review_status'));
            $this->ensureColumn($table, 'client_review_requested_by_member_id', function (Blueprint $t) {
                $t->unsignedBigInteger('client_review_requested_by_member_id')->nullable()->after('client_review_requested_at');
            });

            $this->ensureColumn($table, 'client_approved_at', fn (Blueprint $t) => $t->timestamp('client_approved_at')->nullable()->after('client_review_requested_by_member_id'));
            $this->ensureColumn($table, 'client_approved_by_client_id', function (Blueprint $t) {
                $t->unsignedBigInteger('client_approved_by_client_id')->nullable()->after('client_approved_at');
            });

            $this->ensureColumn($table, 'client_revision_comment', fn (Blueprint $t) => $t->text('client_revision_comment')->nullable()->after('client_approved_by_client_id'));
            $this->ensureColumn($table, 'partial_revision_sections', fn (Blueprint $t) => $t->json('partial_revision_sections')->nullable()->after('client_revision_comment'));
            $this->ensureColumn($table, 'revision_iteration_count', function (Blueprint $t) {
                $t->unsignedInteger('revision_iteration_count')->default(0)->after('partial_revision_sections');
            });

            if (Schema::hasColumn('construction_projects', 'client_review_requested_by_member_id')
                && !Schema::hasIndex('construction_projects', 'cp_client_review_req_by_idx')) {
                $table->index(['client_review_requested_by_member_id'], 'cp_client_review_req_by_idx');
            }
            if (Schema::hasColumn('construction_projects', 'client_approved_by_client_id')
                && !Schema::hasIndex('construction_projects', 'cp_client_approved_by_idx')) {
                $table->index(['client_approved_by_client_id'], 'cp_client_approved_by_idx');
            }
            if (Schema::hasColumn('construction_projects', 'client_review_status')
                && !Schema::hasIndex('construction_projects', 'cp_client_review_status_idx')) {
                $table->index(['client_review_status'], 'cp_client_review_status_idx');
            }
            if (Schema::hasColumn('construction_projects', 'created_by_type')
                && Schema::hasColumn('construction_projects', 'created_by_id')
                && !Schema::hasIndex('construction_projects', 'cp_created_by_morph_idx')) {
                $table->index(['created_by_type', 'created_by_id'], 'cp_created_by_morph_idx');
            }
        });
    }

    public function down(): void
    {
        // No-op: never drop columns in production to avoid data loss.
        // Use a separate explicit migration if rollback is required.
    }

    private function ensureColumn(Blueprint $table, string $column, callable $add): void
    {
        if (!Schema::hasColumn('construction_projects', $column)) {
            $add($table);
        }
    }
};
