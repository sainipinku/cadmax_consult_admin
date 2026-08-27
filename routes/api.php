<?php

use App\Http\Controllers\Api\AdminDashboardApiController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ClientApiController;
use App\Http\Controllers\Api\CompanyApiController;
use App\Http\Controllers\Api\MemberDashboardController;
use App\Http\Controllers\Api\Mobile\ConstructionController;
use App\Http\Controllers\Api\Member\JobController;
use App\Http\Controllers\Api\Member\ProfileController;
use App\Http\Controllers\Api\Member\TaskController;
use App\Http\Controllers\Api\ProjectApiController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'success' => true,
        'message' => 'API is running.',
        'version' => '2.0',
        'endpoints' => [
            'auth' => '/api/auth/*',
            'profile' => '/api/profile',
            'member_dashboard' => '/api/member/dashboard',
            'admin_dashboard' => '/api/admin/dashboard',
            'projects' => '/api/construction/projects/*',
            'companies' => '/api/construction/companies/*',
            'clients' => '/api/construction/clients/*',
        ],
    ]);
});

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::get('/registration-status', [AuthController::class, 'registrationStatus']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/send-otp', [AuthController::class, 'sendOtp']);
    Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);

    Route::middleware('auth:sanctum')->post('/logout', [AuthController::class, 'logout']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);

    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);
    Route::post('/profile/change-password', [ProfileController::class, 'changePassword']);
    Route::get('/profile/completion', [ProfileController::class, 'completion']);
    Route::post('/profile/photo', [ProfileController::class, 'updatePhoto']);
    Route::delete('/profile/photo', [ProfileController::class, 'removePhoto']);
    Route::get('/profile/resume', [ProfileController::class, 'resume']);
    Route::post('/profile/resume', [ProfileController::class, 'uploadResume']);
    Route::delete('/profile/resume', [ProfileController::class, 'deleteResume']);
    Route::get('/profile/resume/view', [ProfileController::class, 'viewResume']);

    Route::get('/jobs', [JobController::class, 'index']);
    Route::get('/jobs/{job}', [JobController::class, 'show']);
    Route::post('/jobs/{job}/apply', [JobController::class, 'apply']);
    Route::post('/jobs/{job}/save', [JobController::class, 'save']);
    Route::delete('/jobs/{job}/save', [JobController::class, 'unsave']);
    Route::get('/saved-jobs', [JobController::class, 'savedIndex']);
    Route::get('/applications', [JobController::class, 'myApplications']);
    Route::delete('/applications/{application}', [JobController::class, 'withdraw']);

    Route::get('/tasks', [TaskController::class, 'index']);
    Route::get('/tasks/{task}', [TaskController::class, 'show']);
    Route::get('/tasks/{task}/notes', [TaskController::class, 'notesIndex']);
    Route::post('/tasks/{task}/notes', [TaskController::class, 'notesStore']);
    Route::delete('/notes/{note}', [TaskController::class, 'notesDestroy']);

    Route::prefix('member')->name('member.')->group(function () {
        Route::get('/dashboard', [MemberDashboardController::class, 'index']);
        Route::get('/dashboard/projects', [MemberDashboardController::class, 'myProjects']);
        Route::get('/dashboard/surveys', [MemberDashboardController::class, 'mySurveys']);
        Route::get('/dashboard/tasks', [MemberDashboardController::class, 'myTasks']);
        Route::get('/dashboard/attendance', [MemberDashboardController::class, 'myAttendance']);
        Route::get('/dashboard/projects/{project}', [MemberDashboardController::class, 'projectDetail']);

        // Mobile App direct routes matching tabs & screens
        Route::get('/projects', [MemberDashboardController::class, 'myProjects']);
        Route::get('/tasks', [MemberDashboardController::class, 'myTasks']);
        Route::post('/tasks', [MemberDashboardController::class, 'storeTask']);
        Route::post('/tasks/{task}/toggle', [MemberDashboardController::class, 'toggleTask']);
        Route::post('/tasks/{task}/status', [MemberDashboardController::class, 'updateTaskStatus']);
        Route::post('/attendance/check-in', [MemberDashboardController::class, 'checkIn']);
        Route::post('/attendance/{attendance}/check-out', [MemberDashboardController::class, 'checkOut']);
        Route::get('/notifications', [MemberDashboardController::class, 'notifications']);

        // Type 2: Survey Duty Location Check-In & Check-Out APIs (Image 2 & 5)
        Route::get('/survey-duty/status', [MemberDashboardController::class, 'surveyDutyStatus']);
        Route::post('/survey-duty/check-in', [MemberDashboardController::class, 'surveyDutyCheckIn']);
        Route::post('/survey-duty/{visit}/check-out', [MemberDashboardController::class, 'surveyDutyCheckOut']);
        Route::post('/survey-duty/submit-data', [MemberDashboardController::class, 'submitDayData']);

        // Task Checklist & Details APIs (Images 1, 3, 5)
        Route::get('/projects/{project}/survey-details', [MemberDashboardController::class, 'projectSurveyDetails']);
        Route::get('/tasks/{task}/details', [MemberDashboardController::class, 'taskDetails']);
        Route::post('/tasks/{task}/checklists', [MemberDashboardController::class, 'storeChecklist']);
        Route::post('/checklists/{checklist}/toggle', [MemberDashboardController::class, 'toggleChecklist']);

        // Profile Tab & Options Routes (Image 6)
        Route::get('/profile', [MemberDashboardController::class, 'profileIndex']);
        Route::get('/sites', [MemberDashboardController::class, 'myProjects']);
        Route::post('/sites', [MemberDashboardController::class, 'storeSite']);
        Route::get('/attendance', [MemberDashboardController::class, 'myAttendance']);
        Route::get('/surveys', [MemberDashboardController::class, 'mySurveys']);
        Route::post('/surveys', [MemberDashboardController::class, 'storeSurvey']);
        Route::post('/account/delete', [MemberDashboardController::class, 'deleteAccount']);
    });

    Route::get('/pages/privacy-policy', [MemberDashboardController::class, 'privacyPolicy']);
    Route::post('/pages/privacy-policy', [MemberDashboardController::class, 'storePrivacyPolicy']);

    Route::get('/pages/terms-and-conditions', [MemberDashboardController::class, 'termsAndConditions']);
    Route::post('/pages/terms-and-conditions', [MemberDashboardController::class, 'storeTermsAndConditions']);

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('/dashboard', [AdminDashboardApiController::class, 'index']);
    });
});

Route::prefix('construction')->middleware('auth:sanctum')->group(function () {

    Route::prefix('companies')->group(function () {
        Route::get('/all', [CompanyApiController::class, 'all']);
        Route::get('/', [CompanyApiController::class, 'index']);
        Route::post('/', [CompanyApiController::class, 'store']);
        Route::get('/{company}', [CompanyApiController::class, 'show']);
        Route::match(['put', 'post'], '/{company}', [CompanyApiController::class, 'update']);
        Route::delete('/{company}', [CompanyApiController::class, 'destroy']);
    });

    Route::prefix('clients')->group(function () {
        Route::get('/all', [ClientApiController::class, 'all']);
        Route::get('/', [ClientApiController::class, 'index']);
        Route::post('/', [ClientApiController::class, 'store']);
        Route::get('/{client}', [ClientApiController::class, 'show']);
        Route::match(['put', 'post'], '/{client}', [ClientApiController::class, 'update']);
        Route::delete('/{client}', [ClientApiController::class, 'destroy']);
    });

    Route::prefix('projects')->group(function () {
        Route::get('/stats', [ProjectApiController::class, 'stats']);
        Route::get('/', [ProjectApiController::class, 'index']);
        Route::post('/', [ProjectApiController::class, 'store']);
        Route::get('/{project}', [ProjectApiController::class, 'show']);
        Route::match(['put', 'post'], '/{project}', [ProjectApiController::class, 'update']);
        Route::delete('/{project}', [ProjectApiController::class, 'destroy']);
        Route::post('/{project}/status', [ProjectApiController::class, 'updateStatus']);

        Route::prefix('{project}/budget')->group(function () {
            Route::get('/', [ProjectApiController::class, 'budgets']);
            Route::post('/', [ProjectApiController::class, 'storeBudget']);
        });

        Route::prefix('{project}/team')->group(function () {
            Route::get('/', [ProjectApiController::class, 'team']);
            Route::post('/', [ProjectApiController::class, 'assignTeam']);
            Route::delete('/{teamMemberId}', [ProjectApiController::class, 'removeTeamMember']);
        });
    });

    Route::prefix('mobile/construction')->group(function () {
        Route::get('/projects/assigned', [ConstructionController::class, 'assignedProjects']);
        Route::get('/survey-plans/{surveyPlan}', [ConstructionController::class, 'showSurveyPlan'])
            ->middleware('construction.permission:survey_plan.manage');
        Route::post(
    '/survey-plans/{surveyPlan}/checklist-works',
    [
        ConstructionController::class,
        'storeSurveyChecklistWork',
    ]
)
    ->middleware(
        'construction.permission:survey_plan.manage'
    );

Route::patch(
    '/survey-plans/{surveyPlan}/checklist-works/{surveyWorkChecklist}',
    [
        ConstructionController::class,
        'updateSurveyChecklistWork',
    ]
)
    ->middleware(
        'construction.permission:survey_plan.manage'
    );    
        Route::post('/survey-visits/check-in', [ConstructionController::class, 'checkIn'])
            ->middleware('construction.permission:survey_plan.manage');
        Route::post('/survey-visits/{surveyVisit}/entries', [ConstructionController::class, 'storeEntry'])
            ->middleware('construction.permission:survey_plan.manage');
        Route::post('/survey-visits/{surveyVisit}/measurements', [ConstructionController::class, 'storeMeasurement'])
            ->middleware('construction.permission:survey_plan.manage');
        Route::post('/survey-visits/{surveyVisit}/submit', [ConstructionController::class, 'submitVisit'])
            ->middleware('construction.permission:survey_plan.manage');
        Route::get('/drafting-jobs', [ConstructionController::class, 'draftingJobs'])
            ->middleware('construction.permission:drafting.manage');
        Route::post('/drafting-jobs/{draftingJob}/revisions', [ConstructionController::class, 'submitRevision'])
            ->middleware('construction.permission:drafting.manage');
        Route::get('/tasks/assigned', [ConstructionController::class, 'assignedTasks'])
            ->middleware('construction.permission:execution_task.manage');
        Route::post('/attendance/check-in', [ConstructionController::class, 'attendanceCheckIn'])
            ->middleware('construction.permission:attendance.manage');
        Route::post('/attendance/{attendance}/check-out', [ConstructionController::class, 'attendanceCheckOut'])
            ->middleware('construction.permission:attendance.manage');
        Route::post('/tasks/{task}/progress', [ConstructionController::class, 'updateTaskProgress'])
            ->middleware('construction.permission:execution_task.manage');
        Route::post('/reports', [ConstructionController::class, 'submitDailyProgress'])
            ->middleware('construction.permission:dpr.manage');
        Route::get('/projects/{project}/vehicles', [ConstructionController::class, 'vehicles'])
            ->middleware('construction.permission:vehicle_tracking.manage');
        Route::post('/projects/{project}/vehicles/pings', [ConstructionController::class, 'vehiclePing'])
            ->middleware('construction.permission:vehicle_tracking.manage');
        Route::get('/projects/{project}/equipment', [ConstructionController::class, 'equipment'])
            ->middleware('construction.permission:equipment_usage.manage,equipment_allocation.manage');
        Route::post('/projects/{project}/equipment/usage', [ConstructionController::class, 'equipmentUsage'])
            ->middleware('construction.permission:equipment_usage.manage');
        Route::post('/projects/{project}/equipment/return', [ConstructionController::class, 'equipmentReturn'])
            ->middleware('construction.permission:equipment_allocation.manage');
        Route::get('/projects/{project}/billing', [ConstructionController::class, 'billing'])
            ->middleware('construction.permission:billing_invoice.manage,billing_payment.manage');
        Route::get('/projects/{project}/handover', [ConstructionController::class, 'handover'])
            ->middleware('construction.permission:handover.manage,project_closure.manage');
        Route::get('/documents/{document}/view', [App\Http\Controllers\Construction\DocumentController::class, 'view'])
            ->middleware('construction.permission:document.manage');
        Route::get('/documents/{document}/download', [App\Http\Controllers\Construction\DocumentController::class, 'download'])
            ->middleware('construction.permission:document.manage');
    });
});

Route::prefix('super')->middleware('auth:sanctum')->name('api.super.')->group(function () {
    Route::prefix('members')->name('members.')->group(function () {
        Route::get('/pending', [App\Http\Controllers\Api\SuperAdminApprovalApiController::class, 'pendingList']);
        Route::get('/approved', [App\Http\Controllers\Api\SuperAdminApprovalApiController::class, 'approvedList']);
        Route::get('/rejected', [App\Http\Controllers\Api\SuperAdminApprovalApiController::class, 'rejectedList']);
        Route::get('/stats', [App\Http\Controllers\Api\SuperAdminApprovalApiController::class, 'approvalStats']);
        Route::get('/{member}/show', [App\Http\Controllers\Api\SuperAdminApprovalApiController::class, 'show']);
        Route::post('/{member}/approve', [App\Http\Controllers\Api\SuperAdminApprovalApiController::class, 'approve']);
        Route::post('/{member}/reject', [App\Http\Controllers\Api\SuperAdminApprovalApiController::class, 'reject']);
        Route::post('/{member}/assign-admin', [App\Http\Controllers\Api\SuperAdminApprovalApiController::class, 'assignAdmin']);
    });

    Route::get('/roles/construction', [App\Http\Controllers\Api\SuperAdminApprovalApiController::class, 'constructionRoles']);
    Route::get('/departments/all', [App\Http\Controllers\Api\SuperAdminApprovalApiController::class, 'allDepartments']);
    Route::get('/designations/by-departments', [App\Http\Controllers\Api\SuperAdminApprovalApiController::class, 'designationsByDepartments']);
});
