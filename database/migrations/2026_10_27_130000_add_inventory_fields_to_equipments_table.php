<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('equipments')) {
            return;
        }

        Schema::table('equipments', function (Blueprint $table) {
            if (!Schema::hasColumn('equipments', 'quantity')) {
                $table->unsignedInteger('quantity')->default(1);
            }
            if (!Schema::hasColumn('equipments', 'ownership_type')) {
                $table->string('ownership_type', 30)->default('owned');
            }
            if (!Schema::hasColumn('equipments', 'location_site')) {
                $table->string('location_site')->nullable();
            }
            if (!Schema::hasColumn('equipments', 'notes')) {
                $table->text('notes')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('equipments')) {
            return;
        }

        Schema::table('equipments', function (Blueprint $table) {
            $columns = array_filter(
                ['quantity', 'ownership_type', 'location_site', 'notes'],
                fn (string $column): bool => Schema::hasColumn('equipments', $column)
            );

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
