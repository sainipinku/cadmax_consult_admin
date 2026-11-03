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
        Schema::create('driver_fuel_entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('driver_member_id')->index();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->unsignedBigInteger('project_id')->nullable()->index();
            $table->unsignedBigInteger('vehicle_id')->nullable()->index();
            $table->string('vehicle_number', 50)->index();
            $table->string('fuel_type', 30)->default('diesel');
            $table->decimal('quantity_liters', 10, 2);
            $table->decimal('total_amount', 12, 2);
            $table->date('fuel_date');
            $table->decimal('odometer_reading', 10, 2);
            $table->string('receipt_photo_url', 2048)->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('pending')->index(); // pending, approved, rejected
            $table->unsignedBigInteger('reviewed_by')->nullable()->index();
            $table->string('reviewed_by_type', 100)->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('admin_remarks')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('driver_fuel_entries');
    }
};
