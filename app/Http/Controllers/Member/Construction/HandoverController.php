<?php

namespace App\Http\Controllers\Member\Construction;

use App\Http\Controllers\Concerns\ResolvesConstructionActor;
use App\Http\Controllers\Controller;
use App\Models\Construction\Project;
use App\Models\Construction\ProjectHandover;
use App\Models\Construction\ProjectHandoverItem;
use App\Models\Construction\ProjectTeamMember;
use App\Models\Member;
use App\Services\Construction\ConstructionHandoverService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HandoverController extends Controller
{
    use ResolvesConstructionActor;

    public function index(): Response
    {
        /** @var Member|null $actor */
        $actor = $this->constructionActor();
        $projectIds = ProjectTeamMember::where('member_id', $actor?->getKey())
            ->where('status', 'active')
            ->pluck('project_id');

        return Inertia::render('Member/Construction/Handover/Index', [
            'projects' => Project::whereIn('id', $projectIds)->orderByDesc('id')->get(['id', 'project_code', 'name', 'status', 'current_stage']),
            'handovers' => ProjectHandover::with(['project', 'items', 'finalDocument'])
                ->whereIn('project_id', $projectIds)
                ->latest()
                ->take(50)
                ->get(),
        ]);
    }

    private function ensureProjectAccess(int $projectId, ?Member $actor): void
    {
        abort_unless(
            $actor && ProjectTeamMember::where('project_id', $projectId)
                ->where('member_id', $actor->getKey())
                ->where('status', 'active')
                ->exists(),
            403
        );
    }

    public function updateItem(ProjectHandoverItem $item, Request $request, ConstructionHandoverService $handoverService): RedirectResponse
    {
        /** @var Member|null $actor */
        $actor = $this->constructionActor();
        $this->ensureProjectAccess((int) $item->handover->project_id, $actor);

        $validated = $request->validate([
            'status' => ['required', 'in:pending,completed,waived'],
            'notes' => ['nullable', 'string'],
        ]);

        $handoverService->updateItemStatus($item, $validated, $actor, $request);

        return back()->with('success', 'Checklist item updated successfully.');
    }
}

