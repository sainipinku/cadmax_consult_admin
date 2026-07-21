<?php

namespace Tests\Feature\Construction;

use App\Models\Construction\Client;
use App\Models\Construction\ClientInvoice;
use App\Models\Construction\Company;
use App\Models\Construction\Project;
use App\Models\Construction\ProjectTeamMember;
use App\Models\Member;
use App\Models\SuperAdmin;
use App\Services\Construction\ConstructionBillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ConstructionBillingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_computes_intra_state_gst_as_cgst_and_sgst(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $project = $this->createProject();
        $member = $this->createMember($superAdmin, 'Billing Member');

        ProjectTeamMember::create([
            'project_id' => $project->id,
            'member_id' => $member->id,
            'status' => 'active',
        ]);

        $service = app(ConstructionBillingService::class);

        $invoice = $service->createInvoice($project, [
            'invoice_date' => now()->toDateString(),
            'tax_type' => 'intra',
            'status' => 'issued',
            'items' => [
                [
                    'description' => 'Civil Work',
                    'quantity' => 1,
                    'rate' => 1000,
                    'gst_percent' => 18,
                ],
            ],
        ], $member);

        $this->assertSame(1000.0, (float) $invoice->subtotal_amount);
        $this->assertSame(90.0, (float) $invoice->cgst_amount);
        $this->assertSame(90.0, (float) $invoice->sgst_amount);
        $this->assertSame(0.0, (float) $invoice->igst_amount);
        $this->assertSame(180.0, (float) $invoice->total_tax_amount);
        $this->assertSame(1180.0, (float) $invoice->total_amount);
        $this->assertSame(1180.0, (float) $invoice->balance_amount);
    }

    public function test_it_prevents_overpayment(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $project = $this->createProject();
        $member = $this->createMember($superAdmin, 'Billing Member');

        ProjectTeamMember::create([
            'project_id' => $project->id,
            'member_id' => $member->id,
            'status' => 'active',
        ]);

        $service = app(ConstructionBillingService::class);

        $invoice = $service->createInvoice($project, [
            'invoice_date' => now()->toDateString(),
            'tax_type' => 'inter',
            'status' => 'issued',
            'items' => [
                [
                    'description' => 'Consulting',
                    'quantity' => 1,
                    'rate' => 100,
                    'gst_percent' => 18,
                ],
            ],
        ], $member);

        try {
            $service->recordPayment($project, [
                'invoice_id' => $invoice->id,
                'amount' => 1000,
                'method' => 'upi',
            ], $member);

            $this->fail('Expected overpayment to fail.');
        } catch (ValidationException $exception) {
            $this->assertSame([
                'Payment amount cannot exceed invoice balance.',
            ], $exception->errors()['amount']);
        }
    }

    public function test_it_rejects_payment_for_invoice_from_another_project(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $projectA = $this->createProject('PRJ-A', 'project-a', 'Project A');
        $projectB = $this->createProject('PRJ-B', 'project-b', 'Project B');
        $member = $this->createMember($superAdmin, 'Billing Member');

        ProjectTeamMember::create([
            'project_id' => $projectA->id,
            'member_id' => $member->id,
            'status' => 'active',
        ]);

        $invoiceB = ClientInvoice::create([
            'project_id' => $projectB->id,
            'invoice_code' => 'INV-00099',
            'invoice_date' => now()->toDateString(),
            'tax_type' => 'inter',
            'status' => 'issued',
            'subtotal_amount' => 100,
            'total_tax_amount' => 0,
            'total_amount' => 100,
            'paid_amount' => 0,
            'balance_amount' => 100,
        ]);

        $service = app(ConstructionBillingService::class);

        try {
            $service->recordPayment($projectA, [
                'invoice_id' => $invoiceB->id,
                'amount' => 10,
            ], $member);

            $this->fail('Expected cross-project invoice payment to fail.');
        } catch (ValidationException $exception) {
            $this->assertSame([
                'The selected invoice does not belong to the chosen project.',
            ], $exception->errors()['invoice_id']);
        }
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

