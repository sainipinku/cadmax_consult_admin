<?php

namespace Tests\Feature\Construction;

use App\Models\Company;
use App\Models\ConstructionRole;
use App\Models\Member;
use App\Models\MemberRoleAssignment;
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

class MaterialManagementNavRemovalFeatureTest extends TestCase
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

    public function test_member_construction_dashboard_renders_without_material_management_navigation_bundles(): void
    {
        $this->seed(PermissionSeeder::class);
        $this->seed(SuperAdminPermissionSeeder::class);
        $this->seed(FoundationSeeder::class);

        $suffix = Str::random(5);
        $company = Company::create(['name' => "Mat Nav Co {$suffix}", 'status' => 'active']);
        $superAdmin = SuperAdmin::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'roles' => ['super_admin'],
            'name' => "Super Mat {$suffix}",
            'email' => "supermat-{$suffix}@example.com",
            'phone' => (string) random_int(7000000000, 9999999999),
            'whatsapp_phone' => (string) random_int(7000000000, 9999999999),
            'password' => Hash::make('secret123'),
            'status' => 1,
        ]);

        app(CompanyRoleProvisioningService::class)->provisionCompanyRoles($company->id);
        app(CompanyRoleProvisioningService::class)->syncAllSuperAdminAndAdminPermissions();

        $member = Member::create([
            'uuid' => (string) Str::uuid(),
            'created_by' => $superAdmin->id,
            'name' => "Draft Member {$suffix}",
            'username' => "draft_mem_{$suffix}",
            'slug' => "draft-mem-{$suffix}",
            'email' => "draftmem-{$suffix}@example.com",
            'phone' => (string) random_int(7000000000, 9999999999),
            'password' => Hash::make('secret123'),
            'status' => Member::STATUS_ACTIVE,
            'company_name' => $company->name,
        ]);

        $draftRole = ConstructionRole::where('company_id', $company->id)
            ->where('slug', 'draft_person')
            ->first();
        if ($draftRole) {
            MemberRoleAssignment::firstOrCreate([
                'member_id' => $member->id,
                'role_id' => $draftRole->id,
            ], [
                'status' => 1,
            ]);
        }

        $this->actingOnlyAs($member, 'member')
            ->get(route('member.construction.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Member/Construction/Dashboard')
            );
    }

    public function test_compiled_vite_bundles_exclude_material_management_navigation_items(): void
    {
        $manifestPath = public_path('build/manifest.json');
        $this->assertFileExists($manifestPath);

        $manifest = json_decode((string) file_get_contents($manifestPath), true);
        $this->assertIsArray($manifest);

        $shellKey = 'resources/js/Pages/Construction/Components/ConstructionShell.jsx';
        $this->assertArrayHasKey($shellKey, $manifest);
        $shellCompiled = (string) file_get_contents(public_path('build/' . $manifest[$shellKey]['file']));

        $this->assertStringNotContainsString('Material Management', $shellCompiled);
        $this->assertStringNotContainsString('member.construction.materials.index', $shellCompiled);
        $this->assertStringContainsString('Site Execution', $shellCompiled);

        $memberLayoutKey = 'resources/js/Pages/Member/Layouts/AuthenticatedLayout.jsx';
        $this->assertArrayHasKey($memberLayoutKey, $manifest);
        $memberLayoutCompiled = (string) file_get_contents(public_path('build/' . $manifest[$memberLayoutKey]['file']));

        $this->assertStringNotContainsString('member.construction.materials.index', $memberLayoutCompiled);
        $this->assertStringNotContainsString('label:"Materials"', $memberLayoutCompiled);
        $this->assertStringContainsString('label:"Projects"', $memberLayoutCompiled);
        $this->assertStringContainsString('label:"Execution"', $memberLayoutCompiled);
    }
}
