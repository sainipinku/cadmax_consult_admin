<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Carbon\Carbon;

class AttendanceDurationCalculationTest extends TestCase
{
    /**
     * Helper to compute shift hours and minutes between check-in and check-out.
     */
    protected function computeShiftDuration(?string $checkIn, ?string $checkOut): array
    {
        if (!$checkIn) {
            return [
                'minutes' => 0,
                'hours' => 0,
                'formatted' => '0h 0m',
                'is_in_progress' => false,
            ];
        }

        $start = Carbon::parse($checkIn);
        $end = $checkOut ? Carbon::parse($checkOut) : Carbon::now();

        $minutes = (int) max(0, $start->diffInMinutes($end));
        $hours = (int) floor($minutes / 60);
        $remMinutes = $minutes % 60;

        return [
            'minutes' => (int) $minutes,
            'hours' => (float) round($minutes / 60, 2),
            'formatted' => "{$hours}h {$remMinutes}m",
            'is_in_progress' => empty($checkOut),
        ];
    }

    /**
     * Helper to calculate Haversine distance in meters between two lat/lng pairs.
     */
    protected function haversineDistance(?float $lat1, ?float $lon1, ?float $lat2, ?float $lon2): ?float
    {
        if ($lat1 === null || $lon1 === null || $lat2 === null || $lon2 === null) {
            return null;
        }

        $earthRadius = 6371000; // in meters
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return round($earthRadius * $c, 2);
    }

    public function test_calculates_completed_shift_duration_accurately(): void
    {
        $duration = $this->computeShiftDuration('2026-11-04 09:15:00', '2026-11-04 18:00:00');

        $this->assertEquals(525, $duration['minutes']); // 8h 45m = 525 min
        $this->assertEquals(8.75, $duration['hours']);
        $this->assertEquals('8h 45m', $duration['formatted']);
        $this->assertFalse($duration['is_in_progress']);
    }

    public function test_calculates_cross_midnight_shift_duration(): void
    {
        $duration = $this->computeShiftDuration('2026-11-04 22:00:00', '2026-11-05 06:30:00');

        $this->assertEquals(510, $duration['minutes']); // 8h 30m = 510 min
        $this->assertEquals(8.5, $duration['hours']);
        $this->assertEquals('8h 30m', $duration['formatted']);
        $this->assertFalse($duration['is_in_progress']);
    }

    public function test_marks_active_check_in_without_check_out_as_in_progress(): void
    {
        $duration = $this->computeShiftDuration('2026-11-04 09:00:00', null);

        $this->assertTrue($duration['is_in_progress']);
        $this->assertGreaterThan(0, $duration['minutes']);
    }

    public function test_haversine_distance_calculation(): void
    {
        // Jaipur Hawa Mahal to Amer Fort approx 7.5 - 8.5 km
        $dist = $this->haversineDistance(26.9239, 75.8267, 26.9855, 75.8513);

        $this->assertNotNull($dist);
        $this->assertGreaterThan(7000, $dist); // > 7km
        $this->assertLessThan(9000, $dist);    // < 9km

        // Same location distance should be 0
        $sameDist = $this->haversineDistance(26.9239, 75.8267, 26.9239, 75.8267);
        $this->assertEquals(0.0, $sameDist);
    }
}
