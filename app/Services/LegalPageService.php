<?php

namespace App\Services;

use App\Models\SystemSetting;

class LegalPageService
{
    public const PRIVACY_POLICY = 'privacy_policy';
    public const TERMS_AND_CONDITIONS = 'terms_and_conditions';

    /**
     * Metadata and fallback definitions keyed by system_settings.name.
     *
     * @var array<string, array{slug: string, default_title: string, default_html: string, default_updated_at: string}>
     */
    private const PAGE_DEFINITIONS = [
        self::PRIVACY_POLICY => [
            'slug' => 'privacy-policy',
            'default_title' => 'Privacy Policy',
            'default_html' => '<h1>Privacy Policy</h1><p>Your privacy is important to us. CadMax collects employee location and activity details solely for project management and attendance verification.</p>',
            'default_updated_at' => '2026-08-01 00:00:00',
        ],
        self::TERMS_AND_CONDITIONS => [
            'slug' => 'terms-and-conditions',
            'default_title' => 'Terms & Conditions',
            'default_html' => '<h1>Terms & Conditions</h1><p>By using the CadMax employee application, you agree to comply with company field activity and reporting policies.</p>',
            'default_updated_at' => '2026-08-01 00:00:00',
        ],
    ];

    /**
     * Resolve a legal page's content and metadata from system_settings with safe defaults.
     *
     * @return array{
     *     key: string,
     *     slug: string,
     *     title: string,
     *     format: string,
     *     updated_by: string|null,
     *     last_updated: string,
     *     is_full_document: bool,
     *     content_html: string,
     *     document_html: string,
     *     content_text: string
     * }
     */
    public function getPage(string $key): array
    {
        if (!isset(self::PAGE_DEFINITIONS[$key])) {
            throw new \InvalidArgumentException("Unsupported legal page key [{$key}].");
        }

        $definition = self::PAGE_DEFINITIONS[$key];
        $setting = SystemSetting::where('name', $key)->first();
        $extra = is_array($setting?->extra) ? $setting->extra : [];

        $title = !empty($extra['title']) && is_string($extra['title'])
            ? trim($extra['title'])
            : $definition['default_title'];

        $storedValue = $setting?->value;
        $contentHtml = ($storedValue !== null && trim((string) $storedValue) !== '')
            ? (string) $storedValue
            : $definition['default_html'];

        $isFullDocument = $this->isFullHtmlDocument($contentHtml);

        return [
            'key' => $key,
            'slug' => $definition['slug'],
            'title' => $title,
            'format' => !empty($extra['format']) && is_string($extra['format']) ? $extra['format'] : 'html',
            'updated_by' => !empty($extra['updated_by']) && is_string($extra['updated_by']) ? $extra['updated_by'] : null,
            'last_updated' => $setting?->updated_at
                ? $setting->updated_at->format('Y-m-d H:i:s')
                : $definition['default_updated_at'],
            'is_full_document' => $isFullDocument,
            'content_html' => $contentHtml,
            'document_html' => $this->buildDocumentHtml($contentHtml, $title, $isFullDocument),
            'content_text' => $this->extractPlainText($contentHtml),
        ];
    }

    /**
     * Determine whether stored HTML represents a complete HTML document rather than a fragment.
     */
    private function isFullHtmlDocument(string $html): bool
    {
        return (bool) preg_match('/<!doctype\s+html|<html\b|<body\b/i', $html);
    }

    /**
     * Build a safe standalone HTML document suitable for isolated rendering.
     */
    private function buildDocumentHtml(string $contentHtml, string $title, bool $isFullDocument): string
    {
        $sanitized = $this->sanitizeHtml($contentHtml);

        if ($isFullDocument) {
            return $sanitized;
        }

        $bodyMarkup = $sanitized === strip_tags($sanitized)
            ? '<p>' . nl2br(e($sanitized)) . '</p>'
            : $sanitized;

        $escapedTitle = e($title);

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{$escapedTitle}</title>
<style>
  *, *::before, *::after { box-sizing: border-box; }
  html, body {
    margin: 0;
    padding: 0;
    background: #ffffff;
    color: #1e293b;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    font-size: 16px;
    line-height: 1.75;
    -webkit-font-smoothing: antialiased;
  }
  .legal-document {
    max-width: 100%;
    padding: 2rem;
  }
  h1, h2, h3, h4 {
    color: #0f172a;
    font-weight: 700;
    line-height: 1.3;
    margin-top: 1.75rem;
    margin-bottom: 0.75rem;
  }
  h1 { font-size: 1.875rem; margin-top: 0; }
  h2 { font-size: 1.375rem; border-bottom: 1px solid #e2e8f0; padding-bottom: 0.4rem; }
  h3 { font-size: 1.125rem; }
  p { margin: 0 0 1rem; color: #334155; }
  ul, ol { margin: 0 0 1.25rem; padding-left: 1.5rem; color: #334155; }
  li { margin-bottom: 0.45rem; }
  a { color: #4f46e5; text-decoration: underline; }
  blockquote {
    margin: 1.25rem 0;
    padding: 0.75rem 1rem;
    border-left: 4px solid #6366f1;
    background: #f8fafc;
    color: #334155;
  }
  table { width: 100%; border-collapse: collapse; margin: 1.25rem 0; }
  th, td { border: 1px solid #e2e8f0; padding: 0.625rem 0.75rem; text-align: left; }
  th { background: #f8fafc; color: #0f172a; font-weight: 600; }
  hr { border: 0; border-top: 1px solid #e2e8f0; margin: 1.5rem 0; }
</style>
</head>
<body>
<main class="legal-document">
{$bodyMarkup}
</main>
</body>
</html>
HTML;
    }

    /**
     * Remove script blocks, inline event handlers, and javascript: URIs before browser display.
     */
    private function sanitizeHtml(string $html): string
    {
        $withoutScripts = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html) ?? $html;
        $withoutScripts = preg_replace('/<script\b[^>]*\/?>/is', '', $withoutScripts) ?? $withoutScripts;

        return preg_replace_callback('/<[^>]+>/', function (array $matches): string {
            $tag = preg_replace('/\s+on[a-z]+\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $matches[0]) ?? $matches[0];
            $tag = preg_replace('/\b(href|src)\s*=\s*(["\'])\s*javascript:[^"\']*\2/i', '$1=$2#$2', $tag) ?? $tag;

            return $tag;
        }, $withoutScripts) ?? $withoutScripts;
    }

    /**
     * Extract clean readable plain text without leaking style/script contents.
     */
    private function extractPlainText(string $html): string
    {
        $clean = preg_replace('/<(head|style|script)\b[^>]*>.*?<\/\1>/is', ' ', $html) ?? $html;
        $text = html_entity_decode(strip_tags($clean), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }
}
