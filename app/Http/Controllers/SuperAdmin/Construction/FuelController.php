<?php

namespace App\Http\Controllers\SuperAdmin\Construction;

use App\Http\Controllers\Concerns\ResolvesConstructionActor;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\DriverFuelEntry;
use App\Models\Member;
use App\Models\Project;
use App\Models\SuperAdmin;
use App\Services\Construction\ConstructionActivityService;
use App\Services\Construction\ConstructionAuthorizationService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;




class FuelController extends Controller
{
    use ResolvesConstructionActor;

    /**
     * Display the Project Fuel History & Verification panel with dynamic project selector.
     */
    public function index(Request $request): Response
    {
        $actor = $this->constructionActor();
        $isProjectOwner = $this->isProjectOwner($actor);
        $accessibleProjectIds = $this->getAccessibleProjectIds($actor);

        $companies = collect();
        if ($isProjectOwner) {
            $companies = Company::orderBy('name')->get(['id', 'name']);
        }

        $projectsQuery = Project::with('company')
            ->whereIn('id', $accessibleProjectIds);

        if ($isProjectOwner && $request->filled('company_id')) {
            $projectsQuery->where('company_id', (int) $request->input('company_id'));
        }

        $projects = $projectsQuery->orderBy('name')
            ->get(['id', 'company_id', 'client_id', 'name', 'project_code', 'status']);

        $selectedProjectId = $request->filled('project_id')
            ? (int) $request->input('project_id')
            : null;

        // Base query for fuel entries
        $fuelQuery = DriverFuelEntry::with([
            'project.company',
            'driver.employee',
            'vehicle',
            'reviewer',
        ]);

        // Multi-tenant isolation: Super Admin Project Owner vs Company Super Admin
        if ($isProjectOwner) {
            if ($selectedProjectId) {
                $fuelQuery->where('project_id', $selectedProjectId);
            }
            if ($request->filled('company_id')) {
                $fuelQuery->where('company_id', (int) $request->input('company_id'));
            }
        } else {
            // Company Super Admin: strictly scoped to company
            if ($actor instanceof SuperAdmin && $actor->company_id) {
                $fuelQuery->where(function ($q) use ($accessibleProjectIds, $actor) {
                    $q->where('company_id', $actor->company_id)
                      ->orWhereIn('project_id', $accessibleProjectIds);
                })->where(function ($q) use ($actor) {
                    $q->where('company_id', $actor->company_id)
                      ->orWhereNull('company_id');
                });
            } else {
                $fuelQuery->whereIn('project_id', $accessibleProjectIds);
            }

            if ($selectedProjectId) {
                $fuelQuery->where('project_id', $selectedProjectId);
            }
        }

        // Date filtering
        $dateFilter = $request->input('date', 'all');
        if ($dateFilter !== 'all' && !empty($dateFilter)) {
            if ($dateFilter === 'today') {
                $fuelQuery->whereDate('fuel_date', Carbon::today());
            } elseif ($dateFilter === 'yesterday') {
                $fuelQuery->whereDate('fuel_date', Carbon::yesterday());
            } else {
                $fuelQuery->whereDate('fuel_date', $dateFilter);
            }
        }

        if ($request->filled('from_date')) {
            $fuelQuery->whereDate('fuel_date', '>=', $request->input('from_date'));
        }
        if ($request->filled('to_date')) {
            $fuelQuery->whereDate('fuel_date', '<=', $request->input('to_date'));
        }

        // Status filter
        if ($request->filled('status') && $request->input('status') !== 'all') {
            $fuelQuery->where('status', strtolower((string) $request->input('status')));
        }

        // Fuel Type filter
        if ($request->filled('fuel_type') && $request->input('fuel_type') !== 'all') {
            $fuelQuery->where('fuel_type', $request->input('fuel_type'));
        }

        // Search term
        if ($request->filled('search')) {
            $term = trim((string) $request->input('search'));
            $fuelQuery->where(function ($q) use ($term) {
                $q->where('vehicle_number', 'like', "%{$term}%")
                  ->orWhere('fuel_type', 'like', "%{$term}%")
                  ->orWhereHas('driver', function ($dq) use ($term) {
                      $dq->where('name', 'like', "%{$term}%")
                         ->orWhere('email', 'like', "%{$term}%")
                         ->orWhere('mobile', 'like', "%{$term}%")
                         ->orWhere('phone', 'like', "%{$term}%");
                  })
                  ->orWhereHas('project', function ($pq) use ($term) {
                      $pq->where('name', 'like', "%{$term}%")
                         ->orWhere('project_code', 'like', "%{$term}%");
                  });
            });
        }

        $allRecords = $fuelQuery->latest('fuel_date')->latest('id')->get();

        // Calculate KPIs
        $totalRecords = $allRecords->count();
        $pendingCount = $allRecords->where('status', 'pending')->count();
        $approvedCount = $allRecords->where('status', 'approved')->count();
        $rejectedCount = $allRecords->where('status', 'rejected')->count();
        $totalLiters = (float) $allRecords->sum('quantity_liters');
        $totalAmount = (float) $allRecords->sum('total_amount');
        $approvedAmount = (float) $allRecords->where('status', 'approved')->sum('total_amount');

        // Transform records
        $fuelEntries = $allRecords->map(function ($entry) {
            $liters = (float) ($entry->quantity_liters ?? 0);
            $amount = (float) ($entry->total_amount ?? 0);
            $ratePerLiter = $liters > 0 ? round($amount / $liters, 2) : 0.0;

            return [
                'id' => $entry->id,
                'project_id' => $entry->project_id,
                'company_id' => $entry->company_id,
                'project' => [
                    'id' => $entry->project?->id,
                    'name' => $entry->project?->name ?? 'Unassigned Project',
                    'project_code' => $entry->project?->project_code ?? ($entry->project_id ? 'PRJ-' . $entry->project_id : 'N/A'),
                    'company_name' => $entry->project?->company?->name ?? $entry->company?->name,
                ],
                'driver' => [
                    'id' => $entry->driver?->id,
                    'name' => $entry->driver?->name ?? 'Unknown Driver',
                    'email' => $entry->driver?->email,
                    'phone' => $entry->driver?->phone ?? $entry->driver?->mobile,
                    'avatar' => $entry->driver?->profile_photo_url,
                    'employee_code' => $entry->driver?->employee?->employee_id ?? ('EMP-' . $entry->driver_member_id),
                    'designation' => $entry->driver?->designation_names ?: 'Vehicle Driver',
                ],
                'vehicle' => [
                    'id' => $entry->vehicle?->id,
                    'vehicle_number' => $entry->vehicle_number ?: ($entry->vehicle?->registration_number ?? 'N/A'),
                    'vehicle_code' => $entry->vehicle?->vehicle_code ?? $entry->vehicle?->vehicle_id,
                    'vehicle_name' => $entry->vehicle?->vehicle_name ?? trim(($entry->vehicle?->make ?? '') . ' ' . ($entry->vehicle?->model ?? '')),
                    'vehicle_type' => $entry->vehicle?->vehicle_type ?? 'Fleet Vehicle',
                ],
                'vehicle_number' => $entry->vehicle_number ?: ($entry->vehicle?->registration_number ?? 'N/A'),
                'fuel_date' => $entry->fuel_date?->format('Y-m-d'),
                'fuel_date_formatted' => $entry->fuel_date?->format('d M Y'),
                'fuel_type' => $entry->fuel_type ?? 'Diesel',
                'quantity_liters' => $liters,
                'total_amount' => $amount,
                'rate_per_liter' => (float) $ratePerLiter,
                'odometer_reading' => (float) ($entry->odometer_reading ?? 0),
                'receipt_photo_url' => $entry->receipt_photo_url,
                'notes' => $entry->notes,
                'status' => $entry->status ?? 'pending',
                'reviewed_by' => $entry->reviewer?->name,
                'reviewed_at' => $entry->reviewed_at?->format('d M Y h:i A'),
                'admin_remarks' => $entry->admin_remarks,
            ];
        });

        // Granular Permissions Check for Action Buttons
        $canVerify = $isProjectOwner || $this->actorHasPermission($actor, 'fuel_entry.verify');
        $canExport = $isProjectOwner || $this->actorHasPermission($actor, 'fuel_entry.export');
        $canCreate = $isProjectOwner || $this->actorHasPermission($actor, 'fuel_entry.create');
        $canEdit = $isProjectOwner || $this->actorHasPermission($actor, 'fuel_entry.edit');
        $canDelete = $isProjectOwner || $this->actorHasPermission($actor, 'fuel_entry.delete');

        $component = $request->is('admin/*') ? 'Admin/Construction/Fuel/Index' : 'SuperAdmin/Construction/Fuel/Index';
        $variant = $request->is('admin/*') ? 'admin' : 'super';

        return Inertia::render($component, [
            'isProjectOwner' => $isProjectOwner,
            'companies' => $companies,
            'projects' => $projects,
            'selectedProjectId' => $selectedProjectId,
            'variant' => $variant,
            'filters' => [
                'company_id' => $request->input('company_id'),
                'project_id' => $selectedProjectId,
                'date' => $dateFilter,
                'from_date' => $request->input('from_date'),
                'to_date' => $request->input('to_date'),
                'status' => $request->input('status', 'all'),
                'fuel_type' => $request->input('fuel_type', 'all'),
                'search' => $request->input('search'),
            ],
            'kpis' => [
                'total' => $totalRecords,
                'pending' => $pendingCount,
                'approved' => $approvedCount,
                'rejected' => $rejectedCount,
                'total_liters' => $totalLiters,
                'total_amount' => $totalAmount,
                'approved_amount' => $approvedAmount,
            ],
            'fuelEntries' => $fuelEntries,
            'can' => [
                'view' => true,
                'details' => true,
                'verify' => $canVerify,
                'export' => $canExport,
                'create' => $canCreate,
                'edit' => $canEdit,
                'delete' => $canDelete,
            ],
        ]);
    }

    /**
     * Return detailed modal data including receipt photo, odometer, rate per liter, and vehicle info.
     */
    public function show(int $id, Request $request): JsonResponse
    {
        $actor = $this->constructionActor();
        $fuel = DriverFuelEntry::with(['project.company', 'driver.employee', 'vehicle', 'reviewer'])->findOrFail($id);

        if (!$this->canActorAccessFuelEntry($actor, $fuel)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: This fuel entry belongs outside your scope.',
            ], 403);
        }

        $liters = (float) ($fuel->quantity_liters ?? 0);
        $amount = (float) ($fuel->total_amount ?? 0);
        $ratePerLiter = $liters > 0 ? round($amount / $liters, 2) : 0.0;

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $fuel->id,
                'project_id' => $fuel->project_id,
                'project' => [
                    'id' => $fuel->project?->id,
                    'name' => $fuel->project?->name ?? 'Unassigned Project',
                    'project_code' => $fuel->project?->project_code ?? ($fuel->project_id ? 'PRJ-' . $fuel->project_id : 'N/A'),
                    'company_name' => $fuel->project?->company?->name ?? $fuel->company?->name,
                ],
                'driver' => [
                    'id' => $fuel->driver?->id,
                    'name' => $fuel->driver?->name ?? 'Unknown Driver',
                    'email' => $fuel->driver?->email,
                    'phone' => $fuel->driver?->phone ?? $fuel->driver?->mobile,
                    'avatar' => $fuel->driver?->profile_photo_url,
                    'employee_code' => $fuel->driver?->employee?->employee_id ?? ('EMP-' . $fuel->driver_member_id),
                    'designation' => $fuel->driver?->designation_names ?: 'Vehicle Driver',
                ],
                'vehicle' => [
                    'id' => $fuel->vehicle?->id,
                    'vehicle_number' => $fuel->vehicle_number ?: ($fuel->vehicle?->registration_number ?? 'N/A'),
                    'vehicle_code' => $fuel->vehicle?->vehicle_code ?? $fuel->vehicle?->vehicle_id,
                    'vehicle_name' => $fuel->vehicle?->vehicle_name ?? trim(($fuel->vehicle?->make ?? '') . ' ' . ($fuel->vehicle?->model ?? '')),
                    'vehicle_type' => $fuel->vehicle?->vehicle_type ?? 'Fleet Vehicle',
                ],
                'vehicle_number' => $fuel->vehicle_number ?: ($fuel->vehicle?->registration_number ?? 'N/A'),
                'fuel_date' => $fuel->fuel_date?->format('Y-m-d'),
                'fuel_date_formatted' => $fuel->fuel_date?->format('d M Y'),
                'fuel_type' => $fuel->fuel_type ?? 'Diesel',
                'quantity_liters' => $liters,
                'total_amount' => $amount,
                'rate_per_liter' => (float) $ratePerLiter,
                'odometer_reading' => (float) ($fuel->odometer_reading ?? 0),
                'receipt_photo_url' => $fuel->receipt_photo_url,
                'notes' => $fuel->notes,
                'status' => $fuel->status ?? 'pending',
                'reviewed_by' => $fuel->reviewer?->name,
                'reviewed_at' => $fuel->reviewed_at?->format('d M Y h:i A'),
                'admin_remarks' => $fuel->admin_remarks,
            ],
        ]);
    }

    /**
     * Approve or reject a fuel entry with admin remarks.
     */
    public function verify(int $id, Request $request)
    {
        $actor = $this->constructionActor();
        $fuel = DriverFuelEntry::findOrFail($id);

        if (!$this->canActorAccessFuelEntry($actor, $fuel)) {
            abort(403, 'Unauthorized access to fuel entry.');
        }

        $validated = $request->validate([
            'status' => ['required', 'in:approved,rejected'],
            'admin_remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        $fuel->update([
            'status' => $validated['status'],
            'admin_remarks' => $validated['admin_remarks'] ?? null,
            'reviewed_by' => $actor?->id,
            'reviewed_by_type' => $actor ? get_class($actor) : null,
            'reviewed_at' => now(),
        ]);

        try {
            app(ConstructionActivityService::class)->log(
                module: 'fleet',
                action: 'driver_fuel_reviewed',
                actor: $actor,
                reference: $fuel,
                projectId: $fuel->project_id,
                description: ($actor?->name ?? 'Super Admin') . " {$validated['status']} fuel entry for vehicle {$fuel->vehicle_number} (₹{$fuel->total_amount})",
                referenceCode: $fuel->vehicle_number,
                meta: [
                    'fuel_entry_id' => $fuel->id,
                    'status' => $fuel->status,
                    'admin_remarks' => $fuel->admin_remarks,
                    'reviewed_by' => $fuel->reviewed_by,
                    'reviewed_at' => $fuel->reviewed_at?->toIso8601String(),
                ],
                request: $request
            );
        } catch (\Throwable $e) {
            report($e);
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Fuel entry verification status updated.',
                'data' => $fuel->fresh(),
            ]);
        }

        return back()->with('success', 'Fuel entry verification updated successfully.');
    }

    /**
     * Export filtered fuel entries to CSV format with multi-tenant scoping.
     */
    public function exportCsv(Request $request): StreamedResponse
    {
        $actor = $this->constructionActor();
        $isProjectOwner = $this->isProjectOwner($actor);
        $accessibleProjectIds = $this->getAccessibleProjectIds($actor);

        $fuelQuery = DriverFuelEntry::with(['project.company', 'driver.employee', 'vehicle', 'reviewer']);

        if ($isProjectOwner) {
            if ($request->filled('project_id')) {
                $fuelQuery->where('project_id', (int) $request->input('project_id'));
            }
            if ($request->filled('company_id')) {
                $fuelQuery->where('company_id', (int) $request->input('company_id'));
            }
        } else {
            if ($actor instanceof SuperAdmin && $actor->company_id) {
                $fuelQuery->where(function ($q) use ($accessibleProjectIds, $actor) {
                    $q->where('company_id', $actor->company_id)
                      ->orWhereIn('project_id', $accessibleProjectIds);
                })->where(function ($q) use ($actor) {
                    $q->where('company_id', $actor->company_id)
                      ->orWhereNull('company_id');
                });
            } else {
                $fuelQuery->whereIn('project_id', $accessibleProjectIds);
            }

            if ($request->filled('project_id')) {
                $fuelQuery->where('project_id', (int) $request->input('project_id'));
            }
        }

        if ($request->filled('status') && $request->input('status') !== 'all') {
            $fuelQuery->where('status', strtolower((string) $request->input('status')));
        }

        if ($request->filled('fuel_type') && $request->input('fuel_type') !== 'all') {
            $fuelQuery->where('fuel_type', $request->input('fuel_type'));
        }

        $records = $fuelQuery->latest('fuel_date')->latest('id')->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="fuel_history_logs_' . date('Y-m-d') . '.csv"',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return response()->stream(function () use ($records) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'ID',
                'Fuel Date',
                'Driver Name',
                'Employee Code',
                'Driver Email',
                'Driver Phone',
                'Project Code',
                'Project Name',
                'Company Name',
                'Vehicle Number',
                'Vehicle Code',
                'Fuel Type',
                'Quantity (Liters)',
                'Total Amount (INR)',
                'Rate / Liter (INR)',
                'Odometer Reading (KM)',
                'Status',
                'Reviewed By',
                'Reviewed At',
                'Admin Remarks',
                'Notes',
            ]);

            foreach ($records as $entry) {
                $liters = (float) ($entry->quantity_liters ?? 0);
                $amount = (float) ($entry->total_amount ?? 0);
                $rate = $liters > 0 ? round($amount / $liters, 2) : 0.0;

                fputcsv($handle, [
                    $entry->id,
                    $entry->fuel_date?->format('Y-m-d') ?? '',
                    $entry->driver?->name ?? 'Unknown Driver',
                    $entry->driver?->employee?->employee_id ?? ('EMP-' . $entry->driver_member_id),
                    $entry->driver?->email ?? '',
                    $entry->driver?->phone ?? $entry->driver?->mobile ?? '',
                    $entry->project?->project_code ?? '',
                    $entry->project?->name ?? 'Unassigned Project',
                    $entry->project?->company?->name ?? $entry->company?->name ?? '',
                    $entry->vehicle_number ?: ($entry->vehicle?->registration_number ?? ''),
                    $entry->vehicle?->vehicle_code ?? '',
                    $entry->fuel_type ?? '',
                    $liters,
                    $amount,
                    $rate,
                    $entry->odometer_reading ?? 0,
                    ucfirst((string) ($entry->status ?? 'pending')),
                    $entry->reviewer?->name ?? '',
                    $entry->reviewed_at?->format('Y-m-d H:i:s') ?? '',
                    $entry->admin_remarks ?? '',
                    $entry->notes ?? '',
                ]);
            }
            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Check if the actor can access or verify a given fuel entry.
     */
    protected function canActorAccessFuelEntry(?Model $actor, DriverFuelEntry $fuel): bool
    {
        if (!$actor) {
            return false;
        }

        if ($this->isProjectOwner($actor)) {
            return true;
        }

        $accessibleProjectIds = $this->getAccessibleProjectIds($actor);

        if ($actor instanceof SuperAdmin && $actor->company_id) {
            // Must belong to the same company or project in company
            if ($fuel->company_id && (int) $fuel->company_id !== (int) $actor->company_id) {
                return false;
            }
            if ($fuel->project_id && !$accessibleProjectIds->contains($fuel->project_id)) {
                return false;
            }
            return true;
        }

        if ($fuel->project_id && $accessibleProjectIds->contains($fuel->project_id)) {
            return true;
        }

        return false;
    }

    /**
     * Check if actor has a specific permission.
     */
    protected function actorHasPermission(?Model $actor, string $slug): bool
    {
        if (!$actor) {
            return false;
        }
        if ($this->isProjectOwner($actor)) {
            return true;
        }
        if ($actor instanceof SuperAdmin) {
            return true;
        }
        if ($actor instanceof Member) {
            return app(ConstructionAuthorizationService::class)->can($actor, $slug);
        }
        return false;
    }
}
