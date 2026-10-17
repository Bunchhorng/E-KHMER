import { retailBarcodeFormats, type BarcodeFrame, type BarcodeResult } from '@/types/barcodeScanner'

interface NativeDetector {
  detect: (source: HTMLCanvasElement) => Promise<{ rawValue: string; format: string }[]>
}
interface NativeDetectorConstructor {
  new (options: { formats: string[] }): NativeDetector
  getSupportedFormats: () => Promise<string[]>
}
export interface BarcodeScanner {
  decode: (canvas: HTMLCanvasElement, thorough: boolean) => Promise<string | null>
  dispose: () => void
}

function deadline<T>(operation: Promise<T>, milliseconds: number): Promise<T> {
  return new Promise((resolve, reject) => {
    const timeout = setTimeout(() => reject(new Error('Barcode decoder timed out')), milliseconds)
    operation.then(resolve, reject).finally(() => clearTimeout(timeout))
  })
}

async function nativeDetector(): Promise<NativeDetector | null> {
  try {
    const Constructor = (window as Window & { BarcodeDetector?: NativeDetectorConstructor }).BarcodeDetector
    if (!Constructor) return null
    const supported = await deadline(Constructor.getSupportedFormats(), 300)
    const formats = retailBarcodeFormats.filter(format => supported.includes(format))
    return formats.length ? new Constructor({ formats }) : null
  } catch { return null }
}

export function createBarcodeScanner(): BarcodeScanner {
  let disposed = false
  let worker: Worker | null = null
  let settle: ((result: BarcodeResult) => void) | null = null
  let workerUnavailable = false
  let nativeUnavailable = false
  let nativeMisses = 0
  let mainThreadReader: Promise<Awaited<ReturnType<typeof legacyReader>>> | null = null
  const native = nativeDetector()

  async function legacyReader() {
    const [{ BrowserMultiFormatOneDReader }, { BarcodeFormat, DecodeHintType, NotFoundException, ChecksumException, FormatException }] = await Promise.all([import('@zxing/browser'), import('@zxing/library')])
    const formats = [BarcodeFormat.EAN_13, BarcodeFormat.EAN_8, BarcodeFormat.UPC_A, BarcodeFormat.UPC_E, BarcodeFormat.CODE_128, BarcodeFormat.CODE_39, BarcodeFormat.CODE_93, BarcodeFormat.ITF, BarcodeFormat.CODABAR]
    const hints = new Map<import('@zxing/library').DecodeHintType, unknown>([[DecodeHintType.POSSIBLE_FORMATS, formats], [DecodeHintType.TRY_HARDER, true]])
    const reader = new BrowserMultiFormatOneDReader(hints)
    return (canvas: HTMLCanvasElement) => {
      if (disposed) return null
      try { return reader.decodeFromCanvas(canvas).getText() }
      catch (cause) {
        if (cause instanceof NotFoundException || cause instanceof ChecksumException || cause instanceof FormatException) return null
        throw cause
      }
    }
  }

  function stopWorker() {
    worker?.terminate()
    worker = null
    settle?.({ value: null })
    settle = null
  }

  async function fallback(canvas: HTMLCanvasElement, thorough: boolean): Promise<string | null> {
    if (disposed) return null
    if (!workerUnavailable) {
      try {
        if (!worker) {
          worker = new Worker(new URL('../workers/barcodeScanner.worker.ts', import.meta.url), { type: 'module' })
          worker.onmessage = (event: MessageEvent<BarcodeResult>) => { settle?.(event.data); settle = null }
          worker.onerror = event => { event.preventDefault(); settle?.({ value: null, failed: true }); settle = null }
        }
        const context = canvas.getContext('2d', { willReadFrequently: true })
        if (!context) throw new Error('Canvas unavailable')
        const frame = context.getImageData(0, 0, canvas.width, canvas.height)
        const result = new Promise<BarcodeResult>(resolve => { settle = resolve })
        const request: BarcodeFrame = { pixels: frame.data.buffer as ArrayBuffer, width: frame.width, height: frame.height, thorough }
        worker.postMessage(request, [request.pixels])
        const response = await deadline(result, 5000)
        if (response.failed) throw new Error('Worker decoding failed')
        return disposed ? null : response.value
      } catch {
        stopWorker()
        workerUnavailable = true
      }
    }
    if (disposed) return null
    // Older browsers or restrictive worker policies still retain camera scanning.
    mainThreadReader ??= legacyReader()
    return (await mainThreadReader)(canvas)
  }

  return {
    async decode(canvas, thorough) {
      if (disposed) return null
      const detector = await native
      if (disposed) return null
      if (detector && !nativeUnavailable) {
        try {
          const results = await deadline(detector.detect(canvas), 400)
          if (disposed) return null
          const value = results.find(result => retailBarcodeFormats.includes(result.format))?.rawValue
          if (value) return value
          // Periodically try all formats with ZXing, even when the native API supports only a subset.
          if (++nativeMisses % 6 !== 0) return null
        } catch { nativeUnavailable = true }
      }
      return fallback(canvas, thorough)
    },
    dispose() { disposed = true; stopWorker() }
  }
}

/** Match object-cover exactly so only the full, visible preview is searched, not hidden edges. */
export function captureBarcodeFrame(video: HTMLVideoElement, canvas: HTMLCanvasElement, maximumEdge: number): boolean {
  if (video.readyState < HTMLMediaElement.HAVE_CURRENT_DATA || !video.videoWidth || !video.videoHeight) return false
  const bounds = video.getBoundingClientRect()
  const aspect = bounds.width && bounds.height ? bounds.width / bounds.height : video.videoWidth / video.videoHeight
  const width = Math.min(video.videoWidth, video.videoHeight * aspect)
  const height = Math.min(video.videoHeight, video.videoWidth / aspect)
  const scale = Math.min(1, maximumEdge / Math.max(width, height))
  const outputWidth = Math.round(width * scale)
  const outputHeight = Math.round(height * scale)
  if (canvas.width !== outputWidth || canvas.height !== outputHeight) { canvas.width = outputWidth; canvas.height = outputHeight }
  const context = canvas.getContext('2d', { willReadFrequently: true })
  if (!context) throw new Error('Canvas unavailable')
  context.drawImage(video, (video.videoWidth - width) / 2, (video.videoHeight - height) / 2, width, height, 0, 0, outputWidth, outputHeight)
  return true
}
