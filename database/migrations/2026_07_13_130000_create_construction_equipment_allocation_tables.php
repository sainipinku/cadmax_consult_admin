<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('construction_equipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('construction_projects')->cascadeOnDelete();
            $table->string('equipment_code', 30)->unique();
            $table->string('name');
            $table->string('equipment_type', 80)->nullable();
            $table->string('serial_number', 60)->nullable();
            $table->string('status')->default('active');
            $table->nullableMorphs('created_by', 'ceq_created_by_idx');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['project_id', 'status'], 'ceq_project_status_idx');
            $table->index(['project_id', 'equipment_type'], 'ceq_project_type_idx');
        });

        Schema::create('construction_equipment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('construction_projects')->cascadeOnDelete();
            $table->foreignId('equipment_id');
            $table->foreignId('assigned_to_member_id')->nullable();
            $table->timestamp('allocated_at')->nullable();
            $table->decimal('allocate_latitude', 10, 7)->nullable();
            $table->decimal('allocate_longitude', 10, 7)->nullable();
            $table->decimal('allocate_gps_accuracy_meters', 10, 2)->nullable();
            $table->boolean('allocate_gps_verified')->default(false);
            $table->timestamp('returned_at')->nullable();
            $table->decimal('return_latitude', 10, 7)->nullable();
            $table->decimal('return_longitude', 10, 7)->nullable();
            $table->decimal('return_gps_accuracy_meters', 10, 2)->nullable();
            $table->boolean('return_gps_verified')->default(false);
            $table->string('status')->default('active');
            $table->text('notes')->nullable();
            $table->nullableMorphs('allocated_by', 'ceqa_allocated_by_idx');
            $table->nullableMorphs('returned_by', 'ceqa_returned_by_idx');
            $table->timestamps();

            $table->foreign('equipment_id', 'ceqa_equipment_fk')
                ->references('id')
                ->on('construction_equipments')
                ->cascadeOnDelete();
            $table->foreign('assigned_to_member_id', 'ceqa_assigned_to_fk')
                ->references('id')
                ->on('members')
                ->nullOnDelete();

            $table->index(['project_id', 'equipment_id', 'status'], 'ceqa_project_equipment_status_idx');
            $table->index(['project_id', 'assigned_to_member_id'], 'ceqa_project_assignee_idx');
        });

        Schema::create('construction_equipment_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('construction_projects')->cascadeOnDelete();
            $table->foreignId('equipment_id');
            $table->foreignId('member_id')->nullable();
            $table->date('log_date');
            $table->decimal('hours_used', 10, 2)->default(0);
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('gps_accuracy_meters', 10, 2)->nullable();
            $table->boolean('gps_verified')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('equipment_id', 'cequl_equipment_fk')
                ->references('id')
                ->on('construction_equipments')
                ->cascadeOnDelete();
            $table->foreign('member_id', 'cequl_member_fk')
                ->references('id')
                ->on('members')
                ->nullOnDelete();

            $table->index(['project_id', 'equipment_id', 'log_date'], 'cequl_project_equipment_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('construction_equipment_usage_logs');
        Schema::dropIfExists('construction_equipment_allocations');
        Schema::dropIfExists('construction_equipments');
    }
};

