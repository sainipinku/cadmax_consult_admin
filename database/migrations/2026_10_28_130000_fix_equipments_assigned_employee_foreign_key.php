<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('equipments') && Schema::hasColumn('equipments', 'assigned_employee_id')) {
            if (DB::getDriverName() !== 'sqlite') {
                try {
                    Schema::table('equipments', function (Blueprint $table) {
                        $table->dropForeign('equipments_assigned_employee_id_foreign');
                    });
                } catch (\Throwable $e) {
                    // Foreign key might not exist with that exact name
                }

                Schema::table('equipments', function (Blueprint $table) {
                    $table->foreign('assigned_employee_id', 'equipments_assigned_employee_id_foreign')
                        ->references('id')
                        ->on('employees')
                        ->nullOnDelete();
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('equipments') && Schema::hasColumn('equipments', 'assigned_employee_id')) {
            if (DB::getDriverName() !== 'sqlite') {
                try {
                    Schema::table('equipments', function (Blueprint $table) {
                        $table->dropForeign('equipments_assigned_employee_id_foreign');
                    });
                } catch (\Throwable $e) {
                    // Ignore
                }

                Schema::table('equipments', function (Blueprint $table) {
                    $table->foreign('assigned_employee_id', 'equipments_assigned_employee_id_foreign')
                        ->references('id')
                        ->on('members')
                        ->nullOnDelete();
                });
            }
        }
    }
};
