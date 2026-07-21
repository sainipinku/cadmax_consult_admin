<?php

namespace App\Services\Construction;

use App\Models\Construction\Material;
use App\Models\Construction\MaterialIssue;
use App\Models\Construction\MaterialIssueItem;
use App\Models\Construction\MaterialReceipt;
use App\Models\Construction\MaterialReceiptItem;
use App\Models\Construction\MaterialStock;
use App\Models\Construction\Project;
use App\Models\Construction\PurchaseOrder;
use App\Models\Construction\PurchaseOrderItem;
use App\Models\Construction\PurchaseRequest;
use App\Models\Construction\PurchaseRequestItem;
use App\Models\Construction\Vendor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ConstructionMaterialService
{
    public function __construct(
        private readonly ConstructionDocumentService $documentService
    ) {
    }

    public function createVendor(Project $project, array $validated, ?Model $actor, ?Request $request = null): Vendor
    {
        return DB::transaction(function () use ($project, $validated, $actor, $request) {
            $nextId = (Vendor::max('id') ?? 0) + 1;
            $vendorCode = $validated['vendor_code'] ?? ('VND-' . str_pad((string) $nextId, 5, '0', STR_PAD_LEFT));

            return Vendor::create([
                'project_id' => $project->id,
                'vendor_code' => $vendorCode,
                'name' => $validated['name'],
                'phone' => $validated['phone'] ?? null,
                'email' => $validated['email'] ?? null,
                'gstin' => $validated['gstin'] ?? null,
                'address' => $validated['address'] ?? null,
                'status' => $validated['status'] ?? 'active',
                'created_by_type' => $actor ? $actor::class : null,
                'created_by_id' => $actor?->getKey(),
            ]);
        });
    }

    public function createMaterial(Project $project, array $validated): Material
    {
        return DB::transaction(function () use ($project, $validated) {
            $nextId = (Material::max('id') ?? 0) + 1;
            $materialCode = $validated['material_code'] ?? ('MAT-' . str_pad((string) $nextId, 5, '0', STR_PAD_LEFT));

            return Material::create([
                'project_id' => $project->id,
                'material_code' => $materialCode,
                'name' => $validated['name'],
                'unit' => $validated['unit'] ?? 'nos',
                'default_rate' => $validated['default_rate'] ?? null,
                'status' => $validated['status'] ?? 'active',
            ]);
        });
    }

    public function createPurchaseRequest(Project $project, array $validated, ?Model $actor): PurchaseRequest
    {
        return DB::transaction(function () use ($project, $validated, $actor) {
            $items = $validated['items'] ?? [];
            if (count($items) === 0) {
                throw ValidationException::withMessages([
                    'items' => 'At least one material item is required.',
                ]);
            }

            $nextId = (PurchaseRequest::max('id') ?? 0) + 1;
            $requestCode = 'PR-' . str_pad((string) $nextId, 5, '0', STR_PAD_LEFT);

            $request = PurchaseRequest::create([
                'project_id' => $project->id,
                'request_code' => $requestCode,
                'requested_by_member_id' => $actor?->getKey(),
                'request_date' => $validated['request_date'],
                'notes' => $validated['notes'] ?? null,
                'status' => $validated['status'] ?? 'submitted',
            ]);

            foreach ($items as $item) {
                $this->ensureMaterialBelongsToProject($project, (int) $item['material_id'], 'items.material_id');
                PurchaseRequestItem::create([
                    'purchase_request_id' => $request->id,
                    'material_id' => $item['material_id'],
                    'quantity' => $item['quantity'],
                    'unit' => $item['unit'] ?? null,
                    'estimated_rate' => $item['estimated_rate'] ?? null,
                    'notes' => $item['notes'] ?? null,
                ]);
            }

            return $request->load(['items.material']);
        });
    }

    public function reviewPurchaseRequest(PurchaseRequest $purchaseRequest, array $validated, ?Model $actor): PurchaseRequest
    {
        return DB::transaction(function () use ($purchaseRequest, $validated, $actor) {
            $purchaseRequest->forceFill([
                'status' => $validated['status'],
                'reviewed_by_member_id' => $actor?->getKey(),
                'reviewed_at' => now(),
                'review_notes' => $validated['review_notes'] ?? null,
            ])->save();

            return $purchaseRequest;
        });
    }

    public function createPurchaseOrder(Project $project, array $validated, ?Model $actor): PurchaseOrder
    {
        return DB::transaction(function () use ($project, $validated, $actor) {
            $items = $validated['items'] ?? [];
            if (count($items) === 0) {
                throw ValidationException::withMessages([
                    'items' => 'At least one purchase order item is required.',
                ]);
            }

            $vendor = Vendor::whereKey((int) $validated['vendor_id'])
                ->where('project_id', $project->id)
                ->first();

            if (!$vendor) {
                throw ValidationException::withMessages([
                    'vendor_id' => 'The selected vendor does not belong to the chosen project.',
                ]);
            }

            $nextId = (PurchaseOrder::max('id') ?? 0) + 1;
            $poCode = 'PO-' . str_pad((string) $nextId, 5, '0', STR_PAD_LEFT);

            $invoiceDocumentId = null;
            if (!empty($validated['invoice_document']) && $validated['invoice_document'] instanceof UploadedFile) {
                $invoiceDocument = $this->documentService->storeDocument(
                    documentable: $project,
                    actor: $actor,
                    folder: 'construction/materials/purchase-orders',
                    file: $validated['invoice_document'],
                    companyId: $project->company_id,
                    projectId: $project->id
                );
                $invoiceDocumentId = $invoiceDocument->id;
            }

            $purchaseOrder = PurchaseOrder::create([
                'project_id' => $project->id,
                'purchase_request_id' => $validated['purchase_request_id'] ?? null,
                'po_code' => $poCode,
                'vendor_id' => $vendor->id,
                'po_date' => $validated['po_date'],
                'expected_delivery_date' => $validated['expected_delivery_date'] ?? null,
                'status' => $validated['status'] ?? 'issued',
                'subtotal_amount' => 0,
                'tax_amount' => 0,
                'total_amount' => 0,
                'invoice_document_id' => $invoiceDocumentId ?? ($validated['invoice_document_id'] ?? null),
                'created_by_type' => $actor ? $actor::class : null,
                'created_by_id' => $actor?->getKey(),
            ]);

            $subtotal = 0.0;
            $tax = 0.0;

            foreach ($items as $item) {
                $this->ensureMaterialBelongsToProject($project, (int) $item['material_id'], 'items.material_id');
                $quantity = (float) $item['quantity'];
                $rate = isset($item['rate']) ? (float) $item['rate'] : 0.0;
                $taxPercent = isset($item['tax_percent']) ? (float) $item['tax_percent'] : 0.0;
                $lineBase = $quantity * $rate;
                $lineTax = $lineBase * ($taxPercent / 100);
                $lineTotal = $lineBase + $lineTax;

                PurchaseOrderItem::create([
                    'purchase_order_id' => $purchaseOrder->id,
                    'material_id' => $item['material_id'],
                    'quantity' => $quantity,
                    'unit' => $item['unit'] ?? null,
                    'rate' => $rate,
                    'tax_percent' => $taxPercent,
                    'tax_amount' => $lineTax,
                    'line_total' => $lineTotal,
                    'received_quantity' => 0,
                ]);

                $subtotal += $lineBase;
                $tax += $lineTax;
            }

            $purchaseOrder->forceFill([
                'subtotal_amount' => $subtotal,
                'tax_amount' => $tax,
                'total_amount' => $subtotal + $tax,
            ])->save();

            return $purchaseOrder->load(['items.material', 'vendor', 'invoiceDocument']);
        });
    }

    public function receiveMaterials(Project $project, array $validated, ?Model $actor): MaterialReceipt
    {
        return DB::transaction(function () use ($project, $validated, $actor) {
            $items = $validated['items'] ?? [];
            if (count($items) === 0) {
                throw ValidationException::withMessages([
                    'items' => 'At least one receipt item is required.',
                ]);
            }

            $nextId = (MaterialReceipt::max('id') ?? 0) + 1;
            $receiptCode = 'MR-' . str_pad((string) $nextId, 5, '0', STR_PAD_LEFT);

            $purchaseOrder = null;
            if (!empty($validated['purchase_order_id'])) {
                $purchaseOrder = PurchaseOrder::whereKey((int) $validated['purchase_order_id'])
                    ->where('project_id', $project->id)
                    ->first();

                if (!$purchaseOrder) {
                    throw ValidationException::withMessages([
                        'purchase_order_id' => 'The selected purchase order does not belong to the chosen project.',
                    ]);
                }
            }

            $receiptDocumentId = null;
            if (!empty($validated['receipt_document']) && $validated['receipt_document'] instanceof UploadedFile) {
                $receiptDocument = $this->documentService->storeDocument(
                    documentable: $project,
                    actor: $actor,
                    folder: 'construction/materials/receipts',
                    file: $validated['receipt_document'],
                    companyId: $project->company_id,
                    projectId: $project->id
                );
                $receiptDocumentId = $receiptDocument->id;
            }

            $receipt = MaterialReceipt::create([
                'project_id' => $project->id,
                'purchase_order_id' => $purchaseOrder?->id,
                'receipt_code' => $receiptCode,
                'received_by_member_id' => $actor?->getKey(),
                'received_at' => $validated['received_at'] ?? now(),
                'latitude' => $validated['latitude'] ?? null,
                'longitude' => $validated['longitude'] ?? null,
                'gps_accuracy_meters' => $validated['gps_accuracy_meters'] ?? null,
                'status' => $validated['status'] ?? 'received',
                'notes' => $validated['notes'] ?? null,
                'receipt_document_id' => $receiptDocumentId,
            ]);

            foreach ($items as $item) {
                $this->ensureMaterialBelongsToProject($project, (int) $item['material_id'], 'items.material_id');
                $quantity = (float) $item['quantity'];
                if ($quantity <= 0) {
                    throw ValidationException::withMessages([
                        'items.quantity' => 'Quantity must be greater than 0.',
                    ]);
                }

                MaterialReceiptItem::create([
                    'material_receipt_id' => $receipt->id,
                    'material_id' => $item['material_id'],
                    'quantity' => $quantity,
                    'unit' => $item['unit'] ?? null,
                    'rate' => $item['rate'] ?? null,
                    'line_total' => $item['line_total'] ?? 0,
                ]);

                $this->incrementStock($project->id, (int) $item['material_id'], $quantity);

                if ($purchaseOrder) {
                    PurchaseOrderItem::where('purchase_order_id', $purchaseOrder->id)
                        ->where('material_id', $item['material_id'])
                        ->update([
                            'received_quantity' => DB::raw('received_quantity + ' . $quantity),
                        ]);
                }
            }

            if ($purchaseOrder) {
                $totalOrdered = (float) $purchaseOrder->items()->sum('quantity');
                $totalReceived = (float) $purchaseOrder->items()->sum('received_quantity');
                $purchaseOrder->forceFill([
                    'status' => $totalReceived >= $totalOrdered ? 'received' : 'partially_received',
                ])->save();
            }

            return $receipt->load(['items.material', 'purchaseOrder.vendor', 'receiptDocument']);
        });
    }

    public function issueMaterials(Project $project, array $validated, ?Model $actor): MaterialIssue
    {
        return DB::transaction(function () use ($project, $validated, $actor) {
            $items = $validated['items'] ?? [];
            if (count($items) === 0) {
                throw ValidationException::withMessages([
                    'items' => 'At least one issue item is required.',
                ]);
            }

            $nextId = (MaterialIssue::max('id') ?? 0) + 1;
            $issueCode = 'MI-' . str_pad((string) $nextId, 5, '0', STR_PAD_LEFT);

            $issue = MaterialIssue::create([
                'project_id' => $project->id,
                'issue_code' => $issueCode,
                'issued_by_member_id' => $actor?->getKey(),
                'issue_date' => $validated['issue_date'],
                'latitude' => $validated['latitude'] ?? null,
                'longitude' => $validated['longitude'] ?? null,
                'gps_accuracy_meters' => $validated['gps_accuracy_meters'] ?? null,
                'status' => $validated['status'] ?? 'issued',
                'notes' => $validated['notes'] ?? null,
            ]);

            foreach ($items as $index => $item) {
                $this->ensureMaterialBelongsToProject($project, (int) $item['material_id'], "items.{$index}.material_id");
                $quantity = (float) $item['quantity'];

                if ($quantity <= 0) {
                    throw ValidationException::withMessages([
                        "items.{$index}.quantity" => 'Quantity must be greater than 0.',
                    ]);
                }

                $this->decrementStock($project->id, (int) $item['material_id'], $quantity, "items.{$index}.quantity");

                MaterialIssueItem::create([
                    'material_issue_id' => $issue->id,
                    'material_id' => $item['material_id'],
                    'execution_task_id' => $item['execution_task_id'] ?? null,
                    'quantity' => $quantity,
                    'unit' => $item['unit'] ?? null,
                    'remarks' => $item['remarks'] ?? null,
                ]);
            }

            return $issue->load(['items.material']);
        });
    }

    private function incrementStock(int $projectId, int $materialId, float $quantity): void
    {
        $stock = MaterialStock::firstOrCreate([
            'project_id' => $projectId,
            'material_id' => $materialId,
        ], [
            'on_hand_quantity' => 0,
        ]);

        $stock->forceFill([
            'on_hand_quantity' => (float) $stock->on_hand_quantity + $quantity,
        ])->save();
    }

    private function decrementStock(int $projectId, int $materialId, float $quantity, string $field): void
    {
        $stock = MaterialStock::firstOrCreate([
            'project_id' => $projectId,
            'material_id' => $materialId,
        ], [
            'on_hand_quantity' => 0,
        ]);

        $next = (float) $stock->on_hand_quantity - $quantity;
        if ($next < 0) {
            throw ValidationException::withMessages([
                $field => 'Insufficient stock available for this material.',
            ]);
        }

        $stock->forceFill([
            'on_hand_quantity' => $next,
        ])->save();
    }

    private function ensureMaterialBelongsToProject(Project $project, int $materialId, string $field): void
    {
        $exists = Material::whereKey($materialId)
            ->where('project_id', $project->id)
            ->exists();

        if (!$exists) {
            throw ValidationException::withMessages([
                $field => 'The selected material does not belong to the chosen project.',
            ]);
        }
    }
}
