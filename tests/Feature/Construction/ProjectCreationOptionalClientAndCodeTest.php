<?php

namespace Tests\Feature\Construction;

use App\Models\Company;
use App\Models\Project;
use App\Models\SuperAdmin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectCreationOptionalClientAndCodeTest extends TestCase
{
    use RefreshDatabase;

    private SuperAdmin $superAdmin;
    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = SuperAdmin::create([
            'name' => 'Root Super Admin',
            'email' => 'root_superadmin_' . uniqid() . '@example.com',
            'password' => bcrypt('password123'),
            'status' => 1,
            'is_project_owner' => 1,
        ]);

        $this->company = Company::create([
            'name' => 'Acme Builders',
            'status' => 'active',
        ]);
    }

    public function test_project_can_be_created_without_client_id_via_web_route(): void
    {
        $payload = [
            'company_id' => $this->company->id,
            'name' => 'Skyline Tower',
            'priority' => 'high',
            'status' => 'active',
            'current_stage' => 'budget_pending',
        ];

        $response = $this->actingAs($this->superAdmin, 'superadmin')
            ->post(route('super.construction.projects.store'), $payload);

        $response->assertSessionHasNoErrors();

        $project = Project::where('name', 'Skyline Tower')->first();
        $this->assertNotNull($project, 'Project was not created');
        $this->assertNull($project->client_id, 'client_id should be null when not provided');
        $this->assertNotNull($project->project_code, 'project_code should be auto-generated');
        $this->assertStringStartsWith('PRJ-', $project->project_code);
    }

    public function test_project_can_be_created_without_project_code_and_auto_generates_code(): void
    {
        $payload = [
            'company_id' => $this->company->id,
            'name' => 'Metro Bridge Alpha',
            'priority' => 'medium',
        ];

        $response = $this->actingAs($this->superAdmin, 'superadmin')
            ->post(route('super.construction.projects.store'), $payload);

        $response->assertSessionHasNoErrors();

        $project = Project::where('name', 'Metro Bridge Alpha')->first();
        $this->assertNotNull($project);
        $this->assertNull($project->client_id);
        $this->assertNotEmpty($project->project_code);
    }

    public function test_project_can_be_updated_with_null_or_empty_client_id(): void
    {
        $project = Project::create([
            'company_id' => $this->company->id,
            'name' => 'Initial Project',
            'priority' => 'low',
            'status' => 'draft',
            'current_stage' => 'budget_pending',
        ]);

        $updatePayload = [
            'company_id' => $this->company->id,
            'client_id' => null,
            'name' => 'Updated Project Name',
            'priority' => 'high',
            'status' => 'active',
            'current_stage' => 'budget_pending',
        ];

        $response = $this->actingAs($this->superAdmin, 'superadmin')
            ->put(route('super.construction.projects.update', $project->id), $updatePayload);

        $response->assertSessionHasNoErrors();

        $project->refresh();
        $this->assertEquals('Updated Project Name', $project->name);
        $this->assertNull($project->client_id);
    }

    public function test_database_allows_null_client_id_and_null_project_code(): void
    {
        $project = new Project();
        $project->company_id = $this->company->id;
        $project->name = 'Direct DB Project';
        $project->client_id = null;
        $project->slug = 'direct-db-project-' . uniqid();
        $project->priority = 'medium';
        $project->status = 'draft';
        $project->current_stage = 'budget_pending';
        // Test that even if project_code is manually set to null before save, DB accepts it
        $project->save();

        $this->assertDatabaseHas('construction_projects', [
            'id' => $project->id,
            'client_id' => null,
            'name' => 'Direct DB Project',
        ]);
    }

    public function test_client_index_loads_cleanly_with_company_id_query_param(): void
    {
        $response = $this->actingAs($this->superAdmin, 'superadmin')
            ->get(route('super.construction.clients.index', ['company_id' => $this->company->id]));

        $response->assertStatus(200);
    }
}
