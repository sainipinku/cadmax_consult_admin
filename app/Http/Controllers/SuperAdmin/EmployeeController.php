<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEmployeeRequest;
use App\Models\Construction\MemberRoleAssignment;
use App\Models\Construction\Role as ConstructionRole;
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

    public function index(Request $request)
    {
        $employees = Employee::query()
            ->with(['member' => function ($q) {
                $q->select('id', 'name', 'email', 'phone', 'roles', 'departments', 'designation', 'gender', 'dob', 'status', 'image', 'created_by');
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
            ->when($request->status, function ($q) use ($request) {
                $statusValue = $request->status == 'active' ? 1 : 0;
                $q->whereHas('member', fn($mq) => $mq->where('status', $statusValue));
            })
            ->latest()
            ->paginate($request->per_page ?? 10);

        // Transform employee data to include member fields
        $employees->getCollection()->transform(function ($employee) {
            $member = $employee->member;
            if ($member) {
                $departmentVal = is_array($member->departments) ? ($member->departments[0] ?? null) : $member->departments;
                $designationVal = is_array($member->designation) ? ($member->designation[0] ?? null) : $member->designation;
                $member->single_department = $departmentVal;
                $member->single_designation = $designationVal;
                $member->role_name = is_array($member->roles) && count($member->roles) > 0
                    ? ((int)$member->roles[0] === 1 ? 'Admin' : 'Member')
                    : '-';
                $member->role_id = is_array($member->roles) && count($member->roles) > 0 ? (int)$member->roles[0] : null;
                
                // Add role_slug for frontend compatibility
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
            'roleOptions' => static::getRoleOptions(),
            'filters' => $request->only(['search', 'department', 'designation', 'status', 'per_page']),
        ]);
    }

    public function store(StoreEmployeeRequest $request)
    {
        try {
            $validated = $request->validated();

            DB::transaction(function () use ($validated, $request) {
                // Convert role slug to ID for Member compatibility
                $role = \App\Models\Role::where('slug', $validated['role'])->where('status', 1)->firstOrFail();
                $roleArray = [$role->id];

                // Store department and designation as single-element arrays for Member compatibility
                $departmentArray = [$validated['department']];
                $designationArray = [$validated['designation']];

                // Step 1: Create Member (authentication account)
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

                // Step 2: Create Employee (employee-specific data)
                $employeeData = [
                    'member_id' => $member->id,
                    'alternate_number' => $validated['alternate_number'] ?? null,
                    'aadhaar_number' => $validated['aadhaar_number'] ?? null,
                    'pan_number' => $validated['pan_number'] ?? null,
                ];

                Employee::create($employeeData);

                // Step 3: Assign site_employee construction role (grants dashboard.view permission)
                $siteEmployeeRole = ConstructionRole::where('slug', 'site_employee')->first();
                if ($siteEmployeeRole) {
                    MemberRoleAssignment::firstOrCreate([
                        'member_id' => $member->id,
                        'role_id' => $siteEmployeeRole->id,
                    ]);
                }
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
                // Convert role slug to ID for Member compatibility
                $role = \App\Models\Role::where('slug', $validated['role'])->where('status', 1)->firstOrFail();
                $roleArray = [$role->id];
                $departmentArray = [$validated['department']];
                $designationArray = [$validated['designation']];

                // Step 1: Update Member (authentication account)
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

                // Step 2: Update Employee-specific data
                $employeeData = [
                    'alternate_number' => $validated['alternate_number'] ?? null,
                    'aadhaar_number' => $validated['aadhaar_number'] ?? null,
                    'pan_number' => $validated['pan_number'] ?? null,
                ];

                $employee->update($employeeData);
            });

            return redirect()->back()->with('success', 'Employee updated successfully!');
        } catch (\Exception $e) {
            Log::error('Employee update failed', ['error' => $e->getMessage()]);
            return redirect()->back()->with('error', 'Failed to update employee: ' . $e->getMessage());
        }
    }

    public function updateStatus(Request $request, $uuid)
    {
        try {
            $employee = Employee::where('uuid', $uuid)->firstOrFail();
            $status = $request->status ?? 1;

            if (!in_array((int)$status, [0, 1])) {
                return redirect()->back()->with('error', 'Invalid status value.');
            }

            $employee->member->update(['status' => $status]);

            return redirect()->back()->with('success', 'Employee status updated successfully!');
        } catch (\Exception $e) {
            Log::error('Employee status update failed', ['error' => $e->getMessage()]);
            return redirect()->back()->with('error', 'Failed to update employee status.');
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