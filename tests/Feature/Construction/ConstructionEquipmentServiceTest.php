<?php

namespace Tests\Feature\Construction;

use App\Models\Client;
use App\Models\Company;
use App\Models\ConstructionEquipment;
use App\Models\Project;
use App\Models\ProjectTeamMember;
use App\Models\Member;
use App\Models\SuperAdmin;
use App\Services\Construction\ConstructionEquipmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ConstructionEquipmentServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_rejects_allocating_equipment_from_another_project(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $projectA = $this->createProject('PRJ-A', 'project-a', 'Project A');
        $projectB = $this->createProject('PRJ-B', 'project-b', 'Project B');
        $member = $this->createMember($superAdmin, 'Allocator');

        ProjectTeamMember::create([
            'project_id' => $projectA->id,
            'member_id' => $member->id,
            'status' => 'active',
        ]);

        $equipmentB = ConstructionEquipment::create([
            'project_id' => $projectB->id,
            'equipment_code' => 'EQP-00001',
            'name' => 'Concrete Mixer',
            'status' => 'active',
        ]);

        $service = app(ConstructionEquipmentService::class);

        try {
            $service->allocateEquipment($projectA, [
                'equipment_id' => $equipmentB->id,
            ], $member);
            $this->fail('Expected cross-project allocation to fail.');
        } catch (ValidationException $exception) {
            $this->assertSame([
                'The selected equipment does not belong to the chosen project.',
            ], $exception->errors()['equipment_id']);
        }
    }

    public function test_it_rejects_double_active_allocation_for_same_equipment(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $project = $this->createProject();
        $member = $this->createMember($superAdmin, 'Allocator');

        ProjectTeamMember::create([
            'project_id' => $project->id,
            'member_id' => $member->id,
            'status' => 'active',
        ]);

        $equipment = ConstructionEquipment::create([
            'project_id' => $project->id,
            'equipment_code' => 'EQP-00002',
            'name' => 'Vibrator',
            'status' => 'active',
        ]);

        $service = app(ConstructionEquipmentService::class);
        $service->allocateEquipment($project, [
            'equipment_id' => $equipment->id,
            'allocate_gps_accuracy_meters' => 10,
        ], $member);

        try {
            $service->allocateEquipment($project, [
                'equipment_id' => $equipment->id,
                'allocate_gps_accuracy_meters' => 10,
            ], $member);
            $this->fail('Expected second allocation to fail.');
        } catch (ValidationException $exception) {
            $this->assertSame([
                'This equipment is already allocated. Please return it before creating a new allocation.',
            ], $exception->errors()['equipment_id']);
        }
    }

    public function test_it_marks_allocation_gps_verified_based_on_accuracy_threshold(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $project = $this->createProject();
        $member = $this->createMember($superAdmin, 'Allocator');

        ProjectTeamMember::create([
            'project_id' => $project->id,
            'member_id' => $member->id,
            'status' => 'active',
        ]);

        $equipment = ConstructionEquipment::create([
            'project_id' => $project->id,
            'equipment_code' => 'EQP-00003',
            'name' => 'Plate Compactor',
            'status' => 'active',
        ]);

        $service = app(ConstructionEquipmentService::class);

        $allocation = $service->allocateEquipment($project, [
            'equipment_id' => $equipment->id,
            'allocate_gps_accuracy_meters' => 25,
        ], $member);

        $this->assertTrue($allocation->allocate_gps_verified);
    }

    public function test_it_allocates_equipment_with_schedule_and_optional_hours(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $project = $this->createProject();
        $member = $this->createMember($superAdmin, 'Site Engineer');

        ProjectTeamMember::create([
            'project_id' => $project->id,
            'member_id' => $member->id,
            'status' => 'active',
        ]);

        $equipment = ConstructionEquipment::create([
            'project_id' => $project->id,
            'equipment_code' => 'EQP-00010',
            'name' => 'Theodolite Alpha',
            'status' => 'active',
        ]);

        $service = app(ConstructionEquipmentService::class);

        $allocation = $service->allocateEquipment($project, [
            'equipment_id' => $equipment->id,
            'assigned_to_member_id' => $member->id,
            'assigned_from' => '2026-10-27 08:30:00',
            'assigned_to' => '2026-10-30 17:00:00',
            'hours' => 7.5,
            'notes' => 'Allocated for site survey phase',
        ], $member);

        $this->assertEquals('active', $allocation->status);
        $this->assertEquals($member->id, $allocation->assigned_to_member_id);
        $this->assertEquals('2026-10-27 08:30:00', $allocation->assigned_from->format('Y-m-d H:i:s'));
        $this->assertEquals('2026-10-30 17:00:00', $allocation->assigned_to->format('Y-m-d H:i:s'));
        $this->assertEquals(7.5, $allocation->hours);
        $this->assertEquals('Allocated for site survey phase', $allocation->notes);
    }

    public function test_it_resolves_and_allocates_main_equipment_seamlessly(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $project = $this->createProject();
        $member = $this->createMember($superAdmin, 'Survey Tech');

        ProjectTeamMember::create([
            'project_id' => $project->id,
            'member_id' => $member->id,
            'status' => 'active',
        ]);

        $category = \App\Models\EquipmentCategory::create([
            'category_name' => 'Survey Instruments',
            'status' => 1,
        ]);

        $mainEquipment = \App\Models\Equipment::create([
            'category_id' => $category->id,
            'equipment_name' => 'Total Station Trimble',
            'status' => 0, // Available
        ]);

        $service = app(ConstructionEquipmentService::class);

        $allocation = $service->allocateEquipment($project, [
            'equipment_id' => "main_{$mainEquipment->id}",
            'assigned_to_member_id' => $member->id,
            'assigned_from' => '2026-10-28 09:00:00',
            'assigned_to' => '2026-10-29 18:00:00',
        ], $member);

        $this->assertNotNull($allocation);
        $this->assertEquals($project->id, $allocation->project_id);
        $this->assertEquals('active', $allocation->status);

        $mainEquipment->refresh();
        $this->assertEquals(1, $mainEquipment->status);
        $this->assertEquals($project->id, $mainEquipment->assigned_project_id);

        // Returning allocation frees up the equipment
        $service->returnEquipment($project, [
            'allocation_id' => $allocation->id,
        ], $member);

        $allocation->refresh();
        $this->assertEquals('returned', $allocation->status);

        $mainEquipment->refresh();
        $this->assertEquals(0, $mainEquipment->status);
    }

    private function createSuperAdmin(): SuperAdmin
    {
        return SuperAdmin::create([
            'uuid' => (string) Str::uuid(),
            'roles' => json_encode(['super_admin']),
            'name' => 'Construction Super Admin',
            'email' => 'super-admin@example.com',
            'phone' => '9999999999',
            'whatsapp_phone' => '9999999999',
            'password' => 'password',
        ]);
    }

    private function createMember(SuperAdmin $superAdmin, string $name): Member
    {
        $slug = Str::slug($name) . '-' . Str::lower(Str::random(5));

        return Member::create([
            'uuid' => (string) Str::uuid(),
            'created_by' => $superAdmin->id,
            'name' => $name,
            'username' => $slug,
            'email' => $slug . '@example.com',
            'phone' => (string) random_int(7000000000, 9999999999),
            'password' => 'password',
            'slug' => $slug,
        ]);
    }

    private function createProject(
        string $projectCode = 'PRJ-001',
        string $slug = 'project-001',
        string $name = 'Construction Project'
    ): Project {
        $company = Company::create([
            'name' => $name . ' Company',
            'status' => 'active',
        ]);

        $client = Client::create([
            'company_id' => $company->id,
            'client_code' => $projectCode . '-CLIENT',
            'name' => $name . ' Client',
            'status' => 'active',
        ]);

        return Project::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'project_code' => $projectCode,
            'name' => $name,
            'slug' => $slug,
            'status' => 'active',
            'current_stage' => 'ready_for_construction',
        ]);
    }
}

