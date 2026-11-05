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

        \Illuminate\Support\Facades\Gate::define('viewApiDocs', function ($user = null) {
            return true;
        });

        if (class_exists(\Dedoc\Scramble\Scramble::class)) {
            \Dedoc\Scramble\Scramble::afterOpenApiGenerated(function (\Dedoc\Scramble\Support\Generator\OpenApi $openApi) {
                $ignoredRawTags = [
                    'AdminDashboardApi', 'ClientApi', 'CompanyApi', 'ProjectApi',
                    'Job', 'Profile', 'Task', 'MemberDashboard', 'Construction',
                    'ProjectTaskApi', 'SuperAdminApprovalApi', 'Document',
                ];

                foreach ($openApi->paths as $pathObj) {
                    $path = '/' . ltrim($pathObj->path, '/');
                    foreach ($pathObj->operations as $method => $operation) {
                        // Filter out raw controller class tags
                        $cleanTags = array_values(array_filter(
                            $operation->tags,
                            fn ($tag) => !in_array($tag, $ignoredRawTags, true)
                        ));

                        // If no tag left or untagged, assign a clean primary folder
                        if (empty($cleanTags)) {
                            if (str_starts_with($path, '/api/auth') || str_starts_with($path, '/auth')) {
                                $cleanTags = ['Authentication'];
                            } elseif (str_contains($path, 'survey-duty') || str_contains($path, 'driver-duty') || str_contains($path, 'driver-trip') || str_contains($path, 'survey')) {
                                $cleanTags = ['Survey & Driver Duty'];
                            } elseif (str_contains($path, 'fuel')) {
                                $cleanTags = ['Survey & Driver Duty'];
                            } elseif (str_contains($path, 'vehicle')) {
                                $cleanTags = ['Member Fleet & Vehicles'];
                            } elseif (str_contains($path, 'equipment')) {
                                $cleanTags = ['Member Equipment & Assets'];
                            } elseif (str_contains($path, 'location')) {
                                $cleanTags = ['Member Location & GPS Tracking'];
                            } elseif (str_contains($path, 'attendance') || str_contains($path, 'dashboard') || str_contains($path, 'notification')) {
                                $cleanTags = ['Member Dashboard & Attendance'];
                            } elseif (str_contains($path, 'task') || str_contains($path, 'checklist')) {
                                $cleanTags = ['Member Tasks & Checklists'];
                            } elseif (str_starts_with($path, '/api/profile') || str_starts_with($path, '/profile') || str_contains($path, '/member/profile') || str_contains($path, '/account/delete')) {
                                $cleanTags = ['Member Profile'];
                            } elseif (str_starts_with($path, '/api/jobs') || str_starts_with($path, '/jobs') || str_contains($path, 'applications') || str_contains($path, 'saved-jobs')) {
                                $cleanTags = ['Job Portal & Applications'];
                            } elseif (str_contains($path, '/projects') || str_contains($path, '/sites')) {
                                $cleanTags = ['Construction Projects'];
                            } elseif (str_contains($path, '/construction/companies')) {
                                $cleanTags = ['Companies & Organizations'];
                            } elseif (str_contains($path, '/construction/clients')) {
                                $cleanTags = ['Clients'];
                            } elseif (str_contains($path, '/construction/mobile')) {
                                $cleanTags = ['Mobile Field Operations'];
                            } elseif (str_starts_with($path, '/api/super') || str_starts_with($path, '/super')) {
                                $cleanTags = ['SuperAdmin Member Approvals'];
                            } elseif (str_contains($path, 'document')) {
                                $cleanTags = ['Documents & Media'];
                            } elseif ($path === '/' || $path === '/api') {
                                $cleanTags = ['System Health & Index'];
                            } else {
                                $cleanTags = ['System & Legal Policies'];
                            }
                        }

                        // Normalize common tags for clean UI presentation
                        $normalized = [];
                        foreach ($cleanTags as $t) {
                            if ($t === 'Auth') {
                                $t = 'Authentication';
                            } elseif ($t === 'Member Jobs') {
                                $t = 'Job Portal & Applications';
                            } elseif ($t === 'Member Tasks') {
                                $t = 'Member Tasks & Checklists';
                            } elseif ($t === 'Super Admin Approvals') {
                                $t = 'SuperAdmin Member Approvals';
                            } elseif ($t === 'Project Tasks') {
                                $t = 'Project Tasks & Execution';
                            } elseif ($t === 'Vehicles' || $t === 'Vehicle') {
                                $t = 'Member Fleet & Vehicles';
                            } elseif ($t === 'Equipment') {
                                $t = 'Member Equipment & Assets';
                            } elseif ($t === 'System Health') {
                                $t = 'System Health & Index';
                            }
                            $normalized[$t] = true;
                        }

                        $operation->tags = array_keys($normalized);
                    }
                }
            });
        }
    }
}
