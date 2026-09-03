<?php

namespace App\Services\Construction;

use App\Models\ExecutionTask;
use App\Models\Member;
use App\Models\Project;
use App\Models\SurveyPlan;
use App\Models\SurveyVisit;
use App\Models\SystemSetting;
use App\Models\TaskChecklist;
use Illuminate\Support\Facades\Cache;
use Throwable;

class SurveyDataService
{
    private const CACHE_TTL = 300;
    private const CACHE_PREFIX = 'survey_data_service:';

    public const SETTING_SHIFT = 'survey_shift_config';
    public const SETTING_DEFAULT_LOCATION = 'survey_default_location';
    public const SETTING_DAY_COUNT = 'survey_day_count';
    public const SETTING_STEPPER_TITLES = 'survey_day_stepper_titles';
    public const SETTING_DEFAULT_CHECKLIST = 'survey_default_checklist_items';
    public const SETTING_SUPERVISOR_DEFAULTS = 'survey_supervisor_defaults';

    private function cacheKey(string $suffix): string
    {
        return self::CACHE_PREFIX . $suffix;
    }

    public function flushAllCache(): void
    {
        foreach ([
            self::SETTING_SHIFT,
            self::SETTING_DEFAULT_LOCATION,
            self::SETTING_DAY_COUNT,
            self::SETTING_STEPPER_TITLES,
            self::SETTING_DEFAULT_CHECKLIST,
            self::SETTING_SUPERVISOR_DEFAULTS,
        ] as $key) {
            Cache::forget($this->cacheKey($key));
        }
    }

    public function getShiftConfig(): array
    {
        try {
            return Cache::remember($this->cacheKey(self::SETTING_SHIFT), self::CACHE_TTL, function (): array {
                $stored = SystemSetting::getSettingValue(self::SETTING_SHIFT, true);

                if (!empty($stored) && is_array($stored)) {
                    return [
                        'name' => $stored['name'] ?? 'Day Shift',
                        'time' => $stored['time'] ?? '9:00 AM - 6:00 PM',
                        'start_time' => $stored['start_time'] ?? '09:00:00',
                        'end_time' => $stored['end_time'] ?? '18:00:00',
                    ];
                }

                return [
                    'name' => 'Day Shift',
                    'time' => '9:00 AM - 6:00 PM',
                    'start_time' => '09:00:00',
                    'end_time' => '18:00:00',
                ];
            });
        } catch (Throwable $e) {
            report($e);

            return [
                'name' => 'Day Shift',
                'time' => '9:00 AM - 6:00 PM',
                'start_time' => '09:00:00',
                'end_time' => '18:00:00',
            ];
        }
    }

    public function getDefaultLocation(): array
    {
        try {
            return Cache::remember($this->cacheKey(self::SETTING_DEFAULT_LOCATION), self::CACHE_TTL, function (): array {
                $stored = SystemSetting::getSettingValue(self::SETTING_DEFAULT_LOCATION, true);

                if (!empty($stored) && is_array($stored)) {
                    return [
                        'address' => $stored['address'] ?? 'Jaipur, Rajasthan',
                        'latitude' => (float) ($stored['latitude'] ?? 26.9124),
                        'longitude' => (float) ($stored['longitude'] ?? 75.7873),
                    ];
                }

                return [
                    'address' => 'Jaipur, Rajasthan',
                    'latitude' => 26.9124,
                    'longitude' => 75.7873,
                ];
            });
        } catch (Throwable $e) {
            report($e);

            return [
                'address' => 'Jaipur, Rajasthan',
                'latitude' => 26.9124,
                'longitude' => 75.7873,
            ];
        }
    }

    public function getTotalSurveyDays(?SurveyPlan $plan = null, ?Project $project = null): int
    {
        $totalDays = null;

        try {
            if ($plan && !empty($plan->survey_days)) {
                $totalDays = (int) $plan->survey_days;
            }
        } catch (Throwable $e) {
            report($e);
        }

        if ($totalDays === null) {
            try {
                $totalDays = Cache::remember($this->cacheKey(self::SETTING_DAY_COUNT), self::CACHE_TTL, function (): int {
                    $stored = SystemSetting::getSettingValue(self::SETTING_DAY_COUNT);

                    return !empty($stored) ? max(1, (int) $stored) : 5;
                });
            } catch (Throwable $e) {
                report($e);
                $totalDays = 5;
            }
        }

        if ($project && $project->start_date && $project->expected_end_date && $totalDays === 5) {
            try {
                $calculated = max(1, (int) $project->start_date->diffInDays($project->expected_end_date));
                if ($calculated > 0 && $calculated <= 30) {
                    $totalDays = $calculated;
                }
            } catch (Throwable $e) {
                report($e);
            }
        }

        return max(1, $totalDays);
    }

    public function getStepperTitles(?SurveyPlan $plan = null, ?int $totalDays = null): array
    {
        $totalDays ??= $this->getTotalSurveyDays($plan);

        try {
            return Cache::remember($this->cacheKey(self::SETTING_STEPPER_TITLES . ':days:' . $totalDays), self::CACHE_TTL, function () use ($totalDays, $plan): array {
                $stored = SystemSetting::getSettingValue(self::SETTING_STEPPER_TITLES, true);

                $titles = [];
                if (!empty($stored) && is_array($stored)) {
                    for ($i = 1; $i <= $totalDays; $i++) {
                        $titles[] = $stored['day_' . $i] ?? $stored[$i - 1] ?? 'Day ' . $i;
                    }
                }

                if (empty($titles) || count($titles) < $totalDays) {
                    $defaultTitles = [
                        'Site Visit',
                        'Topography Survey',
                        'Structure',
                        'Utilities',
                        'Final Report',
                        'Cross-Verification',
                        'Submission',
                    ];
                    for ($i = count($titles); $i < $totalDays; $i++) {
                        $titles[] = $defaultTitles[$i] ?? 'Day ' . ($i + 1);
                    }
                }

                return array_slice($titles, 0, $totalDays);
            });
        } catch (Throwable $e) {
            report($e);

            return array_map(fn ($i) => 'Day ' . $i, range(1, $totalDays));
        }
    }

    public function getDefaultChecklistItems(): array
    {
        try {
            return Cache::remember($this->cacheKey(self::SETTING_DEFAULT_CHECKLIST), self::CACHE_TTL, function (): array {
                $stored = SystemSetting::getSettingValue(self::SETTING_DEFAULT_CHECKLIST, true);

                if (!empty($stored) && is_array($stored) && !empty($stored['items'])) {
                    return array_values($stored['items']);
                }

                return [
                    'Check instrument & battery',
                    'Calibrate total station',
                    'Record existing benchmarks',
                    'Capture elevation points',
                    'Cross-check measurements',
                ];
            });
        } catch (Throwable $e) {
            report($e);

            return [
                'Check instrument & battery',
                'Calibrate total station',
                'Record existing benchmarks',
                'Capture elevation points',
                'Cross-check measurements',
            ];
        }
    }

    public function getSupervisorDefaults(): array
    {
        try {
            return Cache::remember($this->cacheKey(self::SETTING_SUPERVISOR_DEFAULTS), self::CACHE_TTL, function (): array {
                $stored = SystemSetting::getSettingValue(self::SETTING_SUPERVISOR_DEFAULTS, true);

                if (!empty($stored) && is_array($stored)) {
                    return [
                        'name' => $stored['name'] ?? 'Er. Rajesh Sharma',
                        'designation' => $stored['designation'] ?? 'Project Manager',
                        'phone' => $stored['phone'] ?? '9876543210',
                    ];
                }

                return [
                    'name' => 'Er. Rajesh Sharma',
                    'designation' => 'Project Manager',
                    'phone' => '9876543210',
                ];
            });
        } catch (Throwable $e) {
            report($e);

            return [
                'name' => 'Er. Rajesh Sharma',
                'designation' => 'Project Manager',
                'phone' => '9876543210',
            ];
        }
    }

    public function getCurrentDayNumber(?SurveyVisit $visit, ?SurveyPlan $plan = null, ?Project $project = null): int
    {
        if ($visit && !empty($visit->day_number)) {
            return max(1, (int) $visit->day_number);
        }

        try {
            if ($plan) {
                $totalPlanVisits = SurveyVisit::where('survey_plan_id', $plan->id)
                    ->whereNotNull('check_in_at')
                    ->count();
                if ($totalPlanVisits > 0) {
                    return min($this->getTotalSurveyDays($plan, $project), $totalPlanVisits + 1);
                }
            }
        } catch (Throwable $e) {
            report($e);
        }

        try {
            if ($project) {
                $today = now()->setTimezone('Asia/Kolkata')->toDateString();
                $start = $project->start_date ? $project->start_date->toDateString() : $today;
                $diff = max(1, (int) now()->setTimezone('Asia/Kolkata')->diffInDays($start) + 1);

                return min($this->getTotalSurveyDays($plan, $project), $diff);
            }
        } catch (Throwable $e) {
            report($e);
        }

        return 1;
    }

    public function resolveSiteAddress(?SurveyPlan $plan, ?Project $project): string
    {
        $candidates = [
            $plan?->site_address,
            $project?->project_address,
            $this->getDefaultLocation()['address'],
        ];

        foreach ($candidates as $candidate) {
            if (!empty($candidate)) {
                return $candidate;
            }
        }

        return 'Jaipur, Rajasthan';
    }

    public function resolveSiteCoordinates(?SurveyPlan $plan, ?Project $project): array
    {
        $default = $this->getDefaultLocation();

        try {
            if ($plan && !empty($plan->site_latitude) && !empty($plan->site_longitude)) {
                return [
                    'latitude' => (float) $plan->site_latitude,
                    'longitude' => (float) $plan->site_longitude,
                ];
            }
        } catch (Throwable $e) {
            report($e);
        }

        try {
            if ($project && !empty($project->latitude) && !empty($project->longitude)) {
                return [
                    'latitude' => (float) $project->latitude,
                    'longitude' => (float) $project->longitude,
                ];
            }
        } catch (Throwable $e) {
            report($e);
        }

        return [
            'latitude' => $default['latitude'],
            'longitude' => $default['longitude'],
        ];
    }

    public function resolveCheckInCoordinates(?SurveyVisit $visit, ?float $providedLat, ?float $providedLong, ?SurveyPlan $plan, ?Project $project): array
    {
        $coords = $this->resolveSiteCoordinates($plan, $project);

        if ($providedLat !== null && $providedLong !== null) {
            $coords = [
                'latitude' => (float) $providedLat,
                'longitude' => (float) $providedLong,
            ];
        } elseif ($visit && $visit->check_in_latitude !== null && $visit->check_in_longitude !== null) {
            $coords = [
                'latitude' => (float) $visit->check_in_latitude,
                'longitude' => (float) $visit->check_in_longitude,
            ];
        }

        return $coords;
    }

    public function resolveProjectDefaultDescription(?Project $project): string
    {
        $desc = $project?->description;
        if (!empty($desc)) {
            return $desc;
        }

        $category = $project?->category ?? 'Construction';

        return match (strtolower($category)) {
            'residential' => 'Construction of residential villa project including foundation, structure, and utility setup as per design specifications and quality standards.',
            'commercial' => 'Commercial building development project with RCC frame structure, MEP services, and finishing works per approved architectural drawings.',
            'road', 'infrastructure' => 'Road and infrastructure development project including earthwork, pavement layers, drainage, and street lighting installations.',
            'survey' => 'Detailed land and topography survey project covering boundary demarcation, contour mapping, and utility surveys.',
            default => $category . ' project execution including planning, implementation, and quality assurance as per contractual specifications.',
        };
    }

    public function buildTaskDayStepper(int $currentDay, int $totalDays): array
    {
        $titles = $this->getStepperTitles(null, $totalDays);

        $stepper = [];
        for ($i = 1; $i <= $totalDays; $i++) {
            $isActive = $i === $currentDay;
            if ($i < $currentDay) {
                $status = 'Completed';
                $isActive = false;
            } elseif ($i === $currentDay) {
                $status = 'In Progress';
                $isActive = true;
            } else {
                $status = 'Pending';
                $isActive = false;
            }

            $stepper[] = [
                'day' => $i,
                'label' => 'Day ' . $i,
                'title' => $titles[$i - 1] ?? 'Day ' . $i,
                'status' => $status,
                'is_active' => $isActive,
            ];
        }

        return $stepper;
    }

    public function buildProjectSurveyStepper(Project $project, ?SurveyPlan $plan = null): array
    {
        $totalDays = $this->getTotalSurveyDays($plan, $project);
        $titles = $this->getStepperTitles($plan, $totalDays);

        $latestVisit = null;
        try {
            if ($plan) {
                $latestVisit = SurveyVisit::where('survey_plan_id', $plan->id)
                    ->whereNotNull('check_in_at')
                    ->latest('check_in_at')
                    ->first();
            }
        } catch (Throwable $e) {
            report($e);
        }

        $currentDay = $this->getCurrentDayNumber($latestVisit, $plan, $project);

        $stepper = [];
        for ($i = 1; $i <= $totalDays; $i++) {
            if ($i < $currentDay) {
                $status = 'Completed';
                $statusClass = 'completed';
            } elseif ($i === $currentDay) {
                $status = 'In Progress';
                $statusClass = 'in_progress';
            } else {
                $status = 'Pending';
                $statusClass = 'pending';
            }

            $stepper[] = [
                'day' => $i,
                'title' => $titles[$i - 1] ?? 'Day ' . $i,
                'status' => $status,
                'status_class' => $statusClass,
            ];
        }

        return $stepper;
    }

    public function ensureDefaultTaskChecklists(ExecutionTask $task, int $dayNumber): void
    {
        try {
            $existingCount = TaskChecklist::where('execution_task_id', $task->id)->count();
            if ($existingCount > 0) {
                return;
            }

            $defaultItems = $this->getDefaultChecklistItems();
            $now = now();

            $records = array_map(function (string $item) use ($task, $dayNumber, $now): array {
                return [
                    'execution_task_id' => $task->id,
                    'day_number' => $dayNumber,
                    'item_title' => $item,
                    'is_completed' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }, $defaultItems);

            TaskChecklist::insert($records);
        } catch (Throwable $e) {
            report($e);
        }
    }

    public function resolveSupervisorInfo(?Project $project, ?ExecutionTask $task = null): array
    {
        $defaults = $this->getSupervisorDefaults();

        $name = $defaults['name'];
        $designation = $defaults['designation'];
        $phone = $defaults['phone'];

        try {
            if ($task?->supervisor instanceof Member) {
                $name = $task->supervisor->name ?? $name;
                $phone = $task->supervisor->phone ?? $phone;
                $memberDesignation = $task->supervisor->designation_names;
                if (!empty($memberDesignation)) {
                    $designation = $memberDesignation;
                }
            }
        } catch (Throwable $e) {
            report($e);
        }

        try {
            if (($name === $defaults['name'] || $phone === $defaults['phone']) && $project) {
                $candidates = [
                    $project->client,
                    $project->company,
                    $project->createdBy,
                ];
                foreach ($candidates as $candidate) {
                    if ($candidate && !empty($candidate->name)) {
                        $name = $candidate->name;
                        if (!empty($candidate->phone)) {
                            $phone = $candidate->phone;
                        }
                        break;
                    }
                }
            }
        } catch (Throwable $e) {
            report($e);
        }

        return [
            'name' => $name,
            'designation' => $designation,
            'phone' => $phone,
        ];
    }

    public function updateSetting(string $settingKey, mixed $value, ?array $extra = null): void
    {
        SystemSetting::setSettingValue($settingKey, $value, $extra);
        Cache::forget($this->cacheKey($settingKey));
    }
}
