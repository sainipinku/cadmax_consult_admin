<?php

namespace Tests\Feature\Construction;

use App\Models\Construction\Client;
use App\Models\Construction\Company;
use App\Models\Construction\Project;
use App\Models\Construction\ProjectHandover;
use App\Models\Member;
use App\Models\SuperAdmin;
use App\Services\Construction\ConstructionDocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ConstructionDocumentServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_stores_construction_documents_on_public_disk(): void
    {
        Storage::fake('public');

        $superAdmin = $this->createSuperAdmin();
        $project = $this->createProject();
        $member = $this->createMember($superAdmin, 'Document Member');
        $handover = ProjectHandover::create([
            'project_id' => $project->id,
            'handover_code' => 'HND-00001',
            'status' => 'draft',
        ]);

        $service = app(ConstructionDocumentService::class);
        $file = UploadedFile::fake()->create('final-handover.pdf', 128, 'application/pdf');

        $document = $service->storeDocument(
            documentable: $handover,
            actor: $member,
            folder: 'construction/handover/final-docs',
            file: $file,
            companyId: $project->company_id,
            projectId: $project->id
        );

        Storage::disk('public')->assertExists($document->path);
        $this->assertSame('public', $document->disk);
        $this->assertSame('final-handover.pdf', $document->original_name);
        $this->assertGreaterThan(0, $document->file_size);
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

