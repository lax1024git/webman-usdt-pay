<script setup lang="ts">
import { ContentWrap } from '@/components/ContentWrap'
import { useI18n } from '@/hooks/web/useI18n'

const { t } = useI18n()
</script>

<template>
  <ContentWrap :title="t('首页')">
    <div class="welcome">
      <h2>{{ t('欢迎使用管理后台') }}</h2>
      <p>{{ t('当前为纯管理壳：管理员、角色权限、系统配置、字典、日志、上传等。') }}</p>
    </div>
  </ContentWrap>
</template>

<style scoped>
.welcome {
  padding: 24px 8px;
  color: var(--el-text-color-regular);
}
.welcome h2 {
  margin: 0 0 12px;
  font-size: 20px;
  font-weight: 600;
  color: var(--el-text-color-primary);
}
.welcome p {
  margin: 0;
  line-height: 1.6;
}
</style>
