<?php

namespace App\Http\Controllers\SuperAdmin\Construction;

use App\Http\Controllers\Concerns\ResolvesConstructionActor;
use App\Http\Controllers\Controller;
use App\Models\Construction\Company;
use App\Services\Construction\ConstructionActivityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CompanyController extends Controller
{
    use ResolvesConstructionActor;

    public function index(): Response
    {
        return Inertia::render('SuperAdmin/Construction/Companies/Index', [
            'companies' => Company::latest()->get(),
        ]);
    }

    public function store(Request $request, ConstructionActivityService $activityService): RedirectResponse
    {
        $actor = $this->constructionActor();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'gst_number' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'status' => ['required', 'in:active,inactive'],
        ]);

        $company = Company::create([
            ...$validated,
            'created_by_type' => $actor ? $actor::class : null,
            'created_by_id' => $actor?->getKey(),
        ]);

        $activityService->log(
            module: 'company',
            action: 'created',
            actor: $actor,
            reference: $company,
            companyId: $company->id,
            meta: ['name' => $company->name],
            request: $request
        );

        return back()->with('success', 'Company created successfully.');
    }
}
