<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::dropIfExists('construction_survey_voice_notes');
        Schema::dropIfExists('construction_survey_media');
        Schema::dropIfExists('construction_audit_logs');
        Schema::dropIfExists('construction_approval_requests');
        Schema::dropIfExists('construction_approval_workflow_steps');
        Schema::dropIfExists('construction_approval_workflows');
        Schema::dropIfExists('construction_employee_profiles');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Intentionally left empty because these tables were removed from the base ERP scope.
    }
};
