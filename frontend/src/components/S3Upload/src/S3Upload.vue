<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import {
  ElUpload,
  ElMessage,
  ElDialog,
  ElInput,
  ElButton,
  ElProgress,
  type UploadRequestOptions,
  type UploadUserFile
} from 'element-plus'
import { Icon } from '@/components/Icon'
import { uploadToS3 } from '@/utils/s3Upload'
import type { S3UploadEmits, S3UploadProps } from './types'
import { useI18n } from '@/hooks/web/useI18n'

const props = withDefaults(defineProps<S3UploadProps>(), {
  type: 'image',
  accept: '',
  disabled: false,
  showFileList: true,
  limit: 1,
  compact: false
})

const emit = defineEmits<S3UploadEmits>()
const { t } = useI18n()

const uploading = ref(false)
const uploadPercent = ref(0)
const fileList = ref<UploadUserFile[]>([])
const previewVisible = ref(false)
const previewUrl = ref('')
const hiddenUploadRef = ref<InstanceType<typeof ElUpload>>()

const isImage = computed(() => props.type === 'image')
const isVideo = computed(() => props.type === 'video')
const isFile = computed(() => props.type === 'file' || props.type === 'document')
const useUrlInput = computed(() => isVideo.value || isFile.value)

const resolvedAccept = computed(() => {
  if (props.accept) {
    return props.accept
  }
  if (props.type === 'video') {
    return 'video/*,.mp4,.webm,.mov,.mkv'
  }
  if (props.type === 'file') {
    return '.apk,.ipa,.mobileconfig,.plist,.zip,.mp3,.wav,.ogg,.m4a'
  }
  if (props.type === 'document') {
    return '.pdf,.doc,.docx'
  }
  return 'image/jpeg,image/png,image/gif,image/webp'
})

const uploadButtonText = computed(() => {
  if (props.type === 'video') return t('上传视频')
  if (props.type === 'file') return t('上传文件')
  if (props.type === 'document') return t('上传文档')
  return t('点击上传')
})

const urlPlaceholder = computed(() => {
  if (props.type === 'video') return t('请上传视频或填写链接')
  return t('请上传文件或填写下载链接')
})

const currentUrl = computed({
  get: () => props.modelValue ?? '',
  set: (value: string) => emit('update:modelValue', value)
})

const displayName = (url: string) => {
  if (!url) return 'file'
  try {
    const parsed = new URL(url, window.location.origin)
    const base = parsed.pathname.split('/').filter(Boolean).pop()
    if (base) return decodeURIComponent(base)
    return parsed.host || url
  } catch {
    const cleaned = url.split('?')[0]
    return cleaned.split('/').pop() || url
  }
}

const syncFileList = () => {
  const url = currentUrl.value
  fileList.value = url
    ? [
        {
          name: displayName(url),
          url,
          status: 'success',
          uid: 1
        }
      ]
    : []
}

const isLimitReached = computed(() => fileList.value.length >= props.limit)

watch(
  () => props.modelValue,
  () => syncFileList(),
  { immediate: true }
)

const handleUpload = async (options: UploadRequestOptions) => {
  uploading.value = true
  uploadPercent.value = 0
  try {
    const result = await uploadToS3(options.file as File, {
      type: props.type === 'document' ? 'document' : props.type,
      onProgress: (percent) => {
        uploadPercent.value = percent
      }
    })
    currentUrl.value = result.url
    syncFileList()
    emit('success', result)
    ElMessage.success(t('上传成功'))
    options.onSuccess?.(result)
  } catch (error) {
    const err = error instanceof Error ? error : new Error(t('上传失败'))
    emit('error', err)
    ElMessage.error(err.message)
    options.onError?.(err as any)
  } finally {
    uploading.value = false
  }
}

const handleRemove = () => {
  currentUrl.value = ''
  fileList.value = []
  uploadPercent.value = 0
}

const handlePreview = (file: UploadUserFile) => {
  previewUrl.value = file.url || currentUrl.value
  previewVisible.value = true
}

const triggerHiddenUpload = () => {
  const input = hiddenUploadRef.value?.$el?.querySelector(
    'input[type="file"]'
  ) as HTMLInputElement | null
  input?.click()
}
</script>

<template>
  <div class="s3-upload" :class="{ 's3-upload--compact': compact }">
    <template v-if="useUrlInput">
      <div class="s3-upload__url">
        <ElInput
          v-model="currentUrl"
          :placeholder="urlPlaceholder"
          clearable
          :disabled="disabled || uploading"
        >
          <template #append>
            <ElButton :loading="uploading" :disabled="disabled" @click="triggerHiddenUpload">
              {{ uploadButtonText }}
            </ElButton>
          </template>
        </ElInput>
        <ElProgress
          v-if="uploading || uploadPercent > 0"
          class="s3-upload__progress"
          :percentage="uploadPercent"
          :status="uploading ? undefined : 'success'"
        />
        <ElUpload
          ref="hiddenUploadRef"
          class="s3-upload__hidden-uploader"
          :accept="resolvedAccept"
          :disabled="disabled || uploading"
          :show-file-list="false"
          :http-request="handleUpload"
        />
      </div>
    </template>

    <ElUpload
      v-else
      v-model:file-list="fileList"
      class="s3-upload__uploader"
      :class="{
        's3-upload__uploader--image': isImage,
        's3-upload__uploader--limit': isLimitReached
      }"
      :accept="resolvedAccept"
      :disabled="disabled || uploading"
      :show-file-list="showFileList"
      :list-type="isImage ? 'picture-card' : 'text'"
      :limit="limit"
      :http-request="handleUpload"
      :on-remove="handleRemove"
      :on-preview="handlePreview"
    >
      <template v-if="isImage && !isLimitReached">
        <Icon icon="vi-ep:plus" :size="compact ? 14 : 24" />
      </template>
      <template v-else>
        <el-button type="primary" :loading="uploading">{{ t('点击上传') }}</el-button>
      </template>
    </ElUpload>

    <ElDialog
      v-model="previewVisible"
      :title="isVideo ? t('视频预览') : t('图片预览')"
      width="640px"
      append-to-body
    >
      <video
        v-if="isVideo && previewUrl"
        class="s3-upload__preview-video"
        :src="previewUrl"
        controls
      ></video>
      <img v-else class="s3-upload__preview-image" :src="previewUrl" alt="preview" />
    </ElDialog>
  </div>
</template>

<style scoped>
.s3-upload__uploader--image :deep(.el-upload-list--picture-card) {
  margin: 0;
}

.s3-upload__uploader--image.s3-upload__uploader--limit :deep(.el-upload--picture-card) {
  display: none;
}

.s3-upload__hidden-uploader {
  display: none;
}

.s3-upload__progress {
  margin-top: 8px;
}

.s3-upload__preview-image {
  display: block;
  width: 100%;
  max-height: 420px;
  object-fit: contain;
}

.s3-upload__preview-video {
  display: block;
  width: 100%;
  max-height: 420px;
}

.s3-upload--compact :deep(.el-upload--picture-card),
.s3-upload--compact :deep(.el-upload-list--picture-card .el-upload-list__item) {
  width: 36px;
  height: 36px;
  margin: 0;
}

.s3-upload--compact :deep(.el-upload-list--picture-card) {
  display: inline-flex;
  margin: 0;
}

.s3-upload--compact :deep(.el-upload-list__item-actions) {
  font-size: 12px;
}

.s3-upload--compact :deep(.el-upload-list__item-actions span + span) {
  margin-left: 2px;
}
</style>
