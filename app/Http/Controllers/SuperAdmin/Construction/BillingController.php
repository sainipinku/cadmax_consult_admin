<?php

namespace App\Http\Controllers\SuperAdmin\Construction;

use App\Http\Controllers\Concerns\ResolvesConstructionActor;
use App\Http\Controllers\Controller;
use App\Models\Construction\ClientInvoice;
use App\Models\Construction\ClientPayment;
use App\Models\Construction\Project;
use App\Services\Construction\ConstructionBillingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BillingController extends Controller
{
    use ResolvesConstructionActor;

    public function index(): Response
    {
        return Inertia::render('SuperAdmin/Construction/Billing/Index', [
            'projects' => Project::orderByDesc('id')->get(['id', 'project_code', 'name']),
            'invoices' => ClientInvoice::with(['project', 'items', 'payments'])
                ->latest()
                ->take(60)
                ->get(),
            'payments' => ClientPayment::with(['project', 'invoice'])
                ->latest()
                ->take(80)
                ->get(),
        ]);
    }

    public function storeInvoice(Request $request, ConstructionBillingService $billingService): RedirectResponse
    {
        $actor = $this->constructionActor();

        $validated = $request->validate([
            'project_id' => ['required', 'exists:construction_projects,id'],
            'invoice_code' => ['nullable', 'string', 'max:30'],
            'invoice_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date'],
            'tax_type' => ['required', 'in:intra,inter'],
            'status' => ['nullable', 'in:draft,issued'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit' => ['nullable', 'string', 'max:30'],
            'items.*.rate' => ['required', 'numeric', 'min:0'],
            'items.*.gst_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $project = Project::findOrFail($validated['project_id']);
        $billingService->createInvoice($project, $validated, $actor, $request);

        return back()->with('success', 'Invoice saved successfully.');
    }

    public function storePayment(Request $request, ConstructionBillingService $billingService): RedirectResponse
    {
        $actor = $this->constructionActor();

        $validated = $request->validate([
            'project_id' => ['required', 'exists:construction_projects,id'],
            'invoice_id' => ['required', 'exists:construction_client_invoices,id'],
            'payment_code' => ['nullable', 'string', 'max:30'],
            'received_at' => ['nullable', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['nullable', 'in:cash,bank_transfer,upi,cheque,card,other'],
            'reference_no' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
        ]);

        $project = Project::findOrFail($validated['project_id']);
        $billingService->recordPayment($project, $validated, $actor, $request);

        return back()->with('success', 'Payment recorded successfully.');
    }
}

