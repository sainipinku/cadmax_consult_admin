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
        Schema::table('construction_clients', function (Blueprint $table) {
             $table->string('client_company_name')->nullable();
            $table->string('client_designation')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('construction_clients', function (Blueprint $table) {
             $table->dropColumn([
                'client_company_name',
                'client_designation',
            ]);
        });
    }
};
