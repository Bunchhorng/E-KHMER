<?php

namespace Tests\Unit;

use App\Services\ProductDescriptionSanitizer;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProductDescriptionSanitizerTest extends TestCase
{
    public static function formatting(): array
    {
        return array_map(fn ($tag) => [$tag], ['p', 'div', 'span', 'br', 'strong', 'b', 'em', 'i', 'u', 'ul', 'ol', 'li', 'h1', 'h2', 'h3', 'h4', 'blockquote']);
    }

    #[DataProvider('formatting')]
    public function test_supported_editor_formatting_survives_and_is_idempotent(string $tag): void
    {
        $sanitizer = app(ProductDescriptionSanitizer::class);
        $html = $tag === 'br' ? 'A<br>B' : "<{$tag}>Features</{$tag}>";
        $safe = $sanitizer->sanitize($html);
        $this->assertStringContainsString('<'.$tag, $safe);
        $this->assertSame($safe, $sanitizer->sanitize($safe));
    }

    public static function hostileHtml(): array
    {
        return array_map(fn ($html) => [$html], [
            '<p>Safe</p><script>alert(1)</script>',
            '<img src=x onerror="alert(1)">',
            '<svg onload="alert(1)"><a href="javascript:alert(1)">SVG</a></svg>',
            '<iframe srcdoc="<script>alert(1)</script>" src="https://evil.example"></iframe>',
            '<object data="data:text/html,bad"><embed src="https://evil.example"></object>',
            '<form action="https://evil.example"><input autofocus onfocus="alert(1)"><button>Submit</button></form>',
            '<style>body{display:none}</style><p style="position:fixed" class="overlay" id="app" onclick="alert(1)">Safe</p>',
            '<math><mtext><table><mglyph><style><!--</style><img title="--><img src=x onerror=alert(1)>">',
            '<template><img src=x onerror="alert(1)"></template>',
            '<a href="jav&#x61;script&#58;alert(1)">Safe</a>',
            "<a href=\"java\nscript:alert(1)\">Safe</a>",
            '<a href="data:text/html,bad">Safe</a><a href="vbscript:bad">Safe</a>',
        ]);
    }

    #[DataProvider('hostileHtml')]
    public function test_active_markup_and_attributes_are_removed(string $html): void
    {
        $safe = app(ProductDescriptionSanitizer::class)->sanitize($html);
        $document = new \DOMDocument;
        $document->loadHTML('<html><body>'.$safe.'</body></html>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        foreach ($document->getElementsByTagName('*') as $element) {
            if (in_array($element->tagName, ['html', 'body'], true)) {
                continue;
            }
            $this->assertContains($element->tagName, ProductDescriptionSanitizer::TAGS);
            foreach ($element->attributes as $attribute) {
                $this->assertSame('a', $element->tagName);
                $this->assertContains($attribute->name, ['href', 'title', 'rel']);
                if ($attribute->name === 'href') {
                    $this->assertDoesNotMatchRegularExpression('/^(?:javascript|data|vbscript):/i', $attribute->value);
                }
            }
        }
        $this->assertSame($safe, app(ProductDescriptionSanitizer::class)->sanitize($safe));
    }

    public static function safeLinks(): array
    {
        return array_map(fn ($href) => [$href], ['https://example.com/info', 'http://example.com', 'mailto:help@example.com', '/shop?brand=acme', '#features']);
    }

    #[DataProvider('safeLinks')]
    public function test_safe_links_remain_and_new_tab_targets_are_removed(string $href): void
    {
        $safe = app(ProductDescriptionSanitizer::class)->sanitize('<a href="'.$href.'" title="Details" target="_blank" rel="opener" onclick="alert(1)">Details</a>');
        $this->assertStringContainsString('href="'.$href.'"', html_entity_decode($safe, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $this->assertStringContainsString('title="Details"', $safe);
        $this->assertStringContainsString('rel="noopener noreferrer"', $safe);
        $this->assertStringNotContainsString('target=', $safe);
        $this->assertStringNotContainsString('onclick', $safe);
        $this->assertSame($safe, app(ProductDescriptionSanitizer::class)->sanitize($safe));
    }

    public function test_unicode_entities_plain_text_and_empty_values_do_not_double_encode(): void
    {
        $sanitizer = app(ProductDescriptionSanitizer::class);
        $this->assertNull($sanitizer->sanitize(null));
        $this->assertSame('', $sanitizer->sanitize(''));
        $html = '<p><strong>ផលិតផលគុណភាពខ្ពស់</strong> Tom &amp; Jerry 123 ©</p>';
        $safe = $sanitizer->sanitize($html);
        for ($i = 0; $i < 4; $i++) {
            $this->assertSame($safe, $safe = $sanitizer->sanitize($safe));
        }
        $this->assertStringContainsString('ផលិតផលគុណភាពខ្ពស់', $safe);
        $this->assertStringContainsString('Tom &amp; Jerry', $safe);
        $this->assertStringNotContainsString('&amp;amp;', $safe);
        $this->assertSame('Legacy plain text', $sanitizer->sanitize('Legacy plain text'));
    }
}
