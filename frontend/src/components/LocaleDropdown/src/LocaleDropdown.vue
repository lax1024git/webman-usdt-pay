<script setup lang="ts">
import { computed, unref } from 'vue'
import { ElDropdown, ElDropdownMenu, ElDropdownItem } from 'element-plus'
import { useLocaleStore } from '@/store/modules/locale'
import { useLocale } from '@/hooks/web/useLocale'
import { propTypes } from '@/utils/propTypes'
import { useDesign } from '@/hooks/web/useDesign'

const { getPrefixCls } = useDesign()

const prefixCls = getPrefixCls('locale-dropdown')

defineProps({
  color: propTypes.string.def('')
})

const localeStore = useLocaleStore()

const langMap = computed(() => localeStore.getLocaleMap)

const currentLang = computed(() => localeStore.getCurrentLocale)

const { changeLocale } = useLocale()

const setLang = async (lang: LocaleType) => {
  if (lang === unref(currentLang).lang) return
  // 先写入 locale（含 elLocale / localStorage），再切 vue-i18n
  // 多数页面的表头/表单在 setup 时用 t() 固化，需整页刷新才生效
  await changeLocale(lang)
  window.location.reload()
}
</script>

<template>
  <ElDropdown :class="prefixCls" trigger="click" @command="setLang">
    <span class="cursor-pointer flex items-center !p-0" :class="$attrs.class">
      <Icon :size="18" icon="vi-ion:language-sharp" :color="color" class="!p-0" />
    </span>
    <template #dropdown>
      <ElDropdownMenu>
        <ElDropdownItem v-for="item in langMap" :key="item.lang" :command="item.lang">
          {{ item.name }}
        </ElDropdownItem>
      </ElDropdownMenu>
    </template>
  </ElDropdown>
</template>
