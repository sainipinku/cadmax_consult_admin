<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('employees');

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('uuid');
            $table->foreignId('member_id')->unique()->constrained('members')->cascadeOnDelete();
            $table->string('employee_id')->unique();
            $table->string('alternate_number')->nullable();
            $table->string('aadhaar_number')->unique()->nullable();
            $table->string('pan_number')->unique()->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('employee_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};