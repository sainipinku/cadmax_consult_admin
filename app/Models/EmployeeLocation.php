<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class EmployeeLocation extends Model
{
    protected $fillable = [
        'member_id',
        'latitude',
        'longitude',
        'accuracy',
        'captured_at',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'accuracy' => 'float',
        'captured_at' => 'datetime',
    ];

    /**
     * Auto delete data older than yesterday so the table only carries today and yesterday data.
     */
    public static function pruneOlderThanYesterday(): int
    {
        if (!Schema::hasTable('employee_locations')) {
            return 0;
        }

        try {
            // Cutoff is the start of yesterday in Asia/Kolkata timezone, converted to UTC
            $cutoff = now('Asia/Kolkata')->subDay()->startOfDay()->setTimezone('UTC');

            return (int) static::where('captured_at', '<', $cutoff)
                ->orWhere(function ($query) use ($cutoff) {
                    $query->whereNull('captured_at')
                        ->where('created_at', '<', $cutoff);
                })
                ->delete();
        } catch (\Throwable $e) {
            report($e);
            return 0;
        }
    }

    public static function recordIfValid(
        Member $member,
        mixed $latitude,
        mixed $longitude,
        mixed $accuracy = null,
        mixed $capturedAt = null
    ): ?self {
        $employee = $member->employee;
        if (!$employee && class_exists(Employee::class)) {
            $employee = Employee::firstOrCreate(
                ['member_id' => $member->getKey()],
                ['uuid' => (string) \Illuminate\Support\Str::uuid()]
            );
            $member->setRelation('employee', $employee);
        }

        if (
            (int) $member->status !== Member::STATUS_ACTIVE
            || !$employee
            || !is_numeric($latitude)
            || !is_numeric($longitude)
            || $latitude < -90
            || $latitude > 90
            || $longitude < -180
            || $longitude > 180
        ) {
            return null;
        }

        $validAccuracy = is_numeric($accuracy) && $accuracy >= 0 && $accuracy <= 100000
            ? (float) $accuracy
            : null;

        $capturedAt = $capturedAt ? Carbon::parse($capturedAt) : now();

        // 1. Save last lat and last long on member table
        $memberUpdate = [];
        if (Schema::hasColumn('members', 'latitude')) {
            $memberUpdate['latitude'] = (float) $latitude;
        }
        if (Schema::hasColumn('members', 'longitude')) {
            $memberUpdate['longitude'] = (float) $longitude;
        }
        if (Schema::hasColumn('members', 'last_latitude')) {
            $memberUpdate['last_latitude'] = (float) $latitude;
        }
        if (Schema::hasColumn('members', 'last_longitude')) {
            $memberUpdate['last_longitude'] = (float) $longitude;
        }
        if (Schema::hasColumn('members', 'location_captured_at')) {
            $memberUpdate['location_captured_at'] = $capturedAt;
        }

        if (!empty($memberUpdate)) {
            $lastCaptured = $member->location_captured_at ? Carbon::parse($member->location_captured_at) : null;
            if (!$lastCaptured || $capturedAt->getTimestamp() >= $lastCaptured->getTimestamp()) {
                $member->forceFill($memberUpdate)->save();
            }
        }

        if (!Schema::hasTable('employee_locations')) {
            return null;
        }

        // 2. Auto delete older data so employee_locations table only carries today and yesterday data
        static::pruneOlderThanYesterday();

        // 3. Insert into employee_locations
        return static::create([
            'member_id' => $member->getKey(),
            'latitude' => (float) $latitude,
            'longitude' => (float) $longitude,
            'accuracy' => $validAccuracy,
            'captured_at' => $capturedAt,
        ]);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
