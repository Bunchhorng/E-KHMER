/**
 * Attribute values are plain strings ("Red", "Light Blue"), so a swatch cannot be
 * painted from them directly. Explicit `swatch_color` values win; otherwise a
 * small palette covers the common catalogue names and a neutral fills the rest.
 */
const SWATCH_FALLBACKS: Record<string, string> = {
  black: '#1f2937',
  white: '#f9fafb',
  'off white': '#f3f4f6',
  ivory: '#fffff0',
  cream: '#fdf6e3',
  red: '#dc2626',
  crimson: '#9f1239',
  maroon: '#7f1d1d',
  pink: '#ec4899',
  rose: '#f43f5e',
  orange: '#f97316',
  peach: '#fed7aa',
  coral: '#fb7185',
  yellow: '#eab308',
  mustard: '#ca8a04',
  green: '#16a34a',
  olive: '#4d7c0f',
  lime: '#84cc16',
  mint: '#6ee7b7',
  teal: '#0d9488',
  turquoise: '#2dd4bf',
  cyan: '#06b6d4',
  blue: '#2563eb',
  navy: '#1e3a8a',
  sky: '#38bdf8',
  indigo: '#4338ca',
  purple: '#9333ea',
  violet: '#8b5cf6',
  lilac: '#c4b5fd',
  brown: '#92400e',
  tan: '#d6c2a4',
  beige: '#e7d8c9',
  grey: '#9ca3af',
  gray: '#9ca3af',
  charcoal: '#374151',
  silver: '#c0c0c0',
  gold: '#b8860b'
}

export const NEUTRAL_SWATCH = '#cbd5e1'

export function swatchColor(explicit?: string | null, name?: string | null): string {
  if (explicit) return explicit
  const key = (name ?? '').trim().toLowerCase()
  return SWATCH_FALLBACKS[key] ?? NEUTRAL_SWATCH
}