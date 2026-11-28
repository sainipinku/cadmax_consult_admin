<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\SuperAdmin;
use App\Models\SystemSetting;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WebsiteLegalPagesFeatureTest extends TestCase
{
    use DatabaseTransactions;

    public function test_website_privacy_and_terms_routes_are_public_and_render_inertia_page_with_fallback(): void
    {
        SystemSetting::whereIn('name', ['privacy_policy', 'terms_and_conditions'])->delete();

        foreach (['/privacy', '/privacy-policy'] as $uri) {
            $response = $this->get($uri);
            $response->assertStatus(200);
            $response->assertInertia(fn (Assert $page) => $page
                ->component('Public/LegalPage')
                ->where('page.key', 'privacy_policy')
                ->where('page.slug', 'privacy-policy')
                ->where('page.title', 'Privacy Policy')
                ->where('page.last_updated', '2026-08-01 00:00:00')
                ->has('page.content_html')
                ->has('page.document_html')
            );
        }

        foreach (['/terms', '/terms-and-conditions'] as $uri) {
            $response = $this->get($uri);
            $response->assertStatus(200);
            $response->assertInertia(fn (Assert $page) => $page
                ->component('Public/LegalPage')
                ->where('page.key', 'terms_and_conditions')
                ->where('page.slug', 'terms-and-conditions')
                ->where('page.title', 'Terms & Conditions')
                ->where('page.last_updated', '2026-08-01 00:00:00')
                ->has('page.content_html')
                ->has('page.document_html')
            );
        }
    }

    public function test_website_legal_pages_render_dynamic_full_html_documents_from_system_settings(): void
    {
        $privacyHtml = "<!DOCTYPE html>\n<html lang='en'>\n<head>\n<meta charset='UTF-8'>\n<style>h1 { color: #0f172a; }</style>\n</head>\n<body>\n<h1>CadMax Privacy Policy DB</h1>\n<p>Stored dynamically in system_settings.</p>\n</body>\n</html>";
        $termsHtml = "<!DOCTYPE html>\n<html lang='en'>\n<head>\n<meta charset='UTF-8'>\n<style>h1 { color: #312e81; }</style>\n</head>\n<body>\n<h1>CadMax Terms DB</h1>\n<p>Field compliance and usage terms.</p>\n</body>\n</html>";

        SystemSetting::setSettingValue('privacy_policy', $privacyHtml, [
            'title' => 'Privacy Policy (Live DB)',
            'format' => 'html',
            'updated_by' => 'Compliance Admin',
        ]);

        SystemSetting::setSettingValue('terms_and_conditions', $termsHtml, [
            'title' => 'Terms & Conditions (Live DB)',
            'format' => 'html',
            'updated_by' => 'Legal Team',
        ]);

        $privacyResponse = $this->get('/privacy-policy');
        $privacyResponse->assertStatus(200);
        $privacyResponse->assertInertia(fn (Assert $page) => $page
            ->component('Public/LegalPage')
            ->where('page.key', 'privacy_policy')
            ->where('page.title', 'Privacy Policy (Live DB)')
            ->where('page.updated_by', 'Compliance Admin')
            ->where('page.is_full_document', true)
            ->where('page.content_html', $privacyHtml)
        );

        $termsResponse = $this->get('/terms-and-conditions');
        $termsResponse->assertStatus(200);
        $termsResponse->assertInertia(fn (Assert $page) => $page
            ->component('Public/LegalPage')
            ->where('page.key', 'terms_and_conditions')
            ->where('page.title', 'Terms & Conditions (Live DB)')
            ->where('page.updated_by', 'Legal Team')
            ->where('page.is_full_document', true)
            ->where('page.content_html', $termsHtml)
        );
    }

    public function test_updating_legal_pages_via_api_immediately_updates_website_and_api_responses(): void
    {
        $suffix = Str::lower(Str::random(6));
        $superAdmin = SuperAdmin::create([
            'uuid' => (string) Str::uuid(),
            'name' => "SA Legal {$suffix}",
            'email' => "sa-legal-{$suffix}@example.com",
            'phone' => '9' . random_int(100000000, 999999999),
            'whatsapp_phone' => '9' . random_int(100000000, 999999999),
            'password' => Hash::make('secret123'),
            'status' => 1,
        ]);

        $member = Member::create([
            'uuid' => (string) Str::uuid(),
            'created_by' => $superAdmin->id,
            'name' => "Legal Admin {$suffix}",
            'username' => "legal_{$suffix}",
            'slug' => "legal-{$suffix}",
            'email' => "legal-{$suffix}@example.com",
            'phone' => '9' . random_int(100000000, 999999999),
            'password' => Hash::make('secret123'),
            'status' => Member::STATUS_ACTIVE,
            'roles' => [1],
        ]);

        Sanctum::actingAs($member);

        $newPrivacy = "<!DOCTYPE html><html lang='en'><head><meta charset='UTF-8'></head><body><h1>Updated Privacy Policy {$suffix}</h1><p>Dynamic content check.</p></body></html>";
        $newTerms = "<!DOCTYPE html><html lang='en'><head><meta charset='UTF-8'></head><body><h1>Updated Terms {$suffix}</h1><p>Dynamic terms check.</p></body></html>";

        $this->postJson('/api/pages/privacy-policy', [
            'title' => "Privacy {$suffix}",
            'content' => $newPrivacy,
        ])->assertOk();

        $this->postJson('/api/pages/terms-and-conditions', [
            'title' => "Terms {$suffix}",
            'content' => $newTerms,
        ])->assertOk();

        $this->get('/privacy')->assertInertia(fn (Assert $page) => $page
            ->component('Public/LegalPage')
            ->where('page.title', "Privacy {$suffix}")
            ->where('page.content_html', $newPrivacy)
        );

        $this->get('/terms')->assertInertia(fn (Assert $page) => $page
            ->component('Public/LegalPage')
            ->where('page.title', "Terms {$suffix}")
            ->where('page.content_html', $newTerms)
        );

        $this->getJson('/api/pages/privacy-policy')
            ->assertOk()
            ->assertJson([
                'success' => true,
                'title' => "Privacy {$suffix}",
                'content_html' => $newPrivacy,
            ]);

        $this->getJson('/api/pages/terms-and-conditions')
            ->assertOk()
            ->assertJson([
                'success' => true,
                'title' => "Terms {$suffix}",
                'content_html' => $newTerms,
            ]);
    }

    public function test_public_website_pages_and_registration_link_to_privacy_and_terms_routes(): void
    {
        $legalPageComponent = resource_path('js/Pages/Public/LegalPage.jsx');
        $this->assertFileExists($legalPageComponent);

        $homepageContent = file_get_contents(resource_path('js/Pages/Homepage.jsx'));
        $this->assertStringContainsString('/privacy', $homepageContent);
        $this->assertStringContainsString('/terms', $homepageContent);

        $footerContent = file_get_contents(resource_path('js/Components/Homepage/Footer.jsx'));
        $this->assertStringContainsString('/privacy', $footerContent);
        $this->assertStringContainsString('/terms', $footerContent);

        $authRegisterContent = file_get_contents(resource_path('js/Pages/Auth/Register.jsx'));
        $this->assertStringContainsString('/terms', $authRegisterContent);
        $this->assertStringContainsString('/privacy', $authRegisterContent);
        $this->assertStringNotContainsString('onClick={(e) => e.preventDefault()}', $authRegisterContent);

        $manifestPath = public_path('build/manifest.json');
        $this->assertFileExists($manifestPath);
        $manifest = json_decode((string) file_get_contents($manifestPath), true);
        $this->assertIsArray($manifest);
        $this->assertArrayHasKey('resources/js/Pages/Public/LegalPage.jsx', $manifest);
    }

    public function test_authenticated_superadmin_can_access_website_legal_pages(): void
    {
        $superAdmin = SuperAdmin::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Legal Viewer Admin',
            'email' => 'legal-viewer-' . Str::random(6) . '@example.com',
            'phone' => '9' . random_int(100000000, 999999999),
            'whatsapp_phone' => '9' . random_int(100000000, 999999999),
            'password' => Hash::make('secret123'),
            'status' => 1,
        ]);

        foreach (['/privacy', '/privacy-policy', '/terms', '/terms-and-conditions'] as $uri) {
            $this->actingAs($superAdmin, 'superadmin')
                ->get($uri)
                ->assertStatus(200)
                ->assertInertia(fn (Assert $page) => $page->component('Public/LegalPage'));
        }
    }
}
