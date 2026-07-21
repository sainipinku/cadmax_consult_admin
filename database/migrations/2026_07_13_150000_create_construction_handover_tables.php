<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('construction_project_handovers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('construction_projects')->cascadeOnDelete();
            $table->string('handover_code', 30)->unique();
            $table->date('planned_handover_date')->nullable();
            $table->timestamp('actual_handover_at')->nullable();
            $table->timestamp('closure_date')->nullable();
            $table->string('status')->default('draft');
            $table->string('client_signatory_name')->nullable();
            $table->string('client_signatory_role')->nullable();
            $table->text('signoff_notes')->nullable();
            $table->foreignId('final_document_id')->nullable();
            $table->nullableMorphs('created_by', 'cph_created_by_idx');
            $table->nullableMorphs('handed_over_by', 'cph_handover_by_idx');
            $table->nullableMorphs('closed_by', 'cph_closed_by_idx');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('final_document_id', 'cph_final_doc_fk')
                ->references('id')
                ->on('construction_documents')
                ->nullOnDelete();
            $table->index(['project_id', 'status'], 'cph_project_status_idx');
        });

        Schema::create('construction_project_handover_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('handover_id');
            $table->string('title');
            $table->string('category', 80)->nullable();
            $table->string('status')->default('pending');
            $table->text('notes')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('handover_id', 'cphi_handover_fk')
                ->references('id')
                ->on('construction_project_handovers')
                ->cascadeOnDelete();
            $table->index(['handover_id', 'status'], 'cphi_handover_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('construction_project_handover_items');
        Schema::dropIfExists('construction_project_handovers');
    }
};

