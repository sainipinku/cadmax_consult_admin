<?php

namespace Tests\Feature\Construction;

use App\Models\Company;
use App\Models\ConstructionRole;
use App\Models\Member;
use App\Models\MemberRoleAssignment;
use App\Models\Role;
use App\Models\SuperAdmin;
use App\Services\Construction\CompanyRoleProvisioningService;
use Database\Seeders\Construction\FoundationSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\SuperAdminPermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TopNavExecutionTabRemovalFeatureTest extends TestCase
{
    use DatabaseTransactions;

    private function actingOnlyAs($user, string $guard)
    {
        foreach (['superadmin', 'admin', 'member', 'web'] as $g) {
            if (auth()->guard($g)->check()) {
                auth()->guard($g)->logout();
            }
        }
        session()->flush();

        return $this->actingAs($user, $guard);
    }

    public function test_super_admin_and_admin_construction_pages_render_without_execution_top_nav_tab(): void
    {
        $this->seed(PermissionSeeder::class);
        $this->seed(SuperAdminPermissionSeeder::class);
        $this->seed(FoundationSeeder::class);

        $suffix = Str::random(5);
        $company = Company::create(['name' => "Nav Tab Co {$suffix}", 'status' => 'active']);
        $superAdmin = SuperAdmin::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'roles' => ['super_admin'],
            'name' => "Super Nav {$suffix}",
            'email' => "supernav-{$suffix}@example.com",
            'phone' => (string) random_int(7000000000, 9999999999),
            'whatsapp_phone' => (string) random_int(7000000000, 9999999999),
            'password' => Hash::make('secret123'),
            'status' => 1,
        ]);

        app(CompanyRoleProvisioningService::class)->provisionCompanyRoles($company->id);
        app(CompanyRoleProvisioningService::class)->syncAllSuperAdminAndAdminPermissions();

        $this->actingOnlyAs($superAdmin, 'superadmin')
            ->get(route('super.construction.projects.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('SuperAdmin/Construction/Projects/Index')
            );

        $projectAdminSysRole = Role::firstOrCreate(
            ['slug' => 'project_admin'],
            ['name' => 'Project Admin', 'status' => 1, 'is_default' => 1, 'created_by' => 1]
        );

        $adminMember = Member::create([
            'uuid' => (string) Str::uuid(),
            'created_by' => $superAdmin->id,
            'name' => "Admin Nav {$suffix}",
            'username' => "admin_nav_{$suffix}",
            'slug' => "admin-nav-{$suffix}",
            'email' => "adminnav-{$suffix}@example.com",
            'phone' => (string) random_int(7000000000, 9999999999),
            'password' => Hash::make('secret123'),
            'status' => Member::STATUS_ACTIVE,
            'is_admin' => true,
            'company_name' => $company->name,
            'roles' => [$projectAdminSysRole->id],
        ]);

        $projectAdminRole = ConstructionRole::where('company_id', $company->id)
            ->where('slug', 'project_admin')
            ->first();
        if ($projectAdminRole) {
            MemberRoleAssignment::firstOrCreate([
                'member_id' => $adminMember->id,
                'role_id' => $projectAdminRole->id,
            ], [
                'status' => 1,
            ]);
        }

        $this->actingOnlyAs($adminMember, 'admin')
            ->get(route('admin.construction.projects.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Construction/Projects/Index')
            );
    }

    public function test_compiled_vite_bundles_exclude_execution_top_nav_tab_from_super_and_admin_layouts(): void
    {
        $manifestPath = public_path('build/manifest.json');
        $this->assertFileExists($manifestPath);

        $manifest = json_decode((string) file_get_contents($manifestPath), true);
        $this->assertIsArray($manifest);

        foreach ([
            'resources/js/Pages/SuperAdmin/Layouts/AuthenticatedLayout.jsx' => 'super.construction.execution.index',
            'resources/js/Pages/Admin/Layouts/AuthenticatedLayout.jsx' => 'admin.construction.execution.index',
        ] as $entryKey => $forbiddenRoute) {
            $this->assertArrayHasKey($entryKey, $manifest, "{$entryKey} must exist in Vite manifest");
            $bundlePath = public_path('build/' . $manifest[$entryKey]['file']);
            $this->assertFileExists($bundlePath);

            $compiled = (string) file_get_contents($bundlePath);
            $this->assertStringNotContainsString($forbiddenRoute, $compiled, "Compiled {$entryKey} must not reference {$forbiddenRoute}");
            $this->assertStringNotContainsString('label:"Execution"', $compiled, "Compiled {$entryKey} must not contain Execution quick pill");
            $this->assertStringContainsString('label:"Projects"', $compiled);
            $this->assertStringContainsString('label:"Clients"', $compiled);
            $this->assertStringContainsString('label:"Billing"', $compiled);
        }
    }
}
