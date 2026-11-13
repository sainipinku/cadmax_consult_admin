<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Member;
use App\Models\ConstructionRole;
use App\Models\MemberRoleAssignment;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class MemberDashboardVehicleScopingTest extends TestCase
{
    use DatabaseTransactions;

    public function test_member_driver_detection_via_role_assignment(): void
    {
        ConstructionRole::firstOrCreate(
            ['slug' => 'member'],
            ['id' => 1, 'name' => 'Member', 'is_active' => true]
        );

        $driverRole = ConstructionRole::firstOrCreate(
            ['slug' => 'vehicle_driver'],
            ['name' => 'Vehicle Driver', 'is_active' => true]
        );

        $member = Member::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Unit Test Driver',
            'username' => 'driver_' . uniqid(),
            'email' => 'driver_' . uniqid() . '@example.com',
            'phone' => '98765' . rand(10000, 99999),
            'password' => bcrypt('secret123'),
            'status' => 1,
            'roles' => ['member'],
            'created_by' => 1,
            'slug' => 'unit-test-driver-' . uniqid(),
        ]);

        $this->assertFalse($member->isDriver());

        MemberRoleAssignment::create([
            'member_id' => $member->id,
            'role_id' => $driverRole->id,
            'status' => 1,
            'assigned_at' => now(),
        ]);

        $this->assertTrue($member->isDriver());
    }

    public function test_member_driver_detection_via_string_role(): void
    {
        ConstructionRole::firstOrCreate(
            ['id' => 6],
            ['name' => 'Driver', 'slug' => 'driver', 'is_active' => true]
        );

        $member = Member::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Unit Test Driver 2',
            'username' => 'driver2_' . uniqid(),
            'email' => 'driver2_' . uniqid() . '@example.com',
            'phone' => '98765' . rand(10000, 99999),
            'password' => bcrypt('secret123'),
            'status' => 1,
            'created_by' => 1,
            'roles' => ['driver'],
            'slug' => 'unit-test-driver2-' . uniqid(),
        ]);

        $this->assertTrue($member->isDriver());
    }
}
