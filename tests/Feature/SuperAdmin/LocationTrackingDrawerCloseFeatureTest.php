<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Member;
use App\Models\SuperAdmin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LocationTrackingDrawerCloseFeatureTest extends TestCase
{
    use DatabaseTransactions;

    public function test_super_admin_location_tracking_page_and_history_endpoints_work_for_empty_and_populated_dates(): void
    {
        $company = Company::create([
            'name' => 'Track Test Co ' . Str::random(5),
            'status' => 'active',
        ]);

        $superAdmin = SuperAdmin::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'GoRun',
            'email' => 'track_admin_' . Str::random(6) . '@example.com',
            'phone' => '9' . random_int(100000000, 999999999),
            'whatsapp_phone' => '9' . random_int(100000000, 999999999),
            'company_id' => $company->id,
            'password' => Hash::make('password'),
        ]);

        $username = 'ram-ji-' . Str::random(5);
        $member = Member::create([
            'uuid' => (string) Str::uuid(),
            'created_by' => $superAdmin->id,
            'company_name' => $company->name,
            'name' => 'Ram ji',
            'username' => $username,
            'slug' => $username,
            'email' => $username . '@example.com',
            'phone' => '9' . random_int(100000000, 999999999),
            'status' => Member::STATUS_ACTIVE,
            'password' => Hash::make('password'),
            'latitude' => 26.9124,
            'longitude' => 75.7873,
            'location_captured_at' => now(),
        ]);

        $employee = Employee::firstOrCreate(
            ['member_id' => $member->id],
            [
                'uuid' => (string) Str::uuid(),
                'employee_id' => 'EMP-' . str_pad((string) random_int(10000, 999999), 6, '0', STR_PAD_LEFT),
            ]
        );

        // 1. Inertia LocationTracking page renders
        $pageResponse = $this->actingAs($superAdmin, 'superadmin')
            ->get('/super/location-tracking');

        $pageResponse->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('SuperAdmin/LocationTracking')
                ->has('currentSuperAdmin')
                ->where('currentSuperAdmin.id', $superAdmin->id)
            );

        // 2. Live employee locations API returns employee
        $listResponse = $this->actingAs($superAdmin, 'superadmin')
            ->getJson('/api/admin/employee-locations');

        $listResponse->assertStatus(200)
            ->assertJsonPath('success', true);

        $returnedIds = collect($listResponse->json('data'))->pluck('employeeId')->all();
        $this->assertContains($employee->employee_id, $returnedIds);

        // 3. History endpoint for an earlier empty day (Yesterday scenario) succeeds cleanly
        $yesterday = now()->subDay()->toDateString();
        $historyResponse = $this->actingAs($superAdmin, 'superadmin')
            ->getJson("/api/admin/employees/{$employee->employee_id}/location-history?from={$yesterday}%2000:00:00&to={$yesterday}%2023:59:59");

        $historyResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'employee' => [
                    'employeeId' => $employee->employee_id,
                    'name' => 'Ram ji',
                ],
                'data' => [],
            ]);
    }

    public function test_built_vite_bundle_contains_clickable_inner_history_close_button(): void
    {
        $manifestPath = public_path('build/manifest.json');
        $this->assertFileExists($manifestPath, 'Vite build manifest must exist');

        $manifest = json_decode((string) file_get_contents($manifestPath), true);
        $this->assertIsArray($manifest);

        $entryKey = 'resources/js/Pages/SuperAdmin/LocationTracking.jsx';
        $this->assertArrayHasKey($entryKey, $manifest, 'LocationTracking.jsx must be present in Vite manifest');

        $bundleRelativePath = $manifest[$entryKey]['file'] ?? null;
        $this->assertIsString($bundleRelativePath);

        $bundleFullPath = public_path('build/' . $bundleRelativePath);
        $this->assertFileExists($bundleFullPath);

        $compiledJs = (string) file_get_contents($bundleFullPath);

        $this->assertStringContainsString('Close employee history', $compiledJs);
        $this->assertStringContainsString('Dismiss employee profile', $compiledJs);
        $this->assertStringNotContainsString(
            'flex items-center justify-between gap-3 relative pr-8',
            $compiledJs,
            'Compiled LocationTracking bundle must not contain the blocking relative sibling class'
        );
    }
}
