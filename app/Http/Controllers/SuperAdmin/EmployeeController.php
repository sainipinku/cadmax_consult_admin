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
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\SuperAdmin;
use App\Models\Company;
use App\Http\Controllers\Concerns\IntersectsAdminDeletion;
use Inertia\Inertia;

class EmployeeController extends Controller
{
    use IntersectsAdminDeletion;

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
            ['id' => 1, 'name' => 'Member'],
        ];
    }

    public static function getMemberRoleOptions(mixed $currentUser = null): array
    {
        try {
            if (!$currentUser) {
                $currentUser = Auth::guard('superadmin')->user()
                    ?? Auth::guard('admin')->user()
                    ?? Auth::guard('member')->user()
                    ?? Auth::user();
            }

            $roles = Role::accessibleFor($currentUser)
                ->where('status', 1)
                ->whereNotIn('slug', ['super_admin', 'superadmin', 'admin', 'project_admin', 'project-admin', 'member'])
                ->orderBy('name')
                ->get(['id', 'slug', 'name']);

            if ($roles->isNotEmpty()) {
                return $roles->filter(fn($r) => !in_array(strtolower(trim($r->name)), ['super admin', 'admin', 'project admin'], true))
                    ->map(fn($r) => [
                        'id' => (int) $r->id,
                        'slug' => $r->slug,
                        'name' => match ($r->slug) {
                            'surveyor' => 'Survey Man',
                            'vehicle_driver' => 'Driver',
                            'draft_person' => 'Draft Man',
                            default => $r->name,
                        },
                    ])->values()->toArray();
            }
        } catch (\Throwable $e) {
            // fallback if table query fails
        }

        return [
            ['id' => 4, 'slug' => 'surveyor', 'name' => 'Survey Man'],
            ['id' => 6, 'slug' => 'vehicle_driver', 'name' => 'Driver'],
            ['id' => 5, 'slug' => 'draft_person', 'name' => 'Draft Man'],
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
        $currentUser = Auth::guard('superadmin')->user()
            ?? Auth::guard('admin')->user()
            ?? Auth::guard('member')->user()
            ?? Auth::guard('callingteam')->user()
            ?? Auth::user();

        $isProjectOwner = false;
        if (Auth::guard('superadmin')->check() && $currentUser instanceof SuperAdmin) {
            $isProjectOwner = ((int) $currentUser->id === 1 || (string) $currentUser->id === '1' || (isset($currentUser->is_project_owner) && (int) $currentUser->is_project_owner === 1));
        }

        $superAdminsMap = SuperAdmin::with('company')->get()->keyBy('id');
        $allMembersMap = Member::get(['id', 'name', 'created_by', 'company_name', 'roles'])->keyBy('id');

        // Resolve current user's company ID
        $currentUserCompanyId = null;
        if ($currentUser instanceof SuperAdmin) {
            $currentUserCompanyId = $currentUser->company_id;
        } elseif ($currentUser instanceof Member) {
            $current = $currentUser;
            $visited = [];
            while ($current && $current->created_by && !in_array($current->created_by, $visited)) {
                $visited[] = $current->created_by;
                $sa = $superAdminsMap->get($current->created_by);
                if ($sa && $sa->company_id) {
                    $currentUserCompanyId = $sa->company_id;
                    break;
                }
                $current = $allMembersMap->get($current->created_by);
            }
        }

        $employeesQuery = Employee::query()
            ->with(['member' => function ($q) {
                $q->select('id', 'name', 'email', 'phone', 'roles', 'departments', 'designation', 'gender', 'dob', 'status', 'image', 'created_by', 'company_name', 'approved_by', 'approved_at', 'rejected_at', 'approval_remark');
            }]);

        // Filter by company if not Main Project Owner 1
        if (!$isProjectOwner && $currentUserCompanyId) {
            $companySuperAdminIds = $superAdminsMap
                ->filter(fn($sa) => (int) $sa->company_id === (int) $currentUserCompanyId)
                ->keys()
                ->toArray();

            $employeesQuery->whereHas('member', function ($mq) use ($companySuperAdminIds, $currentUser) {
                $mq->where(function ($sq) use ($companySuperAdminIds, $currentUser) {
                    if (!empty($companySuperAdminIds)) {
                        $sq->whereIn('created_by', $companySuperAdminIds);
                    }
                    $sq->orWhere('created_by', $currentUser->id);

                    if ($currentUser instanceof SuperAdmin && $currentUser->company) {
                        $sq->orWhere('company_name', $currentUser->company->name);
                    }
                });
            });
        }

        $employees = $employeesQuery
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

        // Transform employee data to include member fields, company, and added_by
        $employees->getCollection()->transform(function ($employee) use ($superAdminsMap, $allMembersMap) {
            $member = $employee->member;
            if ($member) {
                $departmentVal = is_array($member->departments) ? ($member->departments[0] ?? null) : $member->departments;
                $designationVal = is_array($member->designation) ? ($member->designation[0] ?? null) : $member->designation;
                $member->single_department = $departmentVal;
                $member->single_designation = $designationVal;

                // Resolve Added By (Creator)
                $creatorUser = null;
                if ($member->created_by) {
                    $saCreator = $superAdminsMap->get($member->created_by);
                    if ($saCreator) {
                        $creatorUser = [
                            'name' => $saCreator->name,
                            'role' => 'Super Admin',
                        ];
                    } else {
                        $memCreator = $allMembersMap->get($member->created_by);
                        if ($memCreator) {
                            $roleName = is_array($memCreator->roles) && count($memCreator->roles) > 0 ? (Role::find($memCreator->roles[0])?->name ?? 'Admin') : 'Admin';
                            $creatorUser = [
                                'name' => $memCreator->name,
                                'role' => $roleName,
                            ];
                        }
                    }
                }
                $member->created_by_user = $creatorUser;

                // Resolve Company
                $employeeCompany = null;
                $current = $member;
                $visited = [];
                while ($current && $current->created_by && !in_array($current->created_by, $visited)) {
                    $visited[] = $current->created_by;
                    $sa = $superAdminsMap->get($current->created_by);
                    if ($sa && $sa->company) {
                        $employeeCompany = $sa->company;
                        break;
                    }
                    $current = $allMembersMap->get($current->created_by);
                }

                if (!empty($member->company_name)) {
                    $member->company = (object) ['name' => $member->company_name];
                } elseif ($employeeCompany) {
                    $member->company = $employeeCompany;
                } else {
                    $member->company = null;
                }

                // Load active construction sub-role assignments
                $assignments = MemberRoleAssignment::where('member_id', $member->id)
                    ->where('status', 1)
                    ->with('role')
                    ->get();

                $assignedSlugs = [];
                $assignedNames = [];

                $excludedSlugs = ['super_admin', 'superadmin', 'admin', 'project_admin', 'project-admin'];
                $excludedNames = ['super admin', 'admin', 'project admin'];

                foreach ($assignments as $assignment) {
                    if ($assignment->role) {
                        $slug = strtolower(trim($assignment->role->slug ?? ''));
                        $name = trim($assignment->role->name ?? '');

                        if (in_array($slug, $excludedSlugs, true) || in_array(strtolower($name), $excludedNames, true)) {
                            continue;
                        }

                        $formattedName = match ($assignment->role->slug) {
                            'surveyor' => 'Survey Man',
                            'vehicle_driver' => 'Driver',
                            'draft_person' => 'Draft Man',
                            default => $assignment->role->name,
                        };

                        $assignedSlugs[] = $assignment->role->slug;
                        $assignedNames[] = $formattedName;
                    }
                }

                $member->assigned_roles = array_values(array_unique($assignedSlugs));
                $member->assigned_role_names = array_values(array_unique($assignedNames));

                if (!empty($assignedNames)) {
                    $member->role_name = implode(', ', $assignedNames);
                } else {
                    $member->role_name = 'Member';
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
            'memberRoleOptions' => static::getMemberRoleOptions($currentUser),
            'isProjectOwner' => $isProjectOwner,
            'companies' => $isProjectOwner
                ? Company::where('status', 'active')->orderBy('name')->get(['id', 'name'])
                : [],
            'filters' => $request->only(['search', 'department', 'designation', 'status', 'per_page']),
        ]);
    }

    public function store(StoreEmployeeRequest $request)
    {
        try {
            $validated = $request->validated();

            $currentUser = Auth::guard('superadmin')->user()
                ?? Auth::guard('admin')->user()
                ?? Auth::guard('member')->user()
                ?? Auth::user();

            $creatorId = $currentUser ? $currentUser->id : 1;
            $creatorCompanyName = null;
            if ($currentUser) {
                if ($currentUser instanceof SuperAdmin) {
                    $creatorCompanyName = $currentUser->company?->name;
                } elseif ($currentUser instanceof Member) {
                    $creatorCompanyName = $currentUser->company_name;
                }
            }
            if ($currentUser instanceof SuperAdmin && (int) $currentUser->id === 1) {
                $creatorCompanyName = Company::findOrFail($validated['company_id'])->name;
            }

            DB::transaction(function () use ($validated, $request, $creatorId, $creatorCompanyName) {
                $roleSlug = $request->input('role', 'member') ?: 'member';
                $role = \App\Models\Role::where('slug', $roleSlug)->where('status', 1)->first();
                if (!$role) {
                    $role = \App\Models\Role::firstOrCreate(
                        ['slug' => 'member'],
                        ['name' => 'Member', 'status' => 1, 'created_by' => $creatorId]
                    );
                }
                $roleArray = $role ? [$role->id] : [];

                $departmentArray = [$validated['department']];
                $designationArray = [$validated['designation']];

                $selectedRoles = $request->input('roles', []);
                if (is_string($selectedRoles)) {
                    $selectedRoles = json_decode($selectedRoles, true) ?? [$selectedRoles];
                }
                $selectedRoles = (array) $selectedRoles;

                // Normalize selected role slugs
                $normalizedRoles = [];
                foreach ($selectedRoles as $r) {
                    $rLower = strtolower(trim((string) $r));
                    if ($rLower === 'driver' || $rLower === 'vehicle driver' || $rLower === 'vehicle_driver' || $rLower === '6') {
                        $normalizedRoles[] = 'vehicle_driver';
                    } elseif ($rLower === 'surveyor' || $rLower === 'survey man' || $rLower === '4') {
                        $normalizedRoles[] = 'surveyor';
                    } elseif (in_array($rLower, ['draftsman', 'draft person', 'draft_person', 'draft man', '5'], true)) {
                        $normalizedRoles[] = 'draft_person';
                    } else {
                        $normalizedRoles[] = $r;
                    }
                }
                $selectedRoles = array_values(array_unique(array_filter($normalizedRoles)));

                // Auto-resolve role from designation or single role if empty
                if (empty($selectedRoles)) {
                    $desig = strtolower(trim($validated['designation'] ?? ''));
                    $singleRole = strtolower(trim($request->input('role', '') ?: ''));
                    if (str_contains($desig, 'driver') || str_contains($singleRole, 'driver') || str_contains($singleRole, 'vehicle_driver')) {
                        $selectedRoles = ['vehicle_driver'];
                    } elseif (str_contains($desig, 'surveyor') || str_contains($singleRole, 'surveyor')) {
                        $selectedRoles = ['surveyor'];
                    } elseif (str_contains($desig, 'draft') || str_contains($singleRole, 'draft')) {
                        $selectedRoles = ['draft_person'];
                    }
                }

                // Resolve initial Role IDs for member record
                $initialRoleIds = [];
                if (!empty($selectedRoles)) {
                    $initialRoleIds = \App\Models\Role::where(function ($q) use ($selectedRoles) {
                        $q->whereIn('slug', $selectedRoles)
                            ->orWhereIn('id', array_filter($selectedRoles, 'is_numeric'));
                    })->pluck('id')->map(fn($id) => (int)$id)->toArray();
                }

                if (in_array('vehicle_driver', $selectedRoles, true) && !in_array(6, $initialRoleIds, true)) {
                    $initialRoleIds[] = 6;
                }

                if (empty($initialRoleIds)) {
                    $initialRoleIds = [1];
                }

                $memberData = [
                    'name' => $validated['full_name'],
                    'email' => $validated['email'],
                    'phone' => $validated['phone'],
                    'password' => $validated['password'] ?? null,
                    'roles' => $initialRoleIds,
                    'departments' => $departmentArray,
                    'designations' => $designationArray,
                    'gender' => $validated['gender'] ?? null,
                    'dob' => $validated['dob'] ?? null,
                    'status' => $request->has('status') && $request->input('status') !== '' ? (int) $request->input('status') : 1,
                    'is_calling_team' => false,
                    'created_by' => $creatorId,
                    'company_name' => $creatorCompanyName,
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

                $this->syncMemberRoles($member->id, $selectedRoles);
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

                $selectedRoles = $request->input('roles', []);
                if (is_string($selectedRoles)) {
                    $selectedRoles = json_decode($selectedRoles, true) ?? [$selectedRoles];
                }
                $selectedRoles = (array) $selectedRoles;

                // Normalize selected role slugs
                $normalizedRoles = [];
                foreach ($selectedRoles as $r) {
                    $rLower = strtolower(trim((string) $r));
                    if ($rLower === 'driver' || $rLower === 'vehicle driver' || $rLower === 'vehicle_driver' || $rLower === '6') {
                        $normalizedRoles[] = 'vehicle_driver';
                    } elseif ($rLower === 'surveyor' || $rLower === 'survey man' || $rLower === '4') {
                        $normalizedRoles[] = 'surveyor';
                    } elseif (in_array($rLower, ['draftsman', 'draft person', 'draft_person', 'draft man', '5'], true)) {
                        $normalizedRoles[] = 'draft_person';
                    } else {
                        $normalizedRoles[] = $r;
                    }
                }
                $selectedRoles = array_values(array_unique(array_filter($normalizedRoles)));

                // Auto-resolve role from designation if empty
                if (empty($selectedRoles)) {
                    $desig = strtolower(trim($validated['designation'] ?? ''));
                    $singleRole = strtolower(trim($request->input('role', '') ?: ''));
                    if (str_contains($desig, 'driver') || str_contains($singleRole, 'driver') || str_contains($singleRole, 'vehicle_driver')) {
                        $selectedRoles = ['vehicle_driver'];
                    } elseif (str_contains($desig, 'surveyor') || str_contains($singleRole, 'surveyor')) {
                        $selectedRoles = ['surveyor'];
                    } elseif (str_contains($desig, 'draft') || str_contains($singleRole, 'draft')) {
                        $selectedRoles = ['draft_person'];
                    }
                }

                // Resolve Role IDs for member record
                $initialRoleIds = [];
                if (!empty($selectedRoles)) {
                    $initialRoleIds = \App\Models\Role::where(function ($q) use ($selectedRoles) {
                        $q->whereIn('slug', $selectedRoles)
                            ->orWhereIn('id', array_filter($selectedRoles, 'is_numeric'));
                    })->pluck('id')->map(fn($id) => (int)$id)->toArray();
                }

                if (in_array('vehicle_driver', $selectedRoles, true) && !in_array(6, $initialRoleIds, true)) {
                    $initialRoleIds[] = 6;
                }

                if (empty($initialRoleIds)) {
                    $initialRoleIds = [1];
                }

                $memberData = [
                    'name' => $validated['full_name'],
                    'email' => $validated['email'],
                    'phone' => $validated['phone'],
                    'password' => $validated['password'] ?? null,
                    'roles' => $initialRoleIds,
                    'departments' => $departmentArray,
                    'designations' => $designationArray,
                    'gender' => $validated['gender'] ?? null,
                    'dob' => $validated['dob'] ?? null,
                    'is_calling_team' => false,
                ];
                if ((int) Auth::guard('superadmin')->id() === 1) {
                    $memberData['company_name'] = Company::findOrFail($validated['company_id'])->name;
                }

                if ($request->has('status') && $request->input('status') !== '') {
                    $memberData['status'] = (int) $request->input('status');
                }

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

                $this->syncMemberRoles($employee->member_id, $selectedRoles);
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

    public function updatePassword(Request $request, $uuid)
    {
        try {
            $employee = Employee::where('uuid', $uuid)->firstOrFail();
            $member = $employee->member;
            if (!$member) {
                return redirect()->back()->with('error', 'Member account not found.');
            }

            $request->validate([
                'password' => 'required|string|min:6|confirmed',
            ]);

            $member->update([
                'password' => \Illuminate\Support\Facades\Hash::make($request->password),
            ]);

            return redirect()->back()->with('success', 'Employee password updated successfully!');
        } catch (\Exception $e) {
            Log::error('Employee password update failed', ['error' => $e->getMessage()]);
            return redirect()->back()->with('error', 'Failed to update employee password: ' . $e->getMessage());
        }
    }

    public function syncMemberRoles(int $memberId, array $selectedRoleSlugs): void
    {
        $member = Member::find($memberId);

        // Normalize slugs
        $normalizedSlugs = [];
        foreach ($selectedRoleSlugs as $slug) {
            $lower = strtolower(trim((string) $slug));
            if ($lower === 'driver' || $lower === 'vehicle driver' || $lower === 'vehicle_driver' || $lower === '6') {
                $normalizedSlugs[] = 'vehicle_driver';
            } elseif ($lower === 'surveyor' || $lower === 'survey man' || $lower === '4') {
                $normalizedSlugs[] = 'surveyor';
            } elseif (in_array($lower, ['draftsman', 'draft person', 'draft_person', 'draft man', '5'], true)) {
                $normalizedSlugs[] = 'draft_person';
            } else {
                $normalizedSlugs[] = $slug;
            }
        }
        $selectedRoleSlugs = array_values(array_unique(array_filter($normalizedSlugs)));

        // If empty, auto-resolve from member designation
        if (empty($selectedRoleSlugs) && $member) {
            $desig = is_array($member->designation) ? ($member->designation[0] ?? '') : (string) $member->designation;
            $desigLower = strtolower(trim($desig));
            if (str_contains($desigLower, 'driver')) {
                $selectedRoleSlugs = ['vehicle_driver'];
            } elseif (str_contains($desigLower, 'surveyor')) {
                $selectedRoleSlugs = ['surveyor'];
            } elseif (str_contains($desigLower, 'draft')) {
                $selectedRoleSlugs = ['draft_person'];
            }
        }

        $allRoles = ConstructionRole::where('status', 'active')
            ->whereNotIn('slug', ['super_admin', 'superadmin', 'admin', 'project_admin', 'project-admin'])
            ->get();

        $activeRoleIds = [];

        foreach ($allRoles as $role) {
            if (in_array(strtolower(trim($role->name)), ['super admin', 'admin', 'project admin'], true)) {
                continue;
            }
            $isSelected = in_array($role->slug, $selectedRoleSlugs, true)
                || in_array(strtolower($role->name), array_map('strtolower', $selectedRoleSlugs), true)
                || in_array((string) $role->id, $selectedRoleSlugs, true);

            if ($isSelected) {
                MemberRoleAssignment::updateOrCreate(
                    ['member_id' => $memberId, 'role_id' => $role->id],
                    ['status' => 1]
                );

                $dbRole = Role::where('slug', $role->slug)->first();
                if ($dbRole) {
                    $activeRoleIds[] = (int) $dbRole->id;
                }
            } else {
                MemberRoleAssignment::where('member_id', $memberId)
                    ->where('role_id', $role->id)
                    ->update(['status' => 0]);
            }
        }

        // Also check any roles directly from roles table (custom roles created by super admin)
        foreach ($selectedRoleSlugs as $slugOrId) {
            $customRole = Role::where('slug', $slugOrId)->orWhere('id', $slugOrId)->first();
            if ($customRole && !in_array($customRole->slug, ['super_admin', 'superadmin', 'admin', 'project_admin', 'member'], true)) {
                $activeRoleIds[] = (int) $customRole->id;

                $cRole = ConstructionRole::firstOrCreate(
                    ['slug' => $customRole->slug],
                    ['name' => $customRole->name, 'status' => 'active', 'is_system_role' => false]
                );
                MemberRoleAssignment::updateOrCreate(
                    ['member_id' => $memberId, 'role_id' => $cRole->id],
                    ['status' => 1]
                );
            }
        }

        // If vehicle_driver was selected, ensure role ID 6 is active
        if (in_array('vehicle_driver', $selectedRoleSlugs, true)) {
            $activeRoleIds[] = 6;
        }

        $activeRoleIds = array_values(array_unique(array_filter($activeRoleIds)));
        if (empty($activeRoleIds)) {
            $activeRoleIds = [1];
        }

       if ($member) {
            $member->forceFill(['roles' => $activeRoleIds])->save();
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
            $employee = Employee::with('member')->where('uuid', $uuid)->firstOrFail();

            if ($this->requiresSuperAdminApproval()) {
                return $this->requestDeleteApproval(
                    'Employee',
                    $employee->id,
                    $employee->member ? $employee->member->name : "Employee #{$employee->employee_id}"
                );
            }

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
