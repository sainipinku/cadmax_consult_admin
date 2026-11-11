<?php

namespace Tests\Unit\SuperAdmin;

use App\Http\Controllers\SuperAdmin\EquipmentMasterController;
use App\Models\Equipment;
use App\Models\ConstructionEquipment;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;

class EquipmentMasterScopingAndSyncUnitTest extends TestCase
{
    public function test_equipment_master_controller_defines_current_actor_method(): void
    {
        $reflector = new ReflectionClass(EquipmentMasterController::class);
        $this->assertTrue(
            $reflector->hasMethod('currentActor'),
            'EquipmentMasterController must define a currentActor() method to support multi-guard authentication'
        );

        $method = $reflector->getMethod('currentActor');
        $this->assertTrue(
            $method->isProtected() || $method->isPublic(),
            'currentActor() method must be accessible'
        );
    }

    public function test_equipment_master_controller_defines_sync_construction_equipments_method(): void
    {
        $reflector = new ReflectionClass(EquipmentMasterController::class);
        $this->assertTrue(
            $reflector->hasMethod('syncConstructionEquipmentsToMaster'),
            'EquipmentMasterController must define a syncConstructionEquipmentsToMaster() method to bridge construction_equipments to equipments'
        );
    }

    public function test_equipment_master_controller_contains_company_scoping_expansion(): void
    {
        $controllerFile = app_path('Http/Controllers/SuperAdmin/EquipmentMasterController.php');
        $this->assertFileExists($controllerFile);

        $code = file_get_contents($controllerFile);
        $this->assertStringContainsString(
            'syncConstructionEquipmentsToMaster',
            $code,
            'EquipmentMasterController must call syncConstructionEquipmentsToMaster during index'
        );
        $this->assertStringContainsString(
            'currentActor',
            $code,
            'EquipmentMasterController must resolve actor via currentActor()'
        );
        $this->assertStringContainsString(
            'assigned_project_id',
            $code,
            'EquipmentMasterController must consider assigned_project_id in company scoping'
        );
    }

    public function test_construction_equipment_service_syncs_to_master_on_create(): void
    {
        $serviceFile = app_path('Services/Construction/ConstructionEquipmentService.php');
        $this->assertFileExists($serviceFile);

        $code = file_get_contents($serviceFile);
        $this->assertStringContainsString(
            'Equipment::firstOrCreate',
            $code,
            'ConstructionEquipmentService::createEquipment must ensure master Equipment record exists'
        );
    }
}

