<?php

namespace Tests\Feature;

use Tests\TestCase;

class ApiDocumentationTest extends TestCase
{
    public function test_scalar_api_docs_ui_is_accessible(): void
    {
        $response = $this->get('/docs/api');

        $response->assertOk();
        $this->assertStringContainsString('CadMax Construction ERP API Docs', $response->getContent());
        $this->assertStringContainsString('scalar', strtolower($response->getContent()));
    }

    public function test_openapi_json_document_is_served_with_updated_endpoints_and_parameters(): void
    {
        $response = $this->get('/docs/api.json');

        $response->assertOk();
        $data = $response->json();

        $this->assertIsArray($data);
        $this->assertEquals('CadMax Construction ERP API Docs', $data['info']['title'] ?? null);
        $this->assertArrayHasKey('paths', $data);

        // 1. Verify /member/tasks endpoint parameters
        $this->assertArrayHasKey('/member/tasks', $data['paths']);
        $memberTasksGet = $data['paths']['/member/tasks']['get'] ?? [];
        $this->assertNotEmpty($memberTasksGet);
        $this->assertEquals('List Member Assigned Tasks', $memberTasksGet['summary'] ?? null);

        $tasksParams = collect($memberTasksGet['parameters'] ?? []);
        $dateParam = $tasksParams->firstWhere('name', 'date');
        $this->assertNotNull($dateParam, 'Missing date query parameter in /member/tasks doc');
        $this->assertEquals('query', $dateParam['in']);
        $this->assertStringContainsString('today', $dateParam['description']);

        $statusParam = $tasksParams->firstWhere('name', 'status');
        $this->assertNotNull($statusParam, 'Missing status query parameter in /member/tasks doc');

        $projectIdParam = $tasksParams->firstWhere('name', 'project_id');
        $this->assertNotNull($projectIdParam, 'Missing project_id query parameter in /member/tasks doc');

        // 2. Verify /member/projects endpoint parameters
        $this->assertArrayHasKey('/member/projects', $data['paths']);
        $memberProjectsGet = $data['paths']['/member/projects']['get'] ?? [];
        $this->assertNotEmpty($memberProjectsGet);
        $this->assertEquals('List Assigned Projects', $memberProjectsGet['summary'] ?? null);

        $projectsParams = collect($memberProjectsGet['parameters'] ?? []);
        $projectDateParam = $projectsParams->firstWhere('name', 'date');
        $this->assertNotNull($projectDateParam, 'Missing date query parameter in /member/projects doc');
        $this->assertEquals('query', $projectDateParam['in']);
        $this->assertStringContainsString('today', $projectDateParam['description']);

        // 3. Verify /member/dashboard endpoint schema
        $this->assertArrayHasKey('/member/dashboard', $data['paths']);
        $dashboardGet = $data['paths']['/member/dashboard']['get'] ?? [];
        $this->assertNotEmpty($dashboardGet);
        $this->assertEquals('Member Dashboard Overview', $dashboardGet['summary'] ?? null);

        $resp200Props = $dashboardGet['responses']['200']['content']['application/json']['schema']['properties'] ?? [];
        $this->assertArrayHasKey('todays_summary', $resp200Props);
        $todaysSummaryProps = $resp200Props['todays_summary']['properties'] ?? [];
        $this->assertArrayHasKey('projects_count', $todaysSummaryProps);
        $this->assertArrayHasKey('pending_tasks', $todaysSummaryProps);
        $this->assertArrayHasKey('completed_tasks', $todaysSummaryProps);
        $this->assertArrayHasKey('working_hours', $todaysSummaryProps);
    }

    public function test_exported_api_json_file_is_valid_and_in_sync(): void
    {
        $filePath = base_path('api.json');
        $this->assertFileExists($filePath);

        $contents = file_get_contents($filePath);
        $data = json_decode($contents, true);

        $this->assertIsArray($data);
        $this->assertEquals('CadMax Construction ERP API Docs', $data['info']['title'] ?? null);
        $this->assertArrayHasKey('/member/tasks', $data['paths']);
        $this->assertArrayHasKey('/member/projects', $data['paths']);
        $this->assertArrayHasKey('/member/dashboard', $data['paths']);

        $tasksDateParam = collect($data['paths']['/member/tasks']['get']['parameters'] ?? [])->firstWhere('name', 'date');
        $this->assertNotNull($tasksDateParam);
    }
}

