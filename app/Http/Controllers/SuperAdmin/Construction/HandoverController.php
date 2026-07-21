<?php

namespace App\Http\Controllers\SuperAdmin\Construction;

use App\Http\Controllers\Concerns\ResolvesConstructionActor;
use App\Http\Controllers\Controller;
use App\Models\Construction\Project;
use App\Models\Construction\ProjectHandover;
use App\Models\Construction\ProjectHandoverItem;
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
        return Inertia::render('SuperAdmin/Construction/Handover/Index', [
            'projects' => Project::orderByDesc('id')->get(['id', 'project_code', 'name', 'status', 'current_stage']),
            'handovers' => ProjectHandover::with(['project', 'items', 'finalDocument'])->latest()->take(50)->get(),
        ]);
    }

    public function store(Request $request, ConstructionHandoverService $handoverService): RedirectResponse
    {
        $actor = $this->constructionActor();

        $validated = $request->validate([
            'project_id' => ['required', 'exists:construction_projects,id'],
            'handover_code' => ['nullable', 'string', 'max:30'],
            'planned_handover_date' => ['nullable', 'date'],
            'status' => ['nullable', 'in:draft,in_review'],
            'final_document' => ['nullable', 'file', 'max:20480'],
            'final_document_name' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.title' => ['required', 'string', 'max:255'],
            'items.*.category' => ['nullable', 'string', 'max:80'],
            'items.*.status' => ['nullable', 'in:pending,completed,waived'],
            'items.*.notes' => ['nullable', 'string'],
        ]);

        $project = Project::findOrFail($validated['project_id']);
        $handoverService->createHandover($project, $validated, $actor, $request);

        return back()->with('success', 'Handover checklist saved successfully.');
    }

    public function updateItem(ProjectHandoverItem $item, Request $request, ConstructionHandoverService $handoverService): RedirectResponse
    {
        $actor = $this->constructionActor();
        $validated = $request->validate([
            'status' => ['required', 'in:pending,completed,waived'],
            'notes' => ['nullable', 'string'],
        ]);

        $handoverService->updateItemStatus($item, $validated, $actor, $request);

        return back()->with('success', 'Checklist item updated successfully.');
    }

    public function complete(ProjectHandover $handover, Request $request, ConstructionHandoverService $handoverService): RedirectResponse
    {
        $actor = $this->constructionActor();
        $validated = $request->validate([
            'actual_handover_at' => ['nullable', 'date'],
            'client_signatory_name' => ['required', 'string', 'max:255'],
            'client_signatory_role' => ['nullable', 'string', 'max:255'],
            'signoff_notes' => ['nullable', 'string'],
        ]);

        $handoverService->completeHandover($handover, $validated, $actor, $request);

        return back()->with('success', 'Project handover completed successfully.');
    }

    public function close(ProjectHandover $handover, Request $request, ConstructionHandoverService $handoverService): RedirectResponse
    {
        $actor = $this->constructionActor();
        $validated = $request->validate([
            'closure_date' => ['nullable', 'date'],
            'signoff_notes' => ['nullable', 'string'],
        ]);

        $handoverService->closeProject($handover, $validated, $actor, $request);

        return back()->with('success', 'Project closed successfully.');
    }
}
