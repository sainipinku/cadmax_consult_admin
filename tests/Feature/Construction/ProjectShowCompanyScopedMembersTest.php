<?php

namespace Tests\Feature\Construction;

use App\Models\Company;
use App\Models\Member;
use App\Models\Project;
use App\Models\SuperAdmin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectShowCompanyScopedMembersTest extends TestCase
{
    use RefreshDatabase;

    private Company $companyRoot;
    private Company $companyA;
    private Company $companyB;
    private SuperAdmin $rootSuperAdmin;
    private SuperAdmin $superAdminA;
    private SuperAdmin $superAdminB;
    private Project $projectRoot;
    private Project $projectA;
    private Project $projectB;
    private Member $memberA;
    private Member $memberB;
    private Member $memberUnassigned;

    protected function setUp(): void
    {
        parent::setUp();

        $this->companyRoot = Company::create([
            'name' => 'Root Holding Co',
            'status' => 'active',
        ]);

        // 1. Root platform super admin
        $this->rootSuperAdmin = SuperAdmin::create([
            'name' => 'Platform Root Super Admin',
            'email' => 'root@example.com',
            'password' => bcrypt('password123'),
            'status' => 1,
            'is_project_owner' => 1,
            'company_id' => $this->companyRoot->id,
        ]);

        $this->companyA = Company::create([
            'name' => 'GoRun Construction',
            'status' => 'active',
        ]);

        $this->companyB = Company::create([
            'name' => 'Other Builder Co',
            'status' => 'active',
        ]);

        $this->superAdminA = SuperAdmin::create([
            'name' => 'GoRun Super Admin',
            'email' => 'gorun_sa_' . uniqid() . '@example.com',
            'password' => bcrypt('password123'),
            'status' => 1,
            'company_id' => $this->companyA->id,
            'is_project_owner' => 0,
        ]);

        $this->superAdminB = SuperAdmin::create([
            'name' => 'Other Super Admin',
            'email' => 'other_sa_' . uniqid() . '@example.com',
            'password' => bcrypt('password123'),
            'status' => 1,
            'company_id' => $this->companyB->id,
            'is_project_owner' => 0,
        ]);

        $this->projectRoot = Project::create([
            'company_id' => $this->companyRoot->id,
            'name' => 'Root HQ Project',
            'priority' => 'medium',
            'status' => 'active',
            'current_stage' => 'budget_pending',
        ]);

        $this->projectA = Project::create([
            'company_id' => $this->companyA->id,
            'name' => 'GoRun Site 1',
            'priority' => 'high',
            'status' => 'draft',
            'current_stage' => 'budget_pending',
        ]);

        $this->projectB = Project::create([
            'company_id' => $this->companyB->id,
            'name' => 'Other Site 1',
            'priority' => 'high',
            'status' => 'draft',
            'current_stage' => 'budget_pending',
        ]);

        // Member belonging to Company A
        $this->memberA = Member::create([
            'name' => 'GoRun Worker',
            'email' => 'worker_a_' . uniqid() . '@gorun.com',
            'username' => 'worker_a_' . uniqid(),
            'slug' => 'worker-a-' . uniqid(),
            'password' => bcrypt('password123'),
            'company_name' => $this->companyA->name,
            'created_by' => $this->superAdminA->id,
            'status' => 1,
        ]);

        // Member belonging to Company B
        $this->memberB = Member::create([
            'name' => 'Other Builder Worker',
            'email' => 'worker_b_' . uniqid() . '@other.com',
            'username' => 'worker_b_' . uniqid(),
            'slug' => 'worker-b-' . uniqid(),
            'password' => bcrypt('password123'),
            'company_name' => $this->companyB->name,
            'created_by' => $this->superAdminB->id,
            'status' => 1,
        ]);

        // Unassigned member with company_name = NULL created by root super admin
        $this->memberUnassigned = Member::create([
            'name' => 'Global Unassigned Member',
            'email' => 'unassigned_' . uniqid() . '@example.com',
            'username' => 'unassigned_' . uniqid(),
            'slug' => 'unassigned-' . uniqid(),
            'password' => bcrypt('password123'),
            'company_name' => null,
            'created_by' => $this->rootSuperAdmin->id,
            'status' => 1,
        ]);
    }

    public function test_team_assignment_members_dropdown_returns_only_individuals_for_that_super_admin_company(): void
    {
        $response = $this->actingAs($this->superAdminA, 'superadmin')
            ->get(route('super.construction.projects.show', $this->projectA->id));

        $response->assertStatus(200);

        $members = $response->viewData('page')['props']['members'];
        $memberIds = array_column($members, 'id');

        // Should include Member A (created by SuperAdmin A for GoRun)
        $this->assertContains($this->memberA->id, $memberIds, 'Member A should be present for company A project');

        // Should NOT include Member B (created for company B)
        $this->assertNotContains($this->memberB->id, $memberIds, 'Member B from another company must NOT be present');

        // Should NOT include unassigned global members with company_name = null created by root super admin
        $this->assertNotContains($this->memberUnassigned->id, $memberIds, 'Global unassigned member must NOT be present');
    }

    public function test_super_admin_b_sees_only_company_b_individuals(): void
    {
        $response = $this->actingAs($this->superAdminB, 'superadmin')
            ->get(route('super.construction.projects.show', $this->projectB->id));

        $response->assertStatus(200);

        $members = $response->viewData('page')['props']['members'];
        $memberIds = array_column($members, 'id');

        $this->assertContains($this->memberB->id, $memberIds, 'Member B should be present for company B project');
        $this->assertNotContains($this->memberA->id, $memberIds, 'Member A must not be present');
        $this->assertNotContains($this->memberUnassigned->id, $memberIds, 'Unassigned member must not be present');
    }

    public function test_root_super_admin_does_not_leak_other_company_members(): void
    {
        $response = $this->actingAs($this->rootSuperAdmin, 'superadmin')
            ->get(route('super.construction.projects.show', $this->projectRoot->id));

        $response->assertStatus(200);

        $members = $response->viewData('page')['props']['members'];
        $memberIds = array_column($members, 'id');

        // Should NOT include Member A or Member B
        $this->assertNotContains($this->memberA->id, $memberIds, 'Member A belongs to Company A and must not leak into Root project');
        $this->assertNotContains($this->memberB->id, $memberIds, 'Member B belongs to Company B and must not leak into Root project');
    }

    public function test_team_assignment_rejects_assigning_member_from_another_company(): void
    {
        $response = $this->actingAs($this->superAdminA, 'superadmin')
            ->post(route('super.construction.projects.team.assign', $this->projectA->id), [
                'member_id' => $this->memberB->id,
                'assignment_scope' => 'Survey & CAD Drafting',
            ]);

        $response->assertSessionHasErrors(['member_id']);
    }

    public function test_team_assignment_accepts_assigning_member_from_same_company(): void
    {
        $response = $this->actingAs($this->superAdminA, 'superadmin')
            ->post(route('super.construction.projects.team.assign', $this->projectA->id), [
                'member_id' => $this->memberA->id,
                'assignment_scope' => 'Survey & CAD Drafting',
            ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('construction_project_team_members', [
            'project_id' => $this->projectA->id,
            'member_id' => $this->memberA->id,
        ]);
    }
}
