<script setup lang="ts">
import { nextTick, onBeforeUnmount, onMounted, ref, useId, watch } from 'vue'
import { ScanBarcode, Flashlight, FlashlightOff, ShieldCheck } from 'lucide-vue-next'
import { useI18n } from 'vue-i18n'
import BaseModal from '@/components/BaseModal.vue'
import { captureBarcodeFrame, createBarcodeScanner, type BarcodeScanner } from '@/utils/barcodeScanner'

type CameraCapabilities = MediaTrackCapabilities & { focusMode?: string[]; torch?: boolean }
type CameraConstraintSet = MediaTrackConstraintSet & { focusMode?: string; torch?: boolean }

const props = withDefaults(defineProps<{ modelValue: string; disabled?: boolean; error?: string }>(), { disabled: false })
const emit = defineEmits<{ 'update:modelValue': [value: string] }>()
const { t } = useI18n()
const id = useId()
const video = ref<HTMLVideoElement | null>(null)
const open = ref(false)
const starting = ref(false)
const cameraError = ref('')
const scanned = ref('')
const torchAvailable = ref(false)
const torchOn = ref(false)
const torchBusy = ref(false)
const torchError = ref('')
let stream: MediaStream | null = null
let decoder: BarcodeScanner | null = null
let timer: ReturnType<typeof setTimeout> | undefined
let session = 0
let disposed = false

function stop() {
  session++
  clearTimeout(timer)
  timer = undefined
  decoder?.dispose()
  decoder = null
  stream?.getTracks().forEach(track => track.stop())
  stream = null
  if (video.value) { video.value.pause(); video.value.srcObject = null }
  starting.value = false
  torchAvailable.value = false
  torchOn.value = false
  torchBusy.value = false
}

function close() {
  open.value = false
  stop()
}

function fail(key: string) {
  stop()
  cameraError.value = t(`barcode_scanner.${key}`)
}

function applyCameraOptions(track: MediaStreamTrack, options: CameraConstraintSet) {
  // Keep the chosen resolution/frame rate, and replace previous focus/light settings rather than
  // stacking contradictory advanced constraints (for example torch=true followed by torch=false).
  const constraints = track.getConstraints()
  const advanced = (constraints.advanced ?? []).map(set => {
    const retained: CameraConstraintSet = { ...set }
    for (const key of Object.keys(options) as (keyof CameraConstraintSet)[]) delete retained[key]
    return retained
  }).filter(set => Object.keys(set).length > 0)
  return track.applyConstraints({ ...constraints, advanced: [...advanced, options] })
}

async function start() {
  if (props.disabled || disposed) return
  stop()
  open.value = true
  starting.value = true
  cameraError.value = ''
  torchError.value = ''
  const current = session
  const active = () => !disposed && open.value && !props.disabled && current === session
  if (!window.isSecureContext) { fail('https_required'); return }
  if (!navigator.mediaDevices?.getUserMedia) { fail('unavailable'); return }

  try {
    // Camera permission and native capability checks start together, without waiting for ZXing to load.
    const currentDecoder = createBarcodeScanner()
    decoder = currentDecoder
    const camera = await navigator.mediaDevices.getUserMedia({
      audio: false,
      video: { facingMode: { ideal: 'environment' }, width: { ideal: 1920 }, height: { ideal: 1080 }, frameRate: { ideal: 30 } }
    })
    // Permission may resolve after the dialog was closed, retried or unmounted.
    if (!active()) { camera.getTracks().forEach(track => track.stop()); return }
    stream = camera
    const track = camera.getVideoTracks()[0]
    const capabilities = track?.getCapabilities?.() as CameraCapabilities | undefined
    torchAvailable.value = capabilities?.torch === true
    if (track && capabilities?.focusMode?.includes('continuous')) {
      // Best effort: unsupported focus constraints must never prevent scanning.
      void applyCameraOptions(track, { focusMode: 'continuous' }).catch(() => {})
    }
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
    let frames = 0

    const scan = async () => {
      if (!active()) return
      const began = performance.now()
      try {
        const thorough = ++frames % 4 === 0
        // Reuse bounded buffers; periodically retain more detail for small/distant labels.
        if (captureBarcodeFrame(preview, canvas, thorough ? 1280 : 960)) {
          const result = await currentDecoder.decode(canvas, thorough)
          if (!active()) return
          const value = result?.trim()
          if (value != null) {
            if (!value || value.length > 255 || !/^[\x20-\x7E]+$/.test(value)) { fail('invalid'); return }
            emit('update:modelValue', value)
            scanned.value = value
            close()
            return
          }
        }
      } catch { if (active()) fail('failed'); return }
      // One in-flight decode, up to 12.5 attempts/s. Slow devices never accumulate a frame queue.
      if (active()) timer = setTimeout(() => void scan(), Math.max(0, 80 - (performance.now() - began)))
    }
    void scan()
  } catch (cause) {
    if (!active()) return
    const name = cause instanceof Error ? cause.name : ''
    fail(name === 'NotAllowedError' || name === 'SecurityError' ? 'denied' : name === 'NotFoundError' ? 'no_camera' : 'unavailable')
  }
}

async function toggleTorch() {
  const track = stream?.getVideoTracks()[0]
  if (!track || !torchAvailable.value || torchBusy.value) return
  const current = session
  const enabled = !torchOn.value
  torchBusy.value = true
  torchError.value = ''
  try {
    await applyCameraOptions(track, { torch: enabled })
    if (current === session) torchOn.value = enabled
  } catch { if (current === session) torchError.value = t('barcode_scanner.torch_failed') }
  finally { if (current === session) torchBusy.value = false }
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
    <BaseModal :model-value="open" :title="t('barcode_scanner.scan')" size="lg" @update:model-value="close">
      <div v-show="!cameraError" data-testid="barcode-viewport" class="relative overflow-hidden rounded-2xl bg-slate-950">
        <video ref="video" autoplay muted playsinline class="aspect-[4/3] w-full object-cover sm:aspect-video" :aria-label="t('seller.camera_preview')"></video>
        <div data-testid="barcode-guide" aria-hidden="true" class="pointer-events-none absolute inset-3 rounded-xl border border-white/30">
          <span class="absolute -left-px -top-px h-8 w-8 rounded-tl-xl border-l-[3px] border-t-[3px] border-emerald-400"></span>
          <span class="absolute -right-px -top-px h-8 w-8 rounded-tr-xl border-r-[3px] border-t-[3px] border-emerald-400"></span>
          <span class="absolute -bottom-px -left-px h-8 w-8 rounded-bl-xl border-b-[3px] border-l-[3px] border-emerald-400"></span>
          <span class="absolute -bottom-px -right-px h-8 w-8 rounded-br-xl border-b-[3px] border-r-[3px] border-emerald-400"></span>
        </div>
        <span aria-hidden="true" class="pointer-events-none absolute left-6 top-6 inline-flex items-center gap-2 rounded-full bg-black/65 px-2.5 py-1 text-[10px] font-semibold tracking-wider text-white"><span class="h-1.5 w-1.5 rounded-full bg-emerald-400 motion-safe:animate-pulse"></span>{{ t('barcode_scanner.live') }}</span>
      </div>
      <p v-if="cameraError" role="alert" class="rounded-lg bg-red-500/10 p-3 text-sm leading-relaxed text-red-600 dark:text-red-400">{{ cameraError }}</p>
      <p v-else role="status" class="mt-3 text-sm text-muted">{{ t(starting ? 'seller.camera_starting' : 'barcode_scanner.scanning') }}</p>
      <p v-if="!cameraError" class="mt-2 text-xs leading-relaxed text-muted">{{ t('barcode_scanner.help') }}</p>
      <p v-if="torchError" role="alert" class="mt-2 text-xs text-red-600">{{ torchError }}</p>
      <p class="mt-3 flex items-start gap-2 text-xs leading-relaxed text-muted"><ShieldCheck :size="15" class="mt-0.5 shrink-0" />{{ t('barcode_scanner.privacy') }}</p>
      <template #footer>
        <div class="flex flex-wrap justify-end gap-3">
          <button v-if="cameraError" type="button" class="btn-primary" @click="start">{{ t('actions.retry') }}</button>
          <button v-if="torchAvailable" type="button" class="btn-secondary" :disabled="starting || torchBusy" :aria-pressed="torchOn" @click="toggleTorch"><component :is="torchOn ? FlashlightOff : Flashlight" :size="17" />{{ t(torchOn ? 'barcode_scanner.torch_off' : 'barcode_scanner.torch_on') }}</button>
          <button type="button" class="btn-secondary" @click="close">{{ t('actions.cancel') }}</button>
        </div>
      </template>
    </BaseModal>
  </div>
</template>
