<?php

namespace App\Http\Controllers\SuperAdmin\Construction;

use App\Http\Controllers\Concerns\ResolvesConstructionActor;
use App\Http\Controllers\Controller;
use App\Http\Requests\AssignTeamMemberRequest;
use App\Models\Construction\Client;
use App\Models\Construction\Company;
use App\Models\Construction\ActivityLog;
use App\Models\Construction\MemberRoleAssignment;
use App\Models\Construction\Project;
use App\Models\Construction\ProjectBudget;
use App\Models\Construction\ProjectTeamMember;
use App\Models\Construction\Role;
use App\Models\Member;
use App\Services\Construction\ConstructionActivityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    use ResolvesConstructionActor;

    public function index(): Response
    {
        return Inertia::render('SuperAdmin/Construction/Projects/Index', [
            'projects' => Project::with(['company', 'client', 'latestBudget'])
                ->latest()
                ->get(),
            'companies' => Company::orderBy('name')->get(['id', 'name']),
            'clients' => Client::orderBy('name')->get(['id', 'name', 'company_id']),
        ]);
    }

    public function show(Project $project): Response
    {
        $project->load([
            'company',
            'client',
            'budgets' => fn ($query) => $query->latest('version_no'),
            'teamMembers.member',
            'teamMembers.role',
            'surveyPlans.planMembers.member',
            'surveyPlans.visits.checkedInBy',
            'surveyPlans.visits.entries.capturedBy',
            'surveyPlans.visits.measurements.capturedBy',
            'surveyPlans.visits.submission.submittedBy',
            'surveyPlans.visits.submission.reviewedBy',
            'surveySubmissions.submittedBy',
            'surveySubmissions.reviewedBy',
            'surveySubmissions.surveyVisit.checkedInBy',
            'draftingJobs.assignedTo',
            'draftingJobs.drawingRevisions.uploadedBy',
            'draftingJobs.drawingRevisions.dwgDocument',
            'draftingJobs.drawingRevisions.pdfDocument',
            'draftingJobs.drawingRevisions.approvals.approvedBy',
            'drawingApprovals.drawingRevision',
        ]);

        // Ensure core construction roles exist
        $defaultRoles = [
            ['name' => 'Project Admin', 'slug' => 'project_admin', 'description' => 'Project scoped ERP access', 'is_system_role' => true, 'status' => 'active'],
            ['name' => 'Surveyor', 'slug' => 'surveyor', 'description' => 'Field survey execution', 'is_system_role' => true, 'status' => 'active'],
            ['name' => 'Draft Person', 'slug' => 'draft_person', 'description' => 'Drafting and revisions', 'is_system_role' => true, 'status' => 'active'],
            ['name' => 'Vehicle Driver', 'slug' => 'vehicle_driver', 'description' => 'Vehicle transport and site movement', 'is_system_role' => true, 'status' => 'active'],
            ['name' => 'Review Approver', 'slug' => 'review_approver', 'description' => 'Workflow approvals', 'is_system_role' => true, 'status' => 'active'],
            ['name' => 'Site Employee', 'slug' => 'site_employee', 'description' => 'Construction execution updates and attendance', 'is_system_role' => true, 'status' => 'active'],
        ];

        foreach ($defaultRoles as $rData) {
            Role::firstOrCreate(['slug' => $rData['slug']], $rData);
        }

        $members = Member::where('status', 1)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'departments', 'designation']);

        $members->transform(function ($member) {
            $desigStr = '';
            if (!empty($member->designation)) {
                if (is_array($member->designation)) {
                    $desigValues = array_values($member->designation);
                    if (isset($desigValues[0]) && is_numeric($desigValues[0])) {
                        $desigStr = \App\Models\Designation::whereIn('id', $desigValues)->pluck('name')->implode(', ');
                    } else {
                        $desigStr = implode(', ', $desigValues);
                    }
                } else {
                    $desigStr = (string)$member->designation;
                }
            }
            $member->designation_text = $desigStr;
            return $member;
        });

        return Inertia::render('SuperAdmin/Construction/Projects/Show', [
            'project' => $project,
            'members' => $members,
            'roles' => Role::where('status', 'active')
                ->where('slug', '!=', 'super_admin')
                ->orderBy('name')
                ->get(['id', 'name', 'slug']),
            'activityLog' => ActivityLog::with('actor')
                ->where('project_id', $project->id)
                ->latest('created_at')
                ->take(15)
                ->get(),
        ]);
    }

    public function store(Request $request, ConstructionActivityService $activityService): RedirectResponse
    {
        $actor = $this->constructionActor();

        $validated = $request->validate([
            'company_id' => ['required', 'exists:construction_companies,id'],
            'client_id' => ['required', 'exists:construction_clients,id'],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'project_address' => ['nullable', 'string'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'start_date' => ['nullable', 'date'],
            'expected_end_date' => ['nullable', 'date'],
            'priority' => ['required', 'in:low,medium,high,critical'],
        ]);

        $nextId = (Project::max('id') ?? 0) + 1;
        $nameSlug = Str::slug($validated['name']);

        $project = Project::create([
            ...$validated,
            'project_code' => 'PRJ-' . str_pad((string) $nextId, 5, '0', STR_PAD_LEFT),
            'slug' => $nameSlug . '-' . $nextId,
            'status' => 'draft',
            'current_stage' => 'budget_pending',
            'created_by_type' => $actor ? $actor::class : null,
            'created_by_id' => $actor?->getKey(),
        ]);

        $activityService->log(
            module: 'project',
            action: 'created',
            actor: $actor,
            reference: $project,
            companyId: $project->company_id,
            projectId: $project->id,
            meta: ['project_code' => $project->project_code],
            request: $request
        );

        return redirect()->route('super.construction.projects.show', $project)
            ->with('success', 'Project created successfully.');
    }

    public function storeBudget(Project $project, Request $request, ConstructionActivityService $activityService): RedirectResponse
    {
        $actor = $this->constructionActor();

        $validated = $request->validate([
            'estimated_amount' => ['required', 'numeric', 'min:0'],
            'approved_amount' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'notes' => ['nullable', 'string'],
            'status' => ['required', 'in:pending,approved,rejected'],
        ]);

        $budget = ProjectBudget::create([
            ...$validated,
            'project_id' => $project->id,
            'version_no' => (int) $project->budgets()->max('version_no') + 1,
            'submitted_by_type' => $actor ? $actor::class : null,
            'submitted_by_id' => $actor?->getKey(),
            'approved_by_type' => $validated['status'] === 'approved' && $actor ? $actor::class : null,
            'approved_by_id' => $validated['status'] === 'approved' ? $actor?->getKey() : null,
            'approved_at' => $validated['status'] === 'approved' ? now() : null,
        ]);

        $project->update([
            'status' => $validated['status'] === 'approved' ? 'active' : $project->status,
            'current_stage' => $validated['status'] === 'approved' ? 'budget_approved' : 'budget_pending',
        ]);

        $activityService->log(
            module: 'project_budget',
            action: $validated['status'],
            actor: $actor,
            reference: $budget,
            companyId: $project->company_id,
            projectId: $project->id,
            meta: ['version_no' => $budget->version_no],
            request: $request
        );

        return back()->with('success', 'Project budget saved successfully.');
    }

    public function assignTeam(Project $project, AssignTeamMemberRequest $request, ConstructionActivityService $activityService): RedirectResponse
    {
        $actor = $this->constructionActor();
        $validated = $request->validated();

        // Application-level duplicate check for user-friendly error
        $exists = ProjectTeamMember::where('project_id', $project->id)
            ->where('member_id', $validated['member_id'])
            ->exists();

        if ($exists) {
            return back()->with('error', 'This member is already assigned to this project. Each member can only be assigned once per project.');
        }

        try {
            $teamMember = ProjectTeamMember::create([
                'project_id' => $project->id,
                'member_id' => $validated['member_id'],
                'role_id' => $validated['role_id'] ?? null,
                'assigned_from' => $validated['assigned_from'] ?? null,
                'assigned_to' => $validated['assigned_to'] ?? null,
                'assignment_scope' => $validated['assignment_scope'] ?? null,
                'is_primary' => (bool) ($validated['is_primary'] ?? false),
                'status' => $validated['status'] ?? 'active',
                'assigned_by_type' => $actor ? $actor::class : null,
                'assigned_by_id' => $actor?->getKey(),
            ]);

            if (!empty($validated['role_id'])) {
                MemberRoleAssignment::updateOrCreate([
                    'member_id' => $validated['member_id'],
                    'role_id' => $validated['role_id'],
                    'project_id' => $project->id,
                ]);
            }

            if ($project->current_stage === 'budget_approved') {
                $project->update(['current_stage' => 'team_assigned']);
            }

            $activityService->log(
                module: 'project_team',
                action: 'assigned',
                actor: $actor,
                reference: $teamMember,
                companyId: $project->company_id,
                projectId: $project->id,
                meta: ['member_id' => $teamMember->member_id],
                request: $request
            );

            return back()->with('success', 'Project team member assigned successfully.');
        } catch (\Illuminate\Database\QueryException $e) {
            // Handle race condition - database unique constraint violation
            if (str_contains($e->getMessage(), 'construction_project_team_unique')) {
                return back()->with('error', 'This member is already assigned to this project. Please refresh and try again.');
            }
            throw $e;
        }
    }

    public function updateTeamMember(Project $project, ProjectTeamMember $teamMember, AssignTeamMemberRequest $request, ConstructionActivityService $activityService): RedirectResponse
    {
        // Verify assignment belongs to project
        if ($teamMember->project_id !== $project->id) {
            abort(404);
        }

        $actor = $this->constructionActor();
        $validated = $request->validated();

        // Application-level duplicate check (exclude current record)
        if ($validated['member_id'] != $teamMember->member_id) {
            $exists = ProjectTeamMember::where('project_id', $project->id)
                ->where('member_id', $validated['member_id'])
                ->where('id', '!=', $teamMember->id)
                ->exists();

            if ($exists) {
                return back()->with('error', 'This member is already assigned to this project. Each member can only be assigned once per project.');
            }
        }

        try {
            $teamMember->update([
                'member_id' => $validated['member_id'],
                'role_id' => $validated['role_id'] ?? null,
                'assigned_from' => $validated['assigned_from'] ?? null,
                'assigned_to' => $validated['assigned_to'] ?? null,
                'assignment_scope' => $validated['assignment_scope'] ?? null,
                'is_primary' => (bool) ($validated['is_primary'] ?? false),
                'status' => $validated['status'] ?? $teamMember->status,
            ]);

            // Sync MemberRoleAssignment
            if (!empty($validated['role_id'])) {
                MemberRoleAssignment::updateOrCreate([
                    'member_id' => $validated['member_id'],
                    'role_id' => $validated['role_id'],
                    'project_id' => $project->id,
                ]);
            }

            $activityService->log(
                module: 'project_team',
                action: 'updated',
                actor: $actor,
                reference: $teamMember,
                companyId: $project->company_id,
                projectId: $project->id,
                meta: ['member_id' => $teamMember->member_id],
                request: $request
            );

            return back()->with('success', 'Team member assignment updated successfully.');
        } catch (\Illuminate\Database\QueryException $e) {
            if (str_contains($e->getMessage(), 'construction_project_team_unique')) {
                return back()->with('error', 'This member is already assigned to this project. Please refresh and try again.');
            }
            throw $e;
        }
    }

    public function toggleTeamMemberStatus(Project $project, ProjectTeamMember $teamMember, ConstructionActivityService $activityService): RedirectResponse
    {
        // Verify assignment belongs to project
        if ($teamMember->project_id !== $project->id) {
            abort(404);
        }

        $actor = $this->constructionActor();
        $newStatus = $teamMember->status === 'active' ? 'inactive' : 'active';

        $teamMember->update(['status' => $newStatus]);

        $activityService->log(
            module: 'project_team',
            action: $newStatus === 'active' ? 'activated' : 'deactivated',
            actor: $actor,
            reference: $teamMember,
            companyId: $project->company_id,
            projectId: $project->id,
            meta: ['member_id' => $teamMember->member_id, 'status' => $newStatus],
            request: request()
        );

        return back()->with('success', "Team member {$newStatus} successfully.");
    }

    public function destroyTeamMember(Project $project, ProjectTeamMember $teamMember, ConstructionActivityService $activityService): RedirectResponse
    {
        // Verify assignment belongs to project
        if ($teamMember->project_id !== $project->id) {
            abort(404);
        }

        $actor = $this->constructionActor();
        $memberName = $teamMember->member?->name ?? 'Unknown';
        $memberId = $teamMember->member_id;

        $teamMember->delete();

        $activityService->log(
            module: 'project_team',
            action: 'removed',
            actor: $actor,
            reference: null,
            companyId: $project->company_id,
            projectId: $project->id,
            meta: ['member_id' => $memberId, 'member_name' => $memberName],
            request: request()
        );

        return back()->with('success', "Team member '{$memberName}' removed from project successfully.");
    }

    public function showTeamMember(Project $project, ProjectTeamMember $teamMember): Response
    {
        // Verify assignment belongs to project
        if ($teamMember->project_id !== $project->id) {
            abort(404);
        }

        $teamMember->load([
            'member',
            'role',
        ]);

        $memberId = $teamMember->member_id;

        // Load actual project work/submissions by this team member for this project
        
        // 1. Survey submissions submitted by this member
        $surveySubmissions = \App\Models\Construction\SurveySubmission::with([
            'surveyVisit.checkedInBy',
            'submittedBy',
            'reviewedBy',
        ])
            ->where('project_id', $project->id)
            ->where('submitted_by_member_id', $memberId)
            ->latest('submitted_at')
            ->get();

        // 2. Survey visits where this member checked in
        $surveyVisits = \App\Models\Construction\SurveyVisit::with([
            'checkedInBy',
            'entries.capturedBy',
            'measurements.capturedBy',
            'submission',
        ])
            ->where('project_id', $project->id)
            ->where('checked_in_by_member_id', $memberId)
            ->latest('check_in_at')
            ->get();

        // 3. Survey plans where this member is assigned
        $surveyPlans = \App\Models\Construction\SurveyPlan::with([
            'planMembers.member',
        ])
            ->where('project_id', $project->id)
            ->whereHas('planMembers', function ($query) use ($memberId) {
                $query->where('member_id', $memberId);
            })
            ->latest('planned_date')
            ->get();

        // 4. Execution tasks supervised by this member
        $supervisedTasks = \App\Models\Construction\ExecutionTask::with([
            'assignees.member',
            'progressReports',
            'attendanceRecords',
        ])
            ->where('project_id', $project->id)
            ->where('supervisor_member_id', $memberId)
            ->latest('created_at')
            ->get();

        // 5. Execution tasks where this member is assigned as assignee
        $assignedTasks = \App\Models\Construction\ExecutionTask::with([
            'supervisor',
            'assignees.member',
            'progressReports',
        ])
            ->where('project_id', $project->id)
            ->whereHas('assignees', function ($query) use ($memberId) {
                $query->where('member_id', $memberId);
            })
            ->latest('created_at')
            ->get();

        // 6. Daily progress reports submitted by this member
        $progressReports = \App\Models\Construction\DailyProgressReport::with([
            'submittedBy',
            'reviewedBy',
        ])
            ->where('project_id', $project->id)
            ->where('submitted_by_member_id', $memberId)
            ->latest('report_date')
            ->get();

        // 7. Attendance records for this member
        $attendanceRecords = \App\Models\Construction\AttendanceRecord::with([
            'checkedInBy',
            'checkedOutBy',
        ])
            ->where('project_id', $project->id)
            ->where('member_id', $memberId)
            ->latest('attendance_date')
            ->get();

        // 8. Activity logs for this member in this project
        $activityLog = ActivityLog::with('actor')
            ->where('project_id', $project->id)
            ->where(function ($query) use ($memberId, $teamMember) {
                $query->where('meta->member_id', $memberId)
                      ->orWhere('reference_id', $teamMember->id);
            })
            ->latest('created_at')
            ->take(20)
            ->get();

        return Inertia::render('SuperAdmin/Construction/Projects/TeamMemberShow', [
            'project' => $project,
            'teamMember' => $teamMember,
            'surveySubmissions' => $surveySubmissions,
            'surveyVisits' => $surveyVisits,
            'surveyPlans' => $surveyPlans,
            'supervisedTasks' => $supervisedTasks,
            'assignedTasks' => $assignedTasks,
            'progressReports' => $progressReports,
            'attendanceRecords' => $attendanceRecords,
            'activityLog' => $activityLog,
        ]);
    }

    public function destroy(Project $project, Request $request, ConstructionActivityService $activityService): RedirectResponse
    {
        $actor = $this->constructionActor();

        try {
            $projectId = $project->id;
            $companyId = $project->company_id;
            $projectCode = $project->project_code;

            \DB::transaction(function () use ($project) {
                $projectId = $project->id;

                \App\Models\Construction\ActivityLog::where('project_id', $projectId)->delete();
                \App\Models\Construction\MemberRoleAssignment::where('project_id', $projectId)->delete();
                \App\Models\Construction\ProjectTeamMember::where('project_id', $projectId)->delete();
                \App\Models\Construction\ProjectBudget::where('project_id', $projectId)->delete();
                \App\Models\Construction\VehicleAssignment::where('project_id', $projectId)->delete();
                \App\Models\Construction\VehicleLocationPing::where('project_id', $projectId)->delete();
                \App\Models\Construction\EquipmentAllocation::where('project_id', $projectId)->delete();
                \App\Models\Construction\EquipmentUsageLog::where('project_id', $projectId)->delete();
                \App\Models\Construction\ProjectHandoverItem::whereIn(
                    'handover_id',
                    \App\Models\Construction\ProjectHandover::where('project_id', $projectId)->select('id')
                )->delete();
                \App\Models\Construction\ProjectHandover::where('project_id', $projectId)->delete();
                \App\Models\Construction\ClientPayment::where('project_id', $projectId)->delete();
                \App\Models\Construction\ClientInvoice::where('project_id', $projectId)->delete();
                \App\Models\Construction\MaterialIssue::where('project_id', $projectId)->delete();
                \App\Models\Construction\MaterialReceipt::where('project_id', $projectId)->delete();
                \App\Models\Construction\MaterialStock::where('project_id', $projectId)->delete();
                \App\Models\Construction\PurchaseOrder::where('project_id', $projectId)->delete();
                \App\Models\Construction\PurchaseRequest::where('project_id', $projectId)->delete();
                \App\Models\Construction\DrawingApproval::where('project_id', $projectId)->delete();
                \App\Models\Construction\DrawingRevision::where('project_id', $projectId)->delete();
                \App\Models\Construction\DraftingJob::where('project_id', $projectId)->delete();
                \App\Models\Construction\Document::where('project_id', $projectId)->delete();
                \App\Models\Construction\AttendanceRecord::where('project_id', $projectId)->delete();
                \App\Models\Construction\DailyProgressItem::whereIn(
                    'daily_progress_report_id',
                    \App\Models\Construction\DailyProgressReport::where('project_id', $projectId)->select('id')
                )->delete();
                \App\Models\Construction\DailyProgressReport::where('project_id', $projectId)->delete();
                \App\Models\Construction\ExecutionTaskAssignee::where('project_id', $projectId)->delete();
                \App\Models\Construction\ExecutionTask::where('project_id', $projectId)->delete();
                \App\Models\Construction\ExecutionPlan::where('project_id', $projectId)->delete();
                \App\Models\Construction\SurveyMeasurement::where('project_id', $projectId)->delete();
                \App\Models\Construction\SurveyEntry::where('project_id', $projectId)->delete();
                \App\Models\Construction\SurveySubmission::where('project_id', $projectId)->delete();
                \App\Models\Construction\SurveyVisit::where('project_id', $projectId)->delete();
                \App\Models\Construction\SurveyPlan::where('project_id', $projectId)->delete();

                \App\Models\Construction\Vehicle::where('project_id', $projectId)->update(['project_id' => null]);
                \App\Models\Construction\Equipment::where('project_id', $projectId)->update(['project_id' => null]);
                \App\Models\Construction\Material::where('project_id', $projectId)->update(['project_id' => null]);
                \App\Models\Construction\Vendor::where('project_id', $projectId)->update(['project_id' => null]);

                $project->delete();
            });

            $activityService->log(
                module: 'project',
                action: 'deleted',
                actor: $actor,
                reference: null,
                companyId: $companyId,
                projectId: null,
                meta: ['project_code' => $projectCode, 'project_id' => $projectId],
                request: $request
            );

            $fallback = route('super.construction.projects.index');
            $intended = redirect()->getIntendedUrl();
            $target = $intended && $intended !== route('super.construction.projects.show', $projectId ?? 0) ? $intended : $fallback;

            return redirect()->to($target)->with('success', 'Project and all related data deleted successfully.');
        } catch (\Throwable $e) {
            report($e);
            return back()->with('error', 'Failed to delete project. ' . $e->getMessage());
        }
    }
}
