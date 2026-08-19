<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import {
  ElBadge,
  ElButton,
  ElEmpty,
  ElMessage,
  ElNotification,
  ElPopover,
  ElScrollbar
} from 'element-plus'
import { Icon } from '@/components/Icon'
import {
  getNotificationUnreadApi,
  getPushConfigApi,
  markAllNotificationReadApi,
  markNotificationReadApi
} from '@/api/notification'
import { Push } from '@/utils/webman-push.js'
import { resolvePushWebsocketUrl } from '@/utils/resolvePushWebsocketUrl'
import { useUserStore } from '@/store/modules/user'

interface NotifyItem {
  id: number
  title: string
  content: string
  link?: string
  biz_type?: string
  created_at?: string
  voice_url?: string
}

const router = useRouter()
const userStore = useUserStore()

const visible = ref(false)
const loading = ref(false)
const unreadCount = ref(0)
const items = ref<NotifyItem[]>([])

let pushClient: any = null
let channel: any = null
let audioEl: HTMLAudioElement | null = null

const hasToken = computed(() => !!userStore.getToken)

const loadUnread = async () => {
  if (!hasToken.value) return
  loading.value = true
  try {
    const res = await getNotificationUnreadApi()
    unreadCount.value = Number(res.data?.count ?? 0)
    items.value = (res.data?.items ?? []) as NotifyItem[]
  } finally {
    loading.value = false
  }
}

const playVoice = (url?: string) => {
  if (!url) return
  try {
    if (!audioEl) audioEl = new Audio()
    audioEl.src = url
    audioEl.currentTime = 0
    void audioEl.play().catch(() => undefined)
  } catch {
    // ignore autoplay block
  }
}

const prependItem = (payload: NotifyItem) => {
  const id = Number(payload.id || 0)
  if (id > 0 && items.value.some((x) => x.id === id)) return
  items.value = [payload, ...items.value].slice(0, 30)
  unreadCount.value += 1
}

const onAuditNotify = (data: NotifyItem) => {
  prependItem(data)
  ElNotification({
    title: data.title || '待审核提醒',
    message: data.content || '',
    type: data.biz_type === 'withdraw' ? 'warning' : 'success',
    duration: 6000,
    onClick: () => {
      if (data.link) router.push(data.link)
    }
  })
  playVoice(data.voice_url)
}

const connectPush = async () => {
  if (!hasToken.value || pushClient) return
  try {
    const res = await getPushConfigApi()
    const cfg = res.data || {}
    if (!cfg.enable || !cfg.websocket_url || !cfg.app_key) return

    pushClient = new Push({
      url: resolvePushWebsocketUrl(cfg.websocket_url),
      app_key: cfg.app_key
    })
    channel = pushClient.subscribe(cfg.channel || 'admin-audit')
    channel.on('audit_notify', onAuditNotify)
  } catch (e: any) {
    console.warn('push connect failed', e?.message || e)
  }
}

const disconnectPush = () => {
  try {
    if (channel?.unsubscribe) channel.unsubscribe()
    if (pushClient?.disconnect) pushClient.disconnect()
    else if (pushClient?.connection?.close) pushClient.connection.close()
  } catch {
    // ignore
  }
  channel = null
  pushClient = null
}

const handleOpen = async (val: boolean) => {
  visible.value = val
  if (val) await loadUnread()
}

const handleClickItem = async (item: NotifyItem) => {
  try {
    if (item.id) await markNotificationReadApi(item.id)
  } catch {
    // ignore
  }
  items.value = items.value.filter((x) => x.id !== item.id)
  unreadCount.value = Math.max(0, unreadCount.value - 1)
  visible.value = false
  if (item.link) router.push(item.link)
  else router.push('/system-config/notification')
}

const handleMarkAll = async () => {
  await markAllNotificationReadApi()
  ElMessage.success('全部已读')
  unreadCount.value = 0
  items.value = []
}

const goCenter = () => {
  visible.value = false
  router.push('/system-config/notification')
}

onMounted(async () => {
  await loadUnread()
  await connectPush()
})

onBeforeUnmount(() => {
  disconnectPush()
})
</script>

<template>
  <ElPopover
    :visible="visible"
    placement="bottom-end"
    :width="360"
    trigger="click"
    @update:visible="handleOpen"
  >
    <template #reference>
      <div
        class="custom-hover flex items-center justify-center w-[40px] h-[40px] relative cursor-pointer"
        title="消息通知"
      >
        <ElBadge :value="unreadCount" :hidden="unreadCount <= 0" :max="99">
          <Icon icon="vi-ep:bell" color="var(--top-header-text-color)" :size="18" />
        </ElBadge>
      </div>
    </template>

    <div class="notify-panel">
      <div class="notify-panel__head">
        <span>消息通知</span>
        <div class="flex gap-8px">
          <ElButton link type="primary" @click="handleMarkAll">全部已读</ElButton>
          <ElButton link @click="goCenter">通知中心</ElButton>
        </div>
      </div>

      <ElScrollbar max-height="360px">
        <div v-loading="loading">
          <ElEmpty v-if="!items.length" description="暂无未读消息" :image-size="64" />
          <div
            v-for="item in items"
            :key="item.id"
            class="notify-item"
            @click="handleClickItem(item)"
          >
            <div class="notify-item__title">{{ item.title }}</div>
            <div class="notify-item__content">{{ item.content }}</div>
            <div class="notify-item__time">{{ item.created_at }}</div>
          </div>
        </div>
      </ElScrollbar>
    </div>
  </ElPopover>
</template>

<style scoped>
.notify-panel__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 8px;
  font-weight: 600;
}

.notify-item {
  padding: 10px 4px;
  border-bottom: 1px solid var(--el-border-color-lighter);
  cursor: pointer;
}

.notify-item:hover {
  background: var(--el-fill-color-light);
}

.notify-item__title {
  font-size: 13px;
  font-weight: 600;
  color: var(--el-text-color-primary);
}

.notify-item__content {
  margin-top: 4px;
  font-size: 12px;
  color: var(--el-text-color-regular);
  line-height: 1.5;
}

.notify-item__time {
  margin-top: 4px;
  font-size: 12px;
  color: var(--el-text-color-secondary);
}
</style>
