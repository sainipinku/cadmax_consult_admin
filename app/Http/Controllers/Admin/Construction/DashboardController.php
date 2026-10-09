<?php

namespace App\Http\Controllers\Admin\Construction;

use App\Http\Controllers\Concerns\ResolvesConstructionActor;
use App\Http\Controllers\Controller;
use App\Models\ConstructionActivityLog;
use App\Models\AttendanceRecord;
use App\Models\Client;
use App\Models\ClientInvoice;
use App\Models\ClientPayment;
use App\Models\Company;
use App\Models\DailyProgressReport;
use App\Models\DraftingJob;
use App\Models\ConstructionEquipment;
use App\Models\EquipmentAllocation;
use App\Models\MaterialReceiptItem;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\SurveyPlan;
use App\Models\SurveyPlanMember;
use App\Models\SurveySubmission;
use App\Models\ExecutionTask;
use App\Models\ConstructionVehicle;
use App\Models\VehicleLocationPing;
use App\Models\Member;
use Carbon\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    use ResolvesConstructionActor;

    public function index(): Response
    {
        $actor = $this->constructionActor();
        $projectIds = $this->getAccessibleProjectIds($actor);

        $now = Carbon::now();
        $monthStart = $now->copy()->startOfMonth();
        $today = $now->copy()->toDateString();

        $projects = Project::with(['company', 'client', 'latestBudget'])
            ->whereIn('id', $projectIds)
            ->latest()
            ->take(8)
            ->get();

        $allProjects = Project::whereIn('id', $projectIds)->get(['id', 'status', 'current_stage']);

        $runningStages = [
            'budget_approved',
            'team_assigned',
            'survey_planned',
            'survey_in_progress',
            'drafting_in_progress',
            'drawing_approval_pending',
            'ready_for_construction',
            'planning',
            'survey',
            'foundation',
            'structure',
            'finishing',
            'handover',
        ];
        $pendingStages = ['budget_pending', 'draft'];
        $completedStages = ['completed', 'closed'];

        $totalProjects = $allProjects->count();
        $runningProjects = $allProjects->whereIn('current_stage', $runningStages)->count();
        $completedProjects = $allProjects->whereIn('current_stage', $completedStages)->count();
        $pendingProjects = $allProjects->whereIn('current_stage', $pendingStages)->count();

        $totalEmployees = Member::count();
        $activeEmployees = Member::where('status', 'active')->count();

        $surveyTeams = SurveyPlan::whereIn('project_id', $projectIds)->distinct()->count('id');
        $surveyTeamMembers = SurveyPlanMember::distinct()->count('member_id');

        $totalVehicles = ConstructionVehicle::count();
        $activeVehicles = ConstructionVehicle::whereIn('status', ['active', 'assigned', 'in_use'])->count();

        $totalEquipment = ConstructionEquipment::count();
        $allocatedEquipment = EquipmentAllocation::distinct()->count('equipment_id');

        $totalClients = Client::count();
        $companyClients = Client::where('client_type', 'company')->count();
        $govtClients = Client::where('client_type', 'government')->count();

        $revenue = ClientPayment::whereIn('project_id', $projectIds)->whereBetween('created_at', [$monthStart, $now])->sum('amount');
        $totalRevenue = ClientPayment::whereIn('project_id', $projectIds)->sum('amount');

        $invoiceAmountThisMonth = ClientInvoice::whereIn('project_id', $projectIds)->whereBetween('created_at', [$monthStart, $now])->sum('total_amount');

        $materialPurchaseExpense = PurchaseOrder::whereIn('project_id', $projectIds)
            ->whereBetween('po_date', [$monthStart->toDateString(), $today])
            ->whereIn('status', ['approved', 'issued', 'partially_received', 'received', 'closed'])
            ->sum('total_amount');

        $materialReceiptExpense = MaterialReceiptItem::whereHas('receipt', function ($query) use ($monthStart, $today, $projectIds) {
            $query->whereIn('project_id', $projectIds)->whereBetween('created_at', [$monthStart, Carbon::parse($today)->endOfDay()]);
        })->sum('line_total');

        $labourDaysThisMonth = AttendanceRecord::whereIn('project_id', $projectIds)
            ->whereBetween('attendance_date', [$monthStart->toDateString(), $today])
            ->whereIn('status', ['approved', 'present'])
            ->count();
        $standardDailyRate = 1200.00;
        $labourExpense = $labourDaysThisMonth * $standardDailyRate;

        $monthlyExpenses = (float) $materialPurchaseExpense
            + (float) $materialReceiptExpense
            + (float) $labourExpense;

        $todayAttendance = AttendanceRecord::whereIn('project_id', $projectIds)->where('attendance_date', $today)->count();
        $presentToday = AttendanceRecord::whereIn('project_id', $projectIds)->where('attendance_date', $today)->where('status', 'approved')->count();
        $pendingAttendance = AttendanceRecord::whereIn('project_id', $projectIds)->where('status', 'pending')->count();
        $attendanceThisMonth = AttendanceRecord::whereIn('project_id', $projectIds)->whereBetween('attendance_date', [$monthStart->toDateString(), $today])->count();

        $activeGPSVehicles = VehicleLocationPing::whereBetween('created_at', [$now->copy()->subHours(24), $now])
            ->distinct()
            ->count('vehicle_id');
        $totalGPSPingsToday = VehicleLocationPing::whereBetween('created_at', [$now->copy()->startOfDay(), $now])->count();

        $projectStageDistribution = Project::whereIn('id', $projectIds)
            ->selectRaw('current_stage, COUNT(*) as count')
            ->groupBy('current_stage')
            ->pluck('count', 'current_stage')
            ->toArray();

        return Inertia::render('SuperAdmin/Construction/Dashboard', [
            'stats' => [
                'projects' => [
                    'total' => $totalProjects,
                    'running' => $runningProjects,
                    'completed' => $completedProjects,
                    'pending' => $pendingProjects,
                ],
                'employees' => [
                    'total' => $totalEmployees,
                    'active' => $activeEmployees,
                ],
                'survey' => [
                    'teams' => $surveyTeams,
                    'members' => $surveyTeamMembers,
                ],
                'vehicles' => [
                    'total' => $totalVehicles,
                    'active' => $activeVehicles,
                ],
                'equipment' => [
                    'total' => $totalEquipment,
                    'allocated' => $allocatedEquipment,
                ],
                'clients' => [
                    'total' => $totalClients,
                    'company' => $companyClients,
                    'government' => $govtClients,
                ],
                'finance' => [
                    'monthlyRevenue' => (float)$revenue,
                    'monthlyExpenses' => $monthlyExpenses,
                    'totalRevenue' => (float)$totalRevenue,
                    'monthlyInvoiced' => (float)$invoiceAmountThisMonth,
                    'breakdown' => [
                        'materialPurchase' => (float)$materialPurchaseExpense,
                        'materialReceipt' => (float)$materialReceiptExpense,
                        'labour' => (float)$labourExpense,
                        'labourDays' => $labourDaysThisMonth,
                        'standardDailyRate' => $standardDailyRate,
                    ],
                ],
                'attendance' => [
                    'today' => $todayAttendance,
                    'presentToday' => $presentToday,
                    'pending' => $pendingAttendance,
                    'thisMonth' => $attendanceThisMonth,
                ],
                'gps' => [
                    'activeVehicles24h' => $activeGPSVehicles,
                    'pingsToday' => $totalGPSPingsToday,
                ],
                'stageDistribution' => $projectStageDistribution,

                'companies' => Company::count(),
                'clientsLegacy' => Client::count(),
                'projectsLegacy' => Project::whereIn('id', $projectIds)->count(),
                'budgetPending' => Project::whereIn('id', $projectIds)->where('current_stage', 'budget_pending')->count(),
                'teamAssigned' => Project::whereIn('id', $projectIds)->where('current_stage', 'team_assigned')->count(),
                'surveyPlanned' => SurveyPlan::whereIn('project_id', $projectIds)->whereIn('status', [SurveyPlan::STATUS_PLANNED, SurveyPlan::STATUS_IN_PROGRESS])->count(),
                'surveyApprovalsPending' => SurveySubmission::whereIn('project_id', $projectIds)->where('status', SurveySubmission::STATUS_SUBMITTED)->count(),
                'draftingQueue' => DraftingJob::whereIn('project_id', $projectIds)->whereIn('status', ['queued', 'in_progress'])->count(),
                'readyForConstruction' => Project::whereIn('id', $projectIds)->where('current_stage', 'ready_for_construction')->count(),
                'executionTasks' => ExecutionTask::whereIn('project_id', $projectIds)->count(),
                'dprPending' => DailyProgressReport::whereIn('project_id', $projectIds)->where('status', 'submitted')->count(),
                'attendancePending' => AttendanceRecord::whereIn('project_id', $projectIds)->where('status', 'pending')->count(),
            ],
            'recentProjects' => $projects,
            'recentActivity' => ConstructionActivityLog::with(['project'])
                ->whereIn('project_id', $projectIds)
                ->latest('created_at')
                ->take(10)
                ->get(),
            'projectStatusOptions' => [
                ['value' => 'planning', 'label' => 'Planning'],
                ['value' => 'survey', 'label' => 'Survey'],
                ['value' => 'foundation', 'label' => 'Foundation'],
                ['value' => 'structure', 'label' => 'Structure'],
                ['value' => 'finishing', 'label' => 'Finishing'],
                ['value' => 'handover', 'label' => 'Handover'],
                ['value' => 'completed', 'label' => 'Completed'],
            ],
        ]);
    }
}
