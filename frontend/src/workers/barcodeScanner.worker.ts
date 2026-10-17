import { BinaryBitmap, HybridBinarizer, RGBLuminanceSource, MultiFormatOneDReader, BarcodeFormat, DecodeHintType, NotFoundException, ChecksumException, FormatException } from '@zxing/library'
import type { BarcodeFrame, BarcodeResult } from '@/types/barcodeScanner'

const formats = [BarcodeFormat.EAN_13, BarcodeFormat.EAN_8, BarcodeFormat.UPC_A, BarcodeFormat.UPC_E, BarcodeFormat.CODE_128, BarcodeFormat.CODE_39, BarcodeFormat.CODE_93, BarcodeFormat.ITF, BarcodeFormat.CODABAR]
const fastHints = new Map<DecodeHintType, unknown>([[DecodeHintType.POSSIBLE_FORMATS, formats]])
const thoroughHints = new Map(fastHints).set(DecodeHintType.TRY_HARDER, true)
const reader = new MultiFormatOneDReader(fastHints)
const scope = self as unknown as {
  onmessage: ((event: MessageEvent<BarcodeFrame>) => void) | null
  postMessage: (result: BarcodeResult) => void
}

function decode(luminance: Uint8ClampedArray, width: number, height: number, thorough: boolean): string | null {
  try {
    const bitmap = new BinaryBitmap(new HybridBinarizer(new RGBLuminanceSource(luminance, width, height)))
    return reader.decode(bitmap, thorough ? thoroughHints : fastHints).getText()
  } catch (cause) {
    if (cause instanceof NotFoundException || cause instanceof ChecksumException || cause instanceof FormatException) return null
    throw cause
  } finally { reader.reset() }
}

scope.onmessage = ({ data: { pixels, width, height, thorough } }) => {
  try {
    const rgba = new Uint8ClampedArray(pixels)
    const luminance = new Uint8ClampedArray(width * height)
    for (let pixel = 0, offset = 0; pixel < luminance.length; pixel++, offset += 4) {
      luminance[pixel] = (rgba[offset]! + 2 * rgba[offset + 1]! + rgba[offset + 2]!) >> 2
    }
    let value = decode(luminance, width, height, false)
    if (!value && thorough) {
      // Full-height search periodically, rather than an expensive exhaustive search on every frame.
      value = decode(luminance, width, height, true)
      if (!value) {
        // RGBLuminanceSource does not rotate. Rotate explicitly for vertical/sideways labels.
        const rotated = new Uint8ClampedArray(luminance.length)
        for (let y = 0; y < height; y++) for (let x = 0; x < width; x++) {
          rotated[(width - x - 1) * height + y] = luminance[y * width + x]!
        }
        value = decode(rotated, height, width, true)
      }
    }
    scope.postMessage({ value })
  } catch { scope.postMessage({ value: null, failed: true }) }
}
