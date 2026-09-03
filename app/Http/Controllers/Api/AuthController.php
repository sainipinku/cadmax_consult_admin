<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Employee;
use App\Models\Member;
use App\Models\SuperAdmin;
use App\Enums\ActionTypeEnum;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20', 'unique:members,phone'],
            'email' => ['nullable', 'email', 'max:255', 'unique:members,email'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'password' => ['required', 'confirmed', Password::min(6)],
            'password_confirmation' => ['required', 'string', 'min:6'],
            'terms_agreed' => ['required', 'boolean', 'in:1'],
            'alternate_number' => ['nullable', 'string', 'max:20'],
            'aadhaar_number' => ['nullable', 'string', 'max:20', 'unique:employees,aadhaar_number'],
            'pan_number' => ['nullable', 'string', 'max:20', 'unique:employees,pan_number'],
        ]);

        $creatorId = SuperAdmin::query()->value('id');
        if (! $creatorId) {
            return response()->json([
                'success' => false,
                'message' => 'Super admin not configured. System setup incomplete.',
            ], 500);
        }

        $usernameBase = 'user' . preg_replace('/\D+/', '', $validated['phone']);
        $username = $this->makeUnique('members', 'username', substr($usernameBase, 0, 20));

        $slugBase = 'member-' . preg_replace('/\D+/', '', $validated['phone']);
        $slug = $this->makeUnique('members', 'slug', Str::slug($slugBase));

        $member = null;
        $employee = null;

        DB::transaction(function () use ($validated, $creatorId, $username, $slug, $request, &$member, &$employee) {
            $member = Member::create([
                'uuid' => (string) Str::uuid(),
                'created_by' => $creatorId,
                'name' => $validated['name'],
                'username' => $username,
                'email' => $validated['email'] ?? null,
                'phone' => $validated['phone'],
                'company_name' => $validated['company_name'] ?? null,
                'state' => $validated['state'] ?? null,
                'city' => $validated['city'] ?? null,
                'password' => Hash::make($validated['password']),
                'status' => Member::STATUS_PENDING,
                'registration_source' => $request->isJson() || $request->wantsJson() || $request->header('X-Mobile-App')
                    ? 'mobile_api'
                    : 'web',
                'roles' => [],
                'departments' => [],
                'designation' => [],
                'slug' => $slug,
            ]);

            $employee = Employee::create([
                'member_id' => $member->id,
                'alternate_number' => $validated['alternate_number'] ?? null,
                'aadhaar_number' => $validated['aadhaar_number'] ?? null,
                'pan_number' => $validated['pan_number'] ?? null,
            ]);
        });

        $this->issueOtp($member);

        $payload = [
            'success' => true,
            'message' => 'Registration submitted successfully. Your account is pending admin approval.',
            'member_id' => $member->id,
            'member_uuid' => $member->uuid,
            'employee_id' => $employee->employee_id,
            'employee_uuid' => $employee->uuid,
            'status' => 'pending_approval',
            'status_text' => 'Pending Admin Approval',
            'estimated_time' => 'Typically within 24-48 hours',
        ];

        if (config('app.debug')) {
            $payload['otp'] = $member->otp;
            $payload['otp_expire_at'] = optional($member->otp_expire)->toISOString();
        }

        return response()->json($payload, 201);
    }

    public function registrationStatus(Request $request)
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
        ]);

        $member = Member::query()->with('employee')->where('phone', $validated['phone'])->first();

        if (! $member) {
            return response()->json([
                'success' => false,
                'message' => 'No registration found for this phone number.',
                'registered' => false,
            ], 404);
        }

        $statusMap = [
            Member::STATUS_PENDING => [
                'key' => 'pending_approval',
                'text' => 'Pending Admin Approval',
                'description' => 'Your account is awaiting review by our admin team.',
                'can_login' => false,
            ],
            Member::STATUS_ACTIVE => [
                'key' => 'approved',
                'text' => 'Account Approved',
                'description' => $member->approved_at
                    ? 'Your account was approved on ' . optional($member->approved_at)->format('M d, Y') . '.'
                    : 'Your account is active and ready to use.',
                'can_login' => true,
                'has_roles' => !empty($member->roles),
                'role_names' => $member->role_names,
            ],
            Member::STATUS_REJECTED => [
                'key' => 'rejected',
                'text' => 'Registration Rejected',
                'description' => $member->approval_remark
                    ? 'Reason: ' . $member->approval_remark
                    : 'Unfortunately, your registration was not approved.',
                'can_login' => false,
            ],
        ];

        $statusInfo = $statusMap[$member->status] ?? $statusMap[Member::STATUS_PENDING];

        return response()->json([
            'success' => true,
            'registered' => true,
            'member' => [
                'uuid' => $member->uuid,
                'name' => $member->name,
                'phone' => $member->phone,
                'email' => $member->email,
                'company_name' => $member->company_name,
                'state' => $member->state,
                'city' => $member->city,
                'employee_id' => $member->employee?->employee_id,
                'alternate_number' => $member->employee?->alternate_number,
                'aadhaar_number' => $member->employee?->aadhaar_number,
                'pan_number' => $member->employee?->pan_number,
            ],
            'approval' => $statusInfo + [
                'code' => (int) $member->status,
                'registered_at' => optional($member->created_at)->toISOString(),
                'approved_at' => optional($member->approved_at)->toISOString(),
                'rejected_at' => optional($member->rejected_at)->toISOString(),
                'approval_remark' => $member->approval_remark,
            ],
        ]);
    }

    public function login(Request $request)
    {
        $validated = $request->validate([
            'identifier' => ['required', 'string'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        $identifier = $validated['identifier'];

        $member = Member::query()
            ->where('email', $identifier)
            ->orWhere('phone', $identifier)
            ->orWhere('username', $identifier)
            ->first();

        if (! $member) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials.',
            ], 422);
        }

        if ($member->isPending()) {
            return response()->json([
                'success' => false,
                'message' => 'Your account is pending admin approval. You will be notified once approved.',
                'error_code' => 'PENDING_APPROVAL',
                'can_check_status' => true,
            ], 403);
        }

        if ($member->isRejected()) {
            return response()->json([
                'success' => false,
                'message' => 'Your registration was not approved.' . ($member->approval_remark ? " Reason: {$member->approval_remark}" : ''),
                'error_code' => 'ACCOUNT_REJECTED',
            ], 403);
        }

        if (! $member->isActive()) {
            return response()->json([
                'success' => false,
                'message' => 'Your account is inactive. Please contact admin.',
            ], 403);
        }

        if ($member->is_calling_team) {
            return response()->json([
                'success' => false,
                'message' => 'Use the calling team portal to access your account.',
            ], 403);
        }

        $roles = is_array($member->roles) ? $member->roles : [];
        if (empty($roles)) {
            return response()->json([
                'success' => false,
                'message' => 'No role assigned. Please contact admin to assign your construction role (Driver, Surveyor, Draftsman, Supervisor).',
                'error_code' => 'NO_ROLE_ASSIGNED',
            ], 403);
        }

        if (! Hash::check($validated['password'], $member->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials.',
            ], 422);
        }

        $tokenName = $validated['device_name'] ?? ($request->userAgent() ?: 'api-token');
        $member->tokens()->where('name', $tokenName)->delete();

        $token = $member->createToken($tokenName)->plainTextToken;

        ActivityLog::create([
            'user_id'     => $member->id,
            'user_role'   => 'doer',
            'action_type' => ActionTypeEnum::LOGIN,
            'description' => 'Member logged in via API (' . $tokenName . ')',
            'ip_address'  => $request->ip(),
            'user_agent'  => $request->userAgent(),
            'action_time' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Login successful.',
            'token' => $token,
            'token_type' => 'Bearer',
            'member' => $member->loadMissing(['employee', 'fcm_token']),
            'role_names' => $member->role_names,
            'department_names' => $member->department_names,
            'designation_names' => $member->designation_names,
            'must_change_password' => (bool) $member->must_change_password,
        ]);
    }

    public function sendOtp(Request $request)
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
        ]);

        $member = Member::query()->where('phone', $validated['phone'])->first();

        if (! $member) {
            return response()->json([
                'success' => false,
                'message' => 'No account found for this phone number.',
            ], 404);
        }

        if ($member->isPending()) {
            return response()->json([
                'success' => false,
                'message' => 'Your account is pending admin approval. OTP login is not available until approved.',
                'error_code' => 'PENDING_APPROVAL',
            ], 403);
        }

        if ($member->isRejected()) {
            return response()->json([
                'success' => false,
                'message' => 'Your registration was not approved.',
            ], 403);
        }

        if (! $member->isActive()) {
            return response()->json([
                'success' => false,
                'message' => 'Account is not active.',
            ], 403);
        }

        $this->issueOtp($member);

        $payload = [
            'success' => true,
            'message' => 'OTP sent successfully.',
        ];

        if (config('app.debug')) {
            $payload['otp'] = $member->otp;
            $payload['otp_expire_at'] = optional($member->otp_expire)->toISOString();
        }

        return response()->json($payload);
    }

    public function verifyOtp(Request $request)
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
            'otp' => ['required', 'string', 'max:10'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        $member = Member::query()->where('phone', $validated['phone'])->first();

        if (!$member) {
            return response()->json([
                'success' => false,
                'message' => 'No account found for this phone number.',
            ], 404);
        }

        // if ($member->isPending()) {
        //     return response()->json([
        //         'success' => false,
        //         'message' => 'Your account is pending admin approval.',
        //         'error_code' => 'PENDING_APPROVAL',
        //     ], 403);
        // }

        // if ($member->isRejected()) {
        //     return response()->json([
        //         'success' => false,
        //         'message' => 'Your registration was not approved.',
        //     ], 403);
        // }

        // if (! $member->isActive()) {
        //     return response()->json([
        //         'success' => false,
        //         'message' => 'Account is not active.',
        //     ], 403);
        // }

        if (! $member->otp || ! $member->otp_expire || $member->otp_expire->isPast()) {
            return response()->json([
                'success' => false,
                'message' => 'OTP expired. Please request a new OTP.',
            ], 422);
        }

        if ((string) $member->otp !== (string) $validated['otp']) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid OTP.',
            ], 422);
        }

        $member->forceFill([
            'otp' => null,
            'otp_expire' => null,
            'phone_verify_at' => now(),
        ])->save();

        $roles = is_array($member->roles) ? $member->roles : [];
        // if (empty($roles)) {
        //     return response()->json([
        //         'success' => false,
        //         'message' => 'No role assigned. Please contact admin.',
        //         'error_code' => 'NO_ROLE_ASSIGNED',
        //     ], 403);
        // }

        $tokenName = $validated['device_name'] ?? ($request->userAgent() ?: 'api-token');
        $token = $member->createToken($tokenName)->plainTextToken;

        ActivityLog::create([
            'user_id'     => $member->id,
            'user_role'   => 'doer',
            'action_type' => ActionTypeEnum::LOGIN,
            'description' => 'Member logged in via OTP verification (' . $tokenName . ')',
            'ip_address'  => $request->ip(),
            'user_agent'  => $request->userAgent(),
            'action_time' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'OTP verified successfully.',
            'token' => $token,
            'token_type' => 'Bearer',
            'member' => $member->loadMissing(['employee', 'fcm_token']),
            'role_names' => $member->role_names,
        ]);
    }

    public function me(Request $request)
    {
        $member = $request->user();
        $member->loadMissing(['employee', 'fcm_token', 'approver:id,name']);

        return response()->json([
            'success' => true,
            'member' => $member,
            'role_names' => $member->role_names,
            'department_names' => $member->department_names,
            'designation_names' => $member->designation_names,
            'status_info' => [
                'code' => (int) $member->status,
                'text' => match((int) $member->status) {
                    Member::STATUS_PENDING => 'Pending Approval',
                    Member::STATUS_ACTIVE => 'Active',
                    Member::STATUS_REJECTED => 'Rejected',
                    default => 'Unknown',
                },
            ],
        ]);
    }

    public function logout(Request $request)
    {
        $member = $request->user();
        $token = $member?->currentAccessToken();

        if ($token) {
            $tokenName = $token->name;

            ActivityLog::create([
                'user_id'     => $member->id,
                'user_role'   => 'doer',
                'action_type' => ActionTypeEnum::LOGOUT,
                'description' => 'Member logged out from API (' . $tokenName . ')',
                'ip_address'  => $request->ip(),
                'user_agent'  => $request->userAgent(),
                'action_time' => now(),
            ]);

            $token->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully.',
        ]);
    }

    private function issueOtp(Member $member): void
    {
        $otp = config('app.debug') ? '123456' : (string) random_int(100000, 999999);
        $member->forceFill([
            'otp' => $otp,
            'otp_expire' => now()->addMinutes(5),
        ])->save();
    }

    private function makeUnique(string $table, string $column, string $base): string
    {
        $value = $base;
        $i = 1;

        while (\Illuminate\Support\Facades\DB::table($table)->where($column, $value)->exists()) {
            $suffix = (string) $i;
            $value = substr($base, 0, max(0, 255 - strlen($suffix) - 1)) . '-' . $suffix;
            $i++;
        }

        return $value;
    }
}
