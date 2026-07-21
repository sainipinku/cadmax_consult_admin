<?php

namespace App\Services\Construction;

use App\Models\Construction\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class ConstructionActivityService
{
    public function log(
        string $module,
        string $action,
        ?Model $actor = null,
        ?Model $reference = null,
        ?int $companyId = null,
        ?int $projectId = null,
        array $meta = [],
        ?Request $request = null
    ): void {
        ActivityLog::create([
            'company_id' => $companyId,
            'project_id' => $projectId,
            'actor_type' => $actor ? $actor::class : null,
            'actor_id' => $actor?->getKey(),
            'module' => $module,
            'action' => $action,
            'reference_type' => $reference ? $reference::class : null,
            'reference_id' => $reference?->getKey(),
            'meta' => $meta,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'created_at' => now(),
        ]);
    }
}
