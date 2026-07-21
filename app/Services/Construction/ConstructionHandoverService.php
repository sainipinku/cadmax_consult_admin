<?php

namespace App\Services\Construction;

use App\Models\Construction\Project;
use App\Models\Construction\ProjectHandover;
use App\Models\Construction\ProjectHandoverItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConstructionHandoverService
{
    public function __construct(
        private readonly ConstructionActivityService $activityService,
        private readonly ConstructionDocumentService $documentService
    ) {
    }

    public function createHandover(Project $project, array $validated, ?Model $actor, ?Request $request = null): ProjectHandover
    {
        return DB::transaction(function () use ($project, $validated, $actor, $request) {
            $items = $validated['items'] ?? [];
            if (count($items) === 0) {
                throw ValidationException::withMessages([
                    'items' => 'At least one handover checklist item is required.',
                ]);
            }

            $nextId = (ProjectHandover::max('id') ?? 0) + 1;
            $handoverCode = $validated['handover_code'] ?? ('HND-' . str_pad((string) $nextId, 5, '0', STR_PAD_LEFT));

            $handover = ProjectHandover::create([
                'project_id' => $project->id,
                'handover_code' => $handoverCode,
                'planned_handover_date' => $validated['planned_handover_date'] ?? null,
                'status' => $validated['status'] ?? 'draft',
                'created_by_type' => $actor ? $actor::class : null,
                'created_by_id' => $actor?->getKey(),
            ]);

            if (!empty($validated['final_document'])) {
                $document = $this->documentService->storeDocument(
                    documentable: $handover,
                    actor: $actor,
                    folder: 'construction/handover/final-docs',
                    file: $validated['final_document'],
                    companyId: $project->company_id,
                    projectId: $project->id
                );
            } elseif (!empty($validated['final_document_name'])) {
                $document = $this->documentService->createPlaceholderDocument(
                    documentable: $handover,
                    actor: $actor,
                    folder: 'construction/handover/final-docs',
                    originalName: $validated['final_document_name'],
                    companyId: $project->company_id,
                    projectId: $project->id,
                    mimeType: 'application/pdf'
                );

                $handover->forceFill(['final_document_id' => $document->id])->save();
            } else {
                $document = null;
            }

            if ($document) {
                $handover->forceFill(['final_document_id' => $document->id])->save();
            }

            foreach ($items as $index => $item) {
                $title = trim((string) ($item['title'] ?? ''));
                if ($title === '') {
                    throw ValidationException::withMessages([
                        "items.$index.title" => 'Checklist title is required.',
                    ]);
                }

                ProjectHandoverItem::create([
                    'handover_id' => $handover->id,
                    'title' => $title,
                    'category' => $item['category'] ?? null,
                    'status' => $item['status'] ?? 'pending',
                    'notes' => $item['notes'] ?? null,
                    'completed_at' => in_array(($item['status'] ?? 'pending'), ['completed', 'waived'], true) ? now() : null,
                ]);
            }

            $project->update(['current_stage' => 'client_handover']);

            $this->activityService->log(
                module: 'handover',
                action: 'created',
                actor: $actor,
                reference: $handover,
                companyId: $project->company_id,
                projectId: $project->id,
                meta: [
                    'handover_code' => $handover->handover_code,
                ],
                request: $request
            );

            return $handover->load(['items', 'finalDocument']);
        });
    }

    public function updateItemStatus(ProjectHandoverItem $item, array $validated, ?Model $actor, ?Request $request = null): ProjectHandoverItem
    {
        return DB::transaction(function () use ($item, $validated, $actor, $request) {
            $status = $validated['status'];
            $item->forceFill([
                'status' => $status,
                'notes' => $validated['notes'] ?? $item->notes,
                'completed_at' => in_array($status, ['completed', 'waived'], true) ? now() : null,
            ])->save();

            $handover = $item->handover()->with('project')->first();

            $this->activityService->log(
                module: 'handover_item',
                action: 'status_updated',
                actor: $actor,
                reference: $item,
                companyId: $handover?->project?->company_id,
                projectId: $handover?->project_id,
                meta: [
                    'status' => $status,
                ],
                request: $request
            );

            return $item;
        });
    }

    public function completeHandover(ProjectHandover $handover, array $validated, ?Model $actor, ?Request $request = null): ProjectHandover
    {
        return DB::transaction(function () use ($handover, $validated, $actor, $request) {
            $pendingItems = $handover->items()->where('status', 'pending')->count();
            if ($pendingItems > 0) {
                throw ValidationException::withMessages([
                    'handover' => 'All checklist items must be completed or waived before handover.',
                ]);
            }

            $handover->forceFill([
                'actual_handover_at' => $validated['actual_handover_at'] ?? now(),
                'status' => 'handed_over',
                'client_signatory_name' => $validated['client_signatory_name'],
                'client_signatory_role' => $validated['client_signatory_role'] ?? null,
                'signoff_notes' => $validated['signoff_notes'] ?? null,
                'handed_over_by_type' => $actor ? $actor::class : null,
                'handed_over_by_id' => $actor?->getKey(),
            ])->save();

            $handover->project()->update(['current_stage' => 'client_handover_completed']);

            $this->activityService->log(
                module: 'handover',
                action: 'completed',
                actor: $actor,
                reference: $handover,
                companyId: $handover->project?->company_id,
                projectId: $handover->project_id,
                meta: [
                    'client_signatory_name' => $handover->client_signatory_name,
                ],
                request: $request
            );

            return $handover->fresh(['items', 'finalDocument']);
        });
    }

    public function closeProject(ProjectHandover $handover, array $validated, ?Model $actor, ?Request $request = null): ProjectHandover
    {
        return DB::transaction(function () use ($handover, $validated, $actor, $request) {
            if ($handover->status !== 'handed_over') {
                throw ValidationException::withMessages([
                    'handover' => 'Project can only be closed after handover is completed.',
                ]);
            }

            $handover->forceFill([
                'closure_date' => $validated['closure_date'] ?? now(),
                'status' => 'closed',
                'closed_by_type' => $actor ? $actor::class : null,
                'closed_by_id' => $actor?->getKey(),
                'signoff_notes' => $validated['signoff_notes'] ?? $handover->signoff_notes,
            ])->save();

            $handover->project()->update([
                'current_stage' => 'project_closed',
                'status' => 'closed',
            ]);

            $this->activityService->log(
                module: 'project_closure',
                action: 'closed',
                actor: $actor,
                reference: $handover,
                companyId: $handover->project?->company_id,
                projectId: $handover->project_id,
                request: $request
            );

            return $handover->fresh(['items', 'finalDocument']);
        });
    }
}
