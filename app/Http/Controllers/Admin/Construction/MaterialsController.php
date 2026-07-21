<?php

namespace App\Http\Controllers\Admin\Construction;

use App\Http\Controllers\Concerns\ResolvesConstructionActor;
use App\Http\Controllers\Controller;
use App\Models\Construction\Material;
use App\Models\Construction\MaterialIssue;
use App\Models\Construction\MaterialReceipt;
use App\Models\Construction\MaterialStock;
use App\Models\Construction\Project;
use App\Models\Construction\ProjectTeamMember;
use App\Models\Construction\PurchaseOrder;
use App\Models\Construction\PurchaseRequest;
use App\Models\Construction\Vendor;
use App\Models\Member;
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
        /** @var Member|null $actor */
        $actor = $this->constructionActor();
        $projectIds = ProjectTeamMember::where('member_id', $actor?->getKey())
            ->where('status', 'active')
            ->pluck('project_id');

        return Inertia::render('Admin/Construction/Materials/Index', [
            'projects' => Project::whereIn('id', $projectIds)->orderByDesc('id')->get(['id', 'project_code', 'name']),
            'vendors' => Vendor::with('project')->whereIn('project_id', $projectIds)->latest()->take(50)->get(),
            'materials' => Material::with('project')->whereIn('project_id', $projectIds)->latest()->take(50)->get(),
            'purchaseRequests' => PurchaseRequest::with(['project', 'requestedBy', 'reviewedBy', 'items.material'])
                ->whereIn('project_id', $projectIds)
                ->latest()
                ->take(30)
                ->get(),
            'purchaseOrders' => PurchaseOrder::with(['project', 'vendor', 'items.material', 'invoiceDocument'])
                ->whereIn('project_id', $projectIds)
                ->latest()
                ->take(30)
                ->get(),
            'receipts' => MaterialReceipt::with(['project', 'purchaseOrder.vendor', 'receivedBy', 'items.material', 'receiptDocument'])
                ->whereIn('project_id', $projectIds)
                ->latest()
                ->take(30)
                ->get(),
            'issues' => MaterialIssue::with(['project', 'issuedBy', 'items.material'])
                ->whereIn('project_id', $projectIds)
                ->latest()
                ->take(30)
                ->get(),
            'stocks' => MaterialStock::with(['project', 'material'])
                ->whereIn('project_id', $projectIds)
                ->latest('updated_at')
                ->take(80)
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

    public function storePurchaseRequest(Request $request, ConstructionMaterialService $materialService): RedirectResponse
    {
        /** @var Member|null $actor */
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

        $this->ensureProjectAccess((int) $validated['project_id'], $actor);

        $project = Project::findOrFail($validated['project_id']);
        $materialService->createPurchaseRequest($project, $validated, $actor);

        return back()->with('success', 'Purchase request saved successfully.');
    }

    public function storePurchaseOrder(Request $request, ConstructionMaterialService $materialService): RedirectResponse
    {
        /** @var Member|null $actor */
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

        $this->ensureProjectAccess((int) $validated['project_id'], $actor);

        $project = Project::findOrFail($validated['project_id']);
        $materialService->createPurchaseOrder($project, $validated, $actor);

        return back()->with('success', 'Purchase order saved successfully.');
    }

    public function storeReceipt(Request $request, ConstructionMaterialService $materialService): RedirectResponse
    {
        /** @var Member|null $actor */
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

        $this->ensureProjectAccess((int) $validated['project_id'], $actor);

        $project = Project::findOrFail($validated['project_id']);
        $materialService->receiveMaterials($project, $validated, $actor);

        return back()->with('success', 'Material receipt saved successfully.');
    }

    public function storeIssue(Request $request, ConstructionMaterialService $materialService): RedirectResponse
    {
        /** @var Member|null $actor */
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

        $this->ensureProjectAccess((int) $validated['project_id'], $actor);

        $project = Project::findOrFail($validated['project_id']);
        $materialService->issueMaterials($project, $validated, $actor);

        return back()->with('success', 'Material issue saved successfully.');
    }
}
