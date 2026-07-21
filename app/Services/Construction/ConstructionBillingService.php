<?php

namespace App\Services\Construction;

use App\Models\Construction\ClientInvoice;
use App\Models\Construction\ClientInvoiceItem;
use App\Models\Construction\ClientPayment;
use App\Models\Construction\Project;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConstructionBillingService
{
    public function __construct(
        private readonly ConstructionActivityService $activityService
    ) {
    }

    public function createInvoice(Project $project, array $validated, ?Model $actor, ?Request $request = null): ClientInvoice
    {
        return DB::transaction(function () use ($project, $validated, $actor, $request) {
            $items = $validated['items'] ?? [];
            if (count($items) === 0) {
                throw ValidationException::withMessages([
                    'items' => 'At least one invoice item is required.',
                ]);
            }

            $nextId = (ClientInvoice::max('id') ?? 0) + 1;
            $invoiceCode = $validated['invoice_code'] ?? ('INV-' . str_pad((string) $nextId, 5, '0', STR_PAD_LEFT));
            $taxType = $validated['tax_type'] ?? 'intra';

            if (!in_array($taxType, ['intra', 'inter'], true)) {
                throw ValidationException::withMessages([
                    'tax_type' => 'Invalid tax type.',
                ]);
            }

            $invoice = ClientInvoice::create([
                'project_id' => $project->id,
                'invoice_code' => $invoiceCode,
                'invoice_date' => $validated['invoice_date'],
                'due_date' => $validated['due_date'] ?? null,
                'tax_type' => $taxType,
                'status' => $validated['status'] ?? 'issued',
                'notes' => $validated['notes'] ?? null,
                'subtotal_amount' => 0,
                'cgst_amount' => 0,
                'sgst_amount' => 0,
                'igst_amount' => 0,
                'total_tax_amount' => 0,
                'total_amount' => 0,
                'paid_amount' => 0,
                'balance_amount' => 0,
                'created_by_type' => $actor ? $actor::class : null,
                'created_by_id' => $actor?->getKey(),
            ]);

            $subtotal = 0.0;
            $cgst = 0.0;
            $sgst = 0.0;
            $igst = 0.0;

            foreach ($items as $index => $item) {
                $description = trim((string) ($item['description'] ?? ''));
                if ($description === '') {
                    throw ValidationException::withMessages([
                        "items.$index.description" => 'Description is required.',
                    ]);
                }

                $quantity = (float) ($item['quantity'] ?? 0);
                if ($quantity <= 0) {
                    throw ValidationException::withMessages([
                        "items.$index.quantity" => 'Quantity must be greater than 0.',
                    ]);
                }

                $rate = (float) ($item['rate'] ?? 0);
                if ($rate < 0) {
                    throw ValidationException::withMessages([
                        "items.$index.rate" => 'Rate cannot be negative.',
                    ]);
                }

                $gstPercent = (float) ($item['gst_percent'] ?? 0);
                if ($gstPercent < 0 || $gstPercent > 100) {
                    throw ValidationException::withMessages([
                        "items.$index.gst_percent" => 'GST percent must be between 0 and 100.',
                    ]);
                }

                $lineSubtotal = $quantity * $rate;
                $lineTax = $lineSubtotal * ($gstPercent / 100);
                $lineCgst = 0.0;
                $lineSgst = 0.0;
                $lineIgst = 0.0;

                if ($taxType === 'intra') {
                    $lineCgst = $lineTax / 2;
                    $lineSgst = $lineTax / 2;
                } else {
                    $lineIgst = $lineTax;
                }

                $lineTotal = $lineSubtotal + $lineTax;

                ClientInvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'description' => $description,
                    'quantity' => $quantity,
                    'unit' => $item['unit'] ?? null,
                    'rate' => $rate,
                    'line_subtotal' => $lineSubtotal,
                    'gst_percent' => $gstPercent,
                    'cgst_amount' => $lineCgst,
                    'sgst_amount' => $lineSgst,
                    'igst_amount' => $lineIgst,
                    'line_total_tax' => $lineTax,
                    'line_total' => $lineTotal,
                ]);

                $subtotal += $lineSubtotal;
                $cgst += $lineCgst;
                $sgst += $lineSgst;
                $igst += $lineIgst;
            }

            $totalTax = $cgst + $sgst + $igst;
            $total = $subtotal + $totalTax;

            $invoice->forceFill([
                'subtotal_amount' => $subtotal,
                'cgst_amount' => $cgst,
                'sgst_amount' => $sgst,
                'igst_amount' => $igst,
                'total_tax_amount' => $totalTax,
                'total_amount' => $total,
                'paid_amount' => 0,
                'balance_amount' => $total,
            ])->save();

            $this->activityService->log(
                module: 'billing_invoice',
                action: 'created',
                actor: $actor,
                reference: $invoice,
                companyId: $project->company_id,
                projectId: $project->id,
                meta: [
                    'invoice_code' => $invoice->invoice_code,
                    'tax_type' => $invoice->tax_type,
                    'total_amount' => $invoice->total_amount,
                ],
                request: $request
            );

            return $invoice->load(['items', 'payments']);
        });
    }

    public function recordPayment(Project $project, array $validated, ?Model $actor, ?Request $request = null): ClientPayment
    {
        return DB::transaction(function () use ($project, $validated, $actor, $request) {
            /** @var ClientInvoice|null $invoice */
            $invoice = ClientInvoice::query()
                ->whereKey((int) $validated['invoice_id'])
                ->where('project_id', $project->id)
                ->lockForUpdate()
                ->first();

            if (!$invoice) {
                throw ValidationException::withMessages([
                    'invoice_id' => 'The selected invoice does not belong to the chosen project.',
                ]);
            }

            if ($invoice->status === 'cancelled') {
                throw ValidationException::withMessages([
                    'invoice_id' => 'Cancelled invoices cannot receive payments.',
                ]);
            }

            $amount = (float) $validated['amount'];
            if ($amount <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'Payment amount must be greater than 0.',
                ]);
            }

            $balance = (float) $invoice->balance_amount;
            if ($amount > $balance) {
                throw ValidationException::withMessages([
                    'amount' => 'Payment amount cannot exceed invoice balance.',
                ]);
            }

            $nextId = (ClientPayment::max('id') ?? 0) + 1;
            $paymentCode = $validated['payment_code'] ?? ('PAY-' . str_pad((string) $nextId, 5, '0', STR_PAD_LEFT));

            $payment = ClientPayment::create([
                'project_id' => $project->id,
                'invoice_id' => $invoice->id,
                'payment_code' => $paymentCode,
                'received_at' => $validated['received_at'] ?? now(),
                'amount' => $amount,
                'method' => $validated['method'] ?? 'bank_transfer',
                'reference_no' => $validated['reference_no'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'received_by_type' => $actor ? $actor::class : null,
                'received_by_id' => $actor?->getKey(),
            ]);

            $paidAmount = (float) $invoice->paid_amount + $amount;
            $balanceAmount = (float) $invoice->total_amount - $paidAmount;
            if ($balanceAmount < 0) {
                $balanceAmount = 0;
            }

            $status = $balanceAmount == 0.0 ? 'paid' : 'partially_paid';

            $invoice->forceFill([
                'paid_amount' => $paidAmount,
                'balance_amount' => $balanceAmount,
                'status' => $status,
            ])->save();

            $this->activityService->log(
                module: 'billing_payment',
                action: 'received',
                actor: $actor,
                reference: $payment,
                companyId: $project->company_id,
                projectId: $project->id,
                meta: [
                    'invoice_id' => $invoice->id,
                    'payment_code' => $payment->payment_code,
                    'amount' => $amount,
                ],
                request: $request
            );

            return $payment->load(['invoice']);
        });
    }
}

