<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('construction_task_checklists')) {
            Schema::table('construction_task_checklists', function (Blueprint $table) {
                if (!Schema::hasColumn('construction_task_checklists', 'vehicle_number')) {
                    $table->string('vehicle_number', 50)->nullable()->after('notes');
                }
                if (!Schema::hasColumn('construction_task_checklists', 'start_location')) {
                    $table->string('start_location', 255)->nullable()->after('vehicle_number');
                }
                if (!Schema::hasColumn('construction_task_checklists', 'destination')) {
                    $table->string('destination', 255)->nullable()->after('start_location');
                }
                if (!Schema::hasColumn('construction_task_checklists', 'start_km')) {
                    $table->decimal('start_km', 12, 2)->nullable()->after('destination');
                }
                if (!Schema::hasColumn('construction_task_checklists', 'end_km')) {
                    $table->decimal('end_km', 12, 2)->nullable()->after('start_km');
                }
                if (!Schema::hasColumn('construction_task_checklists', 'total_distance')) {
                    $table->decimal('total_distance', 12, 2)->nullable()->after('end_km');
                }
                if (!Schema::hasColumn('construction_task_checklists', 'trip_status')) {
                    $table->string('trip_status', 50)->nullable()->after('total_distance');
                }
                if (!Schema::hasColumn('construction_task_checklists', 'delivery_proof_urls')) {
                    $table->json('delivery_proof_urls')->nullable()->after('trip_status');
                }
                if (!Schema::hasColumn('construction_task_checklists', 'document_url')) {
                    $table->string('document_url', 1000)->nullable()->after('delivery_proof_urls');
                }
            });
        }

        if (Schema::hasTable('task_checklist_items')) {
            Schema::table('task_checklist_items', function (Blueprint $table) {
                if (!Schema::hasColumn('task_checklist_items', 'vehicle_number')) {
                    $table->string('vehicle_number', 50)->nullable()->after('notes');
                }
                if (!Schema::hasColumn('task_checklist_items', 'start_location')) {
                    $table->string('start_location', 255)->nullable()->after('vehicle_number');
                }
                if (!Schema::hasColumn('task_checklist_items', 'destination')) {
                    $table->string('destination', 255)->nullable()->after('start_location');
                }
                if (!Schema::hasColumn('task_checklist_items', 'start_km')) {
                    $table->decimal('start_km', 12, 2)->nullable()->after('destination');
                }
                if (!Schema::hasColumn('task_checklist_items', 'end_km')) {
                    $table->decimal('end_km', 12, 2)->nullable()->after('start_km');
                }
                if (!Schema::hasColumn('task_checklist_items', 'total_distance')) {
                    $table->decimal('total_distance', 12, 2)->nullable()->after('end_km');
                }
                if (!Schema::hasColumn('task_checklist_items', 'trip_status')) {
                    $table->string('trip_status', 50)->nullable()->after('total_distance');
                }
                if (!Schema::hasColumn('task_checklist_items', 'delivery_proof_urls')) {
                    $table->json('delivery_proof_urls')->nullable()->after('trip_status');
                }
                if (!Schema::hasColumn('task_checklist_items', 'document_url')) {
                    $table->string('document_url', 1000)->nullable()->after('delivery_proof_urls');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('construction_task_checklists')) {
            Schema::table('construction_task_checklists', function (Blueprint $table) {
                $table->dropColumn([
                    'vehicle_number',
                    'start_location',
                    'destination',
                    'start_km',
                    'end_km',
                    'total_distance',
                    'trip_status',
                    'delivery_proof_urls',
                    'document_url',
                ]);
            });
        }

        if (Schema::hasTable('task_checklist_items')) {
            Schema::table('task_checklist_items', function (Blueprint $table) {
                $table->dropColumn([
                    'vehicle_number',
                    'start_location',
                    'destination',
                    'start_km',
                    'end_km',
                    'total_distance',
                    'trip_status',
                    'delivery_proof_urls',
                    'document_url',
                ]);
            });
        }
    }
};
