<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Job extends Model
{
    use HasFactory;

    protected $table = 'job_posts';

    protected $appends = [
        'responsibilities',
        'requirements',
    ];

    protected $fillable = [
        'uuid',
        'title',
        'company',
        'description',
        'location',
        'job_type',
        'openings',
        'experience',
        'salary',
        'skills',
        'perks',
        'key_responsibilities',
        'qualifications',
        'assets',
        'application_questions',
        'last_date',
        'company_image',
        'contact_person',
        'contact_phone',
        'contact_email',
        'company_address',
        'applicants',
        'status',
        'created_by',
        'approved_by',
        'approved_at',
        'rejection_reason',
        'resubmitted_at',
        'approval_logs',
    ];

    protected $casts = [
        'skills' => 'array',
        'perks' => 'array',
        'assets' => 'array',
        'application_questions' => 'array',
        'key_responsibilities' => 'array',
        'qualifications' => 'array',
        'openings' => 'integer',
        'approval_logs' => 'array',
        'approved_at' => 'datetime',
        'resubmitted_at' => 'datetime',
        'last_date' => 'date',
    ];

    public function getCompanyImageAttribute($value)
    {
        if (empty($value)) {
            return null;
        }

        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return $value;
        }

        if (str_starts_with($value, '/storage/')) {
            return $value;
        }

        if (str_starts_with($value, 'storage/')) {
            return '/' . $value;
        }

        return Storage::disk('public')->url($value);
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($job) {
            if (empty($job->uuid)) {
                $job->uuid = (string) Str::uuid();
            }
        });

        static::created(function (Job $job) {
            if (empty($job->created_by)) {
                return;
            }

            $superAdminIds = SuperAdmin::query()->pluck('id');
            foreach ($superAdminIds as $superAdminId) {
                Notification::create([
                    'model' => 'superadmin',
                    'listing_id' => $superAdminId,
                    'job_id' => $job->id,
                    'type' => 'job_created',
                    'status' => 'unread',
                    'data' => [
                        'job_uuid' => $job->uuid,
                        'title' => $job->title,
                        'created_by' => $job->created_by,
                    ],
                ]);
            }
        });

        static::updated(function (Job $job) {
            if (!$job->wasChanged('status')) {
                return;
            }

            $oldStatus = $job->getOriginal('status');
            $newStatus = $job->status;

            if ($newStatus === 'active') {
                $job->notifyAdmin('job_approved', [
                    'job_uuid' => $job->uuid,
                    'title' => $job->title,
                    'approved_by' => $job->approved_by,
                    'approved_at' => optional($job->approved_at)->toDateTimeString(),
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus,
                ]);

                return;
            }

            if ($newStatus === 'declined') {
                $job->notifyAdmin('job_rejected', [
                    'job_uuid' => $job->uuid,
                    'title' => $job->title,
                    'rejection_reason' => $job->rejection_reason,
                    'approved_by' => $job->approved_by,
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus,
                ]);

                return;
            }

            if ($newStatus === 'inactive') {
                $job->notifyAdmin('job_deactivated', [
                    'job_uuid' => $job->uuid,
                    'title' => $job->title,
                    'approved_by' => $job->approved_by,
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus,
                ]);

                return;
            }

            if ($newStatus === 'pending') {
                $type = $oldStatus === 'declined' ? 'job_resubmitted' : 'job_pending';
                $superAdminIds = SuperAdmin::query()->pluck('id');
                foreach ($superAdminIds as $superAdminId) {
                    Notification::create([
                        'model' => 'superadmin',
                        'listing_id' => $superAdminId,
                        'job_id' => $job->id,
                        'type' => $type,
                        'status' => 'unread',
                        'data' => [
                            'job_uuid' => $job->uuid,
                            'title' => $job->title,
                            'created_by' => $job->created_by,
                            'resubmitted_at' => optional($job->resubmitted_at)->toDateTimeString(),
                        ],
                    ]);
                }
            }
        });
    }

    /**
     * Get key_responsibilities as responsibilities array
     */
    public function getResponsibilitiesAttribute()
    {
        $value = $this->key_responsibilities;
        if (empty($value)) {
            return [];
        }
        // Handle both JSON array and old newline-separated formats
        if (is_array($value)) {
            return array_values(array_filter($value, fn($v) => !empty(trim((string) $v))));
        }
        if (is_string($value)) {
            // Check if it's a JSON array string
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return array_values(array_filter($decoded, fn($v) => !empty(trim((string) $v))));
            }
            // Legacy: split by newlines
            return array_values(array_filter(array_map('trim', explode("\n", $value))));
        }
        return [];
    }

    /**
     * Get qualifications as requirements array
     */
    public function getRequirementsAttribute()
    {
        $value = $this->qualifications;
        if (empty($value)) {
            return [];
        }
        // Handle both JSON array and old newline-separated formats
        if (is_array($value)) {
            return array_values(array_filter($value, fn($v) => !empty(trim((string) $v))));
        }
        if (is_string($value)) {
            // Check if it's a JSON array string
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return array_values(array_filter($decoded, fn($v) => !empty(trim((string) $v))));
            }
            // Legacy: split by newlines
            return array_values(array_filter(array_map('trim', explode("\n", $value))));
        }
        return [];
    }

    /**
     * Get the member who created this job
     */
    public function creator()
    {
        return $this->belongsTo(Member::class, 'created_by');
    }

    /**
     * Get the super admin who approved this job
     */
    public function approver()
    {
        return $this->belongsTo(SuperAdmin::class, 'approved_by');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(JobApplication::class, 'job_id');
    }

    /**
     * Scope for pending jobs
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for active jobs
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope for declined jobs
     */
    public function scopeDeclined($query)
    {
        return $query->where('status', 'declined');
    }

    /**
     * Approve the job
     */
    public function approve($superAdminId)
    {
        $this->update([
            'status' => 'active',
            'approved_by' => $superAdminId,
            'approved_at' => now(),
            'rejection_reason' => null,
        ]);

        $this->addApprovalLog('approved', $superAdminId);
    }

    /**
     * Reject the job
     */
    public function reject($superAdminId, $reason = null)
    {
        $this->update([
            'status' => 'declined',
            'approved_by' => $superAdminId,
            'rejection_reason' => $reason,
        ]);

        $this->addApprovalLog('rejected', $superAdminId, $reason);
    }

    /**
     * Resend for approval
     */
    public function resend()
    {
        $this->update([
            'status' => 'pending',
            'resubmitted_at' => now(),
            'rejection_reason' => null,
        ]);

        $this->addApprovalLog('resubmitted', $this->created_by);
    }

    /**
     * Add approval log entry
     */
    private function addApprovalLog($action, $userId, $reason = null)
    {
        $logs = $this->approval_logs ?? [];
        $logs[] = [
            'action' => $action,
            'user_id' => $userId,
            'reason' => $reason,
            'timestamp' => now()->toDateTimeString(),
        ];

        $this->update(['approval_logs' => $logs]);
    }

    private function notifyAdmin(string $type, array $data): void
    {
        if (empty($this->created_by)) {
            return;
        }

        Notification::create([
            'model' => 'admin',
            'listing_id' => $this->created_by,
            'job_id' => $this->id,
            'type' => $type,
            'status' => 'unread',
            'data' => $data,
        ]);
    }
}
