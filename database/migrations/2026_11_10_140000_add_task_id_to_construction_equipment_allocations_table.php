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
        if (Schema::hasTable('construction_equipment_allocations') && !Schema::hasColumn('construction_equipment_allocations', 'task_id')) {
            Schema::table('construction_equipment_allocations', function (Blueprint $table) {
                $table->foreignId('task_id')
                    ->nullable()
                    ->after('assigned_to_member_id')
                    ->constrained('construction_execution_tasks')
                    ->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('construction_equipment_allocations') && Schema::hasColumn('construction_equipment_allocations', 'task_id')) {
            Schema::table('construction_equipment_allocations', function (Blueprint $table) {
                $table->dropForeign(['task_id']);
                $table->dropColumn('task_id');
            });
        }
    }
};

