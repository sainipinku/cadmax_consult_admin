<?php

namespace App\Http\Requests;

use App\Models\Equipment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEquipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $equipment = $this->route('id')
            ? Equipment::find($this->route('id'))
            : null;
        return [
            'category_id' => [
                'bail',
                'required',
                'exists:equipment_categories,id',
            ],

            'equipment_name' => [
                'bail',
                'required',
                'string',
                'max:255',
            ],

            'quantity' => [
                'sometimes',
                'required',
                'integer',
                'min:1',
            ],

            'ownership_type' => [
                'sometimes',
                'required',
                Rule::in(['owned', 'leased', 'rented']),
            ],

            'location_site' => [
                'nullable',
                'string',
                'max:255',
            ],

            'notes' => [
                'nullable',
                'string',
                'max:5000',
            ],

            'company' => [
                'nullable',
                'string',
                'max:255',
            ],

            'brand' => [
                'nullable',
                'string',
                'max:255',
            ],

            'model' => [
                'nullable',
                'string',
                'max:255',
            ],

            'serial_number' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('equipments', 'serial_number')
                    ->ignore($equipment?->id)
                    ->whereNull('deleted_at'),
            ],

            'asset_tag' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('equipments', 'asset_tag')
                    ->ignore($equipment?->id)
                    ->whereNull('deleted_at'),
            ],

            'purchase_date' => [
                'nullable',
                'date',
                'before_or_equal:today',
            ],

            'purchase_cost' => [
                'nullable',
                'numeric',
                'decimal:0,2',
                'min:0',
            ],

            'vendor' => [
                'nullable',
                'string',
                'max:255',
            ],

            'warranty_till' => [
                'nullable',
                'date',
                'after_or_equal:purchase_date',
            ],

            'photo' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,gif,webp',
                'max:5120',
            ],

            'status' => [
                'required',
                'integer',
                'in:0,1,2,3,4,5',
            ],

            'assigned_employee_id' => [
                'nullable',
                'exists:employees,id',
            ],

            'assigned_project_id' => [
                'nullable',
                'exists:construction_projects,id',
            ],

            'assigned_date' => [
                'nullable',
                'date',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'category_id.required' => 'Please select an equipment category.',
            'category_id.exists' => 'Selected equipment category is invalid.',

            'equipment_name.required' => 'Equipment name is required.',
            'quantity.required' => 'Quantity is required.',
            'quantity.integer' => 'Quantity must be a whole number.',
            'quantity.min' => 'Quantity must be at least 1.',

            'serial_number.unique' => 'This serial number already exists.',
            'asset_tag.unique' => 'This asset tag already exists.',

            'purchase_date.date' => 'Please enter a valid purchase date.',
            'purchase_date.before_or_equal' => 'Purchase date cannot be in the future.',

            'purchase_cost.numeric' => 'Purchase cost must be a valid number.',
            'purchase_cost.decimal' => 'Purchase cost can contain up to 2 decimal places.',
            'purchase_cost.min' => 'Purchase cost cannot be negative.',

            'warranty_till.date' => 'Please enter a valid warranty date.',
            'warranty_till.after_or_equal' => 'Warranty date must be on or after the purchase date.',

            'photo.image' => 'Please upload a valid image.',
            'photo.mimes' => 'The image must be a JPG, JPEG, PNG, GIF, or WEBP file.',
            'photo.max' => 'The image size must not exceed 5 MB.',

            'status.required' => 'Please select the equipment status.',
            'status.in' => 'Please select a valid equipment status.',

            'assigned_employee_id.exists' => 'Selected employee is invalid.',
            'assigned_project_id.exists' => 'Selected project is invalid.',

            'assigned_date.required_if' => 'Assigned date is required when equipment status is Assigned.',
            'assigned_date.date' => 'Please enter a valid assigned date.',
        ];
    }
}