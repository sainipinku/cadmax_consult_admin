<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Http\Controllers\SuperAdmin\EmployeeController;

class StoreEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'full_name' => is_string($this->input('full_name')) ? trim($this->input('full_name')) : $this->input('full_name'),
            'email' => is_string($this->input('email')) ? trim($this->input('email')) : $this->input('email'),
            'phone' => is_string($this->input('phone')) ? trim($this->input('phone')) : $this->input('phone'),
            'alternate_number' => is_string($this->input('alternate_number')) ? trim($this->input('alternate_number')) : $this->input('alternate_number'),
            'aadhaar_number' => is_string($this->input('aadhaar_number')) ? trim($this->input('aadhaar_number')) : $this->input('aadhaar_number'),
            'pan_number' => is_string($this->input('pan_number')) ? strtoupper(trim($this->input('pan_number'))) : $this->input('pan_number'),
            'department' => is_string($this->input('department')) ? trim($this->input('department')) : $this->input('department'),
            'designation' => is_string($this->input('designation')) ? trim($this->input('designation')) : $this->input('designation'),
        ]);
    }

    public function rules(): array
    {
        $employeeUuid = $this->route('uuid');
        $employee = null;
        $memberId = null;
        $department = $this->input('department');
        $designationOptions = EmployeeController::getDepartmentDesignationsMap()[$department] ?? [];

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
                'regex:/^[6-9]\d{9}$/',
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
            'department' => ['required', 'string', Rule::in(EmployeeController::getDepartmentOptions())],
            'designation' => ['required', 'string', Rule::in($designationOptions)],
            'gender' => ['nullable', 'in:male,female,other'],
            'dob' => ['nullable', 'date', 'before:today'],
            'status' => ['nullable', 'boolean'],

            // Employee-specific fields
            'profile_photo' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:2048'],
            'alternate_number' => ['nullable', 'string', 'regex:/^[6-9]\d{9}$/'],
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
            'phone.regex' => 'Enter a valid 10-digit Indian mobile number starting with 6, 7, 8, or 9.',
            'password.same' => 'The password and confirmation do not match.',
            'confirm_password.required_with' => 'Confirm the password when changing it.',
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