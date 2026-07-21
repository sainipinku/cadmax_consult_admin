<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('construction_material_receipts', function (Blueprint $table) {
            $table->foreignId('receipt_document_id')->nullable()->after('notes');
            $table->foreign('receipt_document_id', 'cmr_receipt_doc_fk')
                ->references('id')
                ->on('construction_documents')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('construction_material_receipts', function (Blueprint $table) {
            $table->dropForeign('cmr_receipt_doc_fk');
            $table->dropColumn('receipt_document_id');
        });
    }
};

