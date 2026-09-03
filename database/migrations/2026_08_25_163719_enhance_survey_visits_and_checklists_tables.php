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
        Schema::table('construction_survey_visits', function (Blueprint $table) {
            if (!Schema::hasColumn('construction_survey_visits', 'check_out_at')) {
                $table->timestamp('check_out_at')->nullable()->after('check_in_at');
            }
            if (!Schema::hasColumn('construction_survey_visits', 'check_out_latitude')) {
                $table->decimal('check_out_latitude', 10, 7)->nullable()->after('check_in_longitude');
            }
            if (!Schema::hasColumn('construction_survey_visits', 'check_out_longitude')) {
                $table->decimal('check_out_longitude', 10, 7)->nullable()->after('check_out_latitude');
            }
            if (!Schema::hasColumn('construction_survey_visits', 'duration_minutes')) {
                $table->unsignedInteger('duration_minutes')->default(0)->after('check_out_longitude');
            }
            if (!Schema::hasColumn('construction_survey_visits', 'day_number')) {
                $table->unsignedSmallInteger('day_number')->default(1)->after('duration_minutes');
            }
            if (!Schema::hasColumn('construction_survey_visits', 'elevation_m')) {
                $table->decimal('elevation_m', 10, 2)->nullable()->after('day_number');
            }
            if (!Schema::hasColumn('construction_survey_visits', 'distance_covered_m')) {
                $table->decimal('distance_covered_m', 10, 2)->nullable()->after('elevation_m');
            }
            if (!Schema::hasColumn('construction_survey_visits', 'total_points_captured')) {
                $table->unsignedInteger('total_points_captured')->nullable()->after('distance_covered_m');
            }
            if (!Schema::hasColumn('construction_survey_visits', 'remarks')) {
                $table->text('remarks')->nullable()->after('total_points_captured');
            }
            if (!Schema::hasColumn('construction_survey_visits', 'photos')) {
                $table->json('photos')->nullable()->after('remarks');
            }
            if (!Schema::hasColumn('construction_survey_visits', 'file_path')) {
                $table->string('file_path', 500)->nullable()->after('photos');
            }
            if (!Schema::hasColumn('construction_survey_visits', 'notes')) {
                $table->text('notes')->nullable()->after('file_path');
            }
        });

        if (!Schema::hasTable('construction_task_checklists')) {
            Schema::create('construction_task_checklists', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('execution_task_id')->nullable();
                $table->unsignedBigInteger('survey_plan_id')->nullable();
                $table->unsignedSmallInteger('day_number')->default(1);
                $table->string('item_title');
                $table->boolean('is_completed')->default(false);
                $table->unsignedBigInteger('completed_by_member_id')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();

                $table->foreign('execution_task_id')->references('id')->on('construction_execution_tasks')->onDelete('cascade');
                $table->foreign('survey_plan_id')->references('id')->on('construction_survey_plans')->onDelete('cascade');
                $table->foreign('completed_by_member_id')->references('id')->on('members')->onDelete('set null');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('construction_task_checklists');

        Schema::table('construction_survey_visits', function (Blueprint $table) {
            $table->dropColumn([
                'check_out_at',
                'check_out_latitude',
                'check_out_longitude',
                'duration_minutes',
                'day_number',
                'elevation_m',
                'distance_covered_m',
                'total_points_captured',
                'remarks',
                'photos',
                'file_path',
                'notes',
            ]);
        });
    }
};
