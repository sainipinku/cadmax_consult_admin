<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('construction_vendors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('construction_projects')->cascadeOnDelete();
            $table->string('vendor_code', 30)->unique();
            $table->string('name');
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('gstin', 20)->nullable();
            $table->text('address')->nullable();
            $table->string('status')->default('active');
            $table->nullableMorphs('created_by');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['project_id', 'status']);
        });

        Schema::create('construction_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('construction_projects')->cascadeOnDelete();
            $table->string('material_code', 30)->unique();
            $table->string('name');
            $table->string('unit', 50)->default('nos');
            $table->decimal('default_rate', 12, 2)->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['project_id', 'status']);
        });

        Schema::create('construction_purchase_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('construction_projects')->cascadeOnDelete();
            $table->string('request_code', 30)->unique();
            $table->foreignId('requested_by_member_id')->nullable();
            $table->date('request_date');
            $table->text('notes')->nullable();
            $table->string('status')->default('draft');
            $table->foreignId('reviewed_by_member_id')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamps();

            $table->foreign('requested_by_member_id', 'cpr_requested_by_fk')->references('id')->on('members')->nullOnDelete();
            $table->foreign('reviewed_by_member_id', 'cpr_reviewed_by_fk')->references('id')->on('members')->nullOnDelete();
            $table->index(['project_id', 'status']);
        });

        Schema::create('construction_purchase_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_request_id');
            $table->foreignId('material_id');
            $table->decimal('quantity', 12, 2);
            $table->string('unit', 50)->nullable();
            $table->decimal('estimated_rate', 12, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('purchase_request_id', 'cpri_request_fk')
                ->references('id')
                ->on('construction_purchase_requests')
                ->cascadeOnDelete();
            $table->foreign('material_id', 'cpri_material_fk')
                ->references('id')
                ->on('construction_materials')
                ->cascadeOnDelete();
        });

        Schema::create('construction_purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('construction_projects')->cascadeOnDelete();
            $table->foreignId('purchase_request_id')->nullable();
            $table->string('po_code', 30)->unique();
            $table->foreignId('vendor_id');
            $table->date('po_date');
            $table->date('expected_delivery_date')->nullable();
            $table->string('status')->default('draft');
            $table->decimal('subtotal_amount', 14, 2)->default(0);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->foreignId('invoice_document_id')->nullable();
            $table->nullableMorphs('created_by');
            $table->timestamps();

            $table->foreign('purchase_request_id', 'cpo_request_fk')
                ->references('id')
                ->on('construction_purchase_requests')
                ->nullOnDelete();
            $table->foreign('vendor_id', 'cpo_vendor_fk')
                ->references('id')
                ->on('construction_vendors')
                ->cascadeOnDelete();
            $table->foreign('invoice_document_id', 'cpo_invoice_doc_fk')
                ->references('id')
                ->on('construction_documents')
                ->nullOnDelete();

            $table->index(['project_id', 'status']);
        });

        Schema::create('construction_purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id');
            $table->foreignId('material_id');
            $table->decimal('quantity', 12, 2);
            $table->string('unit', 50)->nullable();
            $table->decimal('rate', 12, 2)->nullable();
            $table->decimal('tax_percent', 6, 2)->default(0);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('line_total', 14, 2)->default(0);
            $table->decimal('received_quantity', 12, 2)->default(0);
            $table->timestamps();

            $table->foreign('purchase_order_id', 'cpoi_po_fk')
                ->references('id')
                ->on('construction_purchase_orders')
                ->cascadeOnDelete();
            $table->foreign('material_id', 'cpoi_material_fk')
                ->references('id')
                ->on('construction_materials')
                ->cascadeOnDelete();
        });

        Schema::create('construction_material_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('construction_projects')->cascadeOnDelete();
            $table->foreignId('purchase_order_id')->nullable();
            $table->string('receipt_code', 30)->unique();
            $table->foreignId('received_by_member_id')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('gps_accuracy_meters', 10, 2)->nullable();
            $table->string('status')->default('received');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('purchase_order_id', 'cmr_po_fk')
                ->references('id')
                ->on('construction_purchase_orders')
                ->nullOnDelete();
            $table->foreign('received_by_member_id', 'cmr_received_by_fk')
                ->references('id')
                ->on('members')
                ->nullOnDelete();
            $table->index(['project_id', 'status']);
        });

        Schema::create('construction_material_receipt_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_receipt_id');
            $table->foreignId('material_id');
            $table->decimal('quantity', 12, 2);
            $table->string('unit', 50)->nullable();
            $table->decimal('rate', 12, 2)->nullable();
            $table->decimal('line_total', 14, 2)->default(0);
            $table->timestamps();

            $table->foreign('material_receipt_id', 'cmri_receipt_fk')
                ->references('id')
                ->on('construction_material_receipts')
                ->cascadeOnDelete();
            $table->foreign('material_id', 'cmri_material_fk')
                ->references('id')
                ->on('construction_materials')
                ->cascadeOnDelete();
        });

        Schema::create('construction_material_issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('construction_projects')->cascadeOnDelete();
            $table->string('issue_code', 30)->unique();
            $table->foreignId('issued_by_member_id')->nullable();
            $table->date('issue_date');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('gps_accuracy_meters', 10, 2)->nullable();
            $table->string('status')->default('issued');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('issued_by_member_id', 'cmi_issued_by_fk')->references('id')->on('members')->nullOnDelete();
            $table->index(['project_id', 'status']);
        });

        Schema::create('construction_material_issue_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_issue_id');
            $table->foreignId('material_id');
            $table->foreignId('execution_task_id')->nullable();
            $table->decimal('quantity', 12, 2);
            $table->string('unit', 50)->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->foreign('material_issue_id', 'cmii_issue_fk')
                ->references('id')
                ->on('construction_material_issues')
                ->cascadeOnDelete();
            $table->foreign('material_id', 'cmii_material_fk')
                ->references('id')
                ->on('construction_materials')
                ->cascadeOnDelete();
            $table->foreign('execution_task_id', 'cmii_task_fk')
                ->references('id')
                ->on('construction_execution_tasks')
                ->nullOnDelete();
        });

        Schema::create('construction_material_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('construction_projects')->cascadeOnDelete();
            $table->foreignId('material_id');
            $table->decimal('on_hand_quantity', 14, 2)->default(0);
            $table->timestamps();

            $table->foreign('material_id', 'cms_material_fk')
                ->references('id')
                ->on('construction_materials')
                ->cascadeOnDelete();

            $table->unique(['project_id', 'material_id'], 'cms_project_material_unique');
            $table->index(['project_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('construction_material_stocks');
        Schema::dropIfExists('construction_material_issue_items');
        Schema::dropIfExists('construction_material_issues');
        Schema::dropIfExists('construction_material_receipt_items');
        Schema::dropIfExists('construction_material_receipts');
        Schema::dropIfExists('construction_purchase_order_items');
        Schema::dropIfExists('construction_purchase_orders');
        Schema::dropIfExists('construction_purchase_request_items');
        Schema::dropIfExists('construction_purchase_requests');
        Schema::dropIfExists('construction_materials');
        Schema::dropIfExists('construction_vendors');
    }
};

