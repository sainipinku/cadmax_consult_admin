<?php

namespace App\Http\Controllers\Admin\Construction;

use App\Http\Controllers\Concerns\ResolvesConstructionActor;
use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\DraftingJob;
use App\Models\DailyProgressReport;
use App\Models\ExecutionTask;
use App\Models\Project;
use App\Models\ProjectTeamMember;
use App\Models\SurveyPlan;
use App\Models\SurveySubmission;
use App\Models\Member;
use Inertia\Inertia;
use Inertia\Response;



class DashboardController extends Controller
{
    use ResolvesConstructionActor;








    public function index(): Response
    {
        /** @var Member|null $actor */
        $actor = $this->constructionActor();
        $projectIds = ProjectTeamMember::where('member_id', $actor?->getKey())->pluck('project_id');

        return Inertia::render('Admin/Construction/Dashboard', [
            'stats' => [
                'assignedProjects' => $projectIds->count(),
                'surveyPlans' => SurveyPlan::whereIn('project_id', $projectIds)->count(),
                'surveyApprovalsPending' => SurveySubmission::whereIn('project_id', $projectIds)
                    ->where('status', SurveySubmission::STATUS_SUBMITTED)
                    ->count(),
                'draftingQueue' => DraftingJob::whereIn('project_id', $projectIds)
                    ->whereIn('status', ['queued', 'in_progress', 'submitted'])
                    ->count(),
                'readyForConstruction' => Project::whereIn('id', $projectIds)
                    ->where('current_stage', 'ready_for_construction')
                    ->count(),
                'executionTasks' => ExecutionTask::whereIn('project_id', $projectIds)->count(),
                'dprPending' => DailyProgressReport::whereIn('project_id', $projectIds)
                    ->where('status', 'submitted')
                    ->count(),
                'attendancePending' => AttendanceRecord::whereIn('project_id', $projectIds)
                    ->where('status', 'pending')
                    ->count(),
            ],
            'projects' => Project::with(['company', 'client', 'latestBudget'])
                ->whereIn('id', $projectIds)
                ->latest()
                ->get(),
        ]);
    }
}
