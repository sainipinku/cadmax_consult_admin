<?php

namespace App\Services;

use App\Models\Member;
use App\Models\Department;
use App\Models\Designation;
use App\Models\SuperAdmin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Jobs\SendAccountCreationEmail;
use App\Models\EmailLog;

class MemberService
{
    /**
     * Create or update a Member from validated data.
     * Returns [Member $member, string $plainPassword, string $message]
     */
    public function saveMember(array $validated, ?int $memberId = null, ?Request $request = null): array
    {
        $isUpdate = !is_null($memberId);
        $existingMember = $isUpdate ? Member::findOrFail($memberId) : null;

        $isCallingTeamMember = $existingMember?->is_calling_team ?? ($validated['is_calling_team'] ?? false);

        $requestRoles = collect($validated['roles'] ?? [])
            ->map(fn($role) => (int) $role)
            ->all();

        $plainPassword = $validated['password'] ?? null;

        $data = [
            'name' => $validated['name'],
            'phone' => $validated['phone'],
            'email' => $validated['email'] ?? null,
            'departments' => $isCallingTeamMember ? [] : ($validated['departments'] ?? []),
            'roles' => $isCallingTeamMember ? [] : ($validated['roles'] ?? []),
            'slug' => Str::slug($validated['name'] . '-' . Str::random(4)),
            'gender' => $validated['gender'] ?? null,
            'designation' => $isCallingTeamMember ? [] : ($validated['designations'] ?? []),
            'dob' => $validated['dob'] ?? null,
            'is_calling_team' => $isCallingTeamMember,
            'assigned_admin_id' => $validated['assigned_admin_id'] ?? null,
        ];

        if (!$isUpdate) {
            $data['username'] = $this->generateUsername($validated['name']);
        }

        if (!empty($plainPassword)) {
            $data['password'] = Hash::make($plainPassword);
        }

        if ($isUpdate) {
            $member = $existingMember;

            if (!empty($validated['image']) && $validated['image'] instanceof \Illuminate\Http\UploadedFile) {
                $oldImage = $member->getRawOriginal('image');
                if (!empty($oldImage) && !filter_var($oldImage, FILTER_VALIDATE_URL)) {
                    Storage::disk('public')->delete($oldImage);
                }
                $data['image'] = $validated['image']->store('member-images', 'public');
            }

            $member->update($data);
            $message = $isCallingTeamMember ? 'Calling team member updated successfully!' : 'Member updated successfully!';

            // Sync SuperAdmin if role 2
            if (in_array(2, $member->roles)) {
                $this->syncSuperAdmin($member, $data, $plainPassword);
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
                if (!empty($validated['image']) && $validated['image'] instanceof \Illuminate\Http\UploadedFile) {
                    $oldImage = $existing->getRawOriginal('image');
                    if (!empty($oldImage) && !filter_var($oldImage, FILTER_VALIDATE_URL)) {
                        Storage::disk('public')->delete($oldImage);
                    }
                    $data['image'] = $validated['image']->store('member-images', 'public');
                }
                $existing->update($data);
                $member = $existing;
                $message = $isCallingTeamMember ? 'Calling team member restored successfully!' : 'Member restored successfully!';
            } else {
                $data['created_by'] = $validated['created_by'] ?? auth('superadmin')->id();
                if (!empty($validated['image']) && $validated['image'] instanceof \Illuminate\Http\UploadedFile) {
                    $data['image'] = $validated['image']->store('member-images', 'public');
                }
                $member = Member::create($data);
                $message = $isCallingTeamMember ? 'Calling team member created successfully!' : 'Member created successfully!';
            }

            // Send account creation email
            if (!empty($validated['email']) && (!$existing || !$existing->wasRecentlyRestored)) {
                $requestObj = $request ?? request();
                $this->sendAccountCreationEmail(
                    $validated['email'],
                    $validated['name'],
                    $data['username'] ?? '',
                    $plainPassword,
                    $validated['departments'] ?? [],
                    $validated['designations'] ?? [],
                    $isCallingTeamMember,
                    in_array(1, $requestRoles, true) && !$isCallingTeamMember,
                    $requestObj
                );
            }
        }

        // Sync SuperAdmin if role 2
        if (in_array(2, $requestRoles, true) && !$isCallingTeamMember) {
            $this->syncSuperAdmin($member, $data, $plainPassword);
        }

        return [$member, $plainPassword, $message];
    }

    protected function syncSuperAdmin(Member $member, array $data, ?string $plainPassword): void
    {
        $existingSuperAdmin = SuperAdmin::where(function ($q) use ($member, $data) {
            $q->where('phone', $member->phone)
                ->orWhere('username', $data['username'] ?? null);
        })->first();

        if (!$existingSuperAdmin) {
            SuperAdmin::create([
                'name' => $member->name,
                'roles' => 'super',
                'phone' => $member->phone,
                'whatsapp_phone' => $member->phone,
                'status' => 1,
                'username' => $data['username'] ?? null,
                'password' => !empty($plainPassword) ? Hash::make($plainPassword) : $member->password,
            ]);
        }
    }

    protected function generateUsername(string $name, $excludeId = null): string
    {
        $cleanName = Str::lower(preg_replace('/[^a-z0-9]/', '', $name));
        $base = $cleanName;
        $counter = 0;

        do {
            $username = $base . ($counter > 0 ? $counter : '');
            $exists = Member::withTrashed()
                ->where('username', $username)
                ->when($excludeId, fn($q) => $q->where('id', '!=', $excludeId))
                ->exists();
            $counter++;
        } while ($exists);

        return $username;
    }

    protected function sendAccountCreationEmail(
        string $email,
        string $name,
        string $username,
        ?string $plainPassword,
        array $departmentIds,
        array $designationIds,
        bool $isCallingTeamMember,
        bool $isAdminMember,
        $request
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
            $plainPassword ?? 'N/A',
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
}