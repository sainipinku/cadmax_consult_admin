<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEquipmentRequest;
use App\Models\Project;
use App\Models\Employee;
use App\Models\Equipment;
use App\Models\EquipmentCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class EquipmentMasterController extends Controller
{
    public function index(Request $request)
    {
        $equipments = Equipment::with(['category:id,category_name,category_id', 'assignedEmployee.member:id,name,email,phone', 'assignedProject:id,project_code,name'])
            ->when($request->search, fn ($q) => $q->where(function ($query) use ($request) {
                $query->where('equipment_id', 'like', "%{$request->search}%")
                    ->orWhere('equipment_name', 'like', "%{$request->search}%")
                    ->orWhere('company', 'like', "%{$request->search}%")
                    ->orWhere('brand', 'like', "%{$request->search}%")
                    ->orWhere('model', 'like', "%{$request->search}%")
                    ->orWhere('serial_number', 'like', "%{$request->search}%")
                    ->orWhere('asset_tag', 'like', "%{$request->search}%")
                    ->orWhere('vendor', 'like', "%{$request->search}%")
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

        $categories = EquipmentCategory::where('status', 1)->orderBy('category_name')->get(['id', 'category_id', 'category_name']);
        $employees = Employee::with('member:id,name,email,phone')->get(['id', 'employee_id'])->map(function ($emp) {
            $empName = $emp->member?->name;
            return [
                'id' => $emp->id,
                'employee_id' => $emp->employee_id,
                'name' => $empName ?? $emp->employee_id,
                'email' => $emp->member?->email ?? '',
                'display_name' => $empName ? $empName . ' (' . $emp->employee_id . ')' : $emp->employee_id,
            ];
        })->values();
        $projects = Project::orderBy('project_code')->get(['id', 'project_code', 'name']);

        return Inertia::render('SuperAdmin/Equipment/List', [
            'equipments' => $equipments,
            'categories' => $categories,
            'employees' => $employees,
            'projects' => $projects,
            'filters' => (object) $request->only(['search', 'category_id', 'status', 'sort', 'per_page']),
        ]);
    }

    public function show($id)
    {
        $equipment = Equipment::with(['category', 'assignedEmployee.member', 'assignedProject'])->findOrFail($id);
        return response()->json([
            'success' => true,
            'equipment' => $equipment,
        ]);
    }

    public function store(StoreEquipmentRequest $request)
    {
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
        $equipment = Equipment::findOrFail($id);
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
        $equipment = Equipment::findOrFail($id);

        if ($equipment->photo && Storage::disk('public')->exists($equipment->photo)) {
            Storage::disk('public')->delete($equipment->photo);
        }

        $equipment->delete();

        return redirect()->back()->with('success', 'Equipment deleted successfully!');
    }
}
