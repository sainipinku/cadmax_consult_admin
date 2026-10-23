<?php

namespace App\Models;

use App\Models\ConstructionEquipment;
use App\Models\ConstructionVehicle;

use App\Models\Member;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use SoftDeletes;

    protected $table = 'construction_projects';

    protected $attributes = [
        'priority' => 'medium',
        'status' => 'draft',
        'current_stage' => 'budget_pending',
        'client_review_status' => 'pending',
        'is_locked' => 0,
        'revision_iteration_count' => 0,
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($project) {
            if (empty($project->project_code) || static::withTrashed()->where('project_code', $project->project_code)->exists()) {
                $project->project_code = static::generateUniqueProjectCode($project->project_code);
            }
            if (empty($project->slug) || static::withTrashed()->where('slug', $project->slug)->exists()) {
                $project->slug = static::generateUniqueSlug($project->name ?? 'project');
            }
            if (empty($project->client_review_status)) {
                $project->client_review_status = 'pending';
            }
            if (empty($project->status)) {
                $project->status = 'draft';
            }
            if (empty($project->current_stage)) {
                $project->current_stage = 'budget_pending';
            }
            if (empty($project->priority)) {
                $project->priority = 'medium';
            }
        });
    }

    public static function generateUniqueProjectCode(?string $preferredCode = null): string
    {
        if (!empty($preferredCode)) {
            $exists = static::withTrashed()->where('project_code', $preferredCode)->exists();
            if (!$exists) {
                return $preferredCode;
            }
        }

        $baseNumber = (int) (static::withTrashed()->max('id') ?? 0) + 1;

        $latestCode = static::withTrashed()
            ->where('project_code', 'LIKE', 'PRJ-%')
            ->orderByRaw("CAST(SUBSTRING(project_code, 5) AS UNSIGNED) DESC")
            ->value('project_code');

        if ($latestCode && preg_match('/PRJ-(\d+)/', $latestCode, $matches)) {
            $highestCodeNum = (int) $matches[1];
            if ($highestCodeNum >= $baseNumber) {
                $baseNumber = $highestCodeNum + 1;
            }
        }

        do {
            $candidateCode = 'PRJ-' . str_pad((string) $baseNumber, 5, '0', STR_PAD_LEFT);
            $exists = static::withTrashed()->where('project_code', $candidateCode)->exists();
            if ($exists) {
                $baseNumber++;
            }
        } while ($exists);

        return $candidateCode;
    }

    public static function generateUniqueSlug(string $name, ?int $seedId = null): string
    {
        $baseSlug = \Illuminate\Support\Str::slug($name) ?: 'project';
        $num = $seedId ?? ((int) (static::withTrashed()->max('id') ?? 0) + 1);
        $candidate = $baseSlug . '-' . $num;

        while (static::withTrashed()->where('slug', $candidate)->exists()) {
            $num++;
            $candidate = $baseSlug . '-' . $num;
        }

        return $candidate;
    }

    protected $fillable = [
        'company_id',
        'client_id',
        'project_code',
        'name',
        'slug',
        'category',
        'description',
        'project_address',
        'location_name',
        'khasra_number',
        'site_area',
        'scope_of_work',
        'latitude',
        'longitude',
        'start_date',
        'expected_end_date',
        'priority',
        'status',
        'current_stage',
        'is_locked',
        'payment_status',
        'commercial_billing_amount',
        'commercial_advance_received',
        'commercial_pending_amount',
        'commercial_expenses',
        'accounts_remarks',
        'accounts_proof_document_id',
        'draft_verification_checklist',
        'created_by_type',
        'created_by_id',
        'client_review_status',
        'client_review_requested_at',
        'client_review_requested_by_member_id',
        'client_approved_at',
        'client_approved_by_client_id',
        'client_revision_comment',
        'partial_revision_sections',
        'revision_iteration_count',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'start_date' => 'date',
        'expected_end_date' => 'date',
        'client_review_requested_at' => 'datetime',
        'client_approved_at' => 'datetime',
        'partial_revision_sections' => 'array',
        'revision_iteration_count' => 'integer',
        'is_locked' => 'boolean',
        'commercial_billing_amount' => 'float',
        'commercial_advance_received' => 'float',
        'commercial_pending_amount' => 'float',
        'commercial_expenses' => 'float',
        'draft_verification_checklist' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function createdBy(): MorphTo
    {
        return $this->morphTo();
    }

    public function budgets(): HasMany
    {
        return $this->hasMany(ProjectBudget::class);
    }

    public function latestBudget(): HasOne
    {
        return $this->hasOne(ProjectBudget::class)->latestOfMany();
    }

    public function teamMembers(): HasMany
    {
        return $this->hasMany(ProjectTeamMember::class);
    }

    public function surveyPlans(): HasMany
    {
        return $this->hasMany(SurveyPlan::class);
    }

    public function surveySubmissions(): HasMany
    {
        return $this->hasMany(SurveySubmission::class);
    }

    public function surveyVisits(): HasMany
    {
        return $this->hasMany(SurveyVisit::class);
    }

    public function draftingJobs(): HasMany
    {
        return $this->hasMany(DraftingJob::class);
    }

    public function drawingApprovals(): HasMany
    {
        return $this->hasMany(DrawingApproval::class);
    }

    public function executionPlans(): HasMany
    {
        return $this->hasMany(ExecutionPlan::class);
    }

    public function executionTasks(): HasMany
    {
        return $this->hasMany(ExecutionTask::class);
    }

    public function dailyProgressReports(): HasMany
    {
        return $this->hasMany(DailyProgressReport::class);
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function vendors(): HasMany
    {
        return $this->hasMany(Vendor::class);
    }

    public function materials(): HasMany
    {
        return $this->hasMany(Material::class);
    }

    public function purchaseRequests(): HasMany
    {
        return $this->hasMany(PurchaseRequest::class);
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function materialReceipts(): HasMany
    {
        return $this->hasMany(MaterialReceipt::class);
    }

    public function materialIssues(): HasMany
    {
        return $this->hasMany(MaterialIssue::class);
    }

    public function materialStocks(): HasMany
    {
        return $this->hasMany(MaterialStock::class);
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(ConstructionVehicle::class);
    }

    public function vehicleAssignments(): HasMany
    {
        return $this->hasMany(VehicleAssignment::class);
    }

    public function vehicleLocationPings(): HasMany
    {
        return $this->hasMany(VehicleLocationPing::class);
    }

    public function equipments(): HasMany
    {
        return $this->hasMany(ConstructionEquipment::class);
    }

    public function equipmentAllocations(): HasMany
    {
        return $this->hasMany(EquipmentAllocation::class);
    }

    public function equipmentUsageLogs(): HasMany
    {
        return $this->hasMany(EquipmentUsageLog::class);
    }

    public function clientInvoices(): HasMany
    {
        return $this->hasMany(ClientInvoice::class);
    }

    public function clientPayments(): HasMany
    {
        return $this->hasMany(ClientPayment::class);
    }

    public function handovers(): HasMany
    {
        return $this->hasMany(ProjectHandover::class);
    }

    public function surveyTeams(): HasMany
    {
        return $this->hasMany(SurveyTeam::class);
    }

    public function clientReviewRequestedBy(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'client_review_requested_by_member_id');
    }

    public function clientApprovedBy(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_approved_by_client_id');
    }

    /**
     * Unified dynamic tasks (tasks table). Coexists with legacy execution_tasks
     * relation above and is required for checklistItems eager-loading on both
     * SuperAdmin and Admin project show() and index() endpoints.
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(\App\Models\Task::class, 'project_id');
    }

    public function workflowLogs(): HasMany
    {
        return $this->hasMany(ConstructionWorkflowLog::class, 'project_id')->orderBy('id', 'asc');
    }

    public function accountsProofDocument(): BelongsTo
    {
        return $this->belongsTo(ConstructionDocument::class, 'accounts_proof_document_id');
    }
}
