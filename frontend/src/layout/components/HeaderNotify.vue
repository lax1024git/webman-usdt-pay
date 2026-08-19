<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { ElBadge, ElButton, ElEmpty, ElMessage, ElPopover, ElNotification } from 'element-plus'
import { Icon } from '@/components/Icon'
import {
  getNotificationUnreadApi,
  getPushConfigApi,
  markAllNotificationReadApi,
  markNotificationReadApi
} from '@/api/notification'
import { Push } from '@/utils/webman-push.js'
import { resolvePushWebsocketUrl } from '@/utils/resolvePushWebsocketUrl'

type NotifyItem = {
  id: number
  title: string
  content: string
  link?: string
  biz_type?: string
  created_at?: string
  voice_url?: string
}

type PushConfig = {
  enable?: boolean
  websocket_url?: string
  app_key?: string
  channel?: string
  recharge_voice_url?: string
  withdraw_voice_url?: string
}

const router = useRouter()
const unreadCount = ref(0)
const items = ref<NotifyItem[]>([])
const loading = ref(false)
const visible = ref(false)
const pushConfig = ref<PushConfig>({})

let pushConn: any = null
let audioEl: HTMLAudioElement | null = null
let audioUnlocked = false

const hasUnread = computed(() => unreadCount.value > 0)

const loadUnread = async () => {
  loading.value = true
  try {
    const res = await getNotificationUnreadApi()
    unreadCount.value = Number(res.data?.count ?? 0)
    items.value = (res.data?.items ?? []) as NotifyItem[]
  } catch {
    // ignore
  } finally {
    loading.value = false
  }
}

const unlockAudio = () => {
  if (audioUnlocked) return
  try {
    if (!audioEl) audioEl = new Audio()
    audioEl.muted = true
    void audioEl
      .play()
      .then(() => {
        audioEl?.pause()
        if (audioEl) audioEl.muted = false
        audioUnlocked = true
      })
      .catch(() => undefined)
  } catch {
    // ignore
  }
}

const playVoice = (url?: string) => {
  const src = (url || '').trim()
  if (!src) return
  try {
    if (!audioEl) audioEl = new Audio()
    audioEl.muted = false
    audioEl.src = src
    void audioEl.play().catch(() => undefined)
  } catch {
    // ignore autoplay block
  }
}

const voiceForBiz = (bizType?: string, fallback?: string) => {
  if (fallback) return fallback
  if (bizType === 'withdraw') return pushConfig.value.withdraw_voice_url || ''
  if (bizType === 'recharge') return pushConfig.value.recharge_voice_url || ''
  return ''
}

const prependItem = (payload: NotifyItem) => {
  if (!payload?.id) return
  if (items.value.some((x) => x.id === payload.id)) return
  items.value = [payload, ...items.value].slice(0, 20)
  unreadCount.value += 1
}

const resolveNotifyLink = (item: NotifyItem) => {
  const link = (item.link || '').trim()
  return link || '/system-config/notification'
}

const connectPush = async () => {
  try {
    const res = await getPushConfigApi()
    const cfg = (res.data ?? {}) as PushConfig
    pushConfig.value = cfg
    if (!cfg.enable || !cfg.websocket_url || !cfg.app_key) return

    pushConn = new Push({
      url: resolvePushWebsocketUrl(cfg.websocket_url),
      app_key: cfg.app_key
    })
    const channel = pushConn.subscribe(cfg.channel || 'admin-audit')
    channel.on('audit_notify', (data: NotifyItem) => {
      prependItem(data)
      ElNotification({
        title: data.title || '待审核提醒',
        message: data.content || '',
        type: data.biz_type === 'withdraw' ? 'warning' : 'success',
        duration: 6000,
        onClick: () => {
          router.push(resolveNotifyLink(data))
        }
      })
      playVoice(voiceForBiz(data.biz_type, data.voice_url))
    })
  } catch {
    // push 不可用时仍可轮询/手动刷新
  }
}

const handleOpen = async (item: NotifyItem) => {
  unlockAudio()
  try {
    await markNotificationReadApi(item.id)
  } catch {
    // ignore
  }
  unreadCount.value = Math.max(0, unreadCount.value - 1)
  items.value = items.value.filter((x) => x.id !== item.id)
  visible.value = false
  router.push(resolveNotifyLink(item))
}

const handleMarkAll = async () => {
  await markAllNotificationReadApi()
  ElMessage.success('全部已读')
  unreadCount.value = 0
  items.value = []
}

const handleViewAll = () => {
  visible.value = false
  router.push('/system-config/notification')
}

onMounted(async () => {
  window.addEventListener('pointerdown', unlockAudio, { once: true })
  await loadUnread()
  await connectPush()
})

onUnmounted(() => {
  window.removeEventListener('pointerdown', unlockAudio)
  try {
    pushConn?.disconnect?.()
    pushConn?.connection?.close?.()
  } catch {
    // ignore
  }
  pushConn = null
})
</script>

<template>
  <ElPopover v-model:visible="visible" placement="bottom-end" :width="360" trigger="click">
    <template #reference>
      <div class="custom-hover header-notify-btn" title="消息">
        <ElBadge :value="unreadCount" :hidden="!hasUnread" :max="99">
          <Icon icon="vi-ep:bell" color="var(--top-header-text-color)" :size="18" />
        </ElBadge>
      </div>
    </template>

    <div class="header-notify">
      <div class="header-notify__head">
        <span>待办消息</span>
        <ElButton link type="primary" @click="handleMarkAll">全部已读</ElButton>
      </div>

      <div v-loading="loading" class="header-notify__list">
        <ElEmpty v-if="!items.length" description="暂无未读消息" :image-size="64" />
        <div
          v-for="item in items"
          :key="item.id"
          class="header-notify__item"
          @click="handleOpen(item)"
        >
          <div class="header-notify__title">{{ item.title }}</div>
          <div class="header-notify__content">{{ item.content }}</div>
          <div class="header-notify__time">{{ item.created_at }}</div>
        </div>
      </div>

      <div class="header-notify__foot">
        <ElButton link type="primary" @click="handleViewAll">查看全部</ElButton>
        <ElButton link @click="loadUnread">刷新</ElButton>
      </div>
    </div>
  </ElPopover>
</template>

<style scoped>
.header-notify-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 40px;
  height: 40px;
  cursor: pointer;
}

.header-notify__head,
.header-notify__foot {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.header-notify__head {
  margin-bottom: 8px;
  font-weight: 600;
}

.header-notify__list {
  max-height: 360px;
  overflow: auto;
}

.header-notify__item {
  padding: 10px 4px;
  border-bottom: 1px solid var(--el-border-color-lighter);
  cursor: pointer;
}

.header-notify__item:hover {
  background: var(--el-fill-color-light);
}

.header-notify__title {
  font-size: 14px;
  font-weight: 600;
  color: var(--el-text-color-primary);
}

.header-notify__content {
  margin-top: 4px;
  font-size: 12px;
  color: var(--el-text-color-regular);
  line-height: 1.5;
}

.header-notify__time {
  margin-top: 4px;
  font-size: 12px;
  color: var(--el-text-color-secondary);
}

.header-notify__foot {
  margin-top: 8px;
}
</style>
