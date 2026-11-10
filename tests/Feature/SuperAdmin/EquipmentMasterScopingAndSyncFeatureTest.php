<?php

namespace Tests\Feature\SuperAdmin;

use App\Http\Controllers\SuperAdmin\EquipmentMasterController;
use Tests\TestCase;

class EquipmentMasterScopingAndSyncFeatureTest extends TestCase
{
    public function test_controller_code_includes_multi_guard_actor_resolution(): void
    {
        $controllerFile = app_path('Http/Controllers/SuperAdmin/EquipmentMasterController.php');
        $code = file_get_contents($controllerFile);

        $this->assertStringContainsString("Auth::guard('superadmin')->user()", $code);
        $this->assertStringContainsString("Auth::guard('admin')->user()", $code);
    }

    public function test_controller_scopes_to_company_admin_ids_and_projects(): void
    {
        $controllerFile = app_path('Http/Controllers/SuperAdmin/EquipmentMasterController.php');
        $code = file_get_contents($controllerFile);

        $this->assertStringContainsString('companyAdminIds', $code);
        $this->assertStringContainsString('companyProjectIds', $code);
    }

    public function test_equipment_model_for_actor_scope_supports_company_projects(): void
    {
        $equipmentModelFile = app_path('Models/Equipment.php');
        $code = file_get_contents($equipmentModelFile);

        $this->assertStringContainsString('scopeForActor', $code);
        $this->assertStringContainsString('assigned_project_id', $code);
    }
}

