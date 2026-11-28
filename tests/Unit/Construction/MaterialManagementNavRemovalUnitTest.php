<?php

namespace Tests\Unit\Construction;

use Tests\TestCase;

class MaterialManagementNavRemovalUnitTest extends TestCase
{
    public function test_construction_shell_removes_material_management_from_all_variants(): void
    {
        $path = resource_path('js/Pages/Construction/Components/ConstructionShell.jsx');
        $this->assertFileExists($path);

        $content = file_get_contents($path);
        $this->assertIsString($content);

        $this->assertStringNotContainsString('Material Management', $content);
        $this->assertStringNotContainsString('construction.materials.index', $content);

        // Remaining Member shell tabs must remain intact
        $this->assertStringContainsString('label: "Project Dashboard"', $content);
        $this->assertStringContainsString('label: "Assigned Projects"', $content);
        $this->assertStringContainsString('label: "Site Execution"', $content);
        $this->assertStringContainsString('label: "Vehicle Tracking"', $content);
        $this->assertStringContainsString('label: "Equipment Tracking"', $content);
        $this->assertStringContainsString('label: "Handover & Closure"', $content);
    }

    public function test_member_authenticated_layout_and_sidebar_remove_materials_navigation(): void
    {
        $layoutPath = resource_path('js/Pages/Member/Layouts/AuthenticatedLayout.jsx');
        $this->assertFileExists($layoutPath);

        $layout = file_get_contents($layoutPath);
        $this->assertIsString($layout);

        $this->assertStringNotContainsString('member.construction.materials.index', $layout);
        $this->assertStringNotContainsString('member.construction.materials.*', $layout);
        $this->assertDoesNotMatchRegularExpression('/label:\s*["\']Materials["\']/', $layout);
        $this->assertStringNotContainsString('Material Management', $layout);
        $this->assertStringNotContainsString('FaBoxes', $layout);

        // Remaining Member quickPills stay intact
        $this->assertMatchesRegularExpression('/label:\s*["\']Projects["\']/', $layout);
        $this->assertMatchesRegularExpression('/label:\s*["\']Execution["\']/', $layout);
        $this->assertMatchesRegularExpression('/label:\s*["\']Vehicles["\']/', $layout);

        $sidebarPath = resource_path('js/Pages/Member/Layouts/Sidebar.jsx');
        $this->assertFileExists($sidebarPath);

        $sidebar = file_get_contents($sidebarPath);
        $this->assertIsString($sidebar);
        $this->assertStringNotContainsString('FaBoxes', $sidebar);
        $this->assertStringNotContainsString('Material Management', $sidebar);
        $this->assertStringNotContainsString('Materials → Equipment', $sidebar);
    }

    public function test_super_admin_and_admin_layouts_have_no_leftover_material_management_links(): void
    {
        foreach ([
            resource_path('js/Pages/SuperAdmin/Layouts/AuthenticatedLayout.jsx'),
            resource_path('js/Pages/Admin/Layouts/AuthenticatedLayout.jsx'),
        ] as $path) {
            $content = file_get_contents($path);
            $this->assertIsString($content);
            $this->assertStringNotContainsString('Material Management', $content);
            $this->assertStringNotContainsString('construction.materials.index', $content);
        }
    }
}
