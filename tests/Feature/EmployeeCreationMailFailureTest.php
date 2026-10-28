<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\EmailLog;
use App\Models\SuperAdmin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class EmployeeCreationMailFailureTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_is_created_when_account_creation_email_fails(): void
    {
        $company = Company::create([
            'name' => 'Employee Test Company',
            'status' => 'active',
        ]);
        $superAdmin = SuperAdmin::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Project Owner',
            'email' => 'owner@example.test',
            'phone' => '9' . random_int(100000000, 999999999),
            'whatsapp_phone' => '8' . random_int(100000000, 999999999),
            'password' => 'password',
            'status' => 1,
            'company_id' => $company->id,
        ]);

        Mail::shouldReceive('to')
            ->once()
            ->with('employee@example.test')
            ->andThrow(new RuntimeException('SMTP authentication failed'));

        $response = $this->actingAs($superAdmin, 'superadmin')->post('/super/employees/store', [
            'full_name' => 'Employee Test',
            'email' => 'employee@example.test',
            'phone' => '9876543210',
            'password' => 'secret1',
            'confirm_password' => 'secret1',
            'role' => 'member',
            'company_id' => $company->id,
            'department' => 'Engineering',
            'designation' => 'Civil Engineer',
        ]);

        $response->assertRedirect();
        $flashMessages = serialize(session('flasher::envelopes', []));
        $this->assertStringContainsString('Employee created successfully!', $flashMessages);
        $this->assertStringContainsString('account notification email could not be sent', $flashMessages);
        $this->assertDatabaseHas('members', ['email' => 'employee@example.test']);
        $this->assertDatabaseHas('employees', [
            'member_id' => \App\Models\Member::where('email', 'employee@example.test')->value('id'),
        ]);
        $this->assertSame(1, Employee::count());
        $this->assertDatabaseHas('email_logs', [
            'to' => 'employee@example.test',
            'status' => 'failed',
        ]);
        $this->assertSame(0, EmailLog::where('to', 'employee@example.test')->where('status', 'sent')->count());
    }
}
