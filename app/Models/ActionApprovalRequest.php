<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ActionApprovalRequest extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    public const STATUS_PENDING = 0;
    public const STATUS_APPROVED = 1;
    public const STATUS_REJECTED = 2;

    protected $fillable = [
        'uuid',
        'requester_id',
        'requester_type',
        'requester_name',
        'requester_email',
        'action',
        'resource_type',
        'resource_id',
        'resource_name',
        'payload',
        'reason',
        'status',
        'reviewed_by',
        'reviewed_at',
        'admin_remark',
    ];

    protected $casts = [
        'payload' => 'array',
        'status' => 'integer',
        'reviewed_at' => 'datetime',
    ];

    protected $appends = [
        'status_label',
        'formatted_created_at',
        'formatted_reviewed_at',
    ];

    public function uniqueIds()
    {
        return ['uuid'];
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function scopeRejected($query)
    {
        return $query->where('status', self::STATUS_REJECTED);
    }

    public function statusLabel(): Attribute
    {
        return Attribute::make(
            get: function () {
                return match ((int) $this->status) {
                    self::STATUS_PENDING => 'Pending Approval',
                    self::STATUS_APPROVED => 'Approved',
                    self::STATUS_REJECTED => 'Rejected',
                    default => 'Unknown',
                };
            }
        );
    }

    public function formattedCreatedAt(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->created_at ? $this->created_at->format('d M Y, h:i A') : '--'
        );
    }

    public function formattedReviewedAt(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->reviewed_at ? $this->reviewed_at->format('d M Y, h:i A') : '--'
        );
    }

    public function reviewer()
    {
        return $this->belongsTo(SuperAdmin::class, 'reviewed_by');
    }
}
