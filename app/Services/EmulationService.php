<?php

namespace App\Services;

use App\Models\Member;
use App\Models\SuperAdmin;
use App\Models\User;
use App\Models\ActivityLog;
use App\Enums\ActionTypeEnum;
use App\Services\Construction\ConstructionActivityService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class EmulationService
{
    /**
     * Start impersonation / emulation for a given target user.
     */
    public function startEmulation(string $targetType, string|int $targetId): array
    {
        $currentGuard = $this->getCurrentGuard();
        $currentUser = Auth::guard($currentGuard)->user();

        if (!$currentUser) {
            return ['success' => false, 'message' => 'You must be logged in to emulate a user.'];
        }

        // Check permission
        if ($currentGuard !== 'superadmin' && $currentGuard !== 'admin') {
            return ['success' => false, 'message' => 'Unauthorized: Only Super Admin and Admin can perform emulation.'];
        }

        if ($currentGuard === 'admin' && in_array($targetType, ['superadmin', 'admin'], true)) {
            return ['success' => false, 'message' => 'Admins can only emulate Member panel users.'];
        }

        $targetUser = $this->resolveTargetUser($targetType, $targetId);

        if (!$targetUser) {
            return ['success' => false, 'message' => 'Target user to emulate was not found.'];
        }

        $targetGuard = $this->resolveTargetGuard($targetType, $targetUser);

        // Capture or preserve impersonator info BEFORE logout
        $impersonator = Session::get('impersonator');
        if (!$impersonator) {
            $impersonator = [
                'id' => $currentUser->id,
                'guard' => $currentGuard,
                'name' => $currentUser->name,
                'email' => $currentUser->email ?? null,
                'role' => $currentGuard === 'superadmin' ? 'Super Admin' : 'Admin',
            ];
        }

        // Logout ALL active guards (which flushes session) to ensure clean guard state
        foreach (['superadmin', 'admin', 'member', 'callingteam', 'web'] as $g) {
            if (Auth::guard($g)->check()) {
                Auth::guard($g)->logout();
            }
        }

        // Login as target user under their target guard
        Auth::guard($targetGuard)->login($targetUser);
        Session::regenerate();

        // Save impersonator back into session AFTER guard login/regenerate
        Session::put('impersonator', $impersonator);
        if ($targetUser instanceof Member) {
            $assignedRoles = $targetUser->assigned_roles;
            if (!empty($assignedRoles)) {
                Session::put('active_role', $assignedRoles[0]);
            }
        }
        Session::save();

        try {
            app(ConstructionActivityService::class)->log(
                module: 'Emulation',
                action: 'start_emulation',
                actor: $currentUser,
                reference: $targetUser,
                meta: [
                    'impersonator_id' => $impersonator['id'],
                    'impersonator_role' => $impersonator['role'],
                    'target_user' => $targetUser->name,
                    'target_guard' => $targetGuard,
                ],
                request: request()
            );

            if ($currentUser instanceof Member) {
                ActivityLog::create([
                    'user_id' => $currentUser->id,
                    'user_role' => $impersonator['role'] ?? 'Admin',
                    'action_type' => ActionTypeEnum::LOGIN,
                    'description' => 'Started emulation for ' . $targetUser->name . ' (' . ucfirst($targetGuard) . ')',
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'action_time' => now(),
                ]);
            }
        } catch (\Throwable $e) {
            // Fail safe logging
        }

        $redirectUrl = $this->determineDashboardRoute($targetGuard, $targetUser);

        return [
            'success' => true,
            'message' => 'Now emulating user: ' . $targetUser->name,
            'redirect' => $redirectUrl,
        ];
    }

    /**
     * Exit emulation mode and restore original user session.
     */
    public function exitEmulation(): array
    {
        if (!Session::has('impersonator')) {
            return ['success' => false, 'message' => 'No active emulation session found.'];
        }

        $impersonator = Session::get('impersonator');

        // Logout ALL active guards
        foreach (['superadmin', 'admin', 'member', 'callingteam', 'web'] as $g) {
            if (Auth::guard($g)->check()) {
                Auth::guard($g)->logout();
            }
        }

        $originalGuard = $impersonator['guard'] ?? 'superadmin';
        $originalUser = $this->resolveTargetUser($originalGuard, $impersonator['id']);

        if (!$originalUser) {
            Session::forget('impersonator');
            return ['success' => false, 'message' => 'Original user session could not be restored.', 'redirect' => route('login')];
        }

        Auth::guard($originalGuard)->login($originalUser);
        Session::forget('impersonator');
        Session::save();

        try {
            app(ConstructionActivityService::class)->log(
                module: 'Emulation',
                action: 'exit_emulation',
                actor: $originalUser,
                meta: [
                    'impersonator_id' => $impersonator['id'],
                    'impersonator_role' => $impersonator['role'] ?? 'Impersonator',
                ],
                request: request()
            );

            if ($originalUser instanceof Member) {
                ActivityLog::create([
                    'user_id' => $originalUser->id,
                    'user_role' => $impersonator['role'] ?? 'Admin',
                    'action_type' => ActionTypeEnum::LOGOUT,
                    'description' => 'Exited emulation mode',
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'action_time' => now(),
                ]);
            }
        } catch (\Throwable $e) {
            // Fail safe logging
        }

        $redirectUrl = match ($originalGuard) {
            'superadmin' => route('super.dashboard'),
            'admin' => route('admin.dashboard'),
            default => route('login'),
        };

        return [
            'success' => true,
            'message' => 'Exited emulation mode. Welcome back, ' . $originalUser->name,
            'redirect' => $redirectUrl,
        ];
    }

    public function getCurrentGuard(): ?string
    {
        if (Auth::guard('superadmin')->check()) {
            return 'superadmin';
        }
        if (Auth::guard('admin')->check()) {
            return 'admin';
        }
        if (Auth::guard('member')->check()) {
            return 'member';
        }
        if (Auth::guard('callingteam')->check()) {
            return 'callingteam';
        }
        if (Auth::guard('web')->check()) {
            return 'web';
        }
        return null;
    }

    protected function resolveTargetGuard(string $targetType, $targetUser = null): string
    {
        $type = strtolower($targetType);

        if ($targetUser instanceof SuperAdmin || in_array($type, ['superadmin', 'super_admin'], true)) {
            return 'superadmin';
        }

        if ($type === 'admin' || ($targetUser instanceof Member && $targetUser->isAdmin())) {
            return 'admin';
        }

        if (in_array($type, ['callingteam', 'calling_team'], true) || ($targetUser instanceof Member && method_exists($targetUser, 'isCallingTeam') && $targetUser->isCallingTeam())) {
            return 'callingteam';
        }

        if (in_array($type, ['member', 'employee'], true)) {
            if ($targetUser instanceof Member && $targetUser->isAdmin()) {
                return 'admin';
            }
            return 'member';
        }

        if ($targetUser instanceof Member) {
            if ($targetUser->isAdmin()) {
                return 'admin';
            }
            if (method_exists($targetUser, 'isCallingTeam') && $targetUser->isCallingTeam()) {
                return 'callingteam';
            }
            return 'member';
        }

        return 'member';
    }

    protected function resolveTargetUser(string $targetType, string|int $targetId)
    {
        $guard = $this->resolveTargetGuard($targetType);

        if ($guard === 'superadmin') {
            $user = SuperAdmin::where('id', $targetId)->orWhere('uuid', $targetId)->first();
            if ($user) {
                return $user;
            }
        }

        $member = Member::where('id', $targetId)->orWhere('uuid', $targetId)->first();
        if ($member) {
            return $member;
        }

        return SuperAdmin::where('id', $targetId)->orWhere('uuid', $targetId)->first()
            ?? User::where('id', $targetId)->orWhere('uuid', $targetId)->first();
    }

    protected function determineDashboardRoute(string $guard, $user): string
    {
        if ($guard === 'superadmin') {
            return route('super.dashboard');
        }

        if ($guard === 'admin' || ($user instanceof Member && $user->isAdmin())) {
            return route('admin.dashboard');
        }

        if ($guard === 'callingteam') {
            return route('callingteam.dashboard');
        }

        if ($guard === 'member' || $user instanceof Member) {
            if ($user instanceof Member && $user->is_calling_team) {
                return route('callingteam.dashboard');
            }

            if ($user instanceof Member && $user->isAdmin()) {
                return route('admin.dashboard');
            }

            $activeRole = Session::get('active_role');
            if (!$activeRole && $user instanceof Member && !empty($user->assigned_roles)) {
                $activeRole = $user->assigned_roles[0];
                Session::put('active_role', $activeRole);
            }

            if ($activeRole) {
                return match (strtolower($activeRole)) {
                    'surveyor', 'survey_man' => route('member.construction.dashboard', ['role' => 'surveyor']),
                    'site_employee', 'execution' => route('member.construction.execution.index'),
                    'vehicle_driver', 'driver' => route('member.construction.vehicles.index'),
                    'draft_person', 'draft_man' => route('member.construction.dashboard', ['role' => 'draft_person']),
                    'admin', 'project_admin', 'super_admin' => route('admin.dashboard'),
                    default => route('member.construction.dashboard', ['role' => $activeRole]),
                };
            }

            return route('member.construction.dashboard');
        }

        return route('login');
    }
}
