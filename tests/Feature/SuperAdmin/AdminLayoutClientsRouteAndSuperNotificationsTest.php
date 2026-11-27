<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\Client;
use App\Models\Company;
use App\Models\ConstructionRole;
use App\Models\Member;
use App\Models\MemberRoleAssignment;
use App\Models\Notification;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SuperAdmin;
use App\Services\Construction\CompanyRoleProvisioningService;
use Database\Seeders\Construction\FoundationSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\SuperAdminPermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminLayoutClientsRouteAndSuperNotificationsTest extends TestCase
{
    use DatabaseTransactions;

    private function actingOnlyAs($user, string $guard)
    {
        foreach (['superadmin', 'admin', 'member', 'callingteam', 'web'] as $g) {
            if (auth()->guard($g)->check()) {
                auth()->guard($g)->logout();
            }
        }
        session()->flush();

        return $this->actingAs($user, $guard);
    }

    private function seedPermissions(): void
    {
        $this->seed(PermissionSeeder::class);
        $this->seed(SuperAdminPermissionSeeder::class);
        $this->seed(FoundationSeeder::class);
    }

    private function makeCompanyAdminFixture(string $suffix): array
    {
        $this->seedPermissions();

        $company = Company::create([
            'name' => "Notify Client Co {$suffix}",
            'status' => 'active',
        ]);

        $superAdmin = SuperAdmin::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'roles' => ['super_admin'],
            'name' => "SA {$suffix}",
            'email' => "sa_{$suffix}@example.com",
            'phone' => (string) random_int(7000000000, 9999999999),
            'whatsapp_phone' => (string) random_int(7000000000, 9999999999),
            'password' => Hash::make('secret123'),
            'status' => 1,
        ]);

        $company->update([
            'created_by_type' => SuperAdmin::class,
            'created_by_id' => $superAdmin->id,
        ]);

        app(CompanyRoleProvisioningService::class)->provisionCompanyRoles($company->id);
        app(CompanyRoleProvisioningService::class)->syncAllSuperAdminAndAdminPermissions();

        $adminRole = Role::firstOrCreate(
            ['slug' => 'admin'],
            ['name' => 'Admin', 'status' => 1, 'is_default' => 1, 'created_by' => 1]
        );

        $memberRole = Role::firstOrCreate(
            ['slug' => 'member'],
            ['name' => 'Member', 'status' => 1, 'is_default' => 1, 'created_by' => 1]
        );

        $adminMember = Member::create([
            'uuid' => (string) Str::uuid(),
            'created_by' => $superAdmin->id,
            'company_name' => $company->name,
            'name' => "Company Admin {$suffix}",
            'username' => "admin_{$suffix}",
            'slug' => Str::slug("admin-{$suffix}"),
            'email' => "admin_{$suffix}@example.com",
            'phone' => (string) random_int(7000000000, 9999999999),
            'password' => Hash::make('secret123'),
            'status' => 1,
            'roles' => [$adminRole->id],
        ]);

        $workerMember = Member::create([
            'uuid' => (string) Str::uuid(),
            'created_by' => $superAdmin->id,
            'company_name' => $company->name,
            'name' => "Site Worker {$suffix}",
            'username' => "worker_{$suffix}",
            'slug' => Str::slug("worker-{$suffix}"),
            'email' => "worker_{$suffix}@example.com",
            'phone' => (string) random_int(7000000000, 9999999999),
            'password' => Hash::make('secret123'),
            'status' => 1,
            'roles' => [$memberRole->id],
        ]);

        $cAdminRole = ConstructionRole::where('company_id', $company->id)->where('slug', 'admin')->firstOrFail();
        $clientManagePerm = Permission::where('slug', 'client.manage')->first();
        if ($clientManagePerm) {
            $cAdminRole->permissions()->syncWithoutDetaching([$clientManagePerm->id]);
        }

        MemberRoleAssignment::create([
            'member_id' => $adminMember->id,
            'role_id' => $cAdminRole->id,
            'project_id' => null,
            'status' => 1,
        ]);

        return [$company, $superAdmin, $adminMember, $workerMember];
    }

    public function test_super_notifications_api_does_not_500_for_admin_impersonated_member_or_unauthenticated_impersonator(): void
    {
        $suffix = Str::lower(Str::random(6));
        [$company, $superAdmin, $adminMember, $workerMember] = $this->makeCompanyAdminFixture($suffix);

        Notification::create([
            'model' => 'superadmin',
            'listing_id' => $superAdmin->id,
            'type' => 'job_pending',
            'status' => 'unread',
            'data' => ['title' => 'SA Alert'],
        ]);

        $adminNotif = Notification::create([
            'model' => 'admin',
            'listing_id' => $adminMember->id,
            'type' => 'job_approved',
            'status' => 'unread',
            'data' => ['title' => 'Admin Alert'],
        ]);

        Notification::create([
            'model' => 'member',
            'listing_id' => $workerMember->id,
            'type' => 'job_assigned',
            'status' => 'unread',
            'data' => ['title' => 'Member Alert'],
        ]);

        // 1. SuperAdmin works cleanly
        $this->actingOnlyAs($superAdmin, 'superadmin')
            ->getJson('/super/notifications/api/unread-count')
            ->assertOk()
            ->assertJson(['success' => true, 'unread' => 1]);

        // 2. Company Admin (guard: admin) hitting /super/notifications/api/* must return 200, never 500
        $this->actingOnlyAs($adminMember, 'admin')
            ->getJson('/super/notifications/api/unread-count')
            ->assertOk()
            ->assertJson(['success' => true, 'unread' => 1]);

        $this->actingOnlyAs($adminMember, 'admin')
            ->getJson('/super/notifications/api/list?per_page=10')
            ->assertOk()
            ->assertJson(['success' => true, 'unread' => 1])
            ->assertJsonPath('data.data.0.uuid', $adminNotif->uuid);

        $this->actingOnlyAs($adminMember, 'admin')
            ->patchJson("/super/notifications/api/{$adminNotif->uuid}/read")
            ->assertOk()
            ->assertJson(['success' => true, 'unread' => 0]);

        $this->actingOnlyAs($adminMember, 'admin')
            ->deleteJson("/super/notifications/api/{$adminNotif->uuid}")
            ->assertOk()
            ->assertJson(['success' => true, 'unread' => 0]);

        // 3. Impersonated member session hitting /super/notifications/api/* returns member count (200, never 500)
        $this->actingOnlyAs($workerMember, 'member')
            ->withSession(['impersonator' => ['id' => $superAdmin->id, 'guard' => 'superadmin']])
            ->getJson('/super/notifications/api/unread-count')
            ->assertOk()
            ->assertJson(['success' => true, 'unread' => 1]);

        $this->actingOnlyAs($workerMember, 'member')
            ->withSession(['impersonator' => ['id' => $superAdmin->id, 'guard' => 'superadmin']])
            ->patchJson('/super/notifications/api/read-all')
            ->assertOk()
            ->assertJson(['success' => true, 'unread' => 0]);

        // 4. Stale impersonator session without authenticated guard returns 401 cleanly, never 500
        foreach (['superadmin', 'admin', 'member', 'callingteam', 'web'] as $g) {
            if (auth()->guard($g)->check()) {
                auth()->guard($g)->logout();
            }
        }
        session()->flush();

        $this->withSession(['impersonator' => ['id' => 999]])
            ->getJson('/super/notifications/api/unread-count')
            ->assertStatus(401);

        // 5. SuperAdmin hitting /admin/api/notifications/unread-count returns 200 instead of 401
        $this->actingOnlyAs($superAdmin, 'superadmin')
            ->getJson('/admin/api/notifications/unread-count')
            ->assertOk()
            ->assertJson(['success' => true, 'unread' => 1]);
    }

    public function test_admin_construction_clients_routes_work_for_authorized_admin_and_enforce_permissions(): void
    {
        $suffix = Str::lower(Str::random(6));
        [$company, $superAdmin, $adminMember, $workerMember] = $this->makeCompanyAdminFixture($suffix);

        $otherCompany = Company::create([
            'name' => "Other Co {$suffix}",
            'status' => 'active',
        ]);

        $ownClient = Client::create([
            'company_id' => $company->id,
            'client_type' => 'company',
            'name' => "Own Client {$suffix}",
            'client_code' => Client::generateUniqueClientCode(),
            'status' => 'active',
        ]);

        $otherClient = Client::create([
            'company_id' => $otherCompany->id,
            'client_type' => 'company',
            'name' => "Other Client {$suffix}",
            'client_code' => Client::generateUniqueClientCode(),
            'status' => 'active',
        ]);

        // Admin with client.manage can access admin.construction.clients.index and sees only own company clients
        $res = $this->actingOnlyAs($adminMember, 'admin')
            ->get('/admin/construction/clients');

        $res->assertOk();
        $clients = collect($res->viewData('page')['props']['clients']);
        $this->assertTrue($clients->contains('id', $ownClient->id));
        $this->assertFalse($clients->contains('id', $otherClient->id));

        // Admin can create a client via admin.construction.clients.store
        $createRes = $this->actingOnlyAs($adminMember, 'admin')
            ->post('/admin/construction/clients', [
                'company_id' => $company->id,
                'client_type' => 'individual',
                'name' => "Created Client {$suffix}",
                'phone' => '9876543210',
                'status' => 'active',
            ]);

        $createRes->assertRedirect();
        $this->assertDatabaseHas('construction_clients', [
            'company_id' => $company->id,
            'name' => "Created Client {$suffix}",
        ]);

        // Worker without client permissions gets 403
        $this->actingOnlyAs($workerMember, 'member')
            ->withSession(['impersonator' => ['id' => $superAdmin->id, 'guard' => 'superadmin']])
            ->post('/admin/construction/clients', [
                'company_id' => $company->id,
                'client_type' => 'individual',
                'name' => "Blocked Client {$suffix}",
                'status' => 'active',
            ])
            ->assertStatus(403);
    }
}
