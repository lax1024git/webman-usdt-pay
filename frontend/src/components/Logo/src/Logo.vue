<script setup lang="ts">
import { ref, watch, computed, onMounted, onUnmounted, unref } from 'vue'
import { useAppStore } from '@/store/modules/app'
import { useDesign } from '@/hooks/web/useDesign'
import { getServerTimeApi } from '@/api/login'
import defaultLogo from '@/assets/imgs/logo.png'

const { getPrefixCls } = useDesign()

const prefixCls = getPrefixCls('logo')

const appStore = useAppStore()

const show = ref(true)

const title = computed(() => appStore.getTitle)
const brandLogo = computed(() => appStore.getBrandLogo || defaultLogo)

const layout = computed(() => appStore.getLayout)

const collapse = computed(() => appStore.getCollapse)

/** 同步时刻的服务器 unix 毫秒 */
const serverEpochMs = ref(0)
/** 同步时刻的客户端 Date.now() */
const syncedAtClientMs = ref(0)
const serverTimezone = ref('Asia/Shanghai')

const nowText = ref('--')

let tickTimer: ReturnType<typeof setInterval> | null = null
let syncTimer: ReturnType<typeof setInterval> | null = null

const formatServerNow = () => {
  if (!serverEpochMs.value) {
    return
  }
  const elapsed = Date.now() - syncedAtClientMs.value
  const ms = serverEpochMs.value + elapsed
  try {
    nowText.value = new Intl.DateTimeFormat('sv-SE', {
      timeZone: serverTimezone.value,
      year: 'numeric',
      month: '2-digit',
      day: '2-digit',
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit',
      hour12: false
    }).format(new Date(ms))
  } catch {
    const d = new Date(ms)
    const pad = (n: number) => String(n).padStart(2, '0')
    nowText.value = `${d.getUTCFullYear()}-${pad(d.getUTCMonth() + 1)}-${pad(d.getUTCDate())} ${pad(d.getUTCHours())}:${pad(d.getUTCMinutes())}:${pad(d.getUTCSeconds())}`
  }
}

const syncServerTime = async () => {
  const clientBefore = Date.now()
  try {
    const res = await getServerTimeApi()
    const clientAfter = Date.now()
    const rttHalf = Math.max(0, Math.floor((clientAfter - clientBefore) / 2))
    const serverMs = Number(res.data?.timestamp ?? 0) * 1000
    if (serverMs > 0) {
      serverEpochMs.value = serverMs + rttHalf
      syncedAtClientMs.value = clientAfter
      if (res.data?.timezone) {
        serverTimezone.value = res.data.timezone
      }
      formatServerNow()
    }
  } catch {
    // keep previous sync
  }
}

onMounted(() => {
  if (unref(collapse)) show.value = false
  void syncServerTime()
  tickTimer = setInterval(formatServerNow, 1000)
  syncTimer = setInterval(() => void syncServerTime(), 5 * 60 * 1000)
})

onUnmounted(() => {
  if (tickTimer) {
    clearInterval(tickTimer)
    tickTimer = null
  }
  if (syncTimer) {
    clearInterval(syncTimer)
    syncTimer = null
  }
})

watch(
  () => collapse.value,
  (collapse: boolean) => {
    if (unref(layout) === 'topLeft' || unref(layout) === 'cutMenu') {
      show.value = true
      return
    }
    show.value = !collapse
  }
)

watch(
  () => layout.value,
  (layout) => {
    if (layout === 'top' || layout === 'cutMenu') {
      show.value = true
    } else {
      if (unref(collapse)) {
        show.value = false
      } else {
        show.value = true
      }
    }
  }
)
</script>

<template>
  <div>
    <router-link
      :class="[
        prefixCls,
        layout !== 'classic' ? `${prefixCls}__Top` : '',
        'flex !h-[var(--logo-height)] items-center cursor-pointer pl-8px relative decoration-none overflow-hidden'
      ]"
      to="/"
    >
      <img
        :src="brandLogo"
        alt="logo"
        class="w-[calc(var(--logo-height)-24px)] h-[calc(var(--logo-height)-24px)] shrink-0 object-contain"
      />
      <div
        v-if="show"
        :class="[
          'ml-10px flex flex-col justify-center leading-tight min-w-0',
          {
            'text-[var(--logo-title-text-color)]': layout === 'classic',
            'text-[var(--top-header-text-color)]':
              layout === 'topLeft' || layout === 'top' || layout === 'cutMenu'
          }
        ]"
      >
        <div class="text-16px font-700 truncate">{{ title }}</div>
        <div class="text-11px opacity-75 mt-2px whitespace-nowrap tabular-nums">{{ nowText }}</div>
      </div>
    </router-link>
  </div>
</template>
