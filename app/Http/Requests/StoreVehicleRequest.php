<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $vehicleUuid = $this->route('uuid');
        $vehicle = null;
        $vehicleId = null;

        if ($vehicleUuid) {
            $vehicle = \App\Models\Vehicle::where('uuid', $vehicleUuid)->first();
            $vehicleId = $vehicle?->id;
        }

        return [
            'vehicle_type' => ['required', 'string', 'in:Motorcycle,Car,SUV,Pickup,Truck,JCB,Tractor,Other'],
            'vehicle_number' => [
                'required',
                'string',
                'max:50',
                Rule::unique('vehicles')->ignore($vehicleId)->whereNull('deleted_at'),
            ],
            'vehicle_name' => ['nullable', 'string', 'max:255'],
            'brand' => ['nullable', 'string', 'max:255'],
            'fuel_type' => ['required', 'string', 'in:Petrol,Diesel,CNG,Electric,Hybrid'],
            'color' => ['nullable', 'string', 'max:100'],
            'manufacturing_year' => ['nullable', 'string', 'regex:/^\d{4}$/'],
            'engine_number' => ['nullable', 'string', 'max:255'],
            'chassis_number' => ['nullable', 'string', 'max:255'],
            'purchase_date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'purchase_amount' => ['nullable', 'numeric', 'min:0'],
            'current_km_reading' => ['nullable', 'string', 'max:50'],
            'vehicle_image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:5120'],
            'vehicle_images' => ['nullable', 'array'],
            'vehicle_images.*' => ['nullable', 'file', 'mimes:jpeg,png,jpg,gif,webp', 'max:10240'],

            'status' => ['required', 'integer', 'in:0,1,2'],

            'insurance_provider' => ['nullable', 'string', 'max:255'],
            'policy_number' => ['nullable', 'string', 'max:255'],
            'insurance_type' => ['nullable', 'string', 'in:Third Party,Comprehensive'],
            'insurance_start_date' => ['nullable', 'date_format:Y-m-d', 'required_with:insurance_end_date'],
            'insurance_end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:insurance_start_date'],
            'insurance_document' => ['nullable', 'file', 'mimes:jpeg,png,jpg,webp,pdf', 'max:10240'],

            'puc_certificate_number' => ['nullable', 'string', 'max:255'],
            'puc_issue_date' => ['nullable', 'date_format:Y-m-d', 'required_with:puc_expiry_date'],
            'puc_expiry_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:puc_issue_date'],
            'puc_document' => ['nullable', 'file', 'mimes:jpeg,png,jpg,webp,pdf', 'max:10240'],

            'challan_number' => ['nullable', 'string', 'max:255'],
            'challan_date' => ['nullable', 'date_format:Y-m-d'],
            'violation_type' => ['nullable', 'string', 'max:255'],
            'fine_amount' => ['nullable', 'numeric', 'min:0'],
            'payment_status' => ['nullable', 'integer', 'in:0,1'],
            'challan_document' => ['nullable', 'file', 'mimes:jpeg,png,jpg,webp,pdf', 'max:10240'],

            'other_documents' => ['nullable', 'array'],
            'other_documents.*' => ['nullable', 'file', 'mimes:jpeg,png,jpg,webp,pdf', 'max:10240'],
            'other_document_names' => ['nullable', 'array'],

            'deleted_image_ids' => ['nullable', 'array'],
            'deleted_image_ids.*' => ['integer'],
            'deleted_document_ids' => ['nullable', 'array'],
            'deleted_document_ids.*' => ['integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'vehicle_type.required' => 'Please select a vehicle type.',
            'vehicle_type.in' => 'Please select a valid vehicle type.',
            'vehicle_number.required' => 'Vehicle number is required.',
            'vehicle_number.unique' => 'This vehicle number is already registered.',
            'fuel_type.required' => 'Please select a fuel type.',
            'fuel_type.in' => 'Please select a valid fuel type.',
            'status.required' => 'Please select a status.',
            'status.in' => 'Please select a valid status.',
            'insurance_end_date.after_or_equal' => 'Insurance end date must be after start date.',
            'puc_expiry_date.after_or_equal' => 'PUC expiry date must be after issue date.',
            'manufacturing_year.regex' => 'Manufacturing year must be a valid 4-digit year.',
            'vehicle_image.image' => 'File must be an image.',
            'vehicle_image.max' => 'Image must not exceed 5MB.',
            'purchase_date.date_format' => 'Please enter a valid purchase date (YYYY-MM-DD).',
            'insurance_start_date.date_format' => 'Please enter a valid insurance start date (YYYY-MM-DD).',
            'insurance_end_date.date_format' => 'Please enter a valid insurance end date (YYYY-MM-DD).',
            'puc_issue_date.date_format' => 'Please enter a valid PUC issue date (YYYY-MM-DD).',
            'puc_expiry_date.date_format' => 'Please enter a valid PUC expiry date (YYYY-MM-DD).',
            'challan_date.date_format' => 'Please enter a valid challan date (YYYY-MM-DD).',
        ];
    }
}