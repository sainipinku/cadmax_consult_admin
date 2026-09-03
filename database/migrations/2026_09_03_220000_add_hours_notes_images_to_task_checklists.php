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
        if (Schema::hasTable('construction_task_checklists')) {
            Schema::table('construction_task_checklists', function (Blueprint $table) {
                if (!Schema::hasColumn('construction_task_checklists', 'assign_hours')) {
                    $table->decimal('assign_hours', 8, 2)->nullable()->after('item_title');
                }
                if (!Schema::hasColumn('construction_task_checklists', 'notes')) {
                    $table->text('notes')->nullable()->after('assign_hours');
                }
                if (!Schema::hasColumn('construction_task_checklists', 'status')) {
                    $table->string('status', 50)->default('pending')->after('notes');
                }
                if (!Schema::hasColumn('construction_task_checklists', 'image_url_1')) {
                    $table->string('image_url_1', 1000)->nullable()->after('status');
                }
                if (!Schema::hasColumn('construction_task_checklists', 'image_url_2')) {
                    $table->string('image_url_2', 1000)->nullable()->after('image_url_1');
                }
            });
        }

        if (Schema::hasTable('task_checklist_items')) {
            Schema::table('task_checklist_items', function (Blueprint $table) {
                if (!Schema::hasColumn('task_checklist_items', 'assign_hours')) {
                    $table->decimal('assign_hours', 8, 2)->nullable()->after('item_title');
                }
                if (!Schema::hasColumn('task_checklist_items', 'notes')) {
                    $table->text('notes')->nullable()->after('assign_hours');
                }
                if (!Schema::hasColumn('task_checklist_items', 'status')) {
                    $table->string('status', 50)->default('pending')->after('notes');
                }
                if (!Schema::hasColumn('task_checklist_items', 'image_url_1')) {
                    $table->string('image_url_1', 1000)->nullable()->after('status');
                }
                if (!Schema::hasColumn('task_checklist_items', 'image_url_2')) {
                    $table->string('image_url_2', 1000)->nullable()->after('image_url_1');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('construction_task_checklists')) {
            Schema::table('construction_task_checklists', function (Blueprint $table) {
                $table->dropColumn(['assign_hours', 'notes', 'status', 'image_url_1', 'image_url_2']);
            });
        }

        if (Schema::hasTable('task_checklist_items')) {
            Schema::table('task_checklist_items', function (Blueprint $table) {
                $table->dropColumn(['assign_hours', 'notes', 'status', 'image_url_1', 'image_url_2']);
            });
        }
    }
};
