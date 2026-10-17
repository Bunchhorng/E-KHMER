<script setup lang="ts">
import { nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { Camera, ImagePlus, Star, Trash2, Upload } from 'lucide-vue-next'
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
  <section class="card rounded-2xl p-5 sm:p-6" aria-labelledby="product-photos-title">
    <h2 id="product-photos-title" class="font-semibold">{{ t('seller.photos') }}</h2>
    <p class="mt-1 text-xs leading-relaxed text-muted">{{ t('seller.photos_help') }}</p>
    <input ref="fileInput" type="file" class="hidden" accept="image/jpeg,image/png,image/webp,image/gif" multiple :disabled="disabled" :aria-label="t('seller.upload_photos')" @change="selectFiles" />
    <input ref="captureInput" type="file" class="hidden" accept="image/*" capture="environment" :disabled="disabled" :aria-label="t('seller.take_photo')" @change="selectFiles" />
    <div class="mt-5 flex flex-wrap gap-3">
      <button type="button" class="btn-secondary" :disabled="disabled || modelValue.length >= 20" @click="fileInput?.click()"><Upload :size="17" />{{ t('seller.upload_photos') }}</button>
      <button type="button" class="btn-secondary" :disabled="disabled || modelValue.length >= 20" @click="openCamera"><Camera :size="17" />{{ t('seller.take_photo') }}</button>
      <span class="self-center text-xs text-muted">{{ modelValue.length }} / 20</span>
    </div>
    <p v-if="error || serverError" role="alert" class="mt-3 text-sm text-red-600 dark:text-red-400">{{ error || serverError }}</p>
    <div v-if="!modelValue.length" class="mt-5 flex flex-col items-center rounded-xl border border-dashed border-border-gray bg-canvas px-5 py-9 text-center"><ImagePlus :size="28" class="text-muted" /><p class="mt-3 text-sm text-muted">{{ t('seller.photos_empty') }}</p></div>
    <ul v-else class="mt-5 grid grid-cols-2 gap-4 sm:grid-cols-3">
      <li v-for="(photo, index) in modelValue" :key="photo.key" class="overflow-hidden rounded-xl border border-border-gray">
        <AssetImage :src="photo.url" :alt="t('seller.photo_number', { number: index + 1 })" class="aspect-square w-full bg-canvas p-2" />
        <div class="flex items-center justify-between gap-1 border-t border-border-gray p-2">
          <span v-if="index === 0" class="inline-flex items-center gap-1 text-xs font-semibold text-primary"><Star :size="13" />{{ t('seller.cover_photo') }}</span>
          <button v-else type="button" class="rounded px-1 py-2 text-xs font-medium text-primary hover:bg-primary/5" :disabled="disabled" @click="setCover(photo)">{{ t('seller.make_cover') }}</button>
          <button type="button" class="btn-icon shrink-0 text-red-600 dark:text-red-400" :disabled="disabled" :aria-label="t('seller.remove_photo', { number: index + 1 })" @click="removePhoto(photo.key)"><Trash2 :size="16" /></button>
        </div>
      </li>
    </ul>
    <p class="mt-4 text-xs leading-relaxed text-muted">{{ t('seller.photos_formats') }}</p>
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
