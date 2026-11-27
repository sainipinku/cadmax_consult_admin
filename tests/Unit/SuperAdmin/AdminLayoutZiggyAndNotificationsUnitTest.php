<?php

namespace Tests\Unit\SuperAdmin;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AdminLayoutZiggyAndNotificationsUnitTest extends TestCase
{
    public function test_admin_authenticated_layout_guards_ziggy_routes_and_handles_clients_link(): void
    {
        $layout = file_get_contents(resource_path('js/Pages/Admin/Layouts/AuthenticatedLayout.jsx'));

        $this->assertNotFalse($layout);
        $this->assertMatchesRegularExpression('/import\s*\{[^}]*\bFaCity\b[^}]*\}\s*from\s*["\']react-icons\/fa["\']/', $layout);
        $this->assertMatchesRegularExpression('/const\s+safeRoute\s*=/', $layout);
        $this->assertMatchesRegularExpression('/const\s+safeRouteCurrent\s*=/', $layout);

        // No raw unguarded route("admin.construction.clients.index") in AuthenticatedLayout quickPills
        $this->assertDoesNotMatchRegularExpression(
            '/href:\s*route\(\s*[\'"]admin\.construction\.clients\.index[\'"]\s*\)/',
            $layout
        );
    }

    public function test_admin_construction_clients_routes_and_ziggy_manifest_are_registered(): void
    {
        $this->assertTrue(Route::has('admin.construction.clients.index'));
        $this->assertTrue(Route::has('admin.construction.clients.store'));
        $this->assertTrue(Route::has('admin.construction.clients.update'));
        $this->assertTrue(Route::has('admin.construction.clients.status'));
        $this->assertTrue(Route::has('admin.construction.clients.destroy'));
        $this->assertTrue(Route::has('super.construction.clients.index'));

        $ziggy = file_get_contents(resource_path('js/ziggy.js'));
        $this->assertNotFalse($ziggy);
        $this->assertStringContainsString('admin.construction.clients.index', $ziggy);
    }

    public function test_super_and_admin_controllers_share_multi_guard_notification_context_concern(): void
    {
        $concernPath = app_path('Http/Controllers/Concerns/ResolvesAppNotificationContext.php');
        $this->assertFileExists($concernPath);

        $superController = file_get_contents(app_path('Http/Controllers/SuperAdmin/AdminDashboardController.php'));
        $adminController = file_get_contents(app_path('Http/Controllers/Admin/AdminController.php'));

        $this->assertStringContainsString('use ResolvesAppNotificationContext;', $superController);
        $this->assertStringContainsString('use ResolvesAppNotificationContext;', $adminController);
        $this->assertStringNotContainsString(
            '$superAdmin = Auth::guard(\'superadmin\')->user();',
            substr($superController, strpos($superController, 'notificationsUnreadCount'))
        );
    }
}
