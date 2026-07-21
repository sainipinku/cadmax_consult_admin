<?php

namespace Tests\Feature\Construction;

use App\Models\Construction\Client;
use App\Models\Construction\Company;
use App\Models\Construction\Material;
use App\Models\Construction\Project;
use App\Models\Construction\Vendor;
use App\Models\Member;
use App\Models\SuperAdmin;
use App\Services\Construction\ConstructionMaterialService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ConstructionMaterialDocumentTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_stores_purchase_order_invoice_document(): void
    {
        Storage::fake('public');

        [$project, $member, $material, $vendor] = $this->seedMaterialContext();
        $service = app(ConstructionMaterialService::class);

        $purchaseOrder = $service->createPurchaseOrder($project, [
            'vendor_id' => $vendor->id,
            'po_date' => now()->toDateString(),
            'status' => 'issued',
            'invoice_document' => UploadedFile::fake()->create('vendor-invoice.pdf', 128, 'application/pdf'),
            'items' => [
                [
                    'material_id' => $material->id,
                    'quantity' => 10,
                    'unit' => 'nos',
                    'rate' => 100,
                    'tax_percent' => 18,
                ],
            ],
        ], $member);

        $this->assertNotNull($purchaseOrder->invoice_document_id);
        Storage::disk('public')->assertExists($purchaseOrder->invoiceDocument->path);
    }

    public function test_it_stores_material_receipt_document(): void
    {
        Storage::fake('public');

        [$project, $member, $material, $vendor] = $this->seedMaterialContext();
        $service = app(ConstructionMaterialService::class);

        $purchaseOrder = $service->createPurchaseOrder($project, [
            'vendor_id' => $vendor->id,
            'po_date' => now()->toDateString(),
            'status' => 'issued',
            'items' => [
                [
                    'material_id' => $material->id,
                    'quantity' => 10,
                    'unit' => 'nos',
                    'rate' => 100,
                    'tax_percent' => 18,
                ],
            ],
        ], $member);

        $receipt = $service->receiveMaterials($project, [
            'purchase_order_id' => $purchaseOrder->id,
            'received_at' => now()->toDateTimeString(),
            'receipt_document' => UploadedFile::fake()->create('goods-receipt.pdf', 128, 'application/pdf'),
            'items' => [
                [
                    'material_id' => $material->id,
                    'quantity' => 5,
                    'unit' => 'nos',
                    'rate' => 100,
                ],
            ],
        ], $member);

        $this->assertNotNull($receipt->receipt_document_id);
        Storage::disk('public')->assertExists($receipt->receiptDocument->path);
    }

    private function seedMaterialContext(): array
    {
        $superAdmin = $this->createSuperAdmin();
        $member = $this->createMember($superAdmin, 'Material Member');
        $project = $this->createProject();

        $material = Material::create([
            'project_id' => $project->id,
            'material_code' => 'MAT-00001',
            'name' => 'Cement Bag',
            'unit' => 'bag',
            'status' => 'active',
        ]);

        $vendor = Vendor::create([
            'project_id' => $project->id,
            'vendor_code' => 'VND-00001',
            'name' => 'Vendor One',
            'status' => 'active',
        ]);

        return [$project, $member, $material, $vendor];
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

