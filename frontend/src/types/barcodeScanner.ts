export interface BarcodeFrame {
  pixels: ArrayBuffer
  width: number
  height: number
  thorough: boolean
}

export interface BarcodeResult {
  value: string | null
  failed?: boolean
}

export const retailBarcodeFormats = ['ean_13', 'ean_8', 'upc_a', 'upc_e', 'code_128', 'code_39', 'code_93', 'itf', 'codabar']
