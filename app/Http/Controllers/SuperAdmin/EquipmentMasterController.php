<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEquipmentRequest;
use App\Models\Project;
use App\Models\Employee;
use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\SuperAdmin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class EquipmentMasterController extends Controller
{
    public function index(Request $request)
    {
        $actor = Auth::guard('superadmin')->user();
        $isProductSuperAdmin = $this->isProductSuperAdmin($actor);

        $equipments = Equipment::query()
            ->with(['category:id,category_name,category_id', 'assignedEmployee.member:id,name,email,phone', 'assignedProject:id,project_code,name', 'owningCompany:id,name'])
            ->when(!$isProductSuperAdmin, fn ($query) => $this->scopeEquipmentToActor($query, $actor))
            ->when($request->search, fn ($q) => $q->where(function ($query) use ($request) {
                $query->where('equipment_id', 'like', "%{$request->search}%")
                    ->orWhere('equipment_name', 'like', "%{$request->search}%")
                    ->orWhere('company', 'like', "%{$request->search}%")
                    ->orWhere('brand', 'like', "%{$request->search}%")
                    ->orWhere('model', 'like', "%{$request->search}%")
                    ->orWhere('serial_number', 'like', "%{$request->search}%")
                    ->orWhere('asset_tag', 'like', "%{$request->search}%")
                    ->orWhere('vendor', 'like', "%{$request->search}%")
                    ->orWhere('location_site', 'like', "%{$request->search}%")
                    ->orWhereHas('category', fn ($q) => $q->where('category_name', 'like', "%{$request->search}%"));
            }))
            ->when($request->category_id, fn ($q) => $q->where('category_id', $request->category_id))
            ->when($request->status, fn ($q) => $q->where('status', (int) $request->status))
            ->when($request->sort, function ($q) use ($request) {
                switch ($request->sort) {
                    case 'newest': $q->newestFirst(); break;
                    case 'oldest': $q->oldestFirst(); break;
                    case 'name': $q->orderBy('equipment_name', 'asc'); break;
                    default: $q->newestFirst();
                }
            }, fn ($q) => $q->newestFirst())
            ->paginate($request->integer('per_page', 10))
            ->withQueryString();

        $categories = EquipmentCategory::where('status', 1)->forActor($actor)->orderBy('category_name')->get(['id', 'category_id', 'category_name']);
        $employeesQuery = Employee::with('member:id,name,email,phone');
        $projectsQuery = Project::query();

        if (!$isProductSuperAdmin) {
            if ($actor?->company_id) {
                $companyAdminIds = SuperAdmin::query()
                    ->where('company_id', $actor->company_id)
                    ->pluck('id')
                    ->toArray();

                $targetCompanyName = $actor->company?->name;

                // Ensure every member belonging to this company has an employee record
                $orphanMembers = \App\Models\Member::where(function ($sq) use ($actor, $companyAdminIds, $targetCompanyName) {
                    if (!empty($companyAdminIds)) {
                        $sq->whereIn('created_by', $companyAdminIds);
                    }
                    $sq->orWhere('created_by', $actor->id);

                    if (!empty($targetCompanyName)) {
                        $sq->orWhere('company_name', $targetCompanyName);
                    }
                })
                ->whereDoesntHave('employee')
                ->get();

                foreach ($orphanMembers as $orphan) {
                    Employee::firstOrCreate(
                        ['member_id' => $orphan->id],
                        ['employee_id' => $orphan->employee_id ?: ('EMP-' . str_pad((string) $orphan->id, 5, '0', STR_PAD_LEFT))]
                    );
                }

                $employeesQuery->whereHas('member', function ($query) use ($actor, $companyAdminIds, $targetCompanyName) {
                    $query->where(function ($sq) use ($actor, $companyAdminIds, $targetCompanyName) {
                        if (!empty($companyAdminIds)) {
                            $sq->whereIn('created_by', $companyAdminIds);
                        }
                        $sq->orWhere('created_by', $actor->id);

                        if (!empty($targetCompanyName)) {
                            $sq->orWhere('company_name', $targetCompanyName);
                        }
                    });
                });

                $projectsQuery->where('company_id', $actor->company_id);
            } else {
                $employeesQuery->whereHas('member', fn ($query) => $query->where('created_by', $actor?->id));
                $projectsQuery->whereRaw('1 = 0');
            }
        }

        $employees = $employeesQuery->get(['id', 'member_id', 'employee_id'])->map(function ($emp) {
            $empName = $emp->member?->name;
            $empCode = $emp->employee_id ?: ('EMP-' . $emp->id);
            return [
                'id' => $emp->id,
                'member_id' => $emp->member_id,
                'employee_id' => $empCode,
                'name' => $empName ?: $empCode,
                'email' => $emp->member?->email ?? '',
                'display_name' => $empName ?: $empCode,
            ];
        })->values();
        $projects = $projectsQuery->orderBy('project_code')->get(['id', 'project_code', 'name']);

        return Inertia::render('SuperAdmin/Equipment/List', [
            'equipments' => $equipments,
            'categories' => $categories,
            'employees' => $employees,
            'projects' => $projects,
            'isProductSuperAdmin' => $isProductSuperAdmin,
            'canManageEquipment' => !$isProductSuperAdmin,
            'filters' => (object) $request->only(['search', 'category_id', 'status', 'sort', 'per_page']),
        ]);
    }

    public function show($id)
    {
        $actor = Auth::guard('superadmin')->user();
        $equipment = $this->equipmentQueryForActor($actor)
            ->with(['category', 'assignedEmployee.member', 'assignedProject', 'owningCompany'])
            ->findOrFail($id);
        return response()->json([
            'success' => true,
            'equipment' => $equipment,
        ]);
    }

    public function store(StoreEquipmentRequest $request)
    {
        $actor = Auth::guard('superadmin')->user();
        abort_if($this->isProductSuperAdmin($actor), 403);

        $validated = $request->validated();

        $status = (int) $validated['status'];
        $hasAssignment = !empty($validated['assigned_employee_id']) || !empty($validated['assigned_project_id']);

        // If assignment was specified and status was left as Available (0), auto-promote status to Assigned (1)
        if ($hasAssignment && $status === 0) {
            $status = 1;
        }

        // If status is not Assigned (1), clear assignment fields
        if ($status !== 1) {
            $validated['assigned_employee_id'] = null;
            $validated['assigned_project_id'] = null;
            $validated['assigned_date'] = null;
        } else {
            // Default assigned_date to today if omitted
            if (empty($validated['assigned_date'])) {
                $validated['assigned_date'] = now()->toDateString();
            }
        }

        $equipmentData = [
            'created_by' => $actor?->id,
            'company_id' => $actor?->company_id,
            'category_id' => $validated['category_id'],
            'equipment_name' => $validated['equipment_name'],
            'quantity' => $validated['quantity'] ?? 1,
            'ownership_type' => $validated['ownership_type'] ?? 'owned',
            'location_site' => $validated['location_site'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'company' => $validated['company'] ?? null,
            'brand' => $validated['brand'] ?? null,
            'model' => $validated['model'] ?? null,
            'serial_number' => $validated['serial_number'] ?? null,
            'asset_tag' => $validated['asset_tag'] ?? null,
            'purchase_date' => $validated['purchase_date'] ?? null,
            'purchase_cost' => $validated['purchase_cost'] ?? null,
            'vendor' => $validated['vendor'] ?? null,
            'warranty_till' => $validated['warranty_till'] ?? null,
            'status' => $status,
            'assigned_employee_id' => $validated['assigned_employee_id'] ?? null,
            'assigned_project_id' => $validated['assigned_project_id'] ?? null,
            'assigned_date' => $validated['assigned_date'] ?? null,
        ];

        if ($request->hasFile('photo')) {
            $equipmentData['photo'] = $request->file('photo')->store('equipments', 'public');
        }

        Equipment::create($equipmentData);

        return redirect()->back()->with('success', 'Equipment added successfully!');
    }
    public function update(StoreEquipmentRequest $request, $id)
    {
        $actor = Auth::guard('superadmin')->user();
        abort_if($this->isProductSuperAdmin($actor), 403);

        $equipment = $this->equipmentQueryForActor($actor)->findOrFail($id);
        $validated = $request->validated();

        $status = (int) $validated['status'];
        $hasAssignment = !empty($validated['assigned_employee_id']) || !empty($validated['assigned_project_id']);

        // If assignment was specified and status was left as Available (0), auto-promote status to Assigned (1)
        if ($hasAssignment && $status === 0) {
            $status = 1;
        }

        // If status is not Assigned (1), clear assignment fields
        if ($status !== 1) {
            $validated['assigned_employee_id'] = null;
            $validated['assigned_project_id'] = null;
            $validated['assigned_date'] = null;
        } else {
            // Default assigned_date to today if omitted
            if (empty($validated['assigned_date'])) {
                $validated['assigned_date'] = now()->toDateString();
            }
        }

        $equipmentData = [
            'category_id' => $validated['category_id'],
            'equipment_name' => $validated['equipment_name'],
            'quantity' => $validated['quantity'] ?? $equipment->quantity,
            'ownership_type' => $validated['ownership_type'] ?? $equipment->ownership_type,
            'location_site' => $validated['location_site'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'company' => $validated['company'] ?? null,
            'brand' => $validated['brand'] ?? null,
            'model' => $validated['model'] ?? null,
            'serial_number' => $validated['serial_number'] ?? null,
            'asset_tag' => $validated['asset_tag'] ?? null,
            'purchase_date' => $validated['purchase_date'] ?? null,
            'purchase_cost' => $validated['purchase_cost'] ?? null,
            'vendor' => $validated['vendor'] ?? null,
            'warranty_till' => $validated['warranty_till'] ?? null,
            'status' => $status,
            'assigned_employee_id' => $validated['assigned_employee_id'] ?? null,
            'assigned_project_id' => $validated['assigned_project_id'] ?? null,
            'assigned_date' => $validated['assigned_date'] ?? null,
        ];

        if ($request->hasFile('photo')) {
            if ($equipment->photo && Storage::disk('public')->exists($equipment->photo)) {
                Storage::disk('public')->delete($equipment->photo);
            }
            $equipmentData['photo'] = $request->file('photo')->store('equipments', 'public');
        }

        $equipment->update($equipmentData);

        return redirect()->back()->with('success', 'Equipment updated successfully!');
    }
    public function destroy($id)
    {
        $actor = Auth::guard('superadmin')->user();
        abort_if($this->isProductSuperAdmin($actor), 403);

        $equipment = $this->equipmentQueryForActor($actor)->findOrFail($id);

        if ($equipment->photo && Storage::disk('public')->exists($equipment->photo)) {
            Storage::disk('public')->delete($equipment->photo);
        }

        $equipment->delete();

        return redirect()->back()->with('success', 'Equipment deleted successfully!');
    }

    public function assign(Request $request, $id)
    {
        $actor = Auth::guard('superadmin')->user();
        abort_if($this->isProductSuperAdmin($actor), 403);

        $equipment = $this->equipmentQueryForActor($actor)->findOrFail($id);

        $validated = $request->validate([
            'assigned_employee_id' => 'nullable|exists:employees,id',
            'assigned_project_id' => 'nullable|exists:construction_projects,id',
            'assigned_date' => 'nullable|date',
        ]);

        $hasAssignment = !empty($validated['assigned_employee_id']) || !empty($validated['assigned_project_id']);

        if (!$hasAssignment) {
            $equipment->update([
                'status' => 0,
                'assigned_employee_id' => null,
                'assigned_project_id' => null,
                'assigned_date' => null,
            ]);

            return redirect()->back()->with('success', 'Equipment unassigned successfully!');
        }

        $equipment->update([
            'status' => 1,
            'assigned_employee_id' => $validated['assigned_employee_id'] ?? null,
            'assigned_project_id' => $validated['assigned_project_id'] ?? null,
            'assigned_date' => $validated['assigned_date'] ?? now()->toDateString(),
        ]);

        return redirect()->back()->with('success', 'Equipment assigned successfully!');
    }

    public function unassign($id)
    {
        $actor = Auth::guard('superadmin')->user();
        abort_if($this->isProductSuperAdmin($actor), 403);

        $equipment = $this->equipmentQueryForActor($actor)->findOrFail($id);
        $equipment->update([
            'status' => 0,
            'assigned_employee_id' => null,
            'assigned_project_id' => null,
            'assigned_date' => null,
        ]);

        return redirect()->back()->with('success', 'Equipment unassigned successfully!');
    }

    private function isProductSuperAdmin($actor): bool
    {
        if (!$actor instanceof SuperAdmin) {
            return false;
        }

        return (int) $actor->id === 1
            || (string) $actor->id === '1'
            || (isset($actor->is_project_owner) && (int) $actor->is_project_owner === 1)
            || !$actor->company_id;
    }

    private function scopeEquipmentToActor($query, $actor)
    {
        if ($actor instanceof SuperAdmin && $actor->company_id) {
            return $query->where(function ($scopedQuery) use ($actor) {
                $scopedQuery->where('company_id', $actor->company_id)
                    ->orWhere(function ($legacyQuery) use ($actor) {
                        $legacyQuery->whereNull('company_id')
                            ->where('created_by', $actor->id);
                    });
            });
        }

        return $query->where('created_by', $actor?->id);
    }

    private function equipmentQueryForActor($actor)
    {
        $query = Equipment::query();

        if (!$this->isProductSuperAdmin($actor)) {
            $this->scopeEquipmentToActor($query, $actor);
        }

        return $query;
    }
}
