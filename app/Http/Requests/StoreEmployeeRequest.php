<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $employeeUuid = $this->route('uuid');
        $employee = null;
        $memberId = null;

        if ($employeeUuid) {
            $employee = \App\Models\Employee::where('uuid', $employeeUuid)->first();
            $memberId = $employee?->member_id;
        }

        return [
            // Member fields (authentication)
            'full_name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('members')->ignore($memberId)->whereNull('deleted_at'),
            ],
            'phone' => [
                'required',
                'string',
                'max:20',
                Rule::unique('members')->ignore($memberId)->whereNull('deleted_at'),
            ],
            'password' => [$memberId ? 'nullable' : 'required', 'string', 'min:6', 'same:confirm_password'],
            'confirm_password' => [$memberId ? 'nullable' : 'required', 'string', 'min:6'],
            'role' => ['required', 'string', Rule::exists('roles', 'slug')->where('status', 1)],
            'department' => ['required', 'string', 'max:255'],
            'designation' => ['required', 'string', 'max:255'],
            'gender' => ['nullable', 'in:male,female,other'],
            'dob' => ['nullable', 'date', 'before:today'],
            'status' => ['nullable', 'boolean'],

            // Employee-specific fields
            'profile_photo' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:2048'],
            'alternate_number' => ['nullable', 'string', 'max:20'],
            'aadhaar_number' => [
                'nullable',
                'string',
                'max:12',
                Rule::unique('employees')->ignore($employee?->id)->whereNull('deleted_at'),
            ],
            'pan_number' => [
                'nullable',
                'string',
                'max:10',
                Rule::unique('employees')->ignore($employee?->id)->whereNull('deleted_at'),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.unique' => 'This phone number is already in use.',
            'email.unique' => 'This email address is already in use.',
            'email.required' => 'Email address is required for login.',
            'role.required' => 'Please select a role.',
            'role.in' => 'Role must be Member.',
            'department.required' => 'Please select a department.',
            'designation.required' => 'Please select a designation.',
            'aadhaar_number.unique' => 'This Aadhaar number is already in use.',
            'pan_number.unique' => 'This PAN number is already in use.',
        ];
    }
}