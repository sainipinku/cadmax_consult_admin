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
use Illuminate\Validation\Rule;

class ClientController extends Controller
{
    use ResolvesConstructionActor, IntersectsAdminDeletion;

    /**
     * Determine if the actor can manage (edit, activate/deactivate, delete) the client.
     * - Project Owner (SuperAdmin id == 1 or is_project_owner == 1) has global access.
     * - Super Admin of the company that created/owns the client can manage their company clients.
     */
    public function canActorManageClient(?Model $actor, Client $client): bool
    {
        if (!$actor) {
            return false;
        }

        // 1. Project Owner has full access to all clients
        if ($this->isProjectOwner($actor)) {
            return true;
        }

        // 2. Super Admin belonging to the same company or who created the client
        if ($actor instanceof \App\Models\SuperAdmin) {
            if (!empty($actor->company_id) && !empty($client->company_id) && (int) $actor->company_id === (int) $client->company_id) {
                return true;
            }
            if ($client->created_by_type === $actor::class && (int) $client->created_by_id === (int) $actor->getKey()) {
                return true;
            }
        }

        // 3. Other roles associated with the same company or direct creator
        $accessibleCompanyId = $this->getAccessibleCompanyId($actor);
        if ($accessibleCompanyId && !empty($client->company_id) && (int) $accessibleCompanyId === (int) $client->company_id) {
            return true;
        }

        if ($client->created_by_type === $actor::class && (int) $client->created_by_id === (int) $actor->getKey()) {
            return true;
        }

        return false;
    }

    public function index(): Response
    {
        $actor = $this->constructionActor();
        $isProjectOwner = $this->isProjectOwner($actor);
        $companyId = $isProjectOwner ? null : $this->getAccessibleCompanyId($actor);

        $clientsQuery = Client::with('company')->withCount('projects');
        $companiesQuery = Company::query();

        if ($companyId) {
            $clientsQuery->where('company_id', $companyId);
            $companiesQuery->where('id', $companyId);
        }

        $clients = $clientsQuery->latest()->get()->map(function ($client) use ($actor) {
            $client->can_manage = $this->canActorManageClient($actor, $client);
            return $client;
        });

        return Inertia::render('SuperAdmin/Construction/Clients/Index', [
            'clients' => $clients,
            'companies' => $companiesQuery->orderBy('name')->get(['id', 'name']),
            'is_project_owner' => $isProjectOwner,
            'user_company_id' => $actor?->company_id ?? null,
        ]);
    }

    public function store(Request $request, ConstructionActivityService $activityService): RedirectResponse
    {
        $actor = $this->constructionActor();
        $isProjectOwner = $this->isProjectOwner($actor);
        $companyId = $isProjectOwner ? null : $this->getAccessibleCompanyId($actor);

        if (!$isProjectOwner && !$companyId && $actor instanceof \App\Models\SuperAdmin && $actor->company_id) {
            $companyId = $actor->company_id;
        }

        if (!$companyId) {
            $companyId = $request->input('company_id') ?: Company::where('status', 'active')->value('id');
        }

        if ($companyId) {
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
            

            'client_company_name' => [
                'required_if:client_type,company,government',
                'nullable',
                'string',
                'max:255',
            ],

            'client_designation' => [
                'required_if:client_type,company,government',
                'nullable',
                'string',
                'max:255',
            ],
        ], [
            'phone.digits' => 'The phone number must be exactly 10 digits.',
            'alternate_phone.digits' => 'The alternate phone number must be exactly 10 digits.',
        ]);
        // Individual client ke liye company details save nahi hongi.
        if ($validated['client_type'] === 'individual') {
            $validated['client_company_name'] = null;
            $validated['client_designation'] = null;
        }



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

        $isProjectOwner = $this->isProjectOwner($actor);
        $companyId = $isProjectOwner ? null : $this->getAccessibleCompanyId($actor);

        if (!$isProjectOwner && !$companyId && $actor instanceof \App\Models\SuperAdmin && $actor->company_id) {
            $companyId = $actor->company_id;
        }

        if (!$companyId) {
            $companyId = $request->input('company_id') ?: ($client->company_id ?: Company::where('status', 'active')->value('id'));
        }

        if ($companyId) {
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
        

            'client_company_name' => [
                'required_if:client_type,company,government',
                'nullable',
                'string',
                'max:255',
            ],

            'client_designation' => [
                'required_if:client_type,company,government',
                'nullable',
                'string',
                'max:255',
            ],
        ], [
            'phone.digits' => 'The phone number must be exactly 10 digits.',
            'alternate_phone.digits' => 'The alternate phone number must be exactly 10 digits.',
        ]);

        // Individual client ke liye company details save nahi hongi.
        if ($validated['client_type'] === 'individual') {
            $validated['client_company_name'] = null;
            $validated['client_designation'] = null;
        }


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
