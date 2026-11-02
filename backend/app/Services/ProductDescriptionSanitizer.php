<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/** One product-description policy for seller/admin writes and legacy reads. */
class ProductDescriptionSanitizer
{
    public const MAX_INPUT_BYTES = 60000;

    public const MAX_STORAGE_BYTES = 65535;

    // Keep the browser defense-in-depth policy in productDescription.ts aligned.
    public const TAGS = ['p', 'div', 'span', 'br', 'strong', 'b', 'em', 'i', 'u', 'ul', 'ol', 'li', 'h1', 'h2', 'h3', 'h4', 'blockquote', 'a'];

    private HtmlSanitizer $sanitizer;

    public function __construct()
    {
        $config = (new HtmlSanitizerConfig)
            ->allowLinkSchemes(['http', 'https', 'mailto'])
            ->allowRelativeLinks()
            ->forceAttribute('a', 'rel', 'noopener noreferrer')
            ->withMaxInputLength(self::MAX_STORAGE_BYTES);
        foreach (self::TAGS as $tag) {
            $config = $config->allowElement($tag, $tag === 'a' ? ['href', 'title'] : []);
        }
        foreach (['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'textarea', 'select', 'option', 'svg', 'math', 'canvas', 'video', 'audio', 'template', 'noscript', 'img'] as $tag) {
            $config = $config->dropElement($tag);
        }
        $this->sanitizer = new HtmlSanitizer($config);
    }

    public function sanitize(?string $html): ?string
    {
        return $html === null ? null : $this->sanitizer->sanitize($html);
    }

    public function forStorage(?string $html): ?string
    {
        // Do not silently truncate oversized input or entity-expanded output.
        if ($html !== null && strlen($html) > self::MAX_STORAGE_BYTES) {
            throw ValidationException::withMessages(['description' => 'The description is too large. Please shorten it.']);
        }
        $safe = $this->sanitize($html);
        if ($safe !== null && strlen($safe) > self::MAX_STORAGE_BYTES) {
            throw ValidationException::withMessages(['description' => 'The formatted description is too large. Please shorten it.']);
        }

        return $safe;
    }
}
