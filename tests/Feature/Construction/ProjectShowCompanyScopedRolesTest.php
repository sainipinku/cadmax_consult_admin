<?php

namespace Tests\Feature\Construction;

use App\Models\Company;
use App\Models\ConstructionRole;
use App\Models\Project;
use App\Models\SuperAdmin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectShowCompanyScopedRolesTest extends TestCase
{
    use RefreshDatabase;

    private SuperAdmin $superAdmin;
    private Company $companyA;
    private Company $companyB;
    private Project $projectA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->companyA = Company::create([
            'name' => 'GoRun Construction',
            'status' => 'active',
        ]);

        $this->companyB = Company::create([
            'name' => 'Other Builder',
            'status' => 'active',
        ]);

        $this->superAdmin = SuperAdmin::create([
            'name' => 'GoRun Super Admin',
            'email' => 'gorun_admin_' . uniqid() . '@example.com',
            'password' => bcrypt('password123'),
            'status' => 1,
            'company_id' => $this->companyA->id,
            'is_project_owner' => 0,
        ]);

        $this->projectA = Project::create([
            'company_id' => $this->companyA->id,
            'name' => 'Abhiste Project',
            'priority' => 'medium',
            'status' => 'draft',
            'current_stage' => 'budget_pending',
        ]);

        // Template roles (company_id = null)
        ConstructionRole::create([
            'company_id' => null,
            'name' => 'Surveyor',
            'slug' => 'surveyor',
            'status' => 'active',
        ]);
        ConstructionRole::create([
            'company_id' => null,
            'name' => 'Draft Person',
            'slug' => 'draft_person',
            'status' => 'active',
        ]);

        // Company A roles (company_id = companyA->id)
        ConstructionRole::create([
            'company_id' => $this->companyA->id,
            'name' => 'Surveyor',
            'slug' => 'surveyor',
            'status' => 'active',
        ]);
        ConstructionRole::create([
            'company_id' => $this->companyA->id,
            'name' => 'Draft Person',
            'slug' => 'draft_person',
            'status' => 'active',
        ]);

        // Company B roles (company_id = companyB->id)
        ConstructionRole::create([
            'company_id' => $this->companyB->id,
            'name' => 'Surveyor',
            'slug' => 'surveyor',
            'status' => 'active',
        ]);
    }

    public function test_project_show_returns_only_company_scoped_roles_for_team_assignment(): void
    {
        $response = $this->actingAs($this->superAdmin, 'superadmin')
            ->get(route('super.construction.projects.show', $this->projectA->id));

        $response->assertStatus(200);

        $roles = $response->viewData('page')['props']['roles'];

        $this->assertCount(2, $roles, 'Roles list should contain only Company A roles, without template duplicates or Company B roles');

        foreach ($roles as $role) {
            $this->assertEquals($this->companyA->id, $role['company_id']);
        }

        $names = array_column($roles, 'name');
        $this->assertEquals(['Draft Person', 'Surveyor'], $names);
    }
}

