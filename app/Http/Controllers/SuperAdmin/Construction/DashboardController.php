<?php

namespace App\Http\Controllers\SuperAdmin\Construction;

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
use App\Models\MaterialReceipt;
use App\Models\MaterialReceiptItem;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\SurveyPlan;
use App\Models\SurveyPlanMember;
use App\Models\SurveySubmission;
use App\Models\ExecutionTask;
use App\Models\ConstructionVehicle;
use App\Models\VehicleAssignment;
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
        $now = Carbon::now();
        $monthStart = $now->copy()->startOfMonth();
        $today = $now->copy()->toDateString();

        $projects = Project::with(['company', 'client', 'latestBudget'])
            ->latest()
            ->take(8)
            ->get();

        $allProjects = Project::all(['id', 'status', 'current_stage']);

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

        $surveyTeams = SurveyPlan::distinct()->count('id');
        $surveyTeamMembers = SurveyPlanMember::distinct()->count('member_id');

        $totalVehicles = ConstructionVehicle::count();
        $activeVehicles = ConstructionVehicle::whereIn('status', ['active', 'assigned', 'in_use'])->count();

        $totalEquipment = ConstructionEquipment::count();
        $allocatedEquipment = EquipmentAllocation::distinct()->count('equipment_id');

        $totalClients = Client::count();
        $companyClients = Client::where('client_type', 'company')->count();
        $govtClients = Client::where('client_type', 'government')->count();

        $revenue = ClientPayment::whereBetween('created_at', [$monthStart, $now])->sum('amount');
        $totalRevenue = ClientPayment::sum('amount');

        $invoiceAmountThisMonth = ClientInvoice::whereBetween('created_at', [$monthStart, $now])->sum('total_amount');

        $materialPurchaseExpense = PurchaseOrder::whereBetween('po_date', [$monthStart->toDateString(), $today])
            ->whereIn('status', ['approved', 'issued', 'partially_received', 'received', 'closed'])
            ->sum('total_amount');
        $materialReceiptExpense = MaterialReceiptItem::whereHas('receipt', function ($query) use ($monthStart, $today) {
            $query->whereBetween('created_at', [$monthStart, Carbon::parse($today)->endOfDay()]);
        })->sum('line_total');
        $labourDaysThisMonth = AttendanceRecord::whereBetween('attendance_date', [$monthStart->toDateString(), $today])
            ->whereIn('status', ['approved', 'present'])
            ->count();
        $standardDailyRate = 1200.00;
        $labourExpense = $labourDaysThisMonth * $standardDailyRate;

        $monthlyExpenses = (float) $materialPurchaseExpense
            + (float) $materialReceiptExpense
            + (float) $labourExpense;

        $todayAttendance = AttendanceRecord::where('attendance_date', $today)->count();
        $presentToday = AttendanceRecord::where('attendance_date', $today)->where('status', 'approved')->count();
        $pendingAttendance = AttendanceRecord::where('status', 'pending')->count();
        $attendanceThisMonth = AttendanceRecord::whereBetween('attendance_date', [$monthStart->toDateString(), $today])->count();

        $activeGPSVehicles = VehicleLocationPing::whereBetween('created_at', [$now->copy()->subHours(24), $now])
            ->distinct()
            ->count('vehicle_id');
        $totalGPSPingsToday = VehicleLocationPing::whereBetween('created_at', [$now->copy()->startOfDay(), $now])->count();

        $projectStageDistribution = Project::selectRaw('current_stage, COUNT(*) as count')
            ->groupBy('current_stage')
            ->pluck('count', 'current_stage')
            ->toArray();

        return Inertia::render('SuperAdmin/Construction/Dashboard', [
            'auth' => $this->constructionActor(),
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
                'projectsLegacy' => Project::count(),
                'budgetPending' => Project::where('current_stage', 'budget_pending')->count(),
                'teamAssigned' => Project::where('current_stage', 'team_assigned')->count(),
               'surveyPlanned' => SurveyPlan::whereIn('status', [SurveyPlan::STATUS_PLANNED,SurveyPlan::STATUS_IN_PROGRESS,])->count(),
                'surveyApprovalsPending' => SurveySubmission::where('status',SurveySubmission::STATUS_SUBMITTED)->count(),
                'draftingQueue' => DraftingJob::whereIn('status', ['queued', 'in_progress'])->count(),
                'readyForConstruction' => Project::where('current_stage', 'ready_for_construction')->count(),
                'executionTasks' => ExecutionTask::count(),
                'dprPending' => DailyProgressReport::where('status', 'submitted')->count(),
                'attendancePending' => AttendanceRecord::where('status', 'pending')->count(),
            ],
            'recentProjects' => $projects,
            'recentActivity' => ConstructionActivityLog::with(['project'])
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
