<?php

namespace App\Models\Construction;

use App\Models\Member;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaterialIssue extends Model
{
    protected $table = 'construction_material_issues';

    protected $fillable = [
        'project_id',
        'issue_code',
        'issued_by_member_id',
        'issue_date',
        'latitude',
        'longitude',
        'gps_accuracy_meters',
        'status',
        'notes',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'latitude' => 'float',
        'longitude' => 'float',
        'gps_accuracy_meters' => 'float',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'issued_by_member_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(MaterialIssueItem::class, 'material_issue_id');
    }
}

