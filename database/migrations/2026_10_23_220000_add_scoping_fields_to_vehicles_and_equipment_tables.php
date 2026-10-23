<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('vehicles')) {
            Schema::table('vehicles', function (Blueprint $table) {
                if (!Schema::hasColumn('vehicles', 'created_by')) {
                    $table->unsignedBigInteger('created_by')->nullable()->after('id')->index();
                }
                if (!Schema::hasColumn('vehicles', 'company_id')) {
                    $table->unsignedBigInteger('company_id')->nullable()->after('created_by')->index();
                }
            });

            DB::table('vehicles')
                ->whereNull('created_by')
                ->update([
                    'created_by' => 1,
                    'company_id' => 1,
                ]);
        }

        if (Schema::hasTable('equipment_categories')) {
            Schema::table('equipment_categories', function (Blueprint $table) {
                if (!Schema::hasColumn('equipment_categories', 'created_by')) {
                    $table->unsignedBigInteger('created_by')->nullable()->after('id')->index();
                }
                if (!Schema::hasColumn('equipment_categories', 'company_id')) {
                    $table->unsignedBigInteger('company_id')->nullable()->after('created_by')->index();
                }
            });

            DB::table('equipment_categories')
                ->whereNull('created_by')
                ->update([
                    'created_by' => 1,
                    'company_id' => 1,
                ]);
        }

        if (Schema::hasTable('equipments')) {
            Schema::table('equipments', function (Blueprint $table) {
                if (!Schema::hasColumn('equipments', 'created_by')) {
                    $table->unsignedBigInteger('created_by')->nullable()->after('id')->index();
                }
                if (!Schema::hasColumn('equipments', 'company_id')) {
                    $table->unsignedBigInteger('company_id')->nullable()->after('created_by')->index();
                }
            });

            DB::table('equipments')
                ->whereNull('created_by')
                ->update([
                    'created_by' => 1,
                    'company_id' => 1,
                ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('vehicles')) {
            Schema::table('vehicles', function (Blueprint $table) {
                if (Schema::hasColumn('vehicles', 'company_id')) {
                    $table->dropColumn('company_id');
                }
                if (Schema::hasColumn('vehicles', 'created_by')) {
                    $table->dropColumn('created_by');
                }
            });
        }

        if (Schema::hasTable('equipment_categories')) {
            Schema::table('equipment_categories', function (Blueprint $table) {
                if (Schema::hasColumn('equipment_categories', 'company_id')) {
                    $table->dropColumn('company_id');
                }
                if (Schema::hasColumn('equipment_categories', 'created_by')) {
                    $table->dropColumn('created_by');
                }
            });
        }

        if (Schema::hasTable('equipments')) {
            Schema::table('equipments', function (Blueprint $table) {
                if (Schema::hasColumn('equipments', 'company_id')) {
                    $table->dropColumn('company_id');
                }
                if (Schema::hasColumn('equipments', 'created_by')) {
                    $table->dropColumn('created_by');
                }
            });
        }
    }
};
