<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('construction_client_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('construction_projects')->cascadeOnDelete();
            $table->string('invoice_code', 30)->unique();
            $table->date('invoice_date');
            $table->date('due_date')->nullable();
            $table->string('tax_type', 20)->default('intra');
            $table->string('status')->default('draft');
            $table->text('notes')->nullable();
            $table->decimal('subtotal_amount', 14, 2)->default(0);
            $table->decimal('cgst_amount', 14, 2)->default(0);
            $table->decimal('sgst_amount', 14, 2)->default(0);
            $table->decimal('igst_amount', 14, 2)->default(0);
            $table->decimal('total_tax_amount', 14, 2)->default(0);
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->decimal('paid_amount', 14, 2)->default(0);
            $table->decimal('balance_amount', 14, 2)->default(0);
            $table->nullableMorphs('created_by', 'cci_created_by_idx');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['project_id', 'status'], 'cci_project_status_idx');
        });

        Schema::create('construction_client_invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id');
            $table->string('description');
            $table->decimal('quantity', 12, 2)->default(1);
            $table->string('unit', 30)->nullable();
            $table->decimal('rate', 12, 2)->default(0);
            $table->decimal('line_subtotal', 14, 2)->default(0);
            $table->decimal('gst_percent', 6, 2)->default(0);
            $table->decimal('cgst_amount', 14, 2)->default(0);
            $table->decimal('sgst_amount', 14, 2)->default(0);
            $table->decimal('igst_amount', 14, 2)->default(0);
            $table->decimal('line_total_tax', 14, 2)->default(0);
            $table->decimal('line_total', 14, 2)->default(0);
            $table->timestamps();

            $table->foreign('invoice_id', 'ccii_invoice_fk')
                ->references('id')
                ->on('construction_client_invoices')
                ->cascadeOnDelete();
        });

        Schema::create('construction_client_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('construction_projects')->cascadeOnDelete();
            $table->foreignId('invoice_id');
            $table->string('payment_code', 30)->unique();
            $table->timestamp('received_at')->nullable();
            $table->decimal('amount', 14, 2);
            $table->string('method', 30)->default('bank_transfer');
            $table->string('reference_no', 100)->nullable();
            $table->text('notes')->nullable();
            $table->nullableMorphs('received_by', 'ccp_received_by_idx');
            $table->timestamps();

            $table->foreign('invoice_id', 'ccp_invoice_fk')
                ->references('id')
                ->on('construction_client_invoices')
                ->cascadeOnDelete();

            $table->index(['project_id', 'invoice_id'], 'ccp_project_invoice_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('construction_client_payments');
        Schema::dropIfExists('construction_client_invoice_items');
        Schema::dropIfExists('construction_client_invoices');
    }
};

