<?php

namespace App\Http\Controllers\SuperAdmin\Construction;

use App\Http\Controllers\Concerns\ResolvesConstructionActor;
use App\Http\Controllers\Controller;
use App\Models\Construction\ActivityLog;
use App\Models\Construction\AttendanceRecord;
use App\Models\Construction\Client;
use App\Models\Construction\Company;
use App\Models\Construction\DailyProgressReport;
use App\Models\Construction\DraftingJob;
use App\Models\Construction\Project;
use App\Models\Construction\SurveyPlan;
use App\Models\Construction\SurveySubmission;
use App\Models\Construction\ExecutionTask;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    use ResolvesConstructionActor;

    public function index(): Response
    {
        $projects = Project::with(['company', 'client', 'latestBudget'])
            ->latest()
            ->take(8)
            ->get();

        return Inertia::render('SuperAdmin/Construction/Dashboard', [
            'auth' => $this->constructionActor(),
            'stats' => [
                'companies' => Company::count(),
                'clients' => Client::count(),
                'projects' => Project::count(),
                'budgetPending' => Project::where('current_stage', 'budget_pending')->count(),
                'teamAssigned' => Project::where('current_stage', 'team_assigned')->count(),
                'surveyPlanned' => SurveyPlan::whereIn('status', ['planned', 'in_progress'])->count(),
                'surveyApprovalsPending' => SurveySubmission::where('status', 'submitted')->count(),
                'draftingQueue' => DraftingJob::whereIn('status', ['queued', 'in_progress'])->count(),
                'readyForConstruction' => Project::where('current_stage', 'ready_for_construction')->count(),
                'executionTasks' => ExecutionTask::count(),
                'dprPending' => DailyProgressReport::where('status', 'submitted')->count(),
                'attendancePending' => AttendanceRecord::where('status', 'pending')->count(),
            ],
            'recentProjects' => $projects,
            'recentActivity' => ActivityLog::with(['project'])
                ->latest('created_at')
                ->take(10)
                ->get(),
        ]);
    }
}
