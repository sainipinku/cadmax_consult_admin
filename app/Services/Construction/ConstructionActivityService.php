<?php

namespace App\Services\Construction;

use App\Models\Company;
use App\Models\ConstructionActivityLog;
use App\Models\Employee;
use App\Models\Member;
use App\Models\Project;
use App\Models\SuperAdmin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ConstructionActivityService
{
    private const SENSITIVE_KEYS = [
        'password',
        'password_confirmation',
        'token',
        'api_token',
        'access_token',
        'auth_token',
        'secret',
        'otp',
        'cvv',
        'pin',
    ];

    public function log(
        string $module,
        string $action,
        ?Model $actor = null,
        ?Model $reference = null,
        ?int $companyId = null,
        ?int $projectId = null,
        array $meta = [],
        ?Request $request = null,
        ?string $description = null,
        ?string $referenceCode = null,
        ?string $channel = null,
        ?int $employeeId = null
    ): ConstructionActivityLog {
        $request = $request ?? request();

        // 1. Resolve Actor if omitted
        if (!$actor) {
            $actor = Auth::guard('superadmin')->user()
                ?? Auth::guard('sanctum')->user()
                ?? Auth::guard('web')->user();
        }

        // 2. Resolve Employee & Company context for Member actors
        if ($actor instanceof Member) {
            if (!$employeeId) {
                $employeeId = Employee::where('member_id', $actor->id)->value('id');
            }
            if (!$companyId) {
                if (!empty($actor->company_name)) {
                    $companyId = Company::where('name', $actor->company_name)->value('id');
                }
                if (!$companyId && $actor->created_by) {
                    $companyId = SuperAdmin::where('id', $actor->created_by)->value('company_id');
                }
            }
        } elseif ($actor instanceof SuperAdmin) {
            if (!$companyId && $actor->company_id) {
                $companyId = (int) $actor->company_id;
            }
        }

        // 3. Resolve Project & Company from Reference if available
        if (!$projectId && $reference instanceof Project) {
            $projectId = $reference->id;
        } elseif (!$projectId && $reference && isset($reference->project_id)) {
            $projectId = (int) $reference->project_id;
        }

        if (!$companyId && $reference && isset($reference->company_id)) {
            $companyId = (int) $reference->company_id;
        }

        // 4. Resolve Reference Code if omitted
        if (!$referenceCode && $reference) {
            $referenceCode = $reference->project_code
                ?? $reference->task_code
                ?? $reference->equipment_id
                ?? $reference->vehicle_code
                ?? $reference->code
                ?? null;
        }

        // 5. Detect Channel if omitted
        if (!$channel) {
            $channel = $this->detectChannel($request);
        }

        // 6. Generate narrative description if omitted
        if (!$description) {
            $description = $this->generateNarrative($module, $action, $actor, $referenceCode, $meta);
        }

        // 7. Sanitize sensitive keys from metadata
        $sanitizedMeta = $this->sanitizeMeta($meta);

        return ConstructionActivityLog::create([
            'company_id' => $companyId,
            'project_id' => $projectId,
            'actor_type' => $actor ? $actor::class : null,
            'actor_id' => $actor?->getKey(),
            'employee_id' => $employeeId,
            'channel' => $channel,
            'module' => $module,
            'action' => $action,
            'reference_type' => $reference ? $reference::class : null,
            'reference_id' => $reference?->getKey(),
            'reference_code' => $referenceCode,
            'description' => $description,
            'meta' => $sanitizedMeta,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'created_at' => now(),
        ]);
    }

    private function detectChannel(?Request $request): string
    {
        if (!$request) {
            return 'system';
        }

        if ($request->is('api/*')) {
            $ua = strtolower((string) $request->header('User-Agent'));
            if (str_contains($ua, 'mobile') || str_contains($ua, 'dart') || str_contains($ua, 'okhttp') || str_contains($ua, 'cfnetwork')) {
                return 'api_mobile';
            }
            return 'api_sanctum';
        }

        if ($request->is('super/*')) {
            return 'web_superadmin';
        }

        if ($request->is('admin/*')) {
            return 'web_admin';
        }

        if ($request->is('member/*')) {
            return 'web_member';
        }

        return 'web_panel';
    }

    private function sanitizeMeta(array $meta): array
    {
        $sanitized = [];
        foreach ($meta as $key => $value) {
            if (in_array(strtolower((string) $key), self::SENSITIVE_KEYS, true)) {
                $sanitized[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $sanitized[$key] = $this->sanitizeMeta($value);
            } else {
                $sanitized[$key] = $value;
            }
        }
        return $sanitized;
    }

    private function generateNarrative(
        string $module,
        string $action,
        ?Model $actor,
        ?string $referenceCode,
        array $meta
    ): string {
        $actorName = $actor ? ($actor->name ?? "User #{$actor->getKey()}") : 'System';
        $readableAction = str_replace(['_', '-'], ' ', strtolower($action));
        $readableModule = str_replace(['_', '-'], ' ', strtolower($module));

        $narrative = "{$actorName} {$readableAction} on {$readableModule}";
        if ($referenceCode) {
            $narrative .= " ({$referenceCode})";
        }

        if (!empty($meta['notes'])) {
            $narrative .= ": " . (is_string($meta['notes']) ? $meta['notes'] : '');
        }

        return $narrative;
    }
}

