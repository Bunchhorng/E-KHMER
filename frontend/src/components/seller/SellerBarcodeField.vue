<script setup lang="ts">
import { nextTick, onBeforeUnmount, onMounted, ref, useId, watch } from 'vue'
import { ScanBarcode } from 'lucide-vue-next'
import { useI18n } from 'vue-i18n'
import BaseModal from '@/components/BaseModal.vue'

const props = withDefaults(defineProps<{ modelValue: string; disabled?: boolean; error?: string }>(), { disabled: false })
const emit = defineEmits<{ 'update:modelValue': [value: string] }>()
const { t } = useI18n()
const id = useId()
const video = ref<HTMLVideoElement | null>(null)
const open = ref(false)
const starting = ref(false)
const cameraError = ref('')
const scanned = ref('')
let stream: MediaStream | null = null
let timer: ReturnType<typeof setTimeout> | undefined
let session = 0
let disposed = false

function stop() {
  session++
  clearTimeout(timer)
  timer = undefined
  stream?.getTracks().forEach(track => track.stop())
  stream = null
  if (video.value) { video.value.pause(); video.value.srcObject = null }
  starting.value = false
}

function close() {
  open.value = false
  stop()
}

function fail(key: string) {
  stop()
  cameraError.value = t(`barcode_scanner.${key}`)
}

async function start() {
  if (props.disabled || disposed) return
  stop()
  open.value = true
  starting.value = true
  cameraError.value = ''
  const current = session
  const active = () => !disposed && open.value && !props.disabled && current === session
  if (!window.isSecureContext) { fail('https_required'); return }
  if (!navigator.mediaDevices?.getUserMedia) { fail('unavailable'); return }

  try {
    // Load only when requested. Decoding stays on-device; no camera frames leave the browser.
    const [{ BrowserMultiFormatOneDReader }, { DecodeHintType, BarcodeFormat, NotFoundException, ChecksumException, FormatException }] = await Promise.all([
      import('@zxing/browser'), import('@zxing/library')
    ])
    if (!active()) return
    const hints = new Map<import('@zxing/library').DecodeHintType, unknown>()
    hints.set(DecodeHintType.TRY_HARDER, true)
    // Retail/warehouse barcodes, not payment QR codes. Keep decoded values as strings.
    hints.set(DecodeHintType.POSSIBLE_FORMATS, [BarcodeFormat.EAN_13, BarcodeFormat.EAN_8, BarcodeFormat.UPC_A, BarcodeFormat.UPC_E, BarcodeFormat.CODE_128, BarcodeFormat.CODE_39, BarcodeFormat.CODE_93, BarcodeFormat.ITF, BarcodeFormat.CODABAR])
    const reader = new BrowserMultiFormatOneDReader(hints)
    const camera = await navigator.mediaDevices.getUserMedia({
      audio: false,
      video: { facingMode: { ideal: 'environment' }, width: { ideal: 1280 }, height: { ideal: 720 } }
    })
    // Permission may resolve after the dialog was closed, retried or unmounted.
    if (!active()) { camera.getTracks().forEach(track => track.stop()); return }
    stream = camera
    camera.getVideoTracks().forEach(track => track.addEventListener('ended', () => {
      if (active()) fail('unavailable')
    }, { once: true }))
    await nextTick()
    if (!active()) return
    const preview = video.value
    if (!preview) { fail('unavailable'); return }
    preview.srcObject = camera
    await preview.play()
    if (!active()) return
    starting.value = false
    const canvas = document.createElement('canvas')
    const context = canvas.getContext('2d', { willReadFrequently: true })
    if (!context) { fail('unavailable'); return }

    function scan() {
      if (!active()) return
      if (preview!.readyState >= HTMLMediaElement.HAVE_CURRENT_DATA && preview!.videoWidth > 0) {
        try {
          canvas.width = preview!.videoWidth
          canvas.height = preview!.videoHeight
          context!.drawImage(preview!, 0, 0)
          const value = reader.decodeFromCanvas(canvas).getText().trim()
          if (!value || value.length > 255 || !/^[\x20-\x7E]+$/.test(value)) { fail('invalid'); return }
          emit('update:modelValue', value)
          scanned.value = value
          close()
          return
        } catch (cause) {
          // No readable barcode in this frame is normal; keep looking without alert spam.
          if (!(cause instanceof NotFoundException || cause instanceof ChecksumException || cause instanceof FormatException)) {
            fail('failed')
            return
          }
        }
      }
      timer = setTimeout(scan, 180)
    }
    scan()
  } catch (cause) {
    if (!active()) return
    const name = cause instanceof Error ? cause.name : ''
    fail(name === 'NotAllowedError' || name === 'SecurityError' ? 'denied' : name === 'NotFoundError' ? 'no_camera' : 'unavailable')
  }
}

function onVisibilityChange() { if (document.hidden && open.value) close() }
watch(() => props.disabled, disabled => { if (disabled) close() }, { flush: 'sync' })
onMounted(() => document.addEventListener('visibilitychange', onVisibilityChange))
onBeforeUnmount(() => {
  disposed = true
  stop()
  document.removeEventListener('visibilitychange', onVisibilityChange)
})
</script>

<template>
  <div>
    <label :for="id" class="label">{{ t('seller.barcode') }}</label>
    <div class="flex flex-wrap gap-2">
      <input :id="id" :value="modelValue" type="text" autocomplete="off" spellcheck="false" class="input min-w-0 flex-1 font-mono" maxlength="255" :disabled="disabled" :aria-describedby="`${id}-help`" :aria-invalid="!!error" @input="emit('update:modelValue', ($event.target as HTMLInputElement).value)" @keydown.enter.prevent />
      <button type="button" class="btn-secondary shrink-0" :disabled="disabled" @click="start"><ScanBarcode :size="17" />{{ t('barcode_scanner.scan') }}</button>
    </div>
    <p :id="`${id}-help`" class="mt-1.5 text-xs leading-relaxed" :class="error ? 'text-red-600' : 'text-muted'">{{ error || t('seller.barcode_help') }}</p>
    <p v-if="scanned && scanned === modelValue" role="status" class="mt-1.5 text-xs text-primary">{{ t('barcode_scanner.success', { barcode: scanned }) }}</p>
    <BaseModal :model-value="open" :title="t('barcode_scanner.scan')" @update:model-value="close">
      <p class="mb-4 text-sm leading-relaxed text-muted">{{ t('barcode_scanner.help') }}</p>
      <div v-show="!cameraError" class="relative overflow-hidden rounded-xl bg-black">
        <video ref="video" autoplay muted playsinline class="aspect-video w-full object-contain" :aria-label="t('seller.camera_preview')"></video>
        <div aria-hidden="true" class="pointer-events-none absolute inset-x-6 inset-y-1/3 rounded-lg border-2 border-white/70"></div>
      </div>
      <p v-if="cameraError" role="alert" class="rounded-lg bg-red-500/10 p-3 text-sm leading-relaxed text-red-600 dark:text-red-400">{{ cameraError }}</p>
      <p v-else role="status" class="mt-3 text-sm text-muted">{{ t(starting ? 'seller.camera_starting' : 'barcode_scanner.scanning') }}</p>
      <p class="mt-3 text-xs leading-relaxed text-muted">{{ t('barcode_scanner.privacy') }}</p>
      <template #footer>
        <div class="flex flex-wrap justify-end gap-3">
          <button v-if="cameraError" type="button" class="btn-primary" @click="start">{{ t('actions.retry') }}</button>
          <button type="button" class="btn-secondary" @click="close">{{ t('actions.cancel') }}</button>
        </div>
      </template>
    </BaseModal>
  </div>
</template>
