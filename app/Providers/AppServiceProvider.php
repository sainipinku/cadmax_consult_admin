<?php

namespace App\Providers;

use App\Models\Admin;
use App\Models\ConstructionDocument;
use App\Models\DailyProgressReport;
use App\Models\ExecutionPlan;
use App\Models\ExecutionTask;
use App\Models\ExecutionTaskAssignee;
use App\Models\Member;
use App\Models\Staff;
use App\Models\SurveyPlan;
use App\Models\SurveyPlanMember;
use App\Models\SurveyVisit;
use App\Models\SurveyVisitEntry;
use App\Models\SuperAdmin;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\TaskChecklistItem;
use App\Models\TaskComment;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        Relation::enforceMorphMap([
            'super_admin' => SuperAdmin::class,
            'admin' => Admin::class,
            'member' => Member::class,
            'staff' => Staff::class,
            'task' => Task::class,
            'task_assignment' => TaskAssignment::class,
            'task_checklist' => TaskChecklistItem::class,
            'task_comment' => TaskComment::class,
            'execution_task' => ExecutionTask::class,
            'execution_task_assignee' => ExecutionTaskAssignee::class,
            'execution_plan' => ExecutionPlan::class,
            'survey_plan' => SurveyPlan::class,
            'survey_plan_member' => SurveyPlanMember::class,
            'survey_visit' => SurveyVisit::class,
            'survey_visit_entry' => SurveyVisitEntry::class,
            'dpr' => DailyProgressReport::class,
            'construction_document' => ConstructionDocument::class,
        ]);
    }
}
