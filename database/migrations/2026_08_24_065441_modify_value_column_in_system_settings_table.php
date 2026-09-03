<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        \Illuminate\Support\Facades\DB::statement('ALTER TABLE system_settings MODIFY value LONGTEXT NULL');
    }

    public function down(): void
    {
        \Illuminate\Support\Facades\DB::statement('ALTER TABLE system_settings MODIFY value VARCHAR(255) NULL');
    }
};
