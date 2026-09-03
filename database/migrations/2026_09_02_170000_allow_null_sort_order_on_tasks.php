<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The unified `tasks` `sort_order` column currently fails integrity check during TaskManagementService
 * when a NULL value is force-filled via forceFill() for payloads where no explicit
 * sort_order was supplied. The DB-level default(0) only applies when the
 * column is omitted from the INSERT statement; once forceFill() passes it with an
 * explicit NULL the DB rejects it.
 *
 * Safeguard:
 *   1) Make the column nullable so a NULL value no longer throws a 1048.
 *   2) Keep a sane DB DEFAULT 0 so any future manual INSERTs that omit sort_order fall through the implicit zero.
 *   3) Service layer normalization in TaskManagementService::normalizeSortOrder already assigns the actual
 *      append-at-end value for consistent sort behaviour; this migration is the belt-and-braces second layer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::whenTableHasColumn('tasks', 'sort_order', function (Blueprint $table) {
            $table->unsignedInteger('sort_order')->nullable()->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::whenTableHasColumn('tasks', 'sort_order', function (Blueprint $table) {
            $table->unsignedInteger('sort_order')->nullable(false)->default(null)->change();
        });
    }
};
