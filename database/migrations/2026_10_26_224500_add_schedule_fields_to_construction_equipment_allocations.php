<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('construction_equipment_allocations')) {
            Schema::table('construction_equipment_allocations', function (Blueprint $table) {
                if (!Schema::hasColumn('construction_equipment_allocations', 'assigned_from')) {
                    $table->timestamp('assigned_from')->nullable()->after('allocated_at');
                }
                if (!Schema::hasColumn('construction_equipment_allocations', 'assigned_to')) {
                    $table->timestamp('assigned_to')->nullable()->after('assigned_from');
                }
                if (!Schema::hasColumn('construction_equipment_allocations', 'hours')) {
                    $table->decimal('hours', 8, 2)->nullable()->after('assigned_to');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('construction_equipment_allocations')) {
            Schema::table('construction_equipment_allocations', function (Blueprint $table) {
                if (Schema::hasColumn('construction_equipment_allocations', 'hours')) {
                    $table->dropColumn('hours');
                }
                if (Schema::hasColumn('construction_equipment_allocations', 'assigned_to')) {
                    $table->dropColumn('assigned_to');
                }
                if (Schema::hasColumn('construction_equipment_allocations', 'assigned_from')) {
                    $table->dropColumn('assigned_from');
                }
            });
        }
    }
};
