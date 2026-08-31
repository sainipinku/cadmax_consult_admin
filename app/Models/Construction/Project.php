<?php

namespace App\Models\Construction;

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
        'latitude',
        'longitude',
        'start_date',
        'expected_end_date',
        'priority',
        'status',
        'current_stage',
        'created_by_type',
        'created_by_id',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'start_date' => 'date',
        'expected_end_date' => 'date',
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
        return $this->hasMany(Vehicle::class);
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
        return $this->hasMany(Equipment::class);
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
}
