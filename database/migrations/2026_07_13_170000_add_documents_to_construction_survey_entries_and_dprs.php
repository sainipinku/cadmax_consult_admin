<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('construction_survey_entries', function (Blueprint $table) {
            $table->foreignId('supporting_document_id')->nullable()->after('description');
            $table->foreign('supporting_document_id', 'cse_support_doc_fk')
                ->references('id')
                ->on('construction_documents')
                ->nullOnDelete();
        });

        Schema::table('construction_daily_progress_reports', function (Blueprint $table) {
            $table->foreignId('supporting_document_id')->nullable()->after('weather_summary');
            $table->foreign('supporting_document_id', 'cdpr_support_doc_fk')
                ->references('id')
                ->on('construction_documents')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('construction_daily_progress_reports', function (Blueprint $table) {
            $table->dropForeign('cdpr_support_doc_fk');
            $table->dropColumn('supporting_document_id');
        });

        Schema::table('construction_survey_entries', function (Blueprint $table) {
            $table->dropForeign('cse_support_doc_fk');
            $table->dropColumn('supporting_document_id');
        });
    }
};

