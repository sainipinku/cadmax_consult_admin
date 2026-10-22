<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('roles')) {
            Schema::table('roles', function (Blueprint $table) {
                if (!Schema::hasColumn('roles', 'super_admin_id')) {
                    $table->unsignedBigInteger('super_admin_id')->nullable()->after('status')->index();
                }
                if (!Schema::hasColumn('roles', 'is_default')) {
                    $table->boolean('is_default')->default(false)->after('super_admin_id')->index();
                }
            });

            // Backfill default system roles
            $defaultSlugs = [
                'member',
                'super_admin',
                'superadmin',
                'project_admin',
                'surveyor',
                'draft_person',
                'vehicle_driver',
                'review_approver',
                'site_employee',
                'admin',
            ];

            DB::table('roles')
                ->whereIn('slug', $defaultSlugs)
                ->orWhere('created_by', 1)
                ->update([
                    'is_default' => 1,
                    'super_admin_id' => null,
                ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('roles')) {
            Schema::table('roles', function (Blueprint $table) {
                if (Schema::hasColumn('roles', 'is_default')) {
                    $table->dropColumn('is_default');
                }
                if (Schema::hasColumn('roles', 'super_admin_id')) {
                    $table->dropColumn('super_admin_id');
                }
            });
        }
    }
};
