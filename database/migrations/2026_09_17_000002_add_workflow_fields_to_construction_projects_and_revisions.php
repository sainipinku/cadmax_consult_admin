<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('construction_projects', function (Blueprint $table) {
            $table->boolean('is_locked')->default(false)->after('current_stage');
            $table->string('payment_status', 30)->nullable()->default('pending')->after('is_locked'); // paid, partial, pending, not_applicable
            $table->decimal('commercial_billing_amount', 14, 2)->nullable()->after('payment_status');
            $table->decimal('commercial_advance_received', 14, 2)->nullable()->after('commercial_billing_amount');
            $table->decimal('commercial_pending_amount', 14, 2)->nullable()->after('commercial_advance_received');
            $table->decimal('commercial_expenses', 14, 2)->nullable()->after('commercial_pending_amount');
            $table->text('accounts_remarks')->nullable()->after('commercial_expenses');
            $table->foreignId('accounts_proof_document_id')->nullable()->after('accounts_remarks')->constrained('construction_documents')->nullOnDelete();
            $table->json('draft_verification_checklist')->nullable()->after('accounts_proof_document_id');
        });

        Schema::table('construction_drawing_revisions', function (Blueprint $table) {
            $table->string('drawing_type', 100)->nullable()->after('revision_no');
            $table->string('drawing_no', 100)->nullable()->after('drawing_type');
            $table->string('preview_image_path')->nullable()->after('pdf_document_id');
            $table->text('changes_made')->nullable()->after('preview_image_path');
            $table->boolean('is_locked')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('construction_projects', function (Blueprint $table) {
            $table->dropForeign(['accounts_proof_document_id']);
            $table->dropColumn([
                'is_locked',
                'payment_status',
                'commercial_billing_amount',
                'commercial_advance_received',
                'commercial_pending_amount',
                'commercial_expenses',
                'accounts_remarks',
                'accounts_proof_document_id',
                'draft_verification_checklist',
            ]);
        });

        Schema::table('construction_drawing_revisions', function (Blueprint $table) {
            $table->dropColumn([
                'drawing_type',
                'drawing_no',
                'preview_image_path',
                'changes_made',
                'is_locked',
            ]);
        });
    }
};
