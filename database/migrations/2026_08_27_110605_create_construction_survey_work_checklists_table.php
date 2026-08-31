<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('construction_survey_work_checklists', function (Blueprint $table) {
            $table->id();

            $table->foreignId('survey_plan_member_id');

            $table->string('work_title', 500);

            $table->unsignedTinyInteger('source')
                ->comment('1=super_admin, 2=admin, 3=member');

            $table->unsignedTinyInteger('status')
                ->default(0)
                ->comment('0=pending, 1=completed');

            $table->nullableMorphs(
                'added_by',
                'cswc_added_by_idx'
            );

            $table->foreignId('completed_by_member_id')
                ->nullable();

            $table->timestamp('completed_at')
                ->nullable();

            $table->unsignedInteger('sort_order')
                ->default(0);

            $table->uuid('client_reference')
                ->nullable();

            $table->timestamps();

            $table->foreign(
                'survey_plan_member_id',
                'cswc_plan_member_fk'
            )
                ->references('id')
                ->on('construction_survey_plan_members')
                ->cascadeOnDelete();

            $table->foreign(
                'completed_by_member_id',
                'cswc_completed_member_fk'
            )
                ->references('id')
                ->on('members')
                ->nullOnDelete();

            $table->index(
                ['survey_plan_member_id', 'status'],
                'cswc_assignment_status_idx'
            );

            $table->index(
                ['survey_plan_member_id', 'source', 'sort_order'],
                'cswc_assignment_source_sort_idx'
            );

            $table->unique(
                ['survey_plan_member_id', 'client_reference'],
                'cswc_assignment_client_ref_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('construction_survey_work_checklists');
    }
};