import axios from 'axios'

/**
 * Shape of the validation payload Laravel returns for a 422.
 */
interface ValidationErrors {
  message?: string
  errors?: Record<string, string[]>
}

/**
 * Turn a failed request into a single sentence the UI can show verbatim.
 *
 * Priority: a field-level validation message (422), then the server message,
 * then a fallback. Without this the login and register screens used to report
 * every failure as "invalid credentials" / "registration failed", which is
 * actively misleading once the API starts rate limiting (429) or rejecting a
 * field (422).
 */
export function extractErrorMessage(error: unknown, fallback: string): string {
  if (axios.isAxiosError(error)) {
    const status = error.response?.status
    const data = error.response?.data as ValidationErrors | undefined

    if (status === 429) {
      const retryAfter = Number(error.response?.headers?.['retry-after'] ?? 0)

      return retryAfter > 0
        ? `Too many attempts. Please try again in ${retryAfter} seconds.`
        : 'Too many attempts. Please try again later.'
    }

    const fieldError = firstFieldError(data)
    if (fieldError) return fieldError

    if (data?.message) return data.message

    // No response at all: the request was aborted by our own timeout, or the
    // server was unreachable. Never let this collapse into the generic fallback,
    // which reads like a rejected input (a duplicate email, for instance).
    if (error.code === 'ECONNABORTED') {
      return 'The server took too long to respond. Please try again.'
    }

    if (!error.response) {
      return 'Cannot reach the server. Please check your connection.'
    }

    if (status) return `Request failed (HTTP ${status}). Please try again.`
  }

  return fallback
}

/**
 * Pick the first message the API attached to a specific input, preferring the
 * credential fields so a duplicate-email rejection lands on the email input.
 */
export function extractFieldErrors(error: unknown): Record<string, string> {
  const result: Record<string, string> = {}

  if (!axios.isAxiosError(error)) return result

  const errors = (error.response?.data as ValidationErrors | undefined)?.errors

  if (errors) {
    Object.entries(errors).forEach(([field, messages]) => {
      if (Array.isArray(messages) && messages.length > 0) {
        result[field] = messages[0]
      }
    })
  }

  return result
}

function firstFieldError(data: ValidationErrors | undefined): string | null {
  if (!data?.errors) return null

  const preferredFields = ['email', 'password', 'password_confirmation', 'name', 'current_password']

  for (const field of preferredFields) {
    const message = data.errors[field]?.[0]

    if (message) return message
  }

  return null
}