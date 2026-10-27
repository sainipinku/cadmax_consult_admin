<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Http\Controllers\SuperAdmin\EmployeeController;
use Illuminate\Support\Facades\Auth;

class StoreEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('phone')) {
            $this->merge([
                'phone' => substr(preg_replace('/\D/', '', (string) $this->phone), 0, 12),
            ]);
        }

        if ($this->has('alternate_number') && $this->alternate_number) {
            $this->merge([
                'alternate_number' => substr(preg_replace('/\D/', '', (string) $this->alternate_number), 0, 12),
            ]);
        }

        $roles = $this->input('roles', []);
        if (is_string($roles)) {
            $roles = json_decode($roles, true) ?? [$roles];
        }

        $hasDriverRole = collect((array) $roles)->contains(
            fn ($role) => in_array(
                strtolower(trim((string) $role)),
                ['driver', 'vehicle driver', 'vehicle_driver', '6'],
                true
            )
        );

        if ($hasDriverRole) {
            $this->merge([
                'department' => 'Operations',
                'designation' => 'Driver',
            ]);
        }
    }

    public function rules(): array
    {
        $employeeUuid = $this->route('uuid');
        $employee = null;
        $memberId = null;
        $department = $this->input('department');
        $designationOptions = EmployeeController::getDepartmentDesignationsMap()[$department] ?? [];
        $isProjectOwner = (int) Auth::guard('superadmin')->id() === 1;

        if ($employeeUuid) {
            $employee = \App\Models\Employee::where('uuid', $employeeUuid)->first();
            $memberId = $employee?->member_id;
        }

        return [
            // Member fields (authentication)
            'full_name' => ['required', 'string', 'max:255', 'regex:/^[\pL\pM]+(?:[ .\'-][\pL\pM]+)*$/u'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('members')->ignore($memberId)->whereNull('deleted_at'),
            ],
            'phone' => [
                'required',
                'string',
                'max:12',
                'regex:/^[0-9]{1,12}$/',
                Rule::unique('members')->ignore($memberId)->whereNull('deleted_at'),
            ],
            'password' => [$memberId ? 'nullable' : 'required', 'required_with:confirm_password', 'string', 'min:6', 'same:confirm_password'],
            'confirm_password' => [$memberId ? 'nullable' : 'required', 'required_with:password', 'string', 'min:6'],
            'roles' => ['nullable', 'array'],
            'roles.*' => [
                'string',
                Rule::exists('construction_roles', 'slug')
                    ->where('status', 'active')
                    ->whereNull('deleted_at')
                    ->where('slug', '!=', 'super_admin'),
            ],
            'role' => ['required', Rule::in(['member'])],
            'company_id' => [
                $isProjectOwner ? 'required' : 'nullable',
                'integer',
                Rule::exists('construction_companies', 'id')
                    ->where('status', 'active')
                    ->whereNull('deleted_at'),
            ],
            'department' => ['required', 'string', Rule::in(EmployeeController::getDepartmentOptions())],
            'designation' => ['required', 'string', Rule::in($designationOptions)],
            'gender' => ['nullable', 'in:male,female,other'],
            'dob' => ['nullable', 'date', 'before:today'],
            'status' => ['nullable'],

            // Employee-specific fields
            'profile_photo' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:2048'],
            'alternate_number' => ['nullable', 'string', 'max:12', 'regex:/^[0-9]{1,12}$/'],
            'aadhaar_number' => [
                'nullable',
                'string',
                'regex:/^[2-9]\d{11}$/',
                Rule::unique('employees')->ignore($employee?->id),
            ],
            'pan_number' => [
                'nullable',
                'string',
                'regex:/^[A-Z]{5}[0-9]{4}[A-Z]$/',
                Rule::unique('employees')->ignore($employee?->id),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.unique' => 'This phone number is already in use.',
            'phone.max' => 'Phone number cannot exceed 12 digits.',
            'phone.regex' => 'Phone number must contain only digits (max 12).',
            'alternate_number.max' => 'Alternate number cannot exceed 12 digits.',
            'alternate_number.regex' => 'Alternate number must contain only digits (max 12).',
            'email.unique' => 'This email address is already in use.',
            'email.required' => 'Email address is required for login.',
            'full_name.regex' => 'Enter a valid name using letters, spaces, apostrophes, periods, or hyphens.',
            'role.required' => 'Please select a role.',
            'role.in' => 'The selected role is invalid.',
            'department.required' => 'Please select a department.',
            'department.in' => 'Please select a valid department.',
            'designation.required' => 'Please select a designation.',
            'designation.in' => 'Please select a designation that belongs to the selected department.',
            'alternate_number.regex' => 'Enter a valid 10-digit Indian mobile number starting with 6, 7, 8, or 9.',
            'aadhaar_number.unique' => 'This Aadhaar number is already in use.',
            'aadhaar_number.regex' => 'Enter a valid 12-digit Aadhaar number.',
            'pan_number.unique' => 'This PAN number is already in use.',
            'pan_number.regex' => 'Enter a valid PAN number (for example, ABCDE1234F).',
            'roles.*.exists' => 'Select only active employee roles.',
        ];
    }
}