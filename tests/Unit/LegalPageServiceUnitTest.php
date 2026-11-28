<?php

namespace Tests\Unit;

use App\Models\SystemSetting;
use App\Services\LegalPageService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class LegalPageServiceUnitTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_returns_default_fallback_data_when_database_rows_are_missing(): void
    {
        SystemSetting::whereIn('name', [
            LegalPageService::PRIVACY_POLICY,
            LegalPageService::TERMS_AND_CONDITIONS,
        ])->delete();

        $service = new LegalPageService();

        $privacy = $service->getPage(LegalPageService::PRIVACY_POLICY);
        $terms = $service->getPage(LegalPageService::TERMS_AND_CONDITIONS);

        $this->assertSame('privacy_policy', $privacy['key']);
        $this->assertSame('privacy-policy', $privacy['slug']);
        $this->assertSame('Privacy Policy', $privacy['title']);
        $this->assertStringContainsString('Privacy Policy', $privacy['content_html']);
        $this->assertStringContainsString('Privacy Policy', $privacy['content_text']);
        $this->assertSame('2026-08-01 00:00:00', $privacy['last_updated']);
        $this->assertFalse($privacy['is_full_document']);
        $this->assertStringContainsString('<!DOCTYPE html>', $privacy['document_html']);

        $this->assertSame('terms_and_conditions', $terms['key']);
        $this->assertSame('terms-and-conditions', $terms['slug']);
        $this->assertSame('Terms & Conditions', $terms['title']);
        $this->assertStringContainsString('Terms & Conditions', $terms['content_html']);
        $this->assertStringContainsString('Terms & Conditions', $terms['content_text']);
        $this->assertSame('2026-08-01 00:00:00', $terms['last_updated']);
        $this->assertFalse($terms['is_full_document']);
        $this->assertStringContainsString('<!DOCTYPE html>', $terms['document_html']);
    }

    public function test_falls_back_to_default_html_when_database_value_is_null_or_blank(): void
    {
        SystemSetting::where('name', LegalPageService::PRIVACY_POLICY)->delete();

        SystemSetting::create([
            'uuid' => (string) Str::uuid(),
            'name' => LegalPageService::PRIVACY_POLICY,
            'value' => '   ',
            'extra' => ['title' => 'Custom Privacy Title'],
        ]);

        $service = new LegalPageService();
        $page = $service->getPage(LegalPageService::PRIVACY_POLICY);

        $this->assertSame('Custom Privacy Title', $page['title']);
        $this->assertStringContainsString('Your privacy is important to us', $page['content_html']);
    }

    public function test_handles_full_html_document_from_database_and_strips_scripts_from_document_and_text(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-24 09:21:17'));

        $fullHtml = "<!DOCTYPE html>\n<html lang='en'>\n<head>\n<meta charset='UTF-8'>\n<title>Privacy Policy</title>\n<style>body { font-family: Arial, sans-serif; color: #222; } h1 { color: #1d4ed8; }</style>\n</head>\n<body>\n<h1>CadMax Privacy Notice</h1>\n<p onclick=\"alert('xss')\">We protect your site survey and attendance records.</p>\n<script>alert('bad');</script>\n</body>\n</html>";

        SystemSetting::where('name', LegalPageService::PRIVACY_POLICY)->delete();
        SystemSetting::create([
            'uuid' => (string) Str::uuid(),
            'name' => LegalPageService::PRIVACY_POLICY,
            'value' => $fullHtml,
            'extra' => [
                'title' => 'Dynamic Privacy Policy',
                'format' => 'html',
                'updated_by' => 'Super Admin',
            ],
        ]);

        $service = new LegalPageService();
        $page = $service->getPage(LegalPageService::PRIVACY_POLICY);

        $this->assertSame('Dynamic Privacy Policy', $page['title']);
        $this->assertSame('html', $page['format']);
        $this->assertSame('Super Admin', $page['updated_by']);
        $this->assertSame('2026-08-24 09:21:17', $page['last_updated']);
        $this->assertTrue($page['is_full_document']);
        $this->assertSame($fullHtml, $page['content_html']);

        // document_html preserves full document styling while stripping script tags and inline event handlers
        $this->assertStringContainsString('<style>body { font-family: Arial, sans-serif; color: #222; } h1 { color: #1d4ed8; }</style>', $page['document_html']);
        $this->assertStringContainsString('CadMax Privacy Notice', $page['document_html']);
        $this->assertStringNotContainsString('<script', strtolower($page['document_html']));
        $this->assertStringNotContainsString('onclick=', strtolower($page['document_html']));

        // content_text should not leak <style> or <script> CSS/JS text
        $this->assertStringContainsString('CadMax Privacy Notice', $page['content_text']);
        $this->assertStringContainsString('We protect your site survey and attendance records.', $page['content_text']);
        $this->assertStringNotContainsString('font-family', $page['content_text']);
        $this->assertStringNotContainsString("alert('bad')", $page['content_text']);
    }

    public function test_wraps_html_fragment_into_clean_styled_document_html(): void
    {
        $fragment = '<h2>Terms Summary</h2><p>Employees must follow safety protocols.</p>';

        SystemSetting::where('name', LegalPageService::TERMS_AND_CONDITIONS)->delete();
        SystemSetting::create([
            'uuid' => (string) Str::uuid(),
            'name' => LegalPageService::TERMS_AND_CONDITIONS,
            'value' => $fragment,
            'extra' => [
                'title' => 'Terms & Conditions v2',
                'format' => 'html',
            ],
        ]);

        $service = new LegalPageService();
        $page = $service->getPage(LegalPageService::TERMS_AND_CONDITIONS);

        $this->assertFalse($page['is_full_document']);
        $this->assertSame('Terms & Conditions v2', $page['title']);
        $this->assertSame($fragment, $page['content_html']);
        $this->assertStringContainsString('<!DOCTYPE html>', $page['document_html']);
        $this->assertStringContainsString('<h2>Terms Summary</h2><p>Employees must follow safety protocols.</p>', $page['document_html']);
        $this->assertStringContainsString('Terms Summary', $page['content_text']);
        $this->assertStringContainsString('Employees must follow safety protocols.', $page['content_text']);
    }

    public function test_throws_invalid_argument_exception_for_unknown_legal_page_key(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $service = new LegalPageService();
        $service->getPage('unknown_page_key');
    }
}
