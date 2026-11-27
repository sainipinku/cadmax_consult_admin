<?php

namespace App\Http\Controllers\SuperAdmin\Construction;

use App\Http\Controllers\Concerns\IntersectsAdminDeletion;
use App\Http\Controllers\Concerns\ResolvesConstructionActor;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Company;
use App\Services\Construction\ConstructionActivityService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ClientController extends Controller
{
    use ResolvesConstructionActor, IntersectsAdminDeletion;

    /**
     * Determine if the actor can manage (edit, activate/deactivate, delete) the client.
     */
    public function canActorManageClient(?Model $actor, Client $client): bool
    {
        if (!$actor) {
            return false;
        }

        return $this->getAccessibleClientIds($actor)->contains((int) $client->id);
    }

    public function index(Request $request): Response
    {
        $actor = $this->constructionActor();
        $isProjectOwner = $this->isProjectOwner($actor);
        $clientIds = $this->getAccessibleClientIds($actor);
        $ownedCompanyIds = $this->getOwnedCompanyIds($actor);

        $clients = Client::with('company')
            ->withCount('projects')
            ->whereIn('id', $clientIds)
            ->latest()
            ->get()
            ->map(function ($client) use ($actor) {
                $client->can_manage = $this->canActorManageClient($actor, $client);
                return $client;
            });

        $companiesQuery = Company::query();
        if ($actor instanceof \App\Models\SuperAdmin) {
            $companiesQuery->whereIn('id', $ownedCompanyIds);
        } elseif ($companyId = $this->getAccessibleCompanyId($actor)) {
            $companiesQuery->where('id', $companyId);
        } else {
            $companiesQuery->whereIn('id', $ownedCompanyIds);
        }

        return Inertia::render('SuperAdmin/Construction/Clients/Index', [
            'clients' => $clients,
            'companies' => $companiesQuery->orderBy('name')->get(['id', 'name']),
            'is_project_owner' => $isProjectOwner,
            'user_company_id' => $actor?->company_id ?? null,
            'variant' => $request->is('admin/*') ? 'admin' : 'super',
        ]);
    }

    public function store(Request $request, ConstructionActivityService $activityService): RedirectResponse
    {
        $actor = $this->constructionActor();
        $ownedCompanyIds = $this->getOwnedCompanyIds($actor);
        $companyId = $this->getAccessibleCompanyId($actor);

        if (!$companyId && $actor instanceof \App\Models\SuperAdmin && $actor->company_id) {
            $companyId = $actor->company_id;
        }

        if (!$companyId) {
            $companyId = $request->input('company_id')
                ?: ($ownedCompanyIds->isNotEmpty()
                    ? Company::whereIn('id', $ownedCompanyIds)->where('status', 'active')->value('id')
                    : Company::where('status', 'active')->value('id'));
        }

        if ($companyId) {
            if ($actor instanceof \App\Models\SuperAdmin) {
                $excludedCompanyIds = $this->getOtherSuperAdminCompanyIds($actor);
                abort_if($excludedCompanyIds->contains((int) $companyId), 403, 'Unauthorized. You cannot create clients for another company account.');
            }
            $request->merge(['company_id' => $companyId]);
        }

        if ($request->filled('phone')) {
            $request->merge(['phone' => preg_replace('/\D/', '', (string) $request->input('phone'))]);
        }
        if ($request->filled('alternate_phone')) {
            $request->merge(['alternate_phone' => preg_replace('/\D/', '', (string) $request->input('alternate_phone'))]);
        }

        $validated = $request->validate([
            'company_id' => ['required', 'exists:construction_companies,id'],
            'client_type' => ['required', 'in:individual,company,government'],
            'name' => ['required', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'digits:10'],
            'alternate_phone' => ['nullable', 'string', 'digits:10'],
            'gst_number' => ['nullable', 'string', 'max:50'],
            'billing_address' => ['nullable', 'string'],
            'site_address' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'status' => ['required', 'in:active,inactive'],
        ], [
            'phone.digits' => 'The phone number must be exactly 10 digits.',
            'alternate_phone.digits' => 'The alternate phone number must be exactly 10 digits.',
        ]);

        $client = Client::create([
            ...$validated,
            'client_code' => Client::generateUniqueClientCode(),
            'created_by_type' => $actor ? $actor::class : null,
            'created_by_id' => $actor?->getKey(),
        ]);

        $activityService->log(
            module: 'client',
            action: 'created',
            actor: $actor,
            reference: $client,
            companyId: $client->company_id,
            meta: ['name' => $client->name],
            request: $request
        );

        return back()->with('success', 'Client created successfully.');
    }

    public function update(Request $request, Client $client, ConstructionActivityService $activityService): RedirectResponse
    {
        $actor = $this->constructionActor();

        if (!$this->canActorManageClient($actor, $client)) {
            abort(403, 'Unauthorized. Only the Project Owner or the Super Admin of the client company can edit this client.');
        }

        $ownedCompanyIds = $this->getOwnedCompanyIds($actor);
        $companyId = $this->getAccessibleCompanyId($actor);

        if (!$companyId && $actor instanceof \App\Models\SuperAdmin && $actor->company_id) {
            $companyId = $actor->company_id;
        }

        if (!$companyId) {
            $companyId = $request->input('company_id')
                ?: ($client->company_id
                    ?: ($ownedCompanyIds->isNotEmpty()
                        ? Company::whereIn('id', $ownedCompanyIds)->where('status', 'active')->value('id')
                        : Company::where('status', 'active')->value('id')));
        }

        if ($companyId) {
            if ($actor instanceof \App\Models\SuperAdmin) {
                $excludedCompanyIds = $this->getOtherSuperAdminCompanyIds($actor);
                abort_if($excludedCompanyIds->contains((int) $companyId), 403, 'Unauthorized. You cannot assign a client to another company account.');
            }
            $request->merge(['company_id' => $companyId]);
        }

        if ($request->filled('phone')) {
            $request->merge(['phone' => preg_replace('/\D/', '', (string) $request->input('phone'))]);
        }
        if ($request->filled('alternate_phone')) {
            $request->merge(['alternate_phone' => preg_replace('/\D/', '', (string) $request->input('alternate_phone'))]);
        }

        $validated = $request->validate([
            'company_id' => ['required', 'exists:construction_companies,id'],
            'client_type' => ['required', 'in:individual,company,government'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'digits:10'],
            'alternate_phone' => ['nullable', 'string', 'digits:10'],
            'gst_number' => ['nullable', 'string', 'max:50'],
            'billing_address' => ['nullable', 'string'],
            'site_address' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'status' => ['required', 'in:active,inactive'],
        ], [
            'phone.digits' => 'The phone number must be exactly 10 digits.',
            'alternate_phone.digits' => 'The alternate phone number must be exactly 10 digits.',
        ]);

        $client->update($validated);

        $activityService->log(
            module: 'client',
            action: 'updated',
            actor: $actor,
            reference: $client,
            companyId: $client->company_id,
            meta: ['name' => $client->name],
            request: $request
        );

        return back()->with('success', 'Client updated successfully.');
    }

    public function updateStatus(Request $request, Client $client, ConstructionActivityService $activityService): RedirectResponse
    {
        $actor = $this->constructionActor();

        if (!$this->canActorManageClient($actor, $client)) {
            abort(403, 'Unauthorized. Only the Project Owner or the Super Admin of the client company can change client status.');
        }

        $newStatus = $request->input('status');
        if (!in_array($newStatus, ['active', 'inactive'], true)) {
            $newStatus = $client->status === 'active' ? 'inactive' : 'active';
        }

        $client->update(['status' => $newStatus]);

        $actionVerb = $newStatus === 'active' ? 'activated' : 'deactivated';

        $activityService->log(
            module: 'client',
            action: $actionVerb,
            actor: $actor,
            reference: $client,
            companyId: $client->company_id,
            meta: [
                'name' => $client->name,
                'status' => $newStatus,
            ],
            request: $request
        );

        return back()->with('success', "Client '{$client->name}' has been {$actionVerb} successfully.");
    }

    public function destroy(Request $request, Client $client, ConstructionActivityService $activityService): RedirectResponse
    {
        $actor = $this->constructionActor();

        if (!$this->canActorManageClient($actor, $client)) {
            abort(403, 'Unauthorized. Only the Project Owner or the Super Admin of the client company can delete this client.');
        }

        if ($this->requiresSuperAdminApproval('client', $client->id)) {
            return $this->requestDeleteApproval('client', $client->id, $client->name);
        }

        if ($client->projects()->exists()) {
            return back()->with('error', "Cannot delete client '{$client->name}' because active project records are linked to it. Please reassign or remove associated projects first.");
        }

        $clientName = $client->name;
        $companyId = $client->company_id;

        $this->consumeDeleteApprovalPermission('client', $client->id);
        $client->delete();

        $activityService->log(
            module: 'client',
            action: 'deleted',
            actor: $actor,
            reference: $client,
            companyId: $companyId,
            meta: ['name' => $clientName, 'client_code' => $client->client_code],
            request: $request
        );

        return back()->with('success', "Client '{$clientName}' deleted successfully.");
    }
}
