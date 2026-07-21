<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Mobile\ConstructionController;
use App\Http\Controllers\Api\Member\JobController;
use App\Http\Controllers\Api\Member\ProfileController;
use App\Http\Controllers\Api\Member\TaskController;
use Illuminate\Support\Facades\Route;

    Route::get('/', function () {
        return response()->json([
            'success' => true,
            'message' => 'API is running.',
        ]);
    });

    Route::prefix('auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/send-otp', [AuthController::class, 'sendOtp']);
        Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);

        Route::middleware('auth:sanctum')->post('/logout', [AuthController::class, 'logout']);
    });


    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);

        Route::get('/profile', [ProfileController::class, 'show']);
        Route::put('/profile', [ProfileController::class, 'update']);
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

        Route::prefix('mobile/construction')->group(function () {
            Route::get('/projects/assigned', [ConstructionController::class, 'assignedProjects']);
            Route::get('/survey-plans/{surveyPlan}', [ConstructionController::class, 'showSurveyPlan'])
                ->middleware('construction.permission:survey_plan.manage');
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
