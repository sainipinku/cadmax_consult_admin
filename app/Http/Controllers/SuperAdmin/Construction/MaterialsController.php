<?php

namespace App\Http\Controllers\SuperAdmin\Construction;

use App\Http\Controllers\Concerns\ResolvesConstructionActor;
use App\Http\Controllers\Controller;
use App\Models\Construction\Material;
use App\Models\Construction\MaterialIssue;
use App\Models\Construction\MaterialReceipt;
use App\Models\Construction\MaterialStock;
use App\Models\Construction\Project;
use App\Models\Construction\PurchaseOrder;
use App\Models\Construction\PurchaseRequest;
use App\Models\Construction\Vendor;
use App\Services\Construction\ConstructionMaterialService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MaterialsController extends Controller
{
    use ResolvesConstructionActor;

    public function index(): Response
    {
        return Inertia::render('SuperAdmin/Construction/Materials/Index', [
            'projects' => Project::orderByDesc('id')->get(['id', 'project_code', 'name']),
            'vendors' => Vendor::with('project')->latest()->take(50)->get(),
            'materials' => Material::with('project')->latest()->take(50)->get(),
            'purchaseRequests' => PurchaseRequest::with(['project', 'requestedBy', 'reviewedBy', 'items.material'])
                ->latest()
                ->take(30)
                ->get(),
            'purchaseOrders' => PurchaseOrder::with(['project', 'vendor', 'items.material', 'invoiceDocument'])
                ->latest()
                ->take(30)
                ->get(),
            'receipts' => MaterialReceipt::with(['project', 'purchaseOrder.vendor', 'receivedBy', 'items.material', 'receiptDocument'])
                ->latest()
                ->take(30)
                ->get(),
            'issues' => MaterialIssue::with(['project', 'issuedBy', 'items.material'])
                ->latest()
                ->take(30)
                ->get(),
            'stocks' => MaterialStock::with(['project', 'material'])
                ->latest('updated_at')
                ->take(80)
                ->get(),
        ]);
    }

    public function storeVendor(Request $request, ConstructionMaterialService $materialService): RedirectResponse
    {
        $actor = $this->constructionActor();

        $validated = $request->validate([
            'project_id' => ['required', 'exists:construction_projects,id'],
            'vendor_code' => ['nullable', 'string', 'max:30'],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'gstin' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string'],
            'status' => ['nullable', 'in:active,inactive'],
        ]);

        $project = Project::findOrFail($validated['project_id']);
        $materialService->createVendor($project, $validated, $actor, $request);

        return back()->with('success', 'Vendor saved successfully.');
    }

    public function storeMaterial(Request $request, ConstructionMaterialService $materialService): RedirectResponse
    {
        $validated = $request->validate([
            'project_id' => ['required', 'exists:construction_projects,id'],
            'material_code' => ['nullable', 'string', 'max:30'],
            'name' => ['required', 'string', 'max:255'],
            'unit' => ['nullable', 'string', 'max:50'],
            'default_rate' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', 'in:active,inactive'],
        ]);

        $project = Project::findOrFail($validated['project_id']);
        $materialService->createMaterial($project, $validated);

        return back()->with('success', 'Material saved successfully.');
    }

    public function storePurchaseRequest(Request $request, ConstructionMaterialService $materialService): RedirectResponse
    {
        $actor = $this->constructionActor();

        $validated = $request->validate([
            'project_id' => ['required', 'exists:construction_projects,id'],
            'request_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            'status' => ['nullable', 'in:draft,submitted'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.material_id' => ['required', 'exists:construction_materials,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit' => ['nullable', 'string', 'max:50'],
            'items.*.estimated_rate' => ['nullable', 'numeric', 'min:0'],
            'items.*.notes' => ['nullable', 'string'],
        ]);

        $project = Project::findOrFail($validated['project_id']);
        $materialService->createPurchaseRequest($project, $validated, $actor);

        return back()->with('success', 'Purchase request saved successfully.');
    }

    public function reviewPurchaseRequest(PurchaseRequest $purchaseRequest, Request $request, ConstructionMaterialService $materialService): RedirectResponse
    {
        $actor = $this->constructionActor();

        $validated = $request->validate([
            'status' => ['required', 'in:approved,rejected,revision_requested'],
            'review_notes' => ['nullable', 'string'],
        ]);

        $materialService->reviewPurchaseRequest($purchaseRequest, $validated, $actor);

        return back()->with('success', 'Purchase request reviewed successfully.');
    }

    public function storePurchaseOrder(Request $request, ConstructionMaterialService $materialService): RedirectResponse
    {
        $actor = $this->constructionActor();

        $validated = $request->validate([
            'project_id' => ['required', 'exists:construction_projects,id'],
            'purchase_request_id' => ['nullable', 'exists:construction_purchase_requests,id'],
            'vendor_id' => ['required', 'exists:construction_vendors,id'],
            'po_date' => ['required', 'date'],
            'expected_delivery_date' => ['nullable', 'date'],
            'status' => ['nullable', 'in:draft,issued'],
            'invoice_document' => ['nullable', 'file', 'max:20480'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.material_id' => ['required', 'exists:construction_materials,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit' => ['nullable', 'string', 'max:50'],
            'items.*.rate' => ['nullable', 'numeric', 'min:0'],
            'items.*.tax_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $project = Project::findOrFail($validated['project_id']);
        $materialService->createPurchaseOrder($project, $validated, $actor);

        return back()->with('success', 'Purchase order saved successfully.');
    }

    public function storeReceipt(Request $request, ConstructionMaterialService $materialService): RedirectResponse
    {
        $actor = $this->constructionActor();

        $validated = $request->validate([
            'project_id' => ['required', 'exists:construction_projects,id'],
            'purchase_order_id' => ['nullable', 'exists:construction_purchase_orders,id'],
            'received_at' => ['nullable', 'date'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'gps_accuracy_meters' => ['nullable', 'numeric', 'min:0'],
            'receipt_document' => ['nullable', 'file', 'max:20480'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.material_id' => ['required', 'exists:construction_materials,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit' => ['nullable', 'string', 'max:50'],
            'items.*.rate' => ['nullable', 'numeric', 'min:0'],
        ]);

        $project = Project::findOrFail($validated['project_id']);
        $materialService->receiveMaterials($project, $validated, $actor);

        return back()->with('success', 'Material receipt saved successfully.');
    }

    public function storeIssue(Request $request, ConstructionMaterialService $materialService): RedirectResponse
    {
        $actor = $this->constructionActor();

        $validated = $request->validate([
            'project_id' => ['required', 'exists:construction_projects,id'],
            'issue_date' => ['required', 'date'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'gps_accuracy_meters' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.material_id' => ['required', 'exists:construction_materials,id'],
            'items.*.execution_task_id' => ['nullable', 'exists:construction_execution_tasks,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit' => ['nullable', 'string', 'max:50'],
            'items.*.remarks' => ['nullable', 'string'],
        ]);

        $project = Project::findOrFail($validated['project_id']);
        $materialService->issueMaterials($project, $validated, $actor);

        return back()->with('success', 'Material issue saved successfully.');
    }
}
