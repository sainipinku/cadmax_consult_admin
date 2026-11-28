<?php

namespace Tests\Feature\Construction;

use App\Models\Client;
use App\Models\Company;
use App\Models\Project;
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

class ReadyForSubmissionLabelFeatureTest extends TestCase
{
    use DatabaseTransactions;

    public function test_super_admin_projects_index_preserves_stage_enum_and_renders_cleanly(): void
    {
        $this->seed(PermissionSeeder::class);
        $this->seed(SuperAdminPermissionSeeder::class);
        $this->seed(FoundationSeeder::class);

        $suffix = Str::random(5);
        $company = Company::create(['name' => "Ready Sub Co {$suffix}", 'status' => 'active']);
        $superAdmin = SuperAdmin::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'roles' => ['super_admin'],
            'name' => "Owner {$suffix}",
            'email' => "readysub-{$suffix}@example.com",
            'phone' => (string) random_int(7000000000, 9999999999),
            'whatsapp_phone' => (string) random_int(7000000000, 9999999999),
            'password' => Hash::make('secret123'),
            'status' => 1,
        ]);

        app(CompanyRoleProvisioningService::class)->provisionCompanyRoles($company->id);
        app(CompanyRoleProvisioningService::class)->syncAllSuperAdminAndAdminPermissions();

        $client = Client::create([
            'company_id' => $company->id,
            'client_code' => "CL-{$suffix}",
            'name' => "Client {$suffix}",
            'status' => 'active',
        ]);

        $project = Project::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'project_code' => 'PRJ-RS-' . Str::upper($suffix),
            'name' => "Jaipur Metro {$suffix}",
            'slug' => "jaipur-metro-{$suffix}",
            'status' => 'draft',
            'current_stage' => 'ready_for_construction',
            'created_by_type' => SuperAdmin::class,
            'created_by_id' => $superAdmin->id,
        ]);

        $response = $this->actingAs($superAdmin, 'superadmin')
            ->get(route('super.construction.projects.index'));

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('SuperAdmin/Construction/Projects/Index')
                ->where('projects', fn ($list) => collect($list)->contains(
                    fn ($item) => (int) ($item['id'] ?? 0) === $project->id
                        && ($item['current_stage'] ?? null) === 'ready_for_construction'
                ))
            );
    }

    private function collectChunkAndImports(array $manifest, string $entryKey, array &$visited = []): string
    {
        if (isset($visited[$entryKey])) {
            return '';
        }
        $visited[$entryKey] = true;

        $this->assertArrayHasKey($entryKey, $manifest, "{$entryKey} must exist in Vite manifest");
        $code = (string) file_get_contents(public_path('build/' . $manifest[$entryKey]['file']));
        foreach ($manifest[$entryKey]['imports'] ?? [] as $importKey) {
            if (isset($manifest[$importKey]['file'])) {
                $code .= $this->collectChunkAndImports($manifest, $importKey, $visited);
            }
        }
        return $code;
    }

    public function test_compiled_vite_bundles_display_ready_for_submission_label(): void
    {
        $manifestPath = public_path('build/manifest.json');
        $this->assertFileExists($manifestPath);

        $manifest = json_decode((string) file_get_contents($manifestPath), true);
        $this->assertIsArray($manifest);

        foreach ([
            'resources/js/Pages/SuperAdmin/Construction/Projects/Index.jsx',
            'resources/js/Pages/Admin/Construction/Dashboard.jsx',
            'resources/js/Pages/Member/Construction/Projects/Index.jsx',
            'resources/js/Pages/SuperAdmin/Construction/Drafting/Index.jsx',
            'resources/js/Pages/SuperAdmin/Construction/Projects/Show.jsx',
        ] as $entryKey) {
            $compiled = $this->collectChunkAndImports($manifest, $entryKey);
            $this->assertStringContainsString('Ready for submission', $compiled, "Compiled {$entryKey} bundle must include 'Ready for submission'");
            $this->assertStringNotContainsString('Ready For Construction', $compiled);
            $this->assertStringNotContainsString('Ready for Construction', $compiled);
        }
    }
}
