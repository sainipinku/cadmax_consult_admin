<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('construction_drafting_jobs', function (Blueprint $table) {
            if (!Schema::hasColumn('construction_drafting_jobs', 'khasra_number')) {
                $table->string('khasra_number')->nullable()->after('due_date');
            }
            if (!Schema::hasColumn('construction_drafting_jobs', 'site_area')) {
                $table->string('site_area')->nullable()->after('khasra_number');
            }
            if (!Schema::hasColumn('construction_drafting_jobs', 'scope_of_work')) {
                $table->text('scope_of_work')->nullable()->after('site_area');
            }
        });

        Schema::table('construction_projects', function (Blueprint $table) {
            if (!Schema::hasColumn('construction_projects', 'khasra_number')) {
                $table->string('khasra_number')->nullable()->after('location_name');
            }
            if (!Schema::hasColumn('construction_projects', 'site_area')) {
                $table->string('site_area')->nullable()->after('khasra_number');
            }
            if (!Schema::hasColumn('construction_projects', 'scope_of_work')) {
                $table->text('scope_of_work')->nullable()->after('site_area');
            }
        });
    }

    public function down(): void
    {
        Schema::table('construction_drafting_jobs', function (Blueprint $table) {
            if (Schema::hasColumn('construction_drafting_jobs', 'scope_of_work')) {
                $table->dropColumn('scope_of_work');
            }
            if (Schema::hasColumn('construction_drafting_jobs', 'site_area')) {
                $table->dropColumn('site_area');
            }
            if (Schema::hasColumn('construction_drafting_jobs', 'khasra_number')) {
                $table->dropColumn('khasra_number');
            }
        });

        Schema::table('construction_projects', function (Blueprint $table) {
            if (Schema::hasColumn('construction_projects', 'scope_of_work')) {
                $table->dropColumn('scope_of_work');
            }
            if (Schema::hasColumn('construction_projects', 'site_area')) {
                $table->dropColumn('site_area');
            }
            if (Schema::hasColumn('construction_projects', 'khasra_number')) {
                $table->dropColumn('khasra_number');
            }
        });
    }
};
