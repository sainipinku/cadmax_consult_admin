<?php

namespace Tests\Unit\Construction;

use Tests\TestCase;

class TopNavExecutionTabRemovalUnitTest extends TestCase
{
    public function test_super_admin_authenticated_layout_removes_execution_top_nav_tab_and_retains_remaining_tabs(): void
    {
        $path = resource_path('js/Pages/SuperAdmin/Layouts/AuthenticatedLayout.jsx');
        $this->assertFileExists($path);

        $content = file_get_contents($path);
        $this->assertIsString($content);

        $this->assertStringNotContainsString('super.construction.execution.index', $content);
        $this->assertStringNotContainsString('super.construction.execution.*', $content);
        $this->assertDoesNotMatchRegularExpression('/label:\s*["\']Execution["\']/', $content);
        $this->assertStringNotContainsString('FaHardHat', $content);

        // Remaining quick pills must remain intact
        $this->assertMatchesRegularExpression('/label:\s*["\']Projects["\']/', $content);
        $this->assertMatchesRegularExpression('/label:\s*["\']Clients["\']/', $content);
        $this->assertMatchesRegularExpression('/label:\s*["\']Billing["\']/', $content);
    }

    public function test_admin_authenticated_layout_removes_execution_top_nav_tab_and_retains_remaining_tabs(): void
    {
        $path = resource_path('js/Pages/Admin/Layouts/AuthenticatedLayout.jsx');
        $this->assertFileExists($path);

        $content = file_get_contents($path);
        $this->assertIsString($content);

        $this->assertStringNotContainsString('admin.construction.execution.index', $content);
        $this->assertStringNotContainsString('admin.construction.execution.*', $content);
        $this->assertDoesNotMatchRegularExpression('/label:\s*["\']Execution["\']/', $content);
        $this->assertStringNotContainsString('FaHardHat', $content);

        // Remaining quick pills must remain intact
        $this->assertMatchesRegularExpression('/label:\s*["\']Projects["\']/', $content);
        $this->assertMatchesRegularExpression('/label:\s*["\']Clients["\']/', $content);
        $this->assertMatchesRegularExpression('/label:\s*["\']Billing["\']/', $content);
    }

    public function test_member_authenticated_layout_preserves_member_execution_navigation(): void
    {
        $path = resource_path('js/Pages/Member/Layouts/AuthenticatedLayout.jsx');
        $this->assertFileExists($path);

        $content = file_get_contents($path);
        $this->assertIsString($content);

        $this->assertStringContainsString('member.construction.execution.index', $content);
        $this->assertMatchesRegularExpression('/label:\s*["\']Execution["\']/', $content);
    }
}
