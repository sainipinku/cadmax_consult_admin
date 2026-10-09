<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('super_admins', 'company_id')) {
            Schema::table('super_admins', function (Blueprint $table) {
                $table->foreignId('company_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('construction_companies')
                    ->nullOnDelete();
            });
        }

        // Backfill existing SuperAdmins with their created companies
        $companies = DB::table('construction_companies')
            ->where('created_by_type', 'App\\Models\\SuperAdmin')
            ->get();

        foreach ($companies as $company) {
            DB::table('super_admins')
                ->where('id', $company->created_by_id)
                ->whereNull('company_id')
                ->update(['company_id' => $company->id]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('super_admins', 'company_id')) {
            Schema::table('super_admins', function (Blueprint $table) {
                $table->dropForeign(['company_id']);
                $table->dropColumn('company_id');
            });
        }
    }
};
