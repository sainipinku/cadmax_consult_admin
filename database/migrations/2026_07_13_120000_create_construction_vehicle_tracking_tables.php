<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('construction_vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('construction_projects')->cascadeOnDelete();
            $table->string('vehicle_code', 30)->unique();
            $table->string('registration_number', 30);
            $table->string('vehicle_type', 50)->nullable();
            $table->string('make', 100)->nullable();
            $table->string('model', 100)->nullable();
            $table->string('status')->default('active');
            $table->nullableMorphs('created_by', 'cv_created_by_idx');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['project_id', 'registration_number'], 'cv_project_reg_unique');
            $table->index(['project_id', 'status']);
        });

        Schema::create('construction_vehicle_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('construction_projects')->cascadeOnDelete();
            $table->foreignId('vehicle_id');
            $table->foreignId('driver_member_id')->nullable();
            $table->timestamp('assigned_from')->nullable();
            $table->timestamp('assigned_to')->nullable();
            $table->string('status')->default('active');
            $table->nullableMorphs('assigned_by', 'cva_assigned_by_idx');
            $table->timestamps();

            $table->foreign('vehicle_id', 'cva_vehicle_fk')
                ->references('id')
                ->on('construction_vehicles')
                ->cascadeOnDelete();
            $table->foreign('driver_member_id', 'cva_driver_fk')
                ->references('id')
                ->on('members')
                ->nullOnDelete();

            $table->index(['project_id', 'vehicle_id', 'status'], 'cva_project_vehicle_status_idx');
        });

        Schema::create('construction_vehicle_location_pings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('construction_projects')->cascadeOnDelete();
            $table->foreignId('vehicle_id');
            $table->foreignId('reported_by_member_id')->nullable();
            $table->timestamp('recorded_at')->useCurrent();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->decimal('gps_accuracy_meters', 10, 2)->nullable();
            $table->decimal('speed_kmph', 10, 2)->nullable();
            $table->decimal('heading_degrees', 10, 2)->nullable();
            $table->decimal('odometer_km', 12, 2)->nullable();
            $table->boolean('gps_verified')->default(false);
            $table->string('source', 30)->default('web');
            $table->timestamps();

            $table->foreign('vehicle_id', 'cvlp_vehicle_fk')
                ->references('id')
                ->on('construction_vehicles')
                ->cascadeOnDelete();
            $table->foreign('reported_by_member_id', 'cvlp_reported_by_fk')
                ->references('id')
                ->on('members')
                ->nullOnDelete();

            $table->index(['project_id', 'vehicle_id', 'recorded_at'], 'cvlp_project_vehicle_time_idx');
            $table->index(['project_id', 'gps_verified'], 'cvlp_project_verified_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('construction_vehicle_location_pings');
        Schema::dropIfExists('construction_vehicle_assignments');
        Schema::dropIfExists('construction_vehicles');
    }
};
