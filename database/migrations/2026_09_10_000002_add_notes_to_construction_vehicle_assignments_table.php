<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('construction_vehicle_assignments') && !Schema::hasColumn('construction_vehicle_assignments', 'notes')) {
            Schema::table('construction_vehicle_assignments', function (Blueprint $table) {
                $table->text('notes')->nullable()->after('status');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('construction_vehicle_assignments') && Schema::hasColumn('construction_vehicle_assignments', 'notes')) {
            Schema::table('construction_vehicle_assignments', function (Blueprint $table) {
                $table->dropColumn('notes');
            });
        }
    }
};
