<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignTeamMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware
    }

    public function rules(): array
    {
        $project = $this->route('project');
        $projectId = $project instanceof \App\Models\Construction\Project
            ? $project->id
            : $project;

        $teamMember = $this->route('teamMember');
        $teamMemberId = $teamMember instanceof \App\Models\Construction\ProjectTeamMember
            ? $teamMember->id
            : $teamMember;

        return [
            'member_id' => [
                'required',
                'integer',
                'exists:members,id',
                Rule::unique('construction_project_team_members')
                    ->where('project_id', $projectId)
                    ->ignore($teamMemberId),
            ],
            'role_id' => [
                'nullable',
                'integer',
                'exists:construction_roles,id',
            ],
            'assigned_from' => [
                'nullable',
                'date',
            ],
            'assigned_to' => [
                'nullable',
                'date',
                'after_or_equal:assigned_from',
            ],
            'assignment_scope' => [
                'nullable',
                'string',
                'max:500',
            ],
            'is_primary' => [
                'boolean',
            ],
            'status' => [
                'nullable',
                Rule::in(['active', 'inactive']),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'member_id.required' => 'Please select a team member.',
            'member_id.exists' => 'The selected member does not exist.',
            'member_id.unique' => 'This member is already assigned to this project. Each member can only be assigned once per project.',
            'role_id.exists' => 'The selected role does not exist.',
            'assigned_to.after_or_equal' => 'The assignment end date must be after or equal to the start date.',
            'status.in' => 'The status must be either active or inactive.',
        ];
    }
}