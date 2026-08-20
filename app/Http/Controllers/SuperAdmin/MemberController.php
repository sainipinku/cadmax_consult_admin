<?php

namespace App\Http\Controllers\SuperAdmin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use App\Models\Department;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use App\Models\Member;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;
use App\Models\Role;
use App\Models\Designation;
use App\Models\SuperAdmin;
use App\Models\Task;
use App\Models\TaskInstance;
use Carbon\Carbon;
use App\Jobs\SendAccountCreationEmail;
use App\Models\EmailLog;
use App\Models\User;
use App\Models\SuperAdminPasswordLog;
use App\Jobs\SuperAdminPasswordChangeNotification;
use App\Models\WhatsappLog;
use Illuminate\Support\Facades\Log;
// use App\Services\InteraktServices;
// use App\Services\FirebaseService;
// use function App\createMessagePayload;

class MemberController extends Controller
{

    /**
     * Display a listing of the members with optional filters.
     *
     * @param Request $request The incoming request with optional query params: search, status, per_page.
     * @return Response Returns an Inertia response with paginated members.
     */
   public function index(Request $request)
{
    $statusMap = [
        'pending'  => Member::STATUS_PENDING,
        '0'        => Member::STATUS_PENDING,
        'active'   => Member::STATUS_ACTIVE,
        '1'        => Member::STATUS_ACTIVE,
        'rejected' => Member::STATUS_REJECTED,
        '2'        => Member::STATUS_REJECTED,
    ];
    $filterStatus = null;
    if ($request->filled('status') && isset($statusMap[$request->status])) {
        $filterStatus = $statusMap[$request->status];
    } elseif ($request->filled('status') && is_numeric($request->status) && in_array((int) $request->status, [0, 1, 2], true)) {
        $filterStatus = (int) $request->status;
    }

    $sourceMap = [
        'mobile' => 'mobile_api',
        'app'    => 'mobile_api',
        'web'    => 'web',
        'admin'  => 'admin_created',
    ];
    $filterSource = null;
    if ($request->filled('registration_source')) {
        $rs = strtolower($request->registration_source);
        if (isset($sourceMap[$rs])) {
            $filterSource = $sourceMap[$rs];
        } elseif (in_array($rs, ['mobile_api', 'web', 'admin_created'], true)) {
            $filterSource = $rs;
        }
    }

    $members = Member::query()
        ->with(['assignedAdmin:id,name', 'approver:id,name'])
        ->when(
            $request->search,
            fn($q) =>
            $q->where(function ($sub) use ($request) {
                $sub->where('name', 'like', "%{$request->search}%")
                    ->orWhere('email', 'like', "%{$request->search}%")
                    ->orWhere('phone', 'like', "%{$request->search}%")
                    ->orWhere('company_name', 'like', "%{$request->search}%")
                    ->orWhere('state', 'like', "%{$request->search}%")
                    ->orWhere('city', 'like', "%{$request->search}%");
            })
        )
        ->when(
            $filterStatus !== null,
            fn($q) => $q->where('status', $filterStatus)
        )
        ->when(
            $filterSource !== null,
            fn($q) => $q->where('registration_source', $filterSource)
        )
        ->when(
            $request->filled('has_role'),
            function ($q) use ($request) {
                $hasRole = filter_var($request->has_role, FILTER_VALIDATE_BOOLEAN);
                if ($hasRole) {
                    $q->whereRaw("JSON_LENGTH(roles) > 0");
                } else {
                    $q->where(function ($sq) {
                        $sq->whereNull('roles')
                            ->orWhere('roles', '[]')
                            ->orWhereRaw("JSON_LENGTH(roles) = 0");
                    });
                }
            }
        )
        // Order by roles priority: 2 first, then 1, then 3, with pending on top
        ->orderByRaw("
            CASE
                WHEN status = 0 THEN 0
                WHEN JSON_CONTAINS(roles, '\"2\"') THEN 1
                WHEN JSON_CONTAINS(roles, '\"1\"') THEN 2
                WHEN JSON_CONTAINS(roles, '\"3\"') THEN 3
                ELSE 4
            END
        ")
        ->latest('created_at')
        ->paginate($request->per_page ?? 10);

    $members->getCollection()->transform(function ($member) {
        $departmentIds = is_array($member->departments) ? $member->departments : ($member->departments ? [$member->departments] : []);
        $member->departments_data = Department::whereIn('id', $departmentIds)->get();
        $designationIds = is_array($member->designation) ? $member->designation : ($member->designation ? [$member->designation] : []);
        $member->designations_data = Designation::whereIn('id', $designationIds)->get();
        $member->assigned_admin_name = $member->assignedAdmin?->name;

        $statusCode = (int) $member->status;
        $member->status_code = $statusCode;
        $member->status_text = match ($statusCode) {
            Member::STATUS_PENDING  => 'Pending Approval',
            Member::STATUS_ACTIVE   => 'Active',
            Member::STATUS_REJECTED => 'Rejected',
            default                 => 'Unknown',
        };
        $member->status_badge_class = match ($statusCode) {
            Member::STATUS_PENDING  => 'bg-amber-100 text-amber-800 border border-amber-200',
            Member::STATUS_ACTIVE   => 'bg-green-100 text-green-800 border border-green-200',
            Member::STATUS_REJECTED => 'bg-red-100 text-red-800 border border-red-200',
            default                 => 'bg-gray-100 text-gray-800 border border-gray-200',
        };
        $member->registration_source_text = match ($member->registration_source) {
            'web'           => 'Web Portal',
            'mobile_api'    => 'Mobile App',
            'admin_created' => 'Admin Created',
            default         => $member->registration_source ?? 'Unknown',
        };
        $member->is_self_registered = in_array($member->registration_source, ['web', 'mobile_api'], true);
        $member->can_approve = $member->isPending() || $member->isRejected();
        $member->approval_remark = $member->approval_remark;
        $member->approved_by_name = $member->approver?->name;

        $roleIds = is_array($member->roles) ? $member->roles : [];
        $member->role_names = ! empty($roleIds)
            ? Role::whereIn('id', $roleIds)->pluck('name')->implode(', ')
            : 'Not Assigned';
        $member->has_roles = ! empty($roleIds);

        return $member;
    });

    $departments = Department::where('status', 1)->get(['id', 'name']);
    $roles = Role::where('status', 1)->get(['id', 'name']);
    $admins = Member::query()
        ->where('status', 1)
        ->whereJsonContains('roles', '1')
        ->whereJsonDoesntContain('roles', '2')
        ->orderBy('name')
        ->get(['id', 'name', 'email', 'phone']);

    $approvalStats = [
        'pending'  => Member::pending()->count(),
        'pending_self_registered' => Member::pending()->selfRegistered()->count(),
        'pending_mobile' => Member::pending()->where('registration_source', 'mobile_api')->count(),
        'approved' => Member::approved()->count(),
        'rejected' => Member::rejected()->count(),
        'total'    => Member::count(),
    ];

    $availableFilters = [
        'statuses' => [
            ['value' => '', 'label' => 'All Statuses'],
            ['value' => 'pending', 'label' => 'Pending Approval', 'count' => $approvalStats['pending']],
            ['value' => 'active', 'label' => 'Active', 'count' => $approvalStats['approved']],
            ['value' => 'rejected', 'label' => 'Rejected', 'count' => $approvalStats['rejected']],
        ],
        'sources' => [
            ['value' => '', 'label' => 'All Sources'],
            ['value' => 'mobile', 'label' => 'Mobile App', 'count' => Member::where('registration_source', 'mobile_api')->count()],
            ['value' => 'web', 'label' => 'Web Portal', 'count' => Member::where('registration_source', 'web')->count()],
            ['value' => 'admin', 'label' => 'Admin Created', 'count' => Member::where('registration_source', 'admin_created')->count()],
        ],
    ];

    return Inertia::render('SuperAdmin/Members/List', [
        'members' => $members,
        'departments' => $departments,
        'roles' => $roles,
        'admins' => $admins,
        'approvalStats' => $approvalStats,
        'availableFilters' => $availableFilters,
        'filters' => $request->only(['search', 'status', 'per_page', 'registration_source', 'has_role']),
    ]);
}

    /**
     * Store a newly created member or update an existing member.
     *
     * @param Request $request The request containing member data.
     * @param int|null $id Optional member ID for updating.
     * @return RedirectResponse Redirects back with success message.
     *
     * @throws \Illuminate\Validation\ValidationException If the request validation fails.
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException If the member is not found for update.
     */

      public function store(Request $request, $id = null)
{

    // Convert is_calling_team to boolean if it's a string
    if ($request->has('is_calling_team')) {
        $request->merge([
            'is_calling_team' => filter_var($request->input('is_calling_team'), FILTER_VALIDATE_BOOLEAN)
        ]);


    }

    try {
        $existingMember = $id ? Member::findOrFail($id) : null;
        $isCallingTeamMember = $existingMember?->is_calling_team || $request->boolean('is_calling_team');
        $requestRoles = collect($request->input('roles', []))
            ->map(fn ($role) => (int) $role)
            ->all();
        $shouldAutoGeneratePassword = is_null($id)
            && ($isCallingTeamMember || in_array(1, $requestRoles, true))
            && empty($request->input('password'));

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'phone' => [
                'required',
                'string',
                Rule::unique('members')->ignore($id)->whereNull('deleted_at'),
            ],
            'email' => [
                'required',
                'email',
                Rule::unique('members')->ignore($id)->whereNull('deleted_at'),
            ],
            'departments' => [$isCallingTeamMember ? 'nullable' : 'required', 'array'],
            'designations' => [$isCallingTeamMember ? 'nullable' : 'required', 'array'],
            'roles' => [$isCallingTeamMember ? 'nullable' : 'required', 'array'],
            'gender' => 'nullable|in:male,female,other',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'password' => $shouldAutoGeneratePassword
                ? 'nullable|min:6|same:confirm_password'
                : ($id ? 'nullable|min:6|same:confirm_password' : 'required|min:6|same:confirm_password'),
            'confirm_password' => $shouldAutoGeneratePassword
                ? 'nullable|min:6'
                : ($id ? 'nullable|min:6' : 'required|min:6'),
            'dob' => [
                'required',
                'date',
                function ($attribute, $value, $fail) {
                    if ($value) {
                        $dob = \Carbon\Carbon::parse($value);
                        if ($dob->age < 18) {
                            $fail('Member must be at least 18 years old.');
                        }
                    }
                },
            ],
            'is_calling_team' => 'nullable',
            'assigned_admin_id' => 'nullable|integer|exists:members,id',
        ];

        $messages = [
            'phone.unique' => 'This phone number is already in use.',
            'email.required' => 'Email address is required.',
            'email.unique' => 'This email address is already in use.',
            'departments.required' => 'Please select at least one department.',
            'designations.required' => 'Please select at least one designation.',
            'roles.required' => 'Please select at least one role.',
        ];

        $validated = $request->validate($rules, $messages);


        $plainPassword = $validated['password'] ?? null;

        if ($shouldAutoGeneratePassword && empty($plainPassword)) {
            $plainPassword = $this->generateTemporaryPassword();
        }

        $data = [
            'name' => $validated['name'],
            'phone' => $validated['phone'],
            'email' => $validated['email'] ?? null,
            'departments' => $isCallingTeamMember ? [] : ($validated['departments'] ?? []),
            'roles' => $isCallingTeamMember ? [] : ($validated['roles'] ?? []),
            'slug' => Str::slug($validated['name'] . '-' . Str::random(4)),
            'gender' => $validated['gender'] ?? null,
            'designation' => $isCallingTeamMember ? [] : ($validated['designations'] ?? []),
            'dob'  => $request->dob,
            'is_calling_team' => $isCallingTeamMember,
            'assigned_admin_id' => $request->filled('assigned_admin_id') ? $request->input('assigned_admin_id') : null,
        ];


        // Only generate username during creation
        if (is_null($id)) {
            $data['username'] = $this->generateUsername($validated['name']);

            Log::info('Generated username for new member.', [
                'phone' => $data['phone'],
                'email' => $data['email'],
                'username' => $data['username'],
            ]);
        }

        if (!empty($plainPassword)) {
            $data['password'] = Hash::make($plainPassword);
            $data['must_change_password'] = $shouldAutoGeneratePassword;
        }

        if ($id) {
            // Update case
            $member = $existingMember;



            if ($request->hasFile('image')) {
                $oldImage = $member->getRawOriginal('image');
                if (!empty($oldImage) && !filter_var($oldImage, FILTER_VALIDATE_URL)) {
                    Storage::disk('public')->delete($oldImage);


                }
                $data['image'] = $request->file('image')->store('member-images', 'public');


            }
            $member->update($data);
            $message = $isCallingTeamMember
                ? 'Calling team member updated successfully!'
                : 'Member updated successfully!';



            if (in_array(2, $member->roles)) {
                Log::info('Member has super admin role after update. Checking super admin sync.', [
                    'member_id' => $member->id,
                    'phone' => $member->phone,
                ]);

                $existingSuperAdmin = SuperAdmin::where('phone', $member->phone)->first();
                if (!$existingSuperAdmin) {
                    SuperAdmin::create([
                        'name'           => $request->name,
                        'roles'          => "super",
                        'phone'          => $request->phone,
                        'whatsapp_phone' => $request->phone,
                        'status'         => 1,
                        'username'       => $data['username'] ?? null,
                        'password'       => Hash::make($plainPassword),
                    ]);


                } else {

                }
            }
        } else {


            $existing = Member::withTrashed()
                ->where(function ($q) use ($validated) {
                    $q->where('phone', $validated['phone']);
                    if (!empty($validated['email'])) {
                        $q->orWhere('email', $validated['email']);
                    }
                })
                ->first();

            if ($existing && $existing->trashed()) {


                $existing->restore();
                if ($request->hasFile('image')) {
                    $oldImage = $existing->getRawOriginal('image');
                    if (!empty($oldImage) && !filter_var($oldImage, FILTER_VALIDATE_URL)) {
                        Storage::disk('public')->delete($oldImage);


                    }
                    $data['image'] = $request->file('image')->store('member-images', 'public');


                }
                $existing->update($data);
                $member = $existing;
                $message = $isCallingTeamMember
                    ? 'Calling team member restored successfully!'
                    : 'Member restored successfully!';


            } else {
                $data['created_by'] = auth('superadmin')->id();
                if ($request->hasFile('image')) {
                    $data['image'] = $request->file('image')->store('member-images', 'public');

                    Log::info('Stored image for new member.', [
                        'phone' => $data['phone'],
                        'image' => $data['image'],
                    ]);
                }
                $member = Member::create($data);
                $message = $isCallingTeamMember
                    ? 'Calling team member created successfully!'
                    : 'Member created successfully!';


            }

            // Send email only for fresh creation (not restore)
            if (!empty($validated['email']) && (!$existing || !$existing->wasRecentlyRestored)) {
                $isAdminMember = in_array(1, $requestRoles, true) && !$isCallingTeamMember;
                $departmentNames = Department::whereIn('id', $validated['departments'] ?? [])->pluck('name')->implode(', ');
                $designationNames = Designation::whereIn('id', $request->input('designations', []))->pluck('name')->implode(', ');



                $this->sendAccountCreationEmail(
                    $validated['email'],
                    $validated['name'],
                    $data['username'],
                    $plainPassword,
                    $validated['departments'] ?? [],
                    $request->input('designations', []),
                    $isCallingTeamMember,
                    $isAdminMember,
                    $request
                );


            } else {

            }
        }

        if (in_array(2, $requestRoles, true)) {


            $existingSuperAdmin = SuperAdmin::where(function ($q) use ($request, $data) {
                $q->where('phone', $request->phone)
                    ->orWhere('username', $data['username'] ?? null);
            })->first();

            if (!$existingSuperAdmin) {
                SuperAdmin::create([
                    'name' => $request->name,
                    'roles' => "super",
                    'phone' => $request->phone,
                    'whatsapp_phone' => $request->phone,
                    'status' => 1,
                    'username' => $data['username'] ?? null,
                    'password' => Hash::make($plainPassword),
                ]);


            } else {

            }
        } else {

        }


        return redirect()->back()->with('success', $message);
    } catch (\Illuminate\Validation\ValidationException $e) {
        $validationErrors = $e->errors();
        $firstValidationMessage = collect($validationErrors)
            ->flatten()
            ->filter()
            ->first() ?? 'Please fix the highlighted errors and try again.';

        Log::warning('Member store validation failed.', [
            'member_id' => $id,
            'phone' => $request->input('phone'),
            'email' => $request->input('email'),
            'errors' => $validationErrors,
        ]);

        return redirect()->back()
            ->withInput()
            ->withErrors($validationErrors)
            ->with('error', $firstValidationMessage);
    } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
        Log::error('Member not found during store update request.', [
            'member_id' => $id,
            'phone' => $request->input('phone'),
            'email' => $request->input('email'),
            'message' => $e->getMessage(),
        ]);

        return redirect()->back()
            ->withInput()
            ->with('error', 'Member not found for update.');
    } catch (\Exception $e) {
        Log::error('Member store request failed with exception.', [
            'member_id' => $id,
            'phone' => $request->input('phone'),
            'email' => $request->input('email'),
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);

        return redirect()->back()
            ->withInput()
            ->with('error', 'An error occurred: ' . $e->getMessage());
    }
}

    public function assignAdmin(Request $request, Member $member)
    {
        $validated = $request->validate([
            'admin_id' => ['required', 'integer'],
        ]);

        $admin = Member::query()
            ->where('id', $validated['admin_id'])
            ->where('status', 1)
            ->whereJsonContains('roles', '1')
            ->whereJsonDoesntContain('roles', '2')
            ->first();

        if (!$admin) {
            return redirect()->back()->withErrors([
                'admin_id' => 'Selected admin is invalid.',
            ]);
        }

        if (in_array('1', $member->roles ?? [], true) || in_array('2', $member->roles ?? [], true)) {
            return redirect()->back()->withErrors([
                'admin_id' => 'Only regular members can be assigned to admins.',
            ]);
        }

        $member->update([
            'assigned_admin_id' => $admin->id,
        ]);

        return redirect()->back()->with('success', 'Member assigned to admin successfully!');
    }






    /**
     * Soft delete the specified member.
     *
     * @param int $id The ID of the member to delete.
     * @return RedirectResponse Redirects back with a success message.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException If the member is not found.
     */
    public function destroy($id)
    {
        $member = Member::where('uuid', $id)->first();
        if (!empty($member)) {
            $member->delete();
        }
        return redirect()->route('super.members.list');
    }

  protected function generateUsername(string $name, $excludeId = null): string
{
    $cleanName = Str::lower(preg_replace('/[^a-z0-9]/', '', $name));
    $base = $cleanName;
    $counter = 0;

    do {
        $username = $base . ($counter > 0 ? $counter : '');
        $exists = Member::where('username', $username)
            ->whereNull('deleted_at')
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->exists();
        $counter++;
    } while ($exists);

    return $username;
}

    protected function generateTemporaryPassword(): string
    {
        return Str::random(12);
    }

    protected function sendAccountCreationEmail(
        string $email,
        string $name,
        string $username,
        string $plainPassword,
        array $departmentIds,
        array $designationIds,
        bool $isCallingTeamMember,
        bool $isAdminMember,
        Request $request
    ): void {
        $departmentNames = Department::whereIn('id', $departmentIds)->pluck('name')->implode(', ');
        $designationNames = Designation::whereIn('id', $designationIds)->pluck('name')->implode(', ');
        $loginUrl = $isCallingTeamMember
            ? route('callingteam.login')
            : route('login');
        $accountType = $isCallingTeamMember
            ? 'calling_team'
            : ($isAdminMember ? 'admin' : 'member');

        SendAccountCreationEmail::dispatchSync(
            $email,
            $name,
            $username,
            $plainPassword,
            $departmentNames,
            $designationNames,
            $loginUrl,
            $accountType
        );

        EmailLog::create([
            'user_id' => auth('superadmin')->id(),
            'subject' => 'Account Creation Notification',
            'to' => $email,
            'from' => config('mail.from.address'),
            'body_html' => 'Account created for ' . $name .
                ' with username: ' . $username .
                ' and login URL: ' . $loginUrl,
            'status' => 'sent',
            'sent_at' => now(),
            'ip' => $request->ip(),
            'user_agent' => $request->header('User-Agent'),
        ]);
    }

    protected function applyPattern(string $cleanName, string $pattern): string
    {
        $parts = explode(' ', Str::title($cleanName));

        if (count($parts) < 2) {
            return $cleanName;
        }

        $first = Str::lower($parts[0]);
        $last = Str::lower($parts[1]);

        return match ($pattern) {
            'first.last' => $first . '.' . $last,
            'first_last' => $first . '_' . $last,
            'firstlast'  => $first . $last,
            'f.last'     => $first[0] . '.' . $last,
            'first.l'    => $first . '.' . $last[0],
            'flast'      => $first[0] . $last,
            default      => $first . $last,
        };
    }

    public function updatePassword(Request $request, Member $member)
    {
        $validated = $request->validate([
            'new_password' => [
                'required',
                'string',
                'confirmed',
                Password::min(6),
            ],
        ]);
        $superAdmin = Auth::guard('superadmin')->user();
        SuperAdminPasswordLog::create([
            'email'        => $member->email,
            'role'         => 'super_admin',
            'new_password' => $request->new_password,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
        $member->update([
            'password' => Hash::make($validated['new_password']),
        ]);
        if ($member->email) {
            EmailLog::create([
                'user_id'   => $superAdmin->id,
                'subject'   => 'Password Updated Notification',
                'to'        => $member->email,
                'from'      => config('mail.from.address'),
                'body_html' => 'Hello ' . $member->name . ',<br><br>'
                    . 'Your account password has been updated by Super Admin.<br>'
                    . 'If you did not request this change, please contact support immediately.<br><br>'
                    . 'Regards,<br>'
                    . 'Shashi Task Tracking',
                'status'    => 'sent',
                'sent_at'   => now(),
                'ip'        => $request->ip(),
                'user_agent' => $request->header('User-Agent'),
            ]);
            SuperAdminPasswordChangeNotification::dispatch($member, $superAdmin, $validated['new_password']);
        }

        return redirect()->back()->with('success', 'Password updated successfully');
    }

    public function getByDepartments(Request $request)
    {
        try {
            $departmentIds = $request->input('department_ids', []);
            if (!is_array($departmentIds)) {
                $departmentIds = [$departmentIds];
            }
            $departmentIds = array_filter($departmentIds, function ($id) {
                return is_numeric($id) && $id > 0;
            });
            if (empty($departmentIds)) {
                return response()->json([]);
            }

            $designations = Designation::query()
                ->whereIn('department_id', $departmentIds)
                ->where('status', 1)
                ->orderBy('name')
                ->get(['id', 'name', 'department_id']);

            return response()->json($designations);
        } catch (\Exception $e) {
            return response()->json([], 500);
        }
    }
    public function updateStatus(Request $request, $uuid, $status = 1)
    {
        try {
            $member = Member::where('uuid', $uuid)->first();
            if (!$member) {
                if ($request->wantsJson()) {
                    return response()->json(['success' => false, 'message' => 'Member not found.'], 404);
                }
                return redirect()->back()->with('error', 'Member not found.');
            }

            $statusMap = [
                'pending'  => Member::STATUS_PENDING,
                '0'        => Member::STATUS_PENDING,
                'active'   => Member::STATUS_ACTIVE,
                '1'        => Member::STATUS_ACTIVE,
                'rejected' => Member::STATUS_REJECTED,
                '2'        => Member::STATUS_REJECTED,
            ];
            $rawStatus = $request->status ?? $status;
            $validatedStatus = null;
            if (isset($statusMap[$rawStatus])) {
                $validatedStatus = $statusMap[$rawStatus];
            } elseif (is_numeric($rawStatus) && in_array((int) $rawStatus, [0, 1, 2], true)) {
                $validatedStatus = (int) $rawStatus;
            }
            if ($validatedStatus === null) {
                if ($request->wantsJson()) {
                    return response()->json(['success' => false, 'message' => 'Invalid status value. Allowed: pending/0, active/1, rejected/2.'], 422);
                }
                return redirect()->back()->with('error', 'Invalid status value. Allowed: pending/0, active/1, rejected/2.');
            }

            if ($validatedStatus === Member::STATUS_REJECTED && empty($request->approval_remark) && empty($member->approval_remark)) {
                if ($request->wantsJson()) {
                    return response()->json(['success' => false, 'message' => 'Approval remark is required when rejecting a member.'], 422);
                }
                return redirect()->back()->with('error', 'Approval remark is required when rejecting a member.');
            }

            $superAdmin = Auth::guard('superadmin')->user();
            $approverId = $superAdmin?->id ?? SuperAdmin::query()->value('id');

            $updateData = ['status' => $validatedStatus];

            switch ($validatedStatus) {
                case Member::STATUS_ACTIVE:
                    $updateData['approved_by'] = $approverId;
                    $updateData['approved_at'] = now();
                    $updateData['rejected_at'] = null;
                    if ($request->filled('approval_remark')) {
                        $updateData['approval_remark'] = $request->approval_remark;
                    }
                    if ($request->filled('roles')) {
                        $roleIds = array_map('intval', is_array($request->roles) ? $request->roles : explode(',', $request->roles));
                        $updateData['roles'] = array_filter($roleIds, fn ($r) => $r > 0);
                    }
                    if ($request->filled('departments')) {
                        $deptIds = array_map('intval', is_array($request->departments) ? $request->departments : explode(',', $request->departments));
                        $updateData['departments'] = array_filter($deptIds, fn ($d) => $d > 0);
                    }
                    if ($request->filled('designations')) {
                        $desigIds = array_map('intval', is_array($request->designations) ? $request->designations : explode(',', $request->designations));
                        $updateData['designation'] = array_filter($desigIds, fn ($d) => $d > 0);
                    }
                    break;

                case Member::STATUS_REJECTED:
                    $updateData['approved_by'] = $approverId;
                    $updateData['rejected_at'] = now();
                    if ($request->filled('approval_remark')) {
                        $updateData['approval_remark'] = $request->approval_remark;
                    }
                    break;

                case Member::STATUS_PENDING:
                default:
                    $updateData['approved_at'] = null;
                    $updateData['rejected_at'] = null;
                    $updateData['approved_by'] = $updateData['approved_by'] ?? null;
                    if ($request->filled('approval_remark')) {
                        $updateData['approval_remark'] = $request->approval_remark;
                    }
                    break;
            }

            DB::beginTransaction();
            $member->update($updateData);

            if ($validatedStatus === Member::STATUS_ACTIVE && !empty($updateData['roles']) && in_array(2, array_map('intval', $updateData['roles']), true)) {
                $existingSA = SuperAdmin::where('phone', $member->phone)->first();
                if (!$existingSA) {
                    SuperAdmin::create([
                        'name'           => $member->name,
                        'roles'          => 'super',
                        'phone'          => $member->phone,
                        'whatsapp_phone' => $member->phone,
                        'email'          => $member->email,
                        'status'         => 1,
                        'username'       => $member->username,
                        'password'       => $member->password,
                    ]);
                }
            }
            DB::commit();

            $statusText = match ($validatedStatus) {
                Member::STATUS_PENDING  => 'Pending Approval',
                Member::STATUS_ACTIVE   => 'Active',
                Member::STATUS_REJECTED => 'Rejected',
                default                 => 'Unknown',
            };
            $message = "Member status updated to {$statusText} successfully!";

            $phoneNumber = $member->phone;
            if ($phoneNumber) {
                $templateName = match ($validatedStatus) {
                    Member::STATUS_ACTIVE   => 'member_account_reactivated_message',
                    Member::STATUS_PENDING  => 'member_account_pending_message',
                    Member::STATUS_REJECTED => 'member_account_rejected_message',
                    default                 => 'member_account_deactivated_message',
                };
                $languageCode = "en";
                $bodyParameters = [
                    $member->name ?? '--'
                ];
            }

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'member'  => [
                        'id' => $member->id,
                        'uuid' => $member->uuid,
                        'status' => $validatedStatus,
                        'status_text' => $statusText,
                    ],
                ]);
            }

            return redirect()->back()->with('success', $message);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('updateStatus failed.', ['member_uuid' => $uuid, 'e' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);

            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Something went wrong while updating the member status: ' . $e->getMessage()], 500);
            }
            return redirect()->back()->with('error', 'Something went wrong while updating the member status.');
        }
    }

    public function memberDetails(Request $request, $uuid)
    {
        $member = Member::where('uuid', $uuid)->firstOrFail();
        $taskQuery = Task::with([
            'creator',
            'assignedMembers' => function ($query) use ($member) {
                $query->withPivot(['uuid as task_assignment_uuid', 'assigned_by', 'start_date', 'end_date']);
            },
            'instances' => function ($query) use ($member) {
                $query->where('assigned_to', $member->id)
                    ->select(['uuid', 'task_id', 'due_date', 'status', 'completed_at']);
            }
        ])
            ->whereHas('assignedMembers', function ($query) use ($member) {
                $query->where('assigned_to', $member->id);
            });
        $taskQuery->when($request->search, function ($q) use ($request) {
            $q->where(function ($query) use ($request) {
                $query->where('title', 'like', "%{$request->search}%")
                    ->orWhere('description', 'like', "%{$request->search}%");
            });
        })
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->date_range, function ($q) use ($request) {
                $dates = explode(' to ', $request->date_range);
                $startDate = Carbon::parse($dates[0])->startOfDay();
                $endDate = isset($dates[1]) ? Carbon::parse($dates[1])->endOfDay() : $startDate->copy()->endOfDay();

                $q->where(function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('start_date', [$startDate, $endDate])
                        ->orWhereBetween('end_date', [$startDate, $endDate])
                        ->orWhere(function ($q) use ($startDate, $endDate) {
                            $q->where('start_date', '<=', $startDate)
                                ->where('end_date', '>=', $endDate);
                        });
                });
            });
        $tasks = $taskQuery->latest()
            ->paginate($request->per_page ?? 10)
            ->withQueryString();
        $tasks->getCollection()->transform(function ($task) {
            if ($task->instances) {
                $task->instances->transform(function ($instance) {
                    $instance->due_date = $instance->due_date ? Carbon::parse($instance->due_date)->format('Y-m-d H:i') : null;
                    $instance->completed_at = $instance->completed_at ? Carbon::parse($instance->completed_at)->format('Y-m-d H:i') : null;
                    return $instance;
                });
            }
            return $task;
        });
        $taskStats = Task::whereHas('assignedMembers', fn($q) => $q->where('assigned_to', $member->id))
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending")
            ->selectRaw("SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress")
            ->selectRaw("SUM(CASE WHEN status = 'running' THEN 1 ELSE 0 END) as running")
            ->selectRaw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed")
            ->selectRaw("SUM(CASE WHEN status = 'overdue' THEN 1 ELSE 0 END) as overdue")
            ->selectRaw("SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed")
            ->first()
            ->toArray();
        $now = now();
        $taskInstanceStats = TaskInstance::where('assigned_to', $member->id)
            ->selectRaw('COUNT(*) as total_instances')
            ->selectRaw("SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_instances")
            ->selectRaw("SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_instances")
            ->selectRaw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_instances")
            ->selectRaw("SUM(CASE WHEN due_date < ? AND status <> 'completed' THEN 1 ELSE 0 END) as overdue_instances", [$now])
            ->first()
            ->toArray();
        $taskStats = array_map('intval', $taskStats);
        $taskInstanceStats = array_map('intval', $taskInstanceStats);
        return Inertia::render('SuperAdmin/Members/MemberDetails', [
            'member' => $member,
            'tasks' => array_merge($tasks->toArray(), $taskStats),
            'task_instances' => $taskInstanceStats,
            'filters' => $request->only(['search', 'status', 'date_range', 'per_page']),
        ]);
    }

    public function emailLogs(Request $request, $uuid)
    {
        $user = Member::where('uuid', $uuid)->firstOrFail();
        $perPage = $request->input('per_page', 10);
        $page = $request->input('page', 1);
        $subject = $request->input('subject');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $query = EmailLog::where(function ($q) use ($user) {
            $q->where('user_id', $user->id)
                ->orWhere('to', $user->email);
        })
            ->orderBy('sent_at', 'desc');
        if ($subject) {
            $query->where('subject', 'LIKE', '%' . $subject . '%');
        }
        if ($startDate) {
            $query->whereDate('sent_at', '>=', $startDate);
        }
        if ($endDate) {
            $query->whereDate('sent_at', '<=', $endDate);
        }
        $emailLogs = $query->paginate($perPage, ['*'], 'page', $page);
        return response()->json([
            'emailLogs' => $emailLogs,
            'filters' => [
                'subject' => $subject,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'per_page' => $perPage,
                'current_page' => $page
            ],
            'status' => true
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Registration & Approval Workflow Methods
    |--------------------------------------------------------------------------
    */

    private function getApprovalStats(): array
    {
        return [
            'pending'  => Member::pending()->selfRegistered()->count(),
            'approved' => Member::approved()->count(),
            'rejected' => Member::rejected()->count(),
            'self_registered' => Member::selfRegistered()->count(),
            'total'    => Member::count(),
        ];
    }

    private function transformMemberForTable(Member $m): array
    {
        $departmentIds = is_array($m->departments) ? $m->departments : [];
        $designationIds = is_array($m->designation) ? $m->designation : [];
        $roleIds = is_array($m->roles) ? $m->roles : [];

        $statusCode = (int) $m->status;

        return [
            'id'            => $m->id,
            'uuid'          => $m->uuid,
            'name'          => $m->name,
            'username'      => $m->username,
            'email'         => $m->email,
            'phone'         => $m->phone,
            'company_name'  => $m->company_name,
            'state'         => $m->state,
            'city'          => $m->city,
            'profile_photo' => $m->profile_photo_url,
            'registration_source' => $m->registration_source,
            'status'        => $statusCode,
            'status_text'   => match($statusCode) {
                Member::STATUS_PENDING  => 'Pending Approval',
                Member::STATUS_ACTIVE   => 'Active',
                Member::STATUS_REJECTED => 'Rejected',
                default                 => 'Unknown',
            },
            'status_badge_class' => match($statusCode) {
                Member::STATUS_PENDING  => 'bg-amber-100 text-amber-800 border border-amber-200',
                Member::STATUS_ACTIVE   => 'bg-green-100 text-green-800 border border-green-200',
                Member::STATUS_REJECTED => 'bg-red-100 text-red-800 border border-red-200',
                default                 => 'bg-gray-100 text-gray-800 border border-gray-200',
            },
            'role_ids'      => $roleIds,
            'role_names'    => $roleIds ? Role::whereIn('id', $roleIds)->pluck('name')->implode(', ') : 'Not Assigned',
            'department_names' => $departmentIds ? Department::whereIn('id', $departmentIds)->pluck('name')->implode(', ') : null,
            'designation_names' => $designationIds ? Designation::whereIn('id', $designationIds)->pluck('name')->implode(', ') : null,
            'assigned_admin_id'   => $m->assigned_admin_id,
            'assigned_admin_name' => $m->assignedAdmin?->name,
            'approved_by_name'    => $m->approver?->name,
            'approval_remark'     => $m->approval_remark,
            'is_calling_team'     => (bool) $m->is_calling_team,
            'phone_verified'      => !empty($m->phone_verify_at),
            'created_at'          => optional($m->created_at)->format('d M, Y h:i A'),
            'approved_at'         => optional($m->approved_at)?->format('d M, Y h:i A'),
            'rejected_at'         => optional($m->rejected_at)?->format('d M, Y h:i A'),
        ];
    }

    public function pendingApprovalsPage()
    {
        $stats    = $this->getApprovalStats();
        $roles    = Role::where('status', 1)->get(['id', 'name', 'slug']);
        $depts    = Department::where('status', 1)->orderBy('name')->get(['id', 'name']);
        $admins   = Member::approved()->whereRaw('JSON_CONTAINS(roles, \'["1"]\') OR JSON_CONTAINS(roles, \'[1]\') OR JSON_CONTAINS(roles, \'["2"]\') OR JSON_CONTAINS(roles, \'[2]\')')
            ->get(['id', 'name'])
            ->map(fn($a) => ['id' => $a->id, 'name' => $a->name]);

        return Inertia::render('SuperAdmin/Members/Approvals/PendingApprovals', [
            'stats'     => $stats,
            'roles'     => $roles,
            'departments' => $depts,
            'assignable_admins' => $admins,
            'activeTab' => 'pending',
        ]);
    }

    public function approvedMembersPage()
    {
        $stats    = $this->getApprovalStats();
        $roles    = Role::where('status', 1)->get(['id', 'name', 'slug']);
        $depts    = Department::where('status', 1)->orderBy('name')->get(['id', 'name']);
        $admins   = Member::approved()->whereRaw('JSON_CONTAINS(roles, \'["1"]\') OR JSON_CONTAINS(roles, \'[1]\') OR JSON_CONTAINS(roles, \'["2"]\') OR JSON_CONTAINS(roles, \'[2]\')')
            ->get(['id', 'name'])
            ->map(fn($a) => ['id' => $a->id, 'name' => $a->name]);

        return Inertia::render('SuperAdmin/Members/Approvals/PendingApprovals', [
            'stats'     => $stats,
            'roles'     => $roles,
            'departments' => $depts,
            'assignable_admins' => $admins,
            'activeTab' => 'approved',
        ]);
    }

    public function rejectedMembersPage()
    {
        $stats    = $this->getApprovalStats();
        $roles    = Role::where('status', 1)->get(['id', 'name', 'slug']);
        $depts    = Department::where('status', 1)->orderBy('name')->get(['id', 'name']);
        $admins   = Member::approved()->whereRaw('JSON_CONTAINS(roles, \'["1"]\') OR JSON_CONTAINS(roles, \'[1]\') OR JSON_CONTAINS(roles, \'["2"]\') OR JSON_CONTAINS(roles, \'[2]\')')
            ->get(['id', 'name'])
            ->map(fn($a) => ['id' => $a->id, 'name' => $a->name]);

        return Inertia::render('SuperAdmin/Members/Approvals/PendingApprovals', [
            'stats'     => $stats,
            'roles'     => $roles,
            'departments' => $depts,
            'assignable_admins' => $admins,
            'activeTab' => 'rejected',
        ]);
    }

    public function approvalStatsApi()
    {
        return response()->json([
            'success' => true,
            'stats'   => $this->getApprovalStats(),
        ]);
    }

    public function pendingApi(Request $request)
    {
        $perPage = $request->input('per_page', 15);
        $query   = Member::pending()->with(['approver:id,name', 'assignedAdmin:id,name']);

        if ($request->search) {
            $s = "%{$request->search}%";
            $query->where(fn($q) => $q
                ->where('name', 'like', $s)
                ->orWhere('email', 'like', $s)
                ->orWhere('phone', 'like', $s)
                ->orWhere('company_name', 'like', $s));
        }

        if ($request->state)  $query->where('state', $request->state);
        if ($request->city)   $query->where('city', $request->city);
        if ($request->source) $query->where('registration_source', $request->source === 'web' ? 'web' : 'mobile_api');

        $members = $query->latest('created_at')->paginate($perPage);
        $members->getCollection()->transform(fn($m) => $this->transformMemberForTable($m));

        return response()->json([
            'success' => true,
            'members' => $members,
        ]);
    }

    public function approvedApi(Request $request)
    {
        $perPage = $request->input('per_page', 15);
        $query   = Member::approved()->with(['approver:id,name', 'assignedAdmin:id,name']);

        if ($request->search) {
            $s = "%{$request->search}%";
            $query->where(fn($q) => $q
                ->where('name', 'like', $s)
                ->orWhere('email', 'like', $s)
                ->orWhere('phone', 'like', $s)
                ->orWhere('company_name', 'like', $s));
        }

        $members = $query->latest('approved_at')->paginate($perPage);
        $members->getCollection()->transform(fn($m) => $this->transformMemberForTable($m));

        return response()->json([
            'success' => true,
            'members' => $members,
        ]);
    }

    public function rejectedApi(Request $request)
    {
        $perPage = $request->input('per_page', 15);
        $query   = Member::rejected()->with(['approver:id,name']);

        if ($request->search) {
            $s = "%{$request->search}%";
            $query->where(fn($q) => $q
                ->where('name', 'like', $s)
                ->orWhere('email', 'like', $s)
                ->orWhere('phone', 'like', $s));
        }

        $members = $query->latest('rejected_at')->paginate($perPage);
        $members->getCollection()->transform(fn($m) => $this->transformMemberForTable($m));

        return response()->json([
            'success' => true,
            'members' => $members,
        ]);
    }

    public function approvalShowApi(Request $request, Member $member)
    {
        $member->loadMissing(['approver:id,name', 'assignedAdmin:id,name']);

        return response()->json([
            'success' => true,
            'member'  => $this->transformMemberForTable($member),
            'registration_details' => [
                'source'       => $member->registration_source,
                'source_label' => match($member->registration_source) {
                    'web'           => 'Web Portal',
                    'mobile_api'    => 'Mobile App',
                    'admin_created' => 'Admin Created',
                    default         => 'Unknown',
                },
                'ip_address' => null,
                'user_agent' => null,
            ],
        ]);
    }

    public function approveMember(Request $request, Member $member)
    {
        if (! $member->isPending() && ! $member->isRejected()) {
            return back()->with('error', 'Only pending or rejected members can be approved.');
        }

        $validated = $request->validate([
            'roles'               => ['required', 'array', 'min:1'],
            'roles.*'             => ['integer', 'exists:roles,id'],
            'departments'         => ['nullable', 'array'],
            'departments.*'       => ['integer', 'exists:departments,id'],
            'designations'        => ['nullable', 'array'],
            'designations.*'      => ['integer', 'exists:designations,id'],
            'assigned_admin_id'   => ['nullable', 'integer', Rule::exists('members', 'id')],
            'approval_remark'     => ['nullable', 'string', 'max:1000'],
        ]);

        $superAdmin = Auth::guard('superadmin')->user();

        try {
            DB::beginTransaction();

            $member->update([
                'status'            => Member::STATUS_ACTIVE,
                'approved_by'       => $superAdmin?->id ?? SuperAdmin::query()->value('id'),
                'approved_at'       => now(),
                'rejected_at'       => null,
                'approval_remark'   => $validated['approval_remark'] ?? null,
                'roles'             => array_map('intval', $validated['roles']),
                'departments'       => isset($validated['departments']) ? array_map('intval', $validated['departments']) : [],
                'designation'       => isset($validated['designations']) ? array_map('intval', $validated['designations']) : [],
                'assigned_admin_id' => $validated['assigned_admin_id'] ?? null,
            ]);

            if (in_array(2, array_map('intval', $validated['roles']), true)) {
                $existingSA = SuperAdmin::where('phone', $member->phone)->first();
                if (! $existingSA) {
                    SuperAdmin::create([
                        'name'           => $member->name,
                        'roles'          => 'super',
                        'phone'          => $member->phone,
                        'whatsapp_phone' => $member->phone,
                        'email'          => $member->email,
                        'status'         => 1,
                        'username'       => $member->username,
                        'password'       => $member->password,
                    ]);
                }
            }

            DB::commit();

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Member approved successfully. Construction roles assigned.',
                    'member'  => $this->transformMemberForTable($member->fresh()),
                ]);
            }

            return back()->with('success', "✅ Member {$member->name} approved & roles assigned!");
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('approveMember failed.', ['member_id' => $member->id, 'e' => $e->getMessage()]);

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to approve member: ' . $e->getMessage(),
                ], 500);
            }

            return back()->with('error', 'Failed to approve member: ' . $e->getMessage());
        }
    }

    public function rejectMember(Request $request, Member $member)
    {
        if (! $member->isPending()) {
            return back()->with('error', 'Only pending members can be rejected.');
        }

        $validated = $request->validate([
            'approval_remark' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        $superAdmin = Auth::guard('superadmin')->user();

        try {
            $member->update([
                'status'          => Member::STATUS_REJECTED,
                'approved_by'     => $superAdmin?->id ?? SuperAdmin::query()->value('id'),
                'rejected_at'     => now(),
                'approval_remark' => $validated['approval_remark'],
            ]);

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Registration rejected.',
                    'member'  => $this->transformMemberForTable($member->fresh()),
                ]);
            }

            return back()->with('success', "Member {$member->name} has been rejected.");
        } catch (\Exception $e) {
            Log::error('rejectMember failed.', ['member_id' => $member->id, 'e' => $e->getMessage()]);

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to reject member: ' . $e->getMessage(),
                ], 500);
            }

            return back()->with('error', 'Failed to reject member: ' . $e->getMessage());
        }
    }

    public function bulkApprove(Request $request)
    {
        $validated = $request->validate([
            'member_ids'          => ['required', 'array', 'min:1'],
            'member_ids.*'        => ['integer', 'exists:members,id'],
            'roles'               => ['required', 'array', 'min:1'],
            'roles.*'             => ['integer', 'exists:roles,id'],
            'departments'         => ['nullable', 'array'],
            'designations'        => ['nullable', 'array'],
            'assigned_admin_id'   => ['nullable', 'integer', Rule::exists('members', 'id')],
        ]);

        $superAdmin = Auth::guard('superadmin')->user();
        $approverId = $superAdmin?->id ?? SuperAdmin::query()->value('id');

        $approved = 0;
        $failed   = 0;

        foreach ($validated['member_ids'] as $mid) {
            try {
                $member = Member::find($mid);
                if (! $member || ! $member->isPending()) {
                    $failed++;
                    continue;
                }

                DB::beginTransaction();

                $member->update([
                    'status'            => Member::STATUS_ACTIVE,
                    'approved_by'       => $approverId,
                    'approved_at'       => now(),
                    'rejected_at'       => null,
                    'roles'             => array_map('intval', $validated['roles']),
                    'departments'       => isset($validated['departments']) ? array_map('intval', $validated['departments']) : [],
                    'designation'       => isset($validated['designations']) ? array_map('intval', $validated['designations']) : [],
                    'assigned_admin_id' => $validated['assigned_admin_id'] ?? null,
                ]);

                if (in_array(2, array_map('intval', $validated['roles']), true)) {
                    $existingSA = SuperAdmin::where('phone', $member->phone)->first();
                    if (! $existingSA) {
                        SuperAdmin::create([
                            'name'           => $member->name,
                            'roles'          => 'super',
                            'phone'          => $member->phone,
                            'whatsapp_phone' => $member->phone,
                            'email'          => $member->email,
                            'status'         => 1,
                            'username'       => $member->username,
                            'password'       => $member->password,
                        ]);
                    }
                }

                DB::commit();
                $approved++;
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('bulkApprove member failed.', ['member_id' => $mid, 'e' => $e->getMessage()]);
                $failed++;
            }
        }

        $msg = "✅ Bulk approved {$approved} member(s).";
        if ($failed > 0) $msg .= " ({$failed} skipped/failed)";

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $msg,
                'approved_count' => $approved,
                'failed_count'   => $failed,
            ]);
        }

        return back()->with('success', $msg);
    }
}
