<?php

namespace Tests\Feature\Construction;

use App\Http\Controllers\Construction\DocumentController;
use App\Models\Construction\Client;
use App\Models\Construction\Company;
use App\Models\Construction\Document;
use App\Models\Construction\Project;
use App\Models\Construction\ProjectHandover;
use App\Models\Construction\ProjectTeamMember;
use App\Models\Member;
use App\Models\SuperAdmin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ConstructionDocumentControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_assigned_member_can_view_document(): void
    {
        Storage::fake('public');

        [$document, $member] = $this->createProjectDocumentWithAssignedMember();

        $controller = app(DocumentController::class);
        $request = Request::create('/fake', 'GET');
        $request->setUserResolver(fn () => $member);

        $response = $controller->view($document, $request);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_unassigned_member_cannot_view_document(): void
    {
        Storage::fake('public');

        [$document] = $this->createProjectDocumentWithAssignedMember();
        $superAdmin = $this->createSuperAdmin();
        $otherMember = $this->createMember($superAdmin, 'Other Member');

        $controller = app(DocumentController::class);
        $request = Request::create('/fake', 'GET');
        $request->setUserResolver(fn () => $otherMember);

        $this->expectException(HttpException::class);
        $controller->view($document, $request);
    }

    /**
     * @return array{0: Document, 1: Member}
     */
    private function createProjectDocumentWithAssignedMember(): array
    {
        $superAdmin = $this->createSuperAdmin();
        $project = $this->createProject();
        $member = $this->createMember($superAdmin, 'Document Member');
        $handover = ProjectHandover::create([
            'project_id' => $project->id,
            'handover_code' => 'HND-00001',
            'status' => 'draft',
        ]);

        ProjectTeamMember::create([
            'project_id' => $project->id,
            'member_id' => $member->id,
            'status' => 'active',
        ]);

        Storage::disk('public')->put('construction/handover/final-docs/test.pdf', 'dummy');

        $document = Document::create([
            'company_id' => $project->company_id,
            'project_id' => $project->id,
            'documentable_type' => $handover::class,
            'documentable_id' => $handover->id,
            'folder' => 'construction/handover/final-docs',
            'file_name' => 'test.pdf',
            'original_name' => 'test.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 5,
            'disk' => 'public',
            'path' => 'construction/handover/final-docs/test.pdf',
        ]);

        return [$document, $member];
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

