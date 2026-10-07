<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEmployeeRequest;
use App\Models\MemberRoleAssignment;
use App\Models\ConstructionRole as ConstructionRole;
use App\Models\Employee;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Member;
use App\Models\Role;
use App\Services\MemberService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class EmployeeController extends Controller
{
    protected $memberService;

    public function __construct(MemberService $memberService)
    {
        $this->memberService = $memberService;
    }

    public static function getDepartmentOptions(): array
    {
        return [
            'Administration',
            'Architecture',
            'Planning',
            'Engineering',
            'Survey',
            'Drafting',
            'Accounts',
            'HR',
            'GIS & Mapping',
            'Data Collection',
            'Development',
            'Project Management',
            'Operations',
        ];
    }

    public static function getDesignationOptions(): array
    {
        return [
            'CEO',
            'Director',
            'General Manager',
            'Senior Consultant',
            'Project Manager',
            'Team Leader',
            'Manager (Planning)',
            'Manager (Survey)',
            'Manager (Engineering)',
            'Manager (Admin)',
            'Manager (Accounts)',
            'Manager (Data Collection)',
            'Manager (Drawings)',
            'Architect',
            'Senior Architect',
            'Civil Engineer',
            'Junior Civil Engineer',
            'Site Engineer',
            'Planning Engineer',
            'CAD Engineer',
            'AutoCAD Designer',
            'Draftsman',
            'Senior Draftsman',
            'Junior Draftsman',
            'Surveyor',
            'Senior Surveyor',
            'Assistant Surveyor',
            'GIS Engineer',
            'GIS Analyst',
            'Quantity Surveyor',
            'HR Manager',
            'HR Executive',
            'Accountant',
            'Senior Accountant',
            'Admin Executive',
            'Receptionist',
            'Office Assistant',
            'Assistant',
            'Supervisor',
            'Site Supervisor',
            'Driver',
            'Office Boy',
            'Store Keeper',
        ];
    }

    public static function getRoleOptions(): array
    {
        return [
            ['id' => 3, 'name' => 'Member'],
        ];
    }

    public static function getMemberRoleOptions(): array
    {
        return [
            ['slug' => 'surveyor', 'name' => 'Survey Man'],
            ['slug' => 'vehicle_driver', 'name' => 'Driver'],
            ['slug' => 'draft_person', 'name' => 'Draft Man'],
        ];
    }

    public static function getDepartmentDesignationsMap(): array
    {
        $staticMap = [
            'Administration' => [
                'CEO', 'Director', 'General Manager', 'Manager (Admin)',
                'Admin Executive', 'Receptionist', 'Office Assistant', 'Assistant', 'Office Boy'
            ],
            'Architecture' => [
                'Architect', 'Senior Architect', 'AutoCAD Designer'
            ],
            'Planning' => [
                'Manager (Planning)', 'Planning Engineer'
            ],
            'Engineering' => [
                'Manager (Engineering)', 'Civil Engineer', 'Junior Civil Engineer',
                'Site Engineer', 'CAD Engineer', 'Supervisor', 'Site Supervisor'
            ],
            'Survey' => [
                'Manager (Survey)', 'Surveyor', 'Senior Surveyor', 'Assistant Surveyor', 'Quantity Surveyor'
            ],
            'Drafting' => [
                'Manager (Drawings)', 'Draftsman', 'Senior Draftsman', 'Junior Draftsman'
            ],
            'Accounts' => [
                'Manager (Accounts)', 'Accountant', 'Senior Accountant'
            ],
            'HR' => [
                'HR Manager', 'HR Executive'
            ],
            'GIS & Mapping' => [
                'GIS Engineer', 'GIS Analyst'
            ],
            'Data Collection' => [
                'Manager (Data Collection)'
            ],
            'Development' => [
                'Senior Consultant', 'Team Leader', 'Project Manager'
            ],
            'Project Management' => [
                'Project Manager', 'Team Leader'
            ],
            'Operations' => [
                'Store Keeper', 'Driver'
            ]
        ];

        try {
            $departments = Department::where('status', 1)
                ->with(['designationList' => fn($q) => $q->where('status', 1)])
                ->get();

            foreach ($departments as $dept) {
                $deptName = $dept->name;
                $desigNames = $dept->designationList->pluck('name')->toArray();
                if (!empty($desigNames)) {
                    if (!isset($staticMap[$deptName])) {
                        $staticMap[$deptName] = $desigNames;
                    } else {
                        $staticMap[$deptName] = array_values(array_unique(array_merge($staticMap[$deptName], $desigNames)));
                    }
                }
            }
        } catch (\Exception $e) {
            // Ignore DB errors and use static map
        }

        return $staticMap;
    }

    public function index(Request $request)
    {
        $employees = Employee::query()
            ->with(['member' => function ($q) {
                $q->select('id', 'name', 'email', 'phone', 'roles', 'departments', 'designation', 'gender', 'dob', 'status', 'image', 'created_by', 'approved_by', 'approved_at', 'rejected_at', 'approval_remark');
            }])
            ->when($request->search, fn($q) => $q->where(function ($query) use ($request) {
                $query->where('employee_id', 'like', "%{$request->search}%")
                    ->orWhere('alternate_number', 'like', "%{$request->search}%")
                    ->orWhereHas('member', function ($mq) use ($request) {
                        $mq->where('name', 'like', "%{$request->search}%")
                            ->orWhere('email', 'like', "%{$request->search}%")
                            ->orWhere('phone', 'like', "%{$request->search}%");
                    });
            }))
            ->when($request->department, fn($q) => $q->whereHas('member', fn($mq) => $mq->whereJsonContains('departments', $request->department)))
            ->when($request->designation, fn($q) => $q->whereHas('member', fn($mq) => $mq->whereJsonContains('designation', $request->designation)))
            ->when($request->filled('status'), function ($q) use ($request) {
                $val = strtolower($request->status);
                if ($val === 'active' || $val === '1') {
                    $q->whereHas('member', fn($mq) => $mq->where('status', Member::STATUS_ACTIVE));
                } elseif ($val === 'pending' || $val === '0') {
                    $q->whereHas('member', fn($mq) => $mq->where('status', Member::STATUS_PENDING));
                } elseif ($val === 'rejected' || $val === '2') {
                    $q->whereHas('member', fn($mq) => $mq->where('status', Member::STATUS_REJECTED));
                } elseif ($val === 'inactive') {
                    $q->whereHas('member', fn($mq) => $mq->whereIn('status', [Member::STATUS_PENDING, Member::STATUS_REJECTED]));
                }
            })
            ->latest('created_at')
            ->paginate($request->per_page ?? 10);

        // Transform employee data to include member fields
        $employees->getCollection()->transform(function ($employee) {
            $member = $employee->member;
            if ($member) {
                $departmentVal = is_array($member->departments) ? ($member->departments[0] ?? null) : $member->departments;
                $designationVal = is_array($member->designation) ? ($member->designation[0] ?? null) : $member->designation;
                $member->single_department = $departmentVal;
                $member->single_designation = $designationVal;

                // Load active construction sub-role assignments
                $assignments = MemberRoleAssignment::where('member_id', $member->id)
                    ->where('status', 1)
                    ->with('role')
                    ->get();

                $assignedSlugs = [];
                $assignedNames = [];

                foreach ($assignments as $assignment) {
                    if ($assignment->role) {
                        $slug = $assignment->role->slug;
                        if (in_array($slug, ['surveyor', 'vehicle_driver', 'draft_person'], true)) {
                            $name = match ($slug) {
                                'surveyor' => 'Survey Man',
                                'vehicle_driver' => 'Driver',
                                'draft_person' => 'Draft Man',
                            };
                            $assignedSlugs[] = $slug;
                            $assignedNames[] = $name;
                        }
                    }
                }

                $member->assigned_roles = array_values(array_unique($assignedSlugs));
                $member->assigned_role_names = array_values(array_unique($assignedNames));

                if (!empty($assignedNames)) {
                    $member->role_name = implode(', ', $assignedNames);
                } else {
                    $member->role_name = is_array($member->roles) && count($member->roles) > 0
                        ? (Role::find($member->roles[0])?->name ?? 'Member')
                        : 'Member';
                }

                $member->role_id = is_array($member->roles) && count($member->roles) > 0 ? (int)$member->roles[0] : null;

                if (is_array($member->roles) && count($member->roles) > 0) {
                    $role = Role::find($member->roles[0]);
                    $member->role_slug = $role?->slug ?? '';
                } else {
                    $member->role_slug = '';
                }
            }
            return $employee;
        });

        return Inertia::render('SuperAdmin/Employees/List', [
            'employees' => $employees,
            'departmentOptions' => static::getDepartmentOptions(),
            'designationOptions' => static::getDesignationOptions(),
            'departmentDesignationMap' => static::getDepartmentDesignationsMap(),
            'roleOptions' => static::getRoleOptions(),
            'memberRoleOptions' => static::getMemberRoleOptions(),
            'filters' => $request->only(['search', 'department', 'designation', 'status', 'per_page']),
        ]);
    }

    public function store(StoreEmployeeRequest $request)
    {
        try {
            $validated = $request->validated();

            DB::transaction(function () use ($validated, $request) {
                $roleSlug = $request->input('role', 'member') ?: 'member';
                $role = \App\Models\Role::where('slug', $roleSlug)->where('status', 1)->first();
                if (!$role) {
                    $role = \App\Models\Role::firstOrCreate(
                        ['slug' => 'member'],
                        ['name' => 'Member', 'status' => 1, 'created_by' => auth('superadmin')->id()]
                    );
                }
                $roleArray = $role ? [$role->id] : [];

                $departmentArray = [$validated['department']];
                $designationArray = [$validated['designation']];

                $memberData = [
                    'name' => $validated['full_name'],
                    'email' => $validated['email'],
                    'phone' => $validated['phone'],
                    'password' => $validated['password'] ?? null,
                    'roles' => $roleArray,
                    'departments' => $departmentArray,
                    'designations' => $designationArray,
                    'gender' => $validated['gender'] ?? null,
                    'dob' => $validated['dob'] ?? null,
                    'is_calling_team' => false,
                    'created_by' => auth('superadmin')->id(),
                ];

                if ($request->hasFile('profile_photo')) {
                    $memberData['image'] = $request->file('profile_photo');
                }

                [$member, $plainPassword, $message] = $this->memberService->saveMember($memberData, null, $request);

                $employeeData = [
                    'member_id' => $member->id,
                    'alternate_number' => $validated['alternate_number'] ?? null,
                    'aadhaar_number' => $validated['aadhaar_number'] ?? null,
                    'pan_number' => $validated['pan_number'] ?? null,
                ];

                Employee::create($employeeData);

                $siteEmployeeRole = ConstructionRole::where('slug', 'site_employee')->first();
                if ($siteEmployeeRole) {
                    MemberRoleAssignment::firstOrCreate([
                        'member_id' => $member->id,
                        'role_id' => $siteEmployeeRole->id,
                    ]);
                }

                $selectedRoles = $request->input('roles', []);
                if (is_string($selectedRoles)) {
                    $selectedRoles = json_decode($selectedRoles, true) ?? [$selectedRoles];
                }
                $this->syncMemberRoles($member->id, (array)$selectedRoles);
            });

            return redirect()->back()->with('success', 'Employee created successfully! The employee can now login using the provided email and password.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()->withInput()->withErrors($e->errors())->with('error', 'Please fix the highlighted errors.');
        } catch (\Exception $e) {
            Log::error('Employee creation failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return redirect()->back()->with('error', 'Failed to create employee: ' . $e->getMessage());
        }
    }

    public function update(StoreEmployeeRequest $request, $uuid)
    {
        try {
            $employee = Employee::where('uuid', $uuid)->firstOrFail();
            $validated = $request->validated();

            DB::transaction(function () use ($validated, $request, $employee) {
                $roleSlug = $request->input('role', 'member') ?: 'member';
                $role = \App\Models\Role::where('slug', $roleSlug)->where('status', 1)->first();
                if (!$role) {
                    $role = \App\Models\Role::firstOrCreate(
                        ['slug' => 'member'],
                        ['name' => 'Member', 'status' => 1, 'created_by' => auth('superadmin')->id()]
                    );
                }
                $roleArray = $role ? [$role->id] : [];
                $departmentArray = [$validated['department']];
                $designationArray = [$validated['designation']];

                $memberData = [
                    'name' => $validated['full_name'],
                    'email' => $validated['email'],
                    'phone' => $validated['phone'],
                    'password' => $validated['password'] ?? null,
                    'roles' => $roleArray,
                    'departments' => $departmentArray,
                    'designations' => $designationArray,
                    'gender' => $validated['gender'] ?? null,
                    'dob' => $validated['dob'] ?? null,
                    'is_calling_team' => false,
                ];

                if ($request->hasFile('profile_photo')) {
                    $memberData['image'] = $request->file('profile_photo');
                }

                $this->memberService->saveMember($memberData, $employee->member_id, $request);

                $employeeData = [
                    'alternate_number' => $validated['alternate_number'] ?? null,
                    'aadhaar_number' => $validated['aadhaar_number'] ?? null,
                    'pan_number' => $validated['pan_number'] ?? null,
                ];

                $employee->update($employeeData);

                $selectedRoles = $request->input('roles', []);
                if (is_string($selectedRoles)) {
                    $selectedRoles = json_decode($selectedRoles, true) ?? [$selectedRoles];
                }
                $this->syncMemberRoles($employee->member_id, (array)$selectedRoles);
            });

            return redirect()->back()->with('success', 'Employee updated successfully!');
        } catch (\Exception $e) {
            Log::error('Employee update failed', ['error' => $e->getMessage()]);
            return redirect()->back()->with('error', 'Failed to update employee: ' . $e->getMessage());
        }
    }

    public function assignRole(Request $request, $uuid)
    {
        try {
            $employee = Employee::where('uuid', $uuid)->firstOrFail();
            $member = $employee->member;
            if (!$member) {
                return redirect()->back()->with('error', 'Member account not found.');
            }

            $selectedRoles = $request->input('roles', []);
            if (is_string($selectedRoles)) {
                $selectedRoles = json_decode($selectedRoles, true) ?? [$selectedRoles];
            }

            if ($request->hasFile('profile_photo')) {
                $memberData = ['image' => $request->file('profile_photo')];
                $this->memberService->saveMember($memberData, $member->id, $request);
            }

            $this->syncMemberRoles($member->id, (array)$selectedRoles);

            return redirect()->back()->with('success', 'Employee roles updated successfully!');
        } catch (\Exception $e) {
            Log::error('Assign role failed', ['error' => $e->getMessage()]);
            return redirect()->back()->with('error', 'Failed to update employee roles: ' . $e->getMessage());
        }
    }

    public function syncMemberRoles(int $memberId, array $selectedRoleSlugs): void
    {
        $subRoleSlugs = ['surveyor', 'vehicle_driver', 'draft_person'];

        foreach ($subRoleSlugs as $slug) {
            $name = match ($slug) {
                'surveyor' => 'Survey Man',
                'vehicle_driver' => 'Driver',
                'draft_person' => 'Draft Man',
            };

            $role = ConstructionRole::firstOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'is_system_role' => true, 'status' => 'active']
            );

            $isSelected = in_array($slug, $selectedRoleSlugs, true);

            if ($isSelected) {
                MemberRoleAssignment::updateOrCreate(
                    ['member_id' => $memberId, 'role_id' => $role->id],
                    ['status' => 1]
                );
            } else {
                MemberRoleAssignment::where('member_id', $memberId)
                    ->where('role_id', $role->id)
                    ->update(['status' => 0]);
            }
        }
    }

    public function updateStatus(Request $request, $uuid)
    {
        try {
            $employee = Employee::where('uuid', $uuid)->firstOrFail();
            $status = $request->status ?? 1;

            if (!in_array((int)$status, [0, 1, 2])) {
                return redirect()->back()->with('error', 'Invalid status value.');
            }

            $updateData = ['status' => (int)$status];

            if ((int)$status === Member::STATUS_ACTIVE) {
                $updateData['approved_by'] = auth('superadmin')->id() ?? auth()->id();
                $updateData['approved_at'] = now();
                $updateData['rejected_at'] = null;
            } elseif ((int)$status === Member::STATUS_REJECTED) {
                $updateData['approved_by'] = auth('superadmin')->id() ?? auth()->id();
                $updateData['rejected_at'] = now();
                if ($request->filled('approval_remark')) {
                    $updateData['approval_remark'] = $request->approval_remark;
                }
            }

            if ($employee->member) {
                $employee->member->update($updateData);
            }

            $statusMessage = match((int)$status) {
                Member::STATUS_ACTIVE => 'Employee approved and activated successfully!',
                Member::STATUS_REJECTED => 'Employee registration rejected successfully!',
                Member::STATUS_PENDING => 'Employee status changed to pending approval.',
                default => 'Employee status updated successfully!',
            };

            return redirect()->back()->with('success', $statusMessage);
        } catch (\Exception $e) {
            Log::error('Employee status update failed', ['error' => $e->getMessage()]);
            return redirect()->back()->with('error', 'Failed to update employee status: ' . $e->getMessage());
        }
    }

    public function destroy($uuid)
    {
        try {
            $employee = Employee::where('uuid', $uuid)->firstOrFail();

            DB::transaction(function () use ($employee) {
                $memberId = $employee->member_id;
                $employee->delete();
                Member::find($memberId)?->delete();
            });

            return redirect()->back()->with('success', 'Employee deleted successfully!');
        } catch (\Exception $e) {
            Log::error('Employee deletion failed', ['error' => $e->getMessage()]);
            return redirect()->back()->with('error', 'Failed to delete employee.');
        }
    }
}
