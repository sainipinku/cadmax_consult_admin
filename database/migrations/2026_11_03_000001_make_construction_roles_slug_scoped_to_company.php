<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('construction_roles', function (Blueprint $table) {
            $table->dropUnique('construction_roles_slug_unique');
            $table->index(['company_id', 'slug'], 'construction_roles_company_slug_idx');
        });
    }

    public function down(): void
    {
        Schema::table('construction_roles', function (Blueprint $table) {
            $table->dropIndex('construction_roles_company_slug_idx');
            $table->unique('slug', 'construction_roles_slug_unique');
        });
    }
};

