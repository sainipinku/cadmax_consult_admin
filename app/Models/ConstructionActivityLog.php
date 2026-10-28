<?php

namespace App\Models;

use App\Models\Company;
use App\Models\Project;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ConstructionActivityLog extends Model
{
    public $timestamps = false;

    protected $table = 'construction_activity_logs';

    protected $fillable = [
        'company_id',
        'project_id',
        'actor_type',
        'actor_id',
        'module',
        'action',
        'reference_type',
        'reference_id',
        'meta',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected $casts = [
        'meta' => 'array',
        'created_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
        }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function actor(): MorphTo
    {
        return $this->morphTo();
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
