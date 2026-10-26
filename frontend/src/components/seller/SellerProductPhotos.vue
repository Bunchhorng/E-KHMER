<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { Camera, FileImage, Image, ImagePlus, Star, Trash2, Upload } from 'lucide-vue-next'
import { useI18n } from 'vue-i18n'
import BaseModal from '@/components/BaseModal.vue'
import AssetImage from '@/components/AssetImage.vue'
import type { ProductPhoto } from '@/types/productPhotos'

const props = defineProps<{ modelValue: ProductPhoto[]; disabled: boolean; serverError?: string }>()
const emit = defineEmits<{ 'update:modelValue': [photos: ProductPhoto[]] }>()
const { t } = useI18n()
const fileInput = ref<HTMLInputElement | null>(null)
const captureInput = ref<HTMLInputElement | null>(null)
const video = ref<HTMLVideoElement | null>(null)
const cameraOpen = ref(false)
const cameraLoading = ref(false)
const cameraReady = ref(false)
const capturing = ref(false)
const cameraError = ref('')
const error = ref('')
const dragging = ref(false)
const slots = computed(() => Array.from({ length: Math.max(8, props.modelValue.length) }, (_, index) => props.modelValue[index] ?? null))
let stream: MediaStream | null = null
let cameraSession = 0
let disposed = false
const allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif']

function addFiles(files: File[]) {
  if (props.disabled || disposed) return
  error.value = ''
  const pending = props.modelValue.reduce((total, photo) => total + (photo.file?.size ?? 0), 0)
  if (props.modelValue.length + files.length > 20) error.value = t('seller.photos_limit')
  else if (files.some(file => !allowedTypes.includes(file.type) || file.size > 5 * 1024 * 1024 || file.size === 0)) error.value = t('seller.photos_invalid')
  else if (pending + files.reduce((total, file) => total + file.size, 0) > 10 * 1024 * 1024) error.value = t('seller.photos_total')
  if (error.value) return
  const added = files.map(file => {
    const url = URL.createObjectURL(file)
    return { key: url, url, file }
  })
  emit('update:modelValue', [...props.modelValue, ...added])
}

function selectFiles(event: Event) {
  const input = event.target as HTMLInputElement
  addFiles(Array.from(input.files ?? []))
  input.value = ''
}

function dropFiles(event: DragEvent) {
  dragging.value = false
  addFiles(Array.from(event.dataTransfer?.files ?? []))
}

function removePhoto(key: string) {
  if (!props.disabled) emit('update:modelValue', props.modelValue.filter(photo => photo.key !== key))
}

function setCover(photo: ProductPhoto) {
  if (!props.disabled) emit('update:modelValue', [photo, ...props.modelValue.filter(item => item.key !== photo.key)])
}

function stopCamera() {
  cameraSession++
  stream?.getTracks().forEach(track => track.stop())
  stream = null
  if (video.value) video.value.srcObject = null
  cameraReady.value = false
  cameraLoading.value = false
}

async function openCamera() {
  if (props.disabled || props.modelValue.length >= 20) return
  if (!navigator.mediaDevices?.getUserMedia) { captureInput.value?.click(); return }
  cameraOpen.value = true
  cameraLoading.value = true
  cameraError.value = ''
  const session = ++cameraSession
  try {
    const camera = await navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' }, width: { ideal: 1920 }, height: { ideal: 1440 } }, audio: false })
    if (disposed || !cameraOpen.value || session !== cameraSession) { camera.getTracks().forEach(track => track.stop()); return }
    stream = camera
    await nextTick()
    if (!video.value) { stopCamera(); return }
    video.value.srcObject = camera
    await video.value.play()
  } catch {
    if (session === cameraSession) { stopCamera(); cameraError.value = t('seller.camera_unavailable') }
  } finally { if (session === cameraSession) cameraLoading.value = false }
}

async function takePhoto() {
  if (!video.value || !cameraReady.value || capturing.value) return
  const session = cameraSession
  capturing.value = true
  try {
    const canvas = document.createElement('canvas')
    canvas.width = video.value.videoWidth
    canvas.height = video.value.videoHeight
    const context = canvas.getContext('2d')
    if (!context || !canvas.width || !canvas.height) throw new Error('Camera not ready')
    context.drawImage(video.value, 0, 0)
    const blob = await new Promise<Blob | null>(resolve => canvas.toBlob(resolve, 'image/jpeg', 0.9))
    if (disposed || session !== cameraSession) return
    if (!blob) throw new Error('Could not capture photo')
    addFiles([new File([blob], `product-photo-${Date.now()}.jpg`, { type: 'image/jpeg' })])
    cameraOpen.value = false
  } catch { cameraError.value = t('seller.camera_failed') }
  finally { capturing.value = false }
}

function useDeviceCamera() {
  cameraOpen.value = false
  stopCamera()
  captureInput.value?.click()
}

watch(cameraOpen, open => { if (!open) stopCamera() })
watch(() => props.modelValue, (photos, previous) => {
  for (const photo of previous) {
    if (photo.file && !photos.some(item => item.key === photo.key)) URL.revokeObjectURL(photo.url)
  }
})
onBeforeUnmount(() => {
  disposed = true
  stopCamera()
  props.modelValue.forEach(photo => { if (photo.file) URL.revokeObjectURL(photo.url) })
})
</script>

<template>
  <section class="card product-photos" aria-labelledby="product-photos-title">
    <header class="photos-heading flex items-start justify-between gap-3">
      <div class="flex min-w-0 items-start gap-4"><span class="photo-section-icon"><FileImage :size="24" :stroke-width="1.8" /></span><div class="min-w-0"><h2 id="product-photos-title" class="font-semibold">{{ t('seller.photos') }}</h2><p class="mt-0.5 text-muted">{{ t('seller.photos_intro') }}</p></div></div>
      <span class="photo-count shrink-0 text-muted">{{ t('seller.photo_count', { count: modelValue.length }) }}</span>
    </header>
    <input ref="fileInput" type="file" class="hidden" accept="image/jpeg,image/png,image/webp,image/gif" multiple :disabled="disabled" :aria-label="t('seller.upload_photos')" @change="selectFiles" />
    <input ref="captureInput" type="file" class="hidden" accept="image/*" capture="environment" :disabled="disabled" :aria-label="t('seller.take_photo')" @change="selectFiles" />
    <div class="photos-workspace">
      <div class="photo-dropzone flex flex-col items-center justify-center text-center" :class="{ 'photo-dropzone-active': dragging && !disabled }" @dragover.prevent="dragging = true" @dragleave.prevent="dragging = false" @drop.prevent="dropFiles">
        <ImagePlus :size="31" :stroke-width="1.7" class="text-muted" aria-hidden="true" /><p class="mt-2 font-semibold text-muted">{{ t('seller.drop_photos') }}</p><span class="photo-or text-muted">{{ t('seller.or') }}</span><button type="button" class="btn-primary photo-upload" :disabled="disabled || modelValue.length >= 20" @click="fileInput?.click()"><Upload :size="16" />{{ t('seller.upload_photos') }}</button>
      </div>
      <ul class="photo-grid" :aria-label="t('seller.photos')">
        <li v-for="(photo, index) in slots" :key="photo?.key ?? 'empty-' + index" class="photo-slot relative" :class="{ 'photo-cover': index === 0, 'photo-slot-empty': !photo }">
          <template v-if="photo"><AssetImage :src="photo.url" :alt="t('seller.photo_number', { number: index + 1 })" class="h-full w-full bg-canvas p-1" /><div class="photo-controls"><button v-if="index > 0" type="button" :disabled="disabled" :title="t('seller.make_cover')" :aria-label="t('seller.make_cover')" @click="setCover(photo)"><Star :size="14" /></button><button type="button" :disabled="disabled" :aria-label="t('seller.remove_photo', { number: index + 1 })" @click="removePhoto(photo.key)"><Trash2 :size="14" /></button></div></template>
          <Image v-else :size="21" :stroke-width="1.8" class="text-muted/60" aria-hidden="true" />
          <span v-if="index === 0" class="photo-cover-label">{{ t('seller.cover_photo') }}</span>
        </li>
      </ul>
    </div>
    <p v-if="error || serverError" role="alert" class="mt-3 text-sm text-red-600 dark:text-red-400">{{ error || serverError }}</p>
    <div class="photos-footer flex flex-wrap items-start justify-between gap-2"><p class="min-w-0 flex-1 text-muted">{{ t('seller.photos_formats') }}</p><button type="button" class="photo-camera inline-flex shrink-0 items-center gap-1.5 font-medium text-primary" :disabled="disabled || modelValue.length >= 20" @click="openCamera"><Camera :size="15" />{{ t('seller.take_photo') }}</button></div>
    <BaseModal v-model="cameraOpen" :title="t('seller.take_photo')">
      <p v-if="cameraError" role="alert" class="mb-4 text-sm text-red-600 dark:text-red-400">{{ cameraError }}</p>
      <p v-if="cameraLoading" role="status" class="mb-4 text-sm text-muted">{{ t('seller.camera_starting') }}</p>
      <video ref="video" autoplay playsinline muted class="aspect-[4/3] w-full rounded-xl bg-black object-contain" :aria-label="t('seller.camera_preview')" @loadeddata="cameraReady = true; cameraLoading = false" />
      <p class="mt-3 text-xs leading-relaxed text-muted">{{ t('seller.camera_help') }}</p>
      <template #footer>
        <div class="flex flex-wrap justify-end gap-3">
          <button type="button" class="btn-secondary" @click="useDeviceCamera">{{ t('seller.device_camera') }}</button>
          <button type="button" class="btn-primary" :disabled="!cameraReady || capturing" @click="takePhoto"><Camera :size="17" />{{ t('seller.capture_photo') }}</button>
        </div>
      </template>
    </BaseModal>
  </section>
</template>

<style scoped>
.product-photos { min-width: 0; padding: 16px 20px 12px; border-radius: 16px; }
.photos-heading { margin-bottom: 11px; }
.photo-section-icon { display: flex; width: 42px; height: 42px; flex-shrink: 0; align-items: center; justify-content: center; border-radius: 12px; background: rgb(var(--color-primary) / 9%); color: rgb(var(--color-primary)); }
.photos-heading h2 { font-size: 15px; line-height: 1.5; }
.photos-heading p, .photo-count { font-size: 12px; line-height: 1.5; }
.photo-count { padding-top: 4px; }
.photos-workspace { display: grid; grid-template-columns: 1.1fr 1fr; gap: 14px; }
.photo-dropzone { min-height: 164px; padding: 16px; border: 1px dashed rgb(var(--color-primary) / 45%); border-radius: 8px; background: linear-gradient(135deg, rgb(var(--color-primary) / 2%), rgb(var(--color-canvas) / 65%)); }
.photo-dropzone-active { background: rgb(var(--color-primary) / 9%); border-style: solid; }
.photo-dropzone > p { font-size: 14px; line-height: 1.5; }
.photo-or { font-size: 12px; line-height: 1.8; }
.photo-upload { min-height: 34px; margin-top: 3px; padding: 5px 20px; border-radius: 7px; font-size: 13px; background: linear-gradient(120deg, #1762ff, #2c69ff); }
.photo-grid { display: grid; min-width: 0; max-height: 164px; grid-template-columns: repeat(4, minmax(0, 1fr)); grid-auto-rows: 77px; gap: 10px; overflow-y: auto; }
.photo-slot { display: flex; min-width: 0; align-items: center; justify-content: center; overflow: hidden; border: 1px solid rgb(var(--color-border)); border-radius: 7px; }
.photo-slot-empty { border-style: dashed; border-color: rgb(var(--color-muted) / 30%); background: rgb(var(--color-canvas) / 85%); }
.photo-cover { border: 1px solid rgb(var(--color-primary)); background: rgb(var(--color-primary) / 4%); }
.photo-cover-label { position: absolute; bottom: 0; left: 50%; transform: translateX(-50%); padding: 1px 10px; border-radius: 6px 6px 0 0; background: #1e63ff; color: white; font-size: 10px; line-height: 1.4; white-space: nowrap; }
.photo-controls { position: absolute; top: 3px; right: 3px; display: flex; gap: 3px; }
.photo-controls button { display: flex; width: 24px; height: 24px; align-items: center; justify-content: center; border: 1px solid rgb(var(--color-border)); border-radius: 5px; background: rgb(var(--color-surface) / 95%); color: rgb(var(--color-muted)); }
.photo-controls button:hover { color: rgb(var(--color-primary)); }
.photos-footer { margin-top: 5px; font-size: 11px; line-height: 1.6; }
.photo-camera { padding: 1px; font-size: 11px; }
@media (max-width: 1399px) and (min-width: 1024px) { .photos-workspace { grid-template-columns: 1fr; } .photo-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
@media (max-width: 639px) { .product-photos { padding: 16px; } .photos-heading { flex-wrap: wrap; gap: 8px; } .photos-heading > div { gap: 10px; } .photo-count { padding-left: 52px; padding-top: 0; } .photos-workspace { grid-template-columns: 1fr; } .photo-grid { grid-auto-rows: 72px; max-height: 154px; gap: 8px; } .photos-footer { gap: 8px; } .photo-camera { font-size: 12px; } }
</style>
