<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('construction_activity_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('construction_activity_logs', 'employee_id')) {
                $table->unsignedBigInteger('employee_id')->nullable()->after('actor_id')->index();
            }
            if (!Schema::hasColumn('construction_activity_logs', 'channel')) {
                $table->string('channel', 50)->default('web_panel')->after('employee_id');
            }
            if (!Schema::hasColumn('construction_activity_logs', 'reference_code')) {
                $table->string('reference_code', 100)->nullable()->after('reference_id');
            }
            if (!Schema::hasColumn('construction_activity_logs', 'description')) {
                $table->text('description')->nullable()->after('reference_code');
            }
        });
    }

    public function down(): void
    {
        Schema::table('construction_activity_logs', function (Blueprint $table) {
            $columnsToDrop = [];
            foreach (['employee_id', 'channel', 'reference_code', 'description'] as $col) {
                if (Schema::hasColumn('construction_activity_logs', $col)) {
                    $columnsToDrop[] = $col;
                }
            }
            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};

