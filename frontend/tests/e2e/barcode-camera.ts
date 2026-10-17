import type { Page } from '@playwright/test'

export interface BarcodeCameraState {
  calls: number
  stops: number
  denied: boolean
  workerStarts: number
  workerStops: number
  workerResults: string[]
  nativeCalls: number
  nativeInFlight: number
  nativeMaxInFlight: number
  nativeTimes: number[]
  nativeSizes: number[][]
  nativeFormats: string[]
  constraints: MediaTrackConstraints[]
  release?: () => void
  releaseNative?: () => void
}

export type BarcodeCameraWindow = Window & { barcodeCamera: BarcodeCameraState }

// A valid EAN-8 symbol for 00001236, painted as real bars (not a mocked decoder result).
// The application must decode these pixels from a video MediaStream to pass the scan tests.
const left = ['0001101', '0011001', '0010011', '0111101', '0100011', '0110001', '0101111', '0111011', '0110111', '0001011']
const right = left.map(pattern => [...pattern].map(bit => bit === '1' ? '0' : '1').join(''))
const barcode = '00001236'
const bits = `101${[...barcode.slice(0, 4)].map(digit => left[Number(digit)]).join('')}01010${[...barcode.slice(4)].map(digit => right[Number(digit)]).join('')}101`

interface CameraOptions {
  position?: 'center' | 'top' | 'bottom' | 'vertical'
  native?: 'success' | 'reject' | 'miss' | 'pending'
  torch?: boolean
  workerBlocked?: boolean
  highResolution?: boolean
}

export async function installBarcodeCamera(page: Page, mode: 'barcode' | 'blank' | 'denied' | 'pending' | 'missing' | 'insecure' = 'barcode', options: CameraOptions = {}) {
  await page.addInitScript(({ bits, mode, options }) => {
    const state: BarcodeCameraState = { calls: 0, stops: 0, denied: mode === 'denied', workerStarts: 0, workerStops: 0, workerResults: [], nativeCalls: 0, nativeInFlight: 0, nativeMaxInFlight: 0, nativeTimes: [], nativeSizes: [], nativeFormats: [], constraints: [] }
    ;(window as BarcodeCameraWindow).barcodeCamera = state
    const OriginalWorker = window.Worker
    window.Worker = class extends OriginalWorker {
      constructor(url: string | URL, settings?: WorkerOptions) {
        if (options.workerBlocked) throw new Error('Worker blocked by browser policy')
        super(url, settings)
        state.workerStarts++
        this.addEventListener('message', (event: MessageEvent<{ value: string | null }>) => { if (event.data.value) state.workerResults.push(event.data.value) })
      }
      terminate() { state.workerStops++; super.terminate() }
    }
    // Real-pixel tests explicitly exercise ZXing in a worker, not the machine's native implementation.
    Object.defineProperty(window, 'BarcodeDetector', { configurable: true, value: options.native ? class {
      static async getSupportedFormats() { return ['ean_8', 'code_128', 'qr_code'] }
      constructor({ formats }: { formats: string[] }) { state.nativeFormats = formats }
      async detect(source: HTMLCanvasElement) {
        state.nativeCalls++
        state.nativeTimes.push(performance.now())
        state.nativeSizes.push([source.width, source.height])
        state.nativeInFlight++
        state.nativeMaxInFlight = Math.max(state.nativeMaxInFlight, state.nativeInFlight)
        try {
          if (options.native === 'reject') throw new Error('Native detector unavailable')
          if (options.native === 'pending') await new Promise<void>(resolve => { state.releaseNative = resolve })
          else await new Promise(resolve => setTimeout(resolve, 100))
          return options.native === 'miss' || (options.native === 'success' && state.nativeCalls < 5) ? [] : [{ rawValue: '00001236', format: 'ean_8' }]
        } finally { state.nativeInFlight-- }
      }
    } : undefined })
    if (mode === 'insecure') Object.defineProperty(window, 'isSecureContext', { value: false })
    Object.defineProperty(navigator.mediaDevices, 'getUserMedia', { configurable: true, value: async (constraints: MediaStreamConstraints) => {
      state.calls++
      if (constraints.audio !== false) throw new Error('Barcode scanning must not request a microphone')
      if (state.denied) throw new DOMException('Permission denied', 'NotAllowedError')
      if (mode === 'missing') throw new DOMException('No camera found', 'NotFoundError')
      const canvas = document.createElement('canvas')
      canvas.width = options.highResolution ? 1920 : 640; canvas.height = options.highResolution ? 1080 : 480
      const context = canvas.getContext('2d')!
      function paint() {
        context.fillStyle = '#fff'; context.fillRect(0, 0, canvas.width, canvas.height)
        if (mode !== 'barcode') return
        context.fillStyle = '#000'
        context.save()
        const vertical = options.position === 'vertical'
        if (vertical) { context.translate(320, 240); context.rotate(Math.PI / 2); context.translate(-320, -240) }
        const module = vertical ? 4 : 5
        const y = options.position === 'top' ? 75 : options.position === 'bottom' ? 365 : 135
        const height = options.position === 'top' || options.position === 'bottom' ? 40 : 210
        ;[...bits].forEach((bit, index) => { if (bit === '1') context.fillRect((640 - bits.length * module) / 2 + index * module, y, module, height) })
        context.restore()
      }
      paint()
      const stream = canvas.captureStream(12)
      const repaint = window.setInterval(paint, 80)
      stream.getTracks().forEach(track => {
        const original = track.stop.bind(track)
        track.stop = () => { state.stops++; window.clearInterval(repaint); original() }
        if (options.torch) {
          let applied = typeof constraints.video === 'object' ? structuredClone(constraints.video) : {}
          track.getCapabilities = () => ({ torch: true, focusMode: ['continuous'] }) as MediaTrackCapabilities
          track.getConstraints = () => structuredClone(applied)
          track.applyConstraints = async constraints => {
            if (constraints) { applied = structuredClone(constraints); state.constraints.push(structuredClone(constraints)) }
          }
        }
      })
      if (mode === 'pending') await new Promise<void>(resolve => { state.release = resolve })
      return stream
    } })
  }, { bits, mode, options })
}
