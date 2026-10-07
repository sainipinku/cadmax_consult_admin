<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreVehicleRequest;
use App\Models\Vehicle;
use App\Models\VehicleImage;
use App\Models\VehicleDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class VehicleController extends Controller
{
    public function index(Request $request)
    {
        $vehicles = Vehicle::query()
            ->with(['images', 'documents'])
            ->when($request->search, fn($q) => $q->where(function ($query) use ($request) {
                $query->where('vehicle_number', 'like', "%{$request->search}%")
                    ->orWhere('vehicle_name', 'like', "%{$request->search}%")
                    ->orWhere('brand', 'like', "%{$request->search}%")
                    ->orWhere('vehicle_type', 'like', "%{$request->search}%")
                    ->orWhere('vehicle_id', 'like', "%{$request->search}%")
                    ->orWhere('engine_number', 'like', "%{$request->search}%")
                    ->orWhere('chassis_number', 'like', "%{$request->search}%");
            }))
            ->when($request->vehicle_type, fn($q) => $q->where('vehicle_type', $request->vehicle_type))
            ->when($request->fuel_type, fn($q) => $q->where('fuel_type', $request->fuel_type))
            ->when($request->status !== null && $request->status !== '', fn($q) => $q->where('status', (int) $request->status))
            ->when($request->insurance_status !== null && $request->insurance_status !== '', fn($q) => $q->where('insurance_status', (int) $request->insurance_status))
            ->when($request->sort, function ($q) use ($request) {
                switch ($request->sort) {
                    case 'newest': $q->newestFirst(); break;
                    case 'oldest': $q->oldestFirst(); break;
                    case 'vehicle_number': $q->sortByVehicleNumber(); break;
                    default: $q->newestFirst();
                }
            }, fn($q) => $q->newestFirst())
            ->paginate($request->integer('per_page', 10))
            ->withQueryString();

        return Inertia::render('SuperAdmin/Vehicles/List', [
            'vehicles' => $vehicles,
            'filters' => (object) $request->only(['search', 'vehicle_type', 'fuel_type', 'status', 'insurance_status', 'sort', 'per_page']),
        ]);
    }

    public function show($uuid)
    {
        $vehicle = Vehicle::with(['images', 'documents'])->where('uuid', $uuid)->firstOrFail();
        return response()->json([
            'success' => true,
            'vehicle' => $vehicle,
        ]);
    }

    public function store(StoreVehicleRequest $request)
    {
        $validated = $request->validated();

        $vehicleData = [
            'vehicle_type' => $validated['vehicle_type'],
            'vehicle_number' => $validated['vehicle_number'],
            'vehicle_name' => $validated['vehicle_name'] ?? null,
            'brand' => $validated['brand'] ?? null,
            'fuel_type' => $validated['fuel_type'],
            'color' => $validated['color'] ?? null,
            'manufacturing_year' => $validated['manufacturing_year'] ?? null,
            'engine_number' => $validated['engine_number'] ?? null,
            'chassis_number' => $validated['chassis_number'] ?? null,
            'purchase_date' => $validated['purchase_date'] ?? null,
            'purchase_amount' => $validated['purchase_amount'] ?? null,
            'current_km_reading' => $validated['current_km_reading'] ?? null,
            'status' => (int) $validated['status'],
            'insurance_provider' => $validated['insurance_provider'] ?? null,
            'policy_number' => $validated['policy_number'] ?? null,
            'insurance_type' => $validated['insurance_type'] ?? null,
            'insurance_start_date' => $validated['insurance_start_date'] ?? null,
            'insurance_end_date' => $validated['insurance_end_date'] ?? null,
            'puc_certificate_number' => $validated['puc_certificate_number'] ?? null,
            'puc_issue_date' => $validated['puc_issue_date'] ?? null,
            'puc_expiry_date' => $validated['puc_expiry_date'] ?? null,
            'challan_number' => $validated['challan_number'] ?? null,
            'challan_date' => $validated['challan_date'] ?? null,
            'violation_type' => $validated['violation_type'] ?? null,
            'fine_amount' => $validated['fine_amount'] ?? null,
            'payment_status' => (isset($validated['payment_status']) && $validated['payment_status'] !== null && $validated['payment_status'] !== '') ? (int) $validated['payment_status'] : null,
        ];

        if ($request->hasFile('vehicle_image')) {
            $vehicleData['vehicle_image'] = $request->file('vehicle_image')->store('vehicles', 'public');
        }

        $vehicle = Vehicle::create($vehicleData);

        // Store multiple vehicle images
        if ($request->hasFile('vehicle_images')) {
            $sortOrder = 0;
            foreach ($request->file('vehicle_images') as $file) {
                if ($file && $file->isValid()) {
                    $path = $file->store('vehicles/images', 'public');
                    VehicleImage::create([
                        'vehicle_id' => $vehicle->id,
                        'image_path' => $path,
                        'sort_order' => $sortOrder++,
                    ]);

                    if (empty($vehicle->vehicle_image)) {
                        $vehicle->vehicle_image = $path;
                        $vehicle->save();
                    }
                }
            }
        }

        // Store Insurance Document
        if ($request->hasFile('insurance_document')) {
            $file = $request->file('insurance_document');
            $path = $file->store('vehicles/documents', 'public');
            VehicleDocument::create([
                'vehicle_id' => $vehicle->id,
                'document_type' => 'Insurance',
                'document_name' => 'Insurance Policy Document',
                'file_path' => $path,
                'file_type' => $file->getClientMimeType(),
            ]);
        }

        // Store PUC Document
        if ($request->hasFile('puc_document')) {
            $file = $request->file('puc_document');
            $path = $file->store('vehicles/documents', 'public');
            VehicleDocument::create([
                'vehicle_id' => $vehicle->id,
                'document_type' => 'PUC',
                'document_name' => 'PUC Certificate Document',
                'file_path' => $path,
                'file_type' => $file->getClientMimeType(),
            ]);
        }

        // Store Challan Document
        if ($request->hasFile('challan_document')) {
            $file = $request->file('challan_document');
            $path = $file->store('vehicles/documents', 'public');
            VehicleDocument::create([
                'vehicle_id' => $vehicle->id,
                'document_type' => 'Challan',
                'document_name' => 'Traffic Challan Document',
                'file_path' => $path,
                'file_type' => $file->getClientMimeType(),
            ]);
        }

        // Store Additional Supporting Documents
        if ($request->hasFile('other_documents')) {
            $docNames = $request->input('other_document_names', []);
            foreach ($request->file('other_documents') as $idx => $file) {
                if ($file && $file->isValid()) {
                    $path = $file->store('vehicles/documents', 'public');
                    VehicleDocument::create([
                        'vehicle_id' => $vehicle->id,
                        'document_type' => 'Other',
                        'document_name' => $docNames[$idx] ?? 'Supporting Document',
                        'file_path' => $path,
                        'file_type' => $file->getClientMimeType(),
                    ]);
                }
            }
        }

        return redirect()->back()->with('success', 'Vehicle added successfully!');
    }

    public function update(StoreVehicleRequest $request, $uuid)
    {
        $vehicle = Vehicle::where('uuid', $uuid)->firstOrFail();
        $validated = $request->validated();

        $vehicleData = [
            'vehicle_type' => $validated['vehicle_type'],
            'vehicle_number' => $validated['vehicle_number'],
            'vehicle_name' => $validated['vehicle_name'] ?? null,
            'brand' => $validated['brand'] ?? null,
            'fuel_type' => $validated['fuel_type'],
            'color' => $validated['color'] ?? null,
            'manufacturing_year' => $validated['manufacturing_year'] ?? null,
            'engine_number' => $validated['engine_number'] ?? null,
            'chassis_number' => $validated['chassis_number'] ?? null,
            'purchase_date' => $validated['purchase_date'] ?? null,
            'purchase_amount' => $validated['purchase_amount'] ?? null,
            'current_km_reading' => $validated['current_km_reading'] ?? null,
            'status' => (int) $validated['status'],
            'insurance_provider' => $validated['insurance_provider'] ?? null,
            'policy_number' => $validated['policy_number'] ?? null,
            'insurance_type' => $validated['insurance_type'] ?? null,
            'insurance_start_date' => $validated['insurance_start_date'] ?? null,
            'insurance_end_date' => $validated['insurance_end_date'] ?? null,
            'puc_certificate_number' => $validated['puc_certificate_number'] ?? null,
            'puc_issue_date' => $validated['puc_issue_date'] ?? null,
            'puc_expiry_date' => $validated['puc_expiry_date'] ?? null,
            'challan_number' => $validated['challan_number'] ?? null,
            'challan_date' => $validated['challan_date'] ?? null,
            'violation_type' => $validated['violation_type'] ?? null,
            'fine_amount' => $validated['fine_amount'] ?? null,
            'payment_status' => (isset($validated['payment_status']) && $validated['payment_status'] !== null && $validated['payment_status'] !== '') ? (int) $validated['payment_status'] : null,
        ];

        // Handle image deletions
        if (!empty($validated['deleted_image_ids'])) {
            $imagesToDelete = VehicleImage::whereIn('id', $validated['deleted_image_ids'])
                ->where('vehicle_id', $vehicle->id)
                ->get();
            foreach ($imagesToDelete as $img) {
                if ($img->image_path && Storage::disk('public')->exists($img->image_path)) {
                    Storage::disk('public')->delete($img->image_path);
                }
                $img->delete();
            }
        }

        // Handle document deletions
        if (!empty($validated['deleted_document_ids'])) {
            $docsToDelete = VehicleDocument::whereIn('id', $validated['deleted_document_ids'])
                ->where('vehicle_id', $vehicle->id)
                ->get();
            foreach ($docsToDelete as $doc) {
                if ($doc->file_path && Storage::disk('public')->exists($doc->file_path)) {
                    Storage::disk('public')->delete($doc->file_path);
                }
                $doc->delete();
            }
        }

        // Single Cover Image update
        if ($request->hasFile('vehicle_image')) {
            if ($vehicle->vehicle_image && Storage::disk('public')->exists($vehicle->vehicle_image)) {
                Storage::disk('public')->delete($vehicle->vehicle_image);
            }
            $vehicleData['vehicle_image'] = $request->file('vehicle_image')->store('vehicles', 'public');
        }

        $vehicle->update($vehicleData);

        // Upload new multiple vehicle images
        if ($request->hasFile('vehicle_images')) {
            $maxSort = VehicleImage::where('vehicle_id', $vehicle->id)->max('sort_order') ?? 0;
            foreach ($request->file('vehicle_images') as $file) {
                if ($file && $file->isValid()) {
                    $path = $file->store('vehicles/images', 'public');
                    VehicleImage::create([
                        'vehicle_id' => $vehicle->id,
                        'image_path' => $path,
                        'sort_order' => ++$maxSort,
                    ]);

                    if (empty($vehicle->vehicle_image)) {
                        $vehicle->vehicle_image = $path;
                        $vehicle->save();
                    }
                }
            }
        }

        // Update / Store Insurance Document
        if ($request->hasFile('insurance_document')) {
            $existing = VehicleDocument::where('vehicle_id', $vehicle->id)->where('document_type', 'Insurance')->first();
            if ($existing && $existing->file_path && Storage::disk('public')->exists($existing->file_path)) {
                Storage::disk('public')->delete($existing->file_path);
            }
            $file = $request->file('insurance_document');
            $path = $file->store('vehicles/documents', 'public');
            VehicleDocument::updateOrCreate(
                ['vehicle_id' => $vehicle->id, 'document_type' => 'Insurance'],
                [
                    'document_name' => 'Insurance Policy Document',
                    'file_path' => $path,
                    'file_type' => $file->getClientMimeType(),
                ]
            );
        }

        // Update / Store PUC Document
        if ($request->hasFile('puc_document')) {
            $existing = VehicleDocument::where('vehicle_id', $vehicle->id)->where('document_type', 'PUC')->first();
            if ($existing && $existing->file_path && Storage::disk('public')->exists($existing->file_path)) {
                Storage::disk('public')->delete($existing->file_path);
            }
            $file = $request->file('puc_document');
            $path = $file->store('vehicles/documents', 'public');
            VehicleDocument::updateOrCreate(
                ['vehicle_id' => $vehicle->id, 'document_type' => 'PUC'],
                [
                    'document_name' => 'PUC Certificate Document',
                    'file_path' => $path,
                    'file_type' => $file->getClientMimeType(),
                ]
            );
        }

        // Update / Store Challan Document
        if ($request->hasFile('challan_document')) {
            $existing = VehicleDocument::where('vehicle_id', $vehicle->id)->where('document_type', 'Challan')->first();
            if ($existing && $existing->file_path && Storage::disk('public')->exists($existing->file_path)) {
                Storage::disk('public')->delete($existing->file_path);
            }
            $file = $request->file('challan_document');
            $path = $file->store('vehicles/documents', 'public');
            VehicleDocument::updateOrCreate(
                ['vehicle_id' => $vehicle->id, 'document_type' => 'Challan'],
                [
                    'document_name' => 'Traffic Challan Document',
                    'file_path' => $path,
                    'file_type' => $file->getClientMimeType(),
                ]
            );
        }

        // Store Additional Supporting Documents
        if ($request->hasFile('other_documents')) {
            $docNames = $request->input('other_document_names', []);
            foreach ($request->file('other_documents') as $idx => $file) {
                if ($file && $file->isValid()) {
                    $path = $file->store('vehicles/documents', 'public');
                    VehicleDocument::create([
                        'vehicle_id' => $vehicle->id,
                        'document_type' => 'Other',
                        'document_name' => $docNames[$idx] ?? 'Supporting Document',
                        'file_path' => $path,
                        'file_type' => $file->getClientMimeType(),
                    ]);
                }
            }
        }

        return redirect()->back()->with('success', 'Vehicle updated successfully!');
    }

    public function destroy($uuid)
    {
        $vehicle = Vehicle::with(['images', 'documents'])->where('uuid', $uuid)->firstOrFail();

        // Delete vehicle images from storage
        foreach ($vehicle->images as $img) {
            if ($img->image_path && Storage::disk('public')->exists($img->image_path)) {
                Storage::disk('public')->delete($img->image_path);
            }
        }

        // Delete vehicle documents from storage
        foreach ($vehicle->documents as $doc) {
            if ($doc->file_path && Storage::disk('public')->exists($doc->file_path)) {
                Storage::disk('public')->delete($doc->file_path);
            }
        }

        // Delete main image cover
        if ($vehicle->vehicle_image && Storage::disk('public')->exists($vehicle->vehicle_image)) {
            Storage::disk('public')->delete($vehicle->vehicle_image);
        }

        $vehicle->delete();

        return redirect()->back()->with('success', 'Vehicle deleted successfully!');
    }
}