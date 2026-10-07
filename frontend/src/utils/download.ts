import type { AxiosResponse } from 'axios'

export function downloadBlob(blob: Blob, filename: string): void {
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = filename
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
  URL.revokeObjectURL(url)
}

export function downloadResponse(response: AxiosResponse<Blob>, fallbackFilename: string): void {
  let filename = fallbackFilename
  const disposition = response.headers['content-disposition'] as string | undefined
  if (disposition) {
    const match = /filename="?([^";]+)"?/.exec(disposition)
    if (match?.[1]) {
      filename = match[1]
    }
  }
  downloadBlob(response.data, filename)
}

/**
 * Open a window during the user click so browsers do not treat the eventual
 * PDF print preview as a popup. The receipt can then be fetched asynchronously
 * and sent straight to the platform print dialog.
 */
export function openPrintWindow(): Window | null {
  const printWindow = window.open('', '_blank')
  if (printWindow) printWindow.opener = null

  return printWindow
}

export function printBlob(blob: Blob, printWindow: Window): void {
  const url = URL.createObjectURL(blob)

  printWindow.addEventListener('load', () => {
    window.setTimeout(() => {
      if (!printWindow.closed) {
        printWindow.focus()
        printWindow.print()
      }
    }, 250)
  }, { once: true })

  printWindow.location.replace(url)
  window.setTimeout(() => URL.revokeObjectURL(url), 60_000)
}
