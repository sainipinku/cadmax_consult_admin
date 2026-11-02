<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Concerns\ResolvesConstructionActor;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\ConstructionActivityLog;
use App\Models\Project;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    use ResolvesConstructionActor;

    public function index(Request $request): Response
    {
        $actor = $this->constructionActor();
        $isProjectOwner = $this->isProjectOwner($actor);

        $query = ConstructionActivityLog::query()
            ->forActor($actor)
            ->with(['company:id,name', 'project:id,project_code,name', 'actor', 'employee']);

        // Filters
        if ($request->filled('project_id') && $request->project_id !== 'all') {
            $query->where('project_id', $request->project_id);
        }

        if ($isProjectOwner && $request->filled('company_id') && $request->company_id !== 'all') {
            $query->where('company_id', $request->company_id);
        }

        if ($request->filled('module') && $request->module !== 'all') {
            $query->where('module', $request->module);
        }

        if ($request->filled('action') && $request->action !== 'all') {
            $query->where('action', $request->action);
        }

        if ($request->filled('channel') && $request->channel !== 'all') {
            $query->where('channel', $request->channel);
        }

        if ($request->filled('actor_type') && $request->actor_type !== 'all') {
            if ($request->actor_type === 'superadmin') {
                $query->where('actor_type', \App\Models\SuperAdmin::class);
            } elseif ($request->actor_type === 'member') {
                $query->where('actor_type', \App\Models\Member::class);
            }
        }

        if ($request->filled('from_date')) {
            $query->where('created_at', '>=', $request->from_date . ' 00:00:00');
        }

        if ($request->filled('to_date')) {
            $query->where('created_at', '<=', $request->to_date . ' 23:59:59');
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhere('reference_code', 'like', "%{$search}%")
                  ->orWhere('ip_address', 'like', "%{$search}%")
                  ->orWhereHasMorph('actor', [\App\Models\SuperAdmin::class, \App\Models\Member::class], function ($sq) use ($search) {
                      $sq->where('name', 'like', "%{$search}%")
                         ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        $perPage = (int) $request->input('per_page', 25);
        $logs = $query->latest('id')->paginate($perPage)->withQueryString();

        // Project and Company lists for dropdown filters
        $projectIds = $this->getAccessibleProjectIds($actor);
        $projects = Project::whereIn('id', $projectIds)->orderBy('name')->get(['id', 'project_code', 'name']);
        $companies = $isProjectOwner ? Company::orderBy('name')->get(['id', 'name']) : collect();

        // Distinct filter values
        $modules = ConstructionActivityLog::query()->forActor($actor)->distinct()->pluck('module')->filter()->values();
        $actions = ConstructionActivityLog::query()->forActor($actor)->distinct()->pluck('action')->filter()->values();
        $channels = ConstructionActivityLog::query()->forActor($actor)->distinct()->pluck('channel')->filter()->values();

        return Inertia::render('SuperAdmin/AuditLogs/Index', [
            'logs' => $logs,
            'filters' => [
                'search' => $request->input('search', ''),
                'project_id' => $request->input('project_id', 'all'),
                'company_id' => $request->input('company_id', 'all'),
                'module' => $request->input('module', 'all'),
                'action' => $request->input('action', 'all'),
                'channel' => $request->input('channel', 'all'),
                'actor_type' => $request->input('actor_type', 'all'),
                'from_date' => $request->input('from_date', ''),
                'to_date' => $request->input('to_date', ''),
                'per_page' => $perPage,
            ],
            'projects' => $projects,
            'companies' => $companies,
            'modules' => $modules,
            'actions' => $actions,
            'channels' => $channels,
            'isProjectOwner' => $isProjectOwner,
        ]);
    }
}

