<?php

namespace Tests\Feature\Construction;

use App\Models\Construction\Client;
use App\Models\Construction\Company;
use App\Models\Construction\Project;
use App\Models\Construction\ProjectHandover;
use App\Models\Construction\ProjectHandoverItem;
use App\Models\Member;
use App\Models\SuperAdmin;
use App\Services\Construction\ConstructionHandoverService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ConstructionHandoverServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_rejects_handover_completion_when_items_are_pending(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $project = $this->createProject();
        $member = $this->createMember($superAdmin, 'Handover Member');

        $service = app(ConstructionHandoverService::class);

        $handover = $service->createHandover($project, [
            'items' => [
                ['title' => 'Final drawing set', 'status' => 'pending'],
            ],
        ], $member);

        $this->expectException(ValidationException::class);
        $service->completeHandover($handover, [
            'client_signatory_name' => 'Client Signatory',
        ], $member);
    }

    public function test_it_rejects_project_closure_before_handover_completion(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $project = $this->createProject();
        $member = $this->createMember($superAdmin, 'Handover Member');

        $handover = ProjectHandover::create([
            'project_id' => $project->id,
            'handover_code' => 'HND-00001',
            'status' => 'draft',
        ]);

        ProjectHandoverItem::create([
            'handover_id' => $handover->id,
            'title' => 'Asset transfer',
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        $service = app(ConstructionHandoverService::class);

        $this->expectException(ValidationException::class);
        $service->closeProject($handover, [], $member);
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

