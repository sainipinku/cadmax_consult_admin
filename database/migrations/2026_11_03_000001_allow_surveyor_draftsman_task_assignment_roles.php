<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('task_assignments') && Schema::hasColumn('task_assignments', 'assignment_role')) {
            Schema::table('task_assignments', function (Blueprint $table) {
                $table->string('assignment_role', 50)->default('executor')->change();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('task_assignments') && Schema::hasColumn('task_assignments', 'assignment_role')) {
            Schema::table('task_assignments', function (Blueprint $table) {
                $table->enum('assignment_role', ['owner', 'executor', 'reviewer', 'checker', 'verifier', 'surveyor', 'draft_person'])
                    ->default('executor')
                    ->change();
            });
        }
    }
};
