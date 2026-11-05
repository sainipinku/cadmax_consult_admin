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

        $projectStatusParam = $projectsParams->firstWhere('name', 'status');
        $this->assertNotNull($projectStatusParam, 'Missing status query parameter in /member/projects doc');
        $this->assertEquals('query', $projectStatusParam['in']);
        $this->assertStringContainsString('running', $projectStatusParam['description']);

        $projectStatusAlias = $projectsParams->firstWhere('name', 'project_status');
        $this->assertNotNull($projectStatusAlias, 'Missing project_status alias query parameter in /member/projects doc');

        $projectRespProps = $memberProjectsGet['responses']['200']['content']['application/json']['schema']['properties'] ?? [];
        $this->assertArrayHasKey('filter_status', $projectRespProps);
        $this->assertArrayHasKey('filter_date', $projectRespProps);

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

        // 4. Verify /member/driver-duty/fuel-entries endpoints
        $this->assertArrayHasKey('/member/driver-duty/fuel-entries', $data['paths']);
        $fuelEntriesGet = $data['paths']['/member/driver-duty/fuel-entries']['get'] ?? [];
        $this->assertEquals('List Driver Fuel Entries & History', $fuelEntriesGet['summary'] ?? null);

        $fuelEntriesPost = $data['paths']['/member/driver-duty/fuel-entries']['post'] ?? [];
        $this->assertEquals('Submit Driver Fuel Entry', $fuelEntriesPost['summary'] ?? null);

        // 5. Verify /member/vehicles/history endpoint tags, summary and parameters
        $this->assertArrayHasKey('/member/vehicles/history', $data['paths']);
        $vehicleHistoryGet = $data['paths']['/member/vehicles/history']['get'] ?? [];
        $this->assertNotEmpty($vehicleHistoryGet);
        $this->assertContains('Member Fleet & Vehicles', $vehicleHistoryGet['tags'] ?? []);
        $this->assertEquals('Get Used Vehicle History', $vehicleHistoryGet['summary'] ?? null);

        $vehicleParams = collect($vehicleHistoryGet['parameters'] ?? []);
        $this->assertNotNull($vehicleParams->firstWhere('name', 'status'), 'Missing status param in /member/vehicles/history');
        $this->assertNotNull($vehicleParams->firstWhere('name', 'project_id'), 'Missing project_id param in /member/vehicles/history');
        $this->assertNotNull($vehicleParams->firstWhere('name', 'from_date'), 'Missing from_date param in /member/vehicles/history');
        $this->assertNotNull($vehicleParams->firstWhere('name', 'to_date'), 'Missing to_date param in /member/vehicles/history');
        $this->assertNotNull($vehicleParams->firstWhere('name', 'search'), 'Missing search param in /member/vehicles/history');

        // 6. Verify /member/equipment/history endpoint tags, summary and parameters
        $this->assertArrayHasKey('/member/equipment/history', $data['paths']);
        $equipmentHistoryGet = $data['paths']['/member/equipment/history']['get'] ?? [];
        $this->assertNotEmpty($equipmentHistoryGet);
        $this->assertContains('Member Equipment & Assets', $equipmentHistoryGet['tags'] ?? []);
        $this->assertEquals('Get Used Equipment History', $equipmentHistoryGet['summary'] ?? null);

        $equipmentParams = collect($equipmentHistoryGet['parameters'] ?? []);
        $this->assertNotNull($equipmentParams->firstWhere('name', 'status'), 'Missing status param in /member/equipment/history');
        $this->assertNotNull($equipmentParams->firstWhere('name', 'project_id'), 'Missing project_id param in /member/equipment/history');
        $this->assertNotNull($equipmentParams->firstWhere('name', 'from_date'), 'Missing from_date param in /member/equipment/history');
        $this->assertNotNull($equipmentParams->firstWhere('name', 'to_date'), 'Missing to_date param in /member/equipment/history');
        $this->assertNotNull($equipmentParams->firstWhere('name', 'search'), 'Missing search param in /member/equipment/history');

        // 7. Verify System & Legal Policies is not polluted with fleet, equipment, location or fuel routes
        foreach ($data['paths'] as $pathKey => $ops) {
            foreach ($ops as $op) {
                $tags = $op['tags'] ?? [];
                if (in_array('System & Legal Policies', $tags, true)) {
                    $this->assertStringNotContainsString('vehicle', $pathKey, "Path $pathKey should not be under 'System & Legal Policies'");
                    $this->assertStringNotContainsString('equipment', $pathKey, "Path $pathKey should not be under 'System & Legal Policies'");
                    $this->assertStringNotContainsString('location', $pathKey, "Path $pathKey should not be under 'System & Legal Policies'");
                    $this->assertStringNotContainsString('fuel', $pathKey, "Path $pathKey should not be under 'System & Legal Policies'");
                }
            }
        }
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
        $this->assertArrayHasKey('/member/driver-duty/fuel-entries', $data['paths']);
        $this->assertArrayHasKey('/member/vehicles/history', $data['paths']);
        $this->assertArrayHasKey('/member/equipment/history', $data['paths']);

        $this->assertContains('Member Fleet & Vehicles', $data['paths']['/member/vehicles/history']['get']['tags'] ?? []);
        $this->assertContains('Member Equipment & Assets', $data['paths']['/member/equipment/history']['get']['tags'] ?? []);

        $tasksDateParam = collect($data['paths']['/member/tasks']['get']['parameters'] ?? [])->firstWhere('name', 'date');
        $this->assertNotNull($tasksDateParam);

        $vehicleStatusParam = collect($data['paths']['/member/vehicles/history']['get']['parameters'] ?? [])->firstWhere('name', 'status');
        $this->assertNotNull($vehicleStatusParam);

        $equipmentStatusParam = collect($data['paths']['/member/equipment/history']['get']['parameters'] ?? [])->firstWhere('name', 'status');
        $this->assertNotNull($equipmentStatusParam);
    }
}

