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
        if (Schema::hasTable('construction_vehicle_assignments') && !Schema::hasColumn('construction_vehicle_assignments', 'task_id')) {
            Schema::table('construction_vehicle_assignments', function (Blueprint $table) {
                $table->foreignId('task_id')
                    ->nullable()
                    ->after('driver_member_id')
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
        if (Schema::hasTable('construction_vehicle_assignments') && Schema::hasColumn('construction_vehicle_assignments', 'task_id')) {
            Schema::table('construction_vehicle_assignments', function (Blueprint $table) {
                $table->dropForeign(['task_id']);
                $table->dropColumn('task_id');
            });
        }
    }
};

