<?php

namespace App\Http\Controllers\SuperAdmin\Construction;

use App\Http\Controllers\Concerns\ResolvesConstructionActor;
use App\Http\Controllers\Controller;
use App\Models\Construction\Client;
use App\Models\Construction\Company;
use App\Services\Construction\ConstructionActivityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ClientController extends Controller
{
    use ResolvesConstructionActor;

    public function index(): Response
    {
        return Inertia::render('SuperAdmin/Construction/Clients/Index', [
            'clients' => Client::with('company')->latest()->get(),
            'companies' => Company::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request, ConstructionActivityService $activityService): RedirectResponse
    {
        $actor = $this->constructionActor();

        $validated = $request->validate([
            'company_id' => ['required', 'exists:construction_companies,id'],
            'client_type' => ['required', 'in:individual,company,government'],
            'name' => ['required', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'alternate_phone' => ['nullable', 'string', 'max:30'],
            'gst_number' => ['nullable', 'string', 'max:50'],
            'billing_address' => ['nullable', 'string'],
            'site_address' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'status' => ['required', 'in:active,inactive'],
        ]);

        $client = Client::create([
            ...$validated,
            'client_code' => 'CLI-' . str_pad((string) ((Client::max('id') ?? 0) + 1), 5, '0', STR_PAD_LEFT),
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
}
