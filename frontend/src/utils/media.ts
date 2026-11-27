/**
 * Resolve backend media URLs cleanly across single-origin Docker/Nginx and
 * split Cloudflare Pages + Render API deployments.
 */
export function resolveMediaUrl(src?: string | null): string | null {
  if (!src) return null
  if (/^(https?:|data:|blob:)/i.test(src)) return src

  const normalized = src.startsWith('images/') ? `/storage/${src}` : src
  if (!normalized.startsWith('/storage/')) return normalized

  const apiUrl = import.meta.env.VITE_API_URL
  if (typeof apiUrl === 'string' && /^https?:\/\//i.test(apiUrl)) {
    try {
      const origin = new URL(apiUrl).origin
      return `${origin}${normalized}`
    } catch {
      return normalized
    }
  }

  return normalized
}
