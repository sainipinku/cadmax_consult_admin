<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Member extends Authenticatable
{
    use HasApiTokens, HasFactory, SoftDeletes, HasUuids, Notifiable;

    protected $fillable = [
        'uuid',
        'created_by',
        'assigned_admin_id',
        'is_calling_team',
        'name',
        'username',
        'email',
        'phone',
        'password',
        'must_change_password',
        'status',
        'roles',
        'departments',
        'designation',
        'slug',
        'otp',
        'otp_expire',
        'dob',
        'gender',
        'phone_verify_at',
        'image',
        'candidate_profile',
        'resume_path',
        'resume_original_name',
        'resume_mime',
        'resume_size',
        'resume_uploaded_at',
        'remember_token',
        'reset_password_token',
        'reset_password_token_expires_at',
        'company_name',
        'state',
        'city',
        'registration_source',
        'approved_by',
        'approved_at',
        'approval_remark',
        'rejected_at',
    ];

    protected $casts = [
        'roles' => 'array',
        'departments' => 'array',
        'designation' => 'array',
        'otp_expire' => 'datetime',
        'dob' => 'date',
        'phone_verify_at' => 'datetime',
        'reset_password_token_expires_at' => 'datetime',
        'password' => 'hashed',
        'candidate_profile' => 'array',
        'resume_uploaded_at' => 'datetime',
        'resume_size' => 'integer',
        'is_calling_team' => 'boolean',
        'must_change_password' => 'boolean',
        'status' => 'integer',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    public function getIsDriverAttribute(): int
    {
        return $this->isDriver() ? 1 : 0;
    }

    public function isDriver(): bool
    {
        $driverRoleIds = \App\Models\ConstructionRole::where('slug', 'vehicle_driver')
            ->orWhere('name', 'Driver')
            ->pluck('id');

        if ($driverRoleIds->isNotEmpty()) {
            $isAssigned = \App\Models\MemberRoleAssignment::whereIn('role_id', $driverRoleIds)
                ->where('member_id', $this->id)
                ->where('status', 1)
                ->exists();

            if ($isAssigned) {
                return true;
            }
        }

        if (!empty($this->roles)) {
            $rolesArr = is_array($this->roles) ? $this->roles : [$this->roles];
            foreach ($rolesArr as $r) {
                if (is_string($r) && in_array(strtolower(trim($r)), ['vehicle_driver', 'driver', 'vehicle driver'], true)) {
                    return true;
                }
                if (is_numeric($r)) {
                    $rName = \App\Models\ConstructionRole::where('id', $r)->value('name')
                        ?? \App\Models\Role::where('id', $r)->value('name');
                    if ($rName && str_contains(strtolower($rName), 'driver')) {
                        return true;
                    }
                }
            }
        }

        if (!empty($this->designation)) {
            if (is_array($this->designation)) {
                foreach ($this->designation as $d) {
                    if (is_string($d) && str_contains(strtolower($d), 'driver')) {
                        return true;
                    }
                    if (is_numeric($d)) {
                        $desigName = \App\Models\Designation::where('id', $d)->value('name');
                        if ($desigName && str_contains(strtolower($desigName), 'driver')) {
                            return true;
                        }
                    }
                }
            } elseif (is_string($this->designation) && str_contains(strtolower($this->designation), 'driver')) {
                return true;
            }
        }

        return false;
    }

    public const STATUS_PENDING = 0;
    public const STATUS_ACTIVE = 1;
    public const STATUS_REJECTED = 2;

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeRejected($query)
    {
        return $query->where('status', self::STATUS_REJECTED);
    }

    public function scopeSelfRegistered($query)
    {
        return $query->whereIn('registration_source', ['web', 'mobile_api']);
    }

    public function isPending(): bool
    {
        return (int) $this->status === self::STATUS_PENDING;
    }

    public function isActive(): bool
    {
        return (int) $this->status === self::STATUS_ACTIVE;
    }

    public function isRejected(): bool
    {
        return (int) $this->status === self::STATUS_REJECTED;
    }

    public function approver()
    {
        return $this->belongsTo(SuperAdmin::class, 'approved_by');
    }

    public function uniqueIds()
    {
        return ['uuid'];
    }


    public function creator()
    {
        return $this->belongsTo(Member::class, 'created_by');
    }

    public function assignedAdmin()
    {
        return $this->belongsTo(Member::class, 'assigned_admin_id');
    }

    public function callingTeamAssignments()
    {
        return $this->hasMany(JobApplication::class, 'assigned_calling_team_member_id');
    }


    protected $appends = [
        'profile_photo_url',
        'resume_url',
        'current_age',
        'department_names',
        'designation_names',
        'role_names',
        'employee_code',
        'is_driver',
        'is_admin',
        'assigned_roles',
        'assigned_role_names',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'member_roles', 'member_id', 'role_id');
    }

    /**
     * Scope to filter members by role ID
     */
    public function scopeHasRole($query, $roleId)
    {
        return $query->where(function($q) use ($roleId) {
            $q->whereJsonContains('roles', (string) $roleId);
        });
    }

    /**
     * Scope to filter members by role slug
     */
    public function scopeHasRoleBySlug($query, $slug)
    {
        return $query->whereHas('roles', function($q) use ($slug) {
            $q->where('slug', $slug);
        });
    }

    public function dob(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                return $value ? Carbon::parse($value)->format('Y-m-d') : null;
            }
        );
    }

    public function profilePhotoUrl(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (!empty($this->image)) {
                    if (filter_var($this->image, FILTER_VALIDATE_URL)) {
                        return $this->image;
                    }

                    if (Storage::disk('public')->exists($this->image)) {
                        return Storage::disk('public')->url($this->image);
                    }
                }

                return asset('images/profileimg.png');
            }
        );
    }

    public function resumeUrl(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (empty($this->resume_path)) {
                    return null;
                }

                if (filter_var($this->resume_path, FILTER_VALIDATE_URL)) {
                    return $this->resume_path;
                }

                if (Storage::disk('public')->exists($this->resume_path)) {
                    return Storage::disk('public')->url($this->resume_path);
                }

                return null;
            }
        );
    }

    public function skills(): Attribute
    {
        return Attribute::make(
            get: function () {
                $candidate = is_array($this->candidate_profile) ? $this->candidate_profile : [];
                $skills = $candidate['skills'] ?? null;

                return is_array($skills) ? $skills : null;
            }
        );
    }

    public function experience(): Attribute
    {
        return Attribute::make(
            get: function () {
                $candidate = is_array($this->candidate_profile) ? $this->candidate_profile : [];

                return $candidate['experience'] ?? ($candidate['experience_label'] ?? null);
            }
        );
    }
   public function notify_tokens()
    {
        return $this->hasMany(FcmToken::class, 'user_id');
    }

    public function fcm_token()
    {
        return $this->hasOne(FcmToken::class,'user_id')->latestOfMany();
    }

    public function currentAge(): Attribute
    {
        return Attribute::make(
            get: function () {
                return $this->dob ? (int)Carbon::parse($this->dob)->diffInYears(Carbon::now()) : 0;
            }
        );
    }
    public function isAdmin(): bool
    {
        if ($this->slug === 'admin') {
            return true;
        }
        $assignedSlugs = $this->assigned_role_details['slugs'] ?? [];
        if (is_array($assignedSlugs) && in_array('admin', $assignedSlugs, true)) {
            return true;
        }
        if (is_array($this->roles) && (in_array(1, $this->roles, true) || in_array('1', $this->roles, true))) {
            return true;
        }
        return false;
    }
 public function isSuperAdmin(): bool
    {
        return $this->id === 2 || $this->slug === 'super-admin';
    }
    /**
     * Check if the role is a member role
     */
    public function isMember(): bool
    {
        return $this->id === 3 || $this->slug === 'member';
    }

    public function isCallingTeam(): bool
    {
        return (bool) $this->is_calling_team;
    }


    public function getRoleName($roleId)
    {
        // Get role name from relationship
        return $this->roles()->where('id', $roleId)->value('name');
    }

    public function getGuardName(): string
    {
        if ($this->is_calling_team) {
            return 'callingteam';
        }

        return match ($this->id) {
            1 => 'admin',
            3 => 'member',
            default => 'web',
        };
    }

    /**
     * Get the dashboard route for this role
     */
    public function getDashboardRoute(): string
    {
        if ($this->is_calling_team) {
            return 'callingteam.dashboard';
        }

        return match ($this->id) {
            1 => 'admin.dashboard',
            3 => 'member.dashboard',
            default => 'home',
        };
    }
    public function employee()
    {
        return $this->hasOne(Employee::class, 'member_id');
    }

    public function departmentList()
    {
        return $this->belongsToMany(Department::class, 'member_department', 'member_id', 'department_id')
            ->withTimestamps();
    }

    public function designationList()
    {
        return $this->belongsToMany(Designation::class, 'member_designation', 'member_id', 'designation_id')
            ->withTimestamps();
    }
    public function getDepartmentNamesAttribute()
    {
        if (is_array($this->departments)) {
            return Department::whereIn('id', $this->departments)->pluck('name')->implode(', ');
        }
        return '';
    }
    public function getAssignedRoleDetailsAttribute(): array
    {
        $assignments = \App\Models\MemberRoleAssignment::where('member_id', $this->id)
            ->where('status', 1)
            ->with('role')
            ->get();

        $assignedSlugs = [];
        $assignedNames = [];

        foreach ($assignments as $assignment) {
            if ($assignment->role) {
                $slug = $assignment->role->slug;
                $name = match ($slug) {
                    'surveyor' => 'Survey Man',
                    'vehicle_driver' => 'Driver',
                    'draft_person' => 'Draft Man',
                    default => $assignment->role->name,
                };
                $assignedSlugs[] = $slug;
                $assignedNames[] = $name;
            }
        }

        return [
            'slugs' => array_values(array_unique($assignedSlugs)),
            'names' => array_values(array_unique($assignedNames)),
        ];
    }

    public function getIsAdminAttribute(): bool
    {
        return $this->isAdmin();
    }

    public function getAssignedRolesAttribute(): array
    {
        $slugs = $this->assigned_role_details['slugs'];
        if (!empty($slugs)) {
            return $slugs;
        }

        if ($this->isAdmin() || $this->slug === 'admin') {
            return ['admin'];
        }

        if (is_array($this->roles) && !empty($this->roles)) {
            $roleSlugs = \App\Models\ConstructionRole::whereIn('id', $this->roles)
                ->pluck('slug')
                ->toArray();
            if (!empty($roleSlugs)) {
                return $roleSlugs;
            }
        }

        return [$this->slug ?? 'member'];
    }

    public function getAssignedRoleNamesAttribute(): array
    {
        $names = $this->assigned_role_details['names'];
        if (!empty($names)) {
            return $names;
        }

        if (is_array($this->roles) && !empty($this->roles)) {
            $systemRoleNames = Role::whereIn('id', $this->roles)->pluck('name')->toArray();
            if (!empty($systemRoleNames)) {
                return $systemRoleNames;
            }
        }

        return ['Member'];
    }

    public function getRoleNamesAttribute()
    {
        $assignedNames = $this->assigned_role_names;
        if (!empty($assignedNames)) {
            return implode(', ', $assignedNames);
        }

        if (is_array($this->roles) && !empty($this->roles)) {
            return Role::whereIn('id', $this->roles)->pluck('name')->implode(', ');
        }

        return '';
    }

    public function getDesignationNamesAttribute()
    {
        if (is_array($this->designation)) {
            return Designation::whereIn('id', $this->designation)->pluck('name')->implode(', ');
        }
        return '';
    }

    public function appNotifications(string $model = 'member'): HasMany
    {
        return $this->hasMany(Notification::class, 'listing_id')
            ->where('model', $model);
    }

    public function unreadAppNotifications(string $model = 'member'): HasMany
    {
        return $this->appNotifications($model)
            ->where('status', 'unread')
            ->whereNull('viewed_at');
    }

    public function unreadAppNotificationsCount(string $model = 'member'): int
    {
        return $this->unreadAppNotifications($model)->count();
    }

    public function markAppNotificationAsRead(string $uuid, string $model = 'member'): bool
    {
        $notification = $this->appNotifications($model)->where('uuid', $uuid)->first();
        if (!$notification) {
            return false;
        }

        $notification->update([
            'status' => 'read',
            'viewed_at' => $notification->viewed_at ?? now(),
        ]);

        return true;
    }

    public function markAllAppNotificationsAsRead(string $model = 'member'): int
    {
        return $this->unreadAppNotifications($model)->update([
            'status' => 'read',
            'viewed_at' => now(),
        ]);
    }

    public function deleteAppNotification(string $uuid, string $model = 'member'): bool
    {
        $notification = $this->appNotifications($model)->where('uuid', $uuid)->first();
        if (!$notification) {
            return false;
        }

        $notification->delete();
        return true;
    }

    public function deleteAllAppNotifications(string $model = 'member'): int
    {
        return $this->appNotifications($model)->delete();
    }

    public function generatePasswordResetToken()
    {
        $this->reset_password_token = Str::random(60);
        $this->reset_password_token_expires_at = now()->addMinutes(10);
        $this->save();

        return $this->reset_password_token;
    }

    public function clearPasswordResetToken()
    {
        $this->reset_password_token = null;
        $this->reset_password_token_expires_at = null;
        $this->save();
    }

    public function getEmployeeCodeAttribute(): string
    {
        if ($this->relationLoaded('employee') && $this->employee && !empty($this->employee->employee_id)) {
            return 'Employee ID: ' . $this->employee->employee_id;
        }

        $emp = Employee::where('member_id', $this->id)->first();
        if ($emp && !empty($emp->employee_id)) {
            return 'Employee ID: ' . $emp->employee_id;
        }

        // Standardized fallback formatted as shown in screenshot (e.g. EMP00125)
        $cleanId = sprintf('%05d', abs(crc32($this->id)) % 100000);
        return 'Employee ID: EMP' . $cleanId;
    }
}
