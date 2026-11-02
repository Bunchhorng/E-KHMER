import createDOMPurify from 'dompurify'

// Mirrors ProductDescriptionSanitizer: one browser policy for editor, preview,
// storefront and plain-text excerpts. Never compile seller content as Vue.
export const PRODUCT_DESCRIPTION_TAGS = ['p', 'div', 'span', 'br', 'strong', 'b', 'em', 'i', 'u', 'ul', 'ol', 'li', 'h1', 'h2', 'h3', 'h4', 'blockquote', 'a']
const droppedTags = ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'textarea', 'select', 'option', 'svg', 'math', 'canvas', 'video', 'audio', 'template', 'noscript', 'img']
const purifier = createDOMPurify(window)
purifier.addHook('uponSanitizeAttribute', (node, attribute) => {
  if (node.nodeName !== 'A' || !['href', 'title'].includes(attribute.attrName)) attribute.keepAttr = false
})
purifier.addHook('afterSanitizeAttributes', node => {
  if (node.nodeName !== 'A') return
  const href = node.getAttribute('href')
  if (href) {
    try {
      if (!['http:', 'https:', 'mailto:'].includes(new URL(href, window.location.origin).protocol)) node.removeAttribute('href')
    } catch { node.removeAttribute('href') }
  }
  // Targets are stripped: description links use the current tab.
  node.setAttribute('rel', 'noopener noreferrer')
})

export function sanitizeProductDescription(value: string | null | undefined): string {
  return purifier.sanitize(value ?? '', {
    ALLOWED_TAGS: PRODUCT_DESCRIPTION_TAGS,
    ALLOWED_ATTR: ['href', 'title'],
    FORBID_TAGS: droppedTags,
    FORBID_CONTENTS: droppedTags,
    ALLOW_DATA_ATTR: false,
    ALLOW_ARIA_ATTR: false,
  })
}

/** Prepare the HTML and its text projection once per content change. */
export function prepareProductDescription(value: string | null | undefined): { html: string; text: string } {
  const html = sanitizeProductDescription(value)
  const document = new DOMParser().parseFromString(html, 'text/html')
  for (const block of document.body.querySelectorAll('p,div,br,li,h1,h2,h3,h4,blockquote')) block.append('\n')
  return { html, text: (document.body.textContent ?? '').replace(/\u00a0/g, ' ').trim() }
}

export function productDescriptionText(value: string | null | undefined): string {
  return prepareProductDescription(value).text
}
