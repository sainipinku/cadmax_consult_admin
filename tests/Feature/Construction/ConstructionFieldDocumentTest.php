<?php

namespace Tests\Feature\Construction;

use App\Models\Client;
use App\Models\Company;
use App\Models\ExecutionPlan;
use App\Models\Project;
use App\Models\ProjectTeamMember;
use App\Models\SurveyEntry;
use App\Models\SurveyPlan;
use App\Models\SurveyVisit;
use App\Models\Member;
use App\Models\SuperAdmin;
use App\Services\Construction\ConstructionDocumentService;
use App\Services\Construction\ConstructionExecutionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ConstructionFieldDocumentTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_stores_supporting_document_for_dpr(): void
    {
        Storage::fake('public');

        [$project, $member] = $this->seedProjectMemberContext();
        $service = app(ConstructionExecutionService::class);

        $report = $service->submitDailyProgress($project, [
            'report_date' => now()->toDateString(),
            'summary' => 'Daily site progress',
            'supporting_document' => UploadedFile::fake()->create('dpr-photo.jpg', 128, 'image/jpeg'),
            'items' => [
                [
                    'title' => 'Masonry update',
                    'completed_quantity' => 5,
                ],
            ],
        ], $member);

        $this->assertNotNull($report->supporting_document_id);
        Storage::disk('public')->assertExists($report->supportingDocument->path);
    }

    public function test_it_stores_supporting_document_for_survey_entry(): void
    {
        Storage::fake('public');

        [$project, $member] = $this->seedProjectMemberContext();
        $documentService = app(ConstructionDocumentService::class);

        $surveyPlan = SurveyPlan::create([
            'project_id' => $project->id,
            'survey_code' => 'SUR-00001',
            'title' => 'Initial Survey',
            'status' => SurveyPlan::STATUS_IN_PROGRESS,
        ]);

        $surveyVisit = SurveyVisit::create([
            'project_id' => $project->id,
            'survey_plan_id' => $surveyPlan->id,
            'checked_in_by_member_id' => $member->id,
            'check_in_at' => now(),
            'status' => SurveyVisit::STATUS_IN_PROGRESS,
        ]);

        $document = $documentService->storeDocument(
            documentable: $surveyVisit,
            actor: $member,
            folder: 'construction/survey/entries',
            file: UploadedFile::fake()->create('survey-note.pdf', 64, 'application/pdf'),
            companyId: $project->company_id,
            projectId: $project->id
        );

        $entry = SurveyEntry::create([
            'project_id' => $project->id,
            'survey_visit_id' => $surveyVisit->id,
            'entry_type' => 'photo',
            'title' => 'Front elevation',
            'supporting_document_id' => $document->id,
            'captured_by_member_id' => $member->id,
            'captured_at' => now(),
            'sort_order' => 0,
        ]);

        $this->assertNotNull($entry->supporting_document_id);
        Storage::disk('public')->assertExists($entry->supportingDocument->path);
    }

    private function seedProjectMemberContext(): array
    {
        $superAdmin = $this->createSuperAdmin();
        $project = $this->createProject();
        $member = $this->createMember($superAdmin, 'Field Member');

        ProjectTeamMember::create([
            'project_id' => $project->id,
            'member_id' => $member->id,
            'status' => 'active',
        ]);

        ExecutionPlan::create([
            'project_id' => $project->id,
            'plan_code' => 'EXP-00001',
            'title' => 'Execution Plan',
            'status' => 'planned',
        ]);

        return [$project, $member];
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

