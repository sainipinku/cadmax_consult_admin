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
        Schema::create('action_approval_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('requester_id');
            $table->string('requester_type')->default('admin'); // 'admin', 'member', etc.
            $table->string('requester_name');
            $table->string('requester_email')->nullable();
            $table->string('action')->default('delete'); // 'delete', etc.
            $table->string('resource_type'); // e.g. 'Employee', 'Vehicle', 'SurveySubmission', 'Project', 'Task'
            $table->string('resource_id');
            $table->string('resource_name')->nullable();
            $table->json('payload')->nullable();
            $table->text('reason')->nullable();
            $table->unsignedTinyInteger('status')->default(0); // 0 = Pending, 1 = Approved, 2 = Rejected
            $table->string('reviewed_by')->nullable(); // SuperAdmin ID/UUID
            $table->timestamp('reviewed_at')->nullable();
            $table->text('admin_remark')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'action']);
            $table->index(['requester_id', 'requester_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('action_approval_requests');
    }
};
