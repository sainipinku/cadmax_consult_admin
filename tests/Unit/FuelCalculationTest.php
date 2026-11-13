<?php

namespace Tests\Unit;

use Tests\TestCase;

class FuelCalculationTest extends TestCase
{
    public function test_fuel_rate_per_liter_calculation(): void
    {
        $amount = 4500.00;
        $liters = 45.0;

        $ratePerLiter = $liters > 0 ? round($amount / $liters, 2) : 0.0;
        $this->assertEquals(100.00, $ratePerLiter);
    }

    public function test_fuel_rate_per_liter_with_zero_liters(): void
    {
        $amount = 1000.00;
        $liters = 0.0;

        $ratePerLiter = $liters > 0 ? round($amount / $liters, 2) : 0.0;
        $this->assertEquals(0.0, $ratePerLiter);
    }

    public function test_fuel_consumption_metrics(): void
    {
        $entries = collect([
            ['quantity_liters' => 50.0, 'total_amount' => 5000.0, 'status' => 'approved'],
            ['quantity_liters' => 40.0, 'total_amount' => 4000.0, 'status' => 'approved'],
            ['quantity_liters' => 30.0, 'total_amount' => 3000.0, 'status' => 'pending'],
            ['quantity_liters' => 20.0, 'total_amount' => 2000.0, 'status' => 'rejected'],
        ]);

        $approvedEntries = $entries->where('status', 'approved');
        $this->assertEquals(90.0, $approvedEntries->sum('quantity_liters'));
        $this->assertEquals(9000.0, $approvedEntries->sum('total_amount'));
        $this->assertEquals(1, $entries->where('status', 'pending')->count());
        $this->assertEquals(1, $entries->where('status', 'rejected')->count());
        $this->assertEquals(4, $entries->count());
    }
}

