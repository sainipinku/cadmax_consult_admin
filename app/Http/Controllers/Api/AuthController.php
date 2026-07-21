<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\SuperAdmin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20', 'unique:members,phone'],
            'email' => ['nullable', 'email', 'max:255', 'unique:members,email'],
        ]);

        $creatorId = SuperAdmin::query()->value('id');
        if (! $creatorId) {
            return response()->json([
                'success' => false,
                'message' => 'Super admin not configured.',
            ], 500);
        }

        $usernameBase = 'user' . preg_replace('/\D+/', '', $validated['phone']);
        $username = $this->makeUnique('members', 'username', substr($usernameBase, 0, 20));

        $slugBase = 'member-' . preg_replace('/\D+/', '', $validated['phone']);
        $slug = $this->makeUnique('members', 'slug', Str::slug($slugBase));

        $member = Member::create([
            'uuid' => (string) Str::uuid(),
            'created_by' => $creatorId,
            'name' => $validated['name'],
            'username' => $username,
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'],
            'password' => Str::random(32),
            'status' => '1',
            'roles' => [3],
            'slug' => $slug,
        ]);

        $this->issueOtp($member);

        $payload = [
            'success' => true,
            'message' => 'Registered. OTP sent.',
            'member_id' => $member->id,
        ];

        if (config('app.debug')) {
            $payload['otp'] = $member->otp;
            $payload['otp_expire_at'] = optional($member->otp_expire)->toISOString();
        }

        return response()->json($payload);
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

        if (! $member || $member->status != 1) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials.',
            ], 422);
        }

        if (! Hash::check($validated['password'], $member->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials.',
            ], 422);
        }

        $tokenName = $validated['device_name'] ?? ($request->userAgent() ?: 'api');
        $token = $member->createToken($tokenName)->plainTextToken;

        return response()->json([
            'success' => true,
            'token' => $token,
            'member' => $member,
        ]);
    }

    public function sendOtp(Request $request)
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
        ]);

        $member = Member::query()->where('phone', $validated['phone'])->first();

        if (! $member || $member->status != 1) {
            return response()->json([
                'success' => false,
                'message' => 'Account not found.',
            ], 404);
        }

        $this->issueOtp($member);

        $payload = [
            'success' => true,
            'message' => 'OTP sent.',
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

        if (! $member || $member->status != 1) {
            return response()->json([
                'success' => false,
                'message' => 'Account not found.',
            ], 404);
        }

        if (! $member->otp || ! $member->otp_expire || $member->otp_expire->isPast()) {
            return response()->json([
                'success' => false,
                'message' => 'OTP expired.',
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

        $tokenName = $validated['device_name'] ?? ($request->userAgent() ?: 'api');
        $token = $member->createToken($tokenName)->plainTextToken;

        return response()->json([
            'success' => true,
            'token' => $token,
            'member' => $member,
        ]);
    }

    public function me(Request $request)
    {
        return response()->json([
            'success' => true,
            'member' => $request->user(),
        ]);
    }

    public function logout(Request $request)
    {
        $token = $request->user()?->currentAccessToken();

        if ($token) {
            $token->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Logged out.',
        ]);
    }

    private function issueOtp(Member $member): void
    {
        $member->forceFill([
            'otp' => '123456',
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
