import type { Page } from '@playwright/test'

export interface BarcodeCameraState {
  calls: number
  stops: number
  denied: boolean
  release?: () => void
}

export type BarcodeCameraWindow = Window & { barcodeCamera: BarcodeCameraState }

// A valid EAN-8 symbol for 00001236, painted as real bars (not a mocked decoder result).
// The application must decode these pixels from a video MediaStream to pass the scan tests.
const left = ['0001101', '0011001', '0010011', '0111101', '0100011', '0110001', '0101111', '0111011', '0110111', '0001011']
const right = left.map(pattern => [...pattern].map(bit => bit === '1' ? '0' : '1').join(''))
const barcode = '00001236'
const bits = `101${[...barcode.slice(0, 4)].map(digit => left[Number(digit)]).join('')}01010${[...barcode.slice(4)].map(digit => right[Number(digit)]).join('')}101`

export async function installBarcodeCamera(page: Page, mode: 'barcode' | 'blank' | 'denied' | 'pending' | 'missing' | 'insecure' = 'barcode') {
  await page.addInitScript(({ bits, mode }) => {
    const state: BarcodeCameraState = { calls: 0, stops: 0, denied: mode === 'denied' }
    ;(window as BarcodeCameraWindow).barcodeCamera = state
    if (mode === 'insecure') Object.defineProperty(window, 'isSecureContext', { value: false })
    Object.defineProperty(navigator.mediaDevices, 'getUserMedia', { configurable: true, value: async (constraints: MediaStreamConstraints) => {
      state.calls++
      if (constraints.audio !== false) throw new Error('Barcode scanning must not request a microphone')
      if (state.denied) throw new DOMException('Permission denied', 'NotAllowedError')
      if (mode === 'missing') throw new DOMException('No camera found', 'NotFoundError')
      const canvas = document.createElement('canvas')
      canvas.width = 640; canvas.height = 480
      const context = canvas.getContext('2d')!
      function paint() {
        context.fillStyle = '#fff'; context.fillRect(0, 0, canvas.width, canvas.height)
        if (mode !== 'barcode') return
        context.fillStyle = '#000'
        ;[...bits].forEach((bit, index) => { if (bit === '1') context.fillRect(153 + index * 5, 135, 5, 210) })
      }
      paint()
      const stream = canvas.captureStream(12)
      const repaint = window.setInterval(paint, 80)
      stream.getTracks().forEach(track => {
        const original = track.stop.bind(track)
        track.stop = () => { state.stops++; window.clearInterval(repaint); original() }
      })
      if (mode === 'pending') await new Promise<void>(resolve => { state.release = resolve })
      return stream
    } })
  }, { bits, mode })
}
