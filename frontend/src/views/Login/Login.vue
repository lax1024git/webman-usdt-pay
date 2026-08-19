<script setup lang="ts">
import { LoginForm } from './components'
import { ThemeSwitch } from '@/components/ThemeSwitch'
import { LocaleDropdown } from '@/components/LocaleDropdown'
import { useI18n } from '@/hooks/web/useI18n'
import { underlineToHump } from '@/utils'
import { useAppStore } from '@/store/modules/app'
import { computed } from 'vue'
import defaultLogo from '@/assets/imgs/logo.png'

const appStore = useAppStore()
const brandLogo = computed(() => appStore.getBrandLogo || defaultLogo)
const brandTitle = computed(() => underlineToHump(appStore.getTitle))

const { t } = useI18n()
</script>

<template>
  <div class="login-page">
    <div class="login-bg" aria-hidden="true">
      <div class="login-bg__grid" />
      <div class="login-bg__orb login-bg__orb--1" />
      <div class="login-bg__orb login-bg__orb--2" />
      <div class="login-bg__orb login-bg__orb--3" />
      <div class="login-bg__scanline" />
      <div class="login-bg__core">
        <span class="login-core__ring login-core__ring--1" />
        <span class="login-core__ring login-core__ring--2" />
        <span class="login-core__ring login-core__ring--3" />
      </div>
    </div>

    <header class="login-header">
      <div class="login-brand">
        <div class="login-brand__logo">
          <img :src="brandLogo" alt="" class="login-brand__img" />
          <span class="login-brand__ring" />
        </div>
        <div class="login-brand__text">
          <span class="login-brand__title">{{ brandTitle }}</span>
          <span class="login-brand__tag">{{ t('login.consoleTag') }}</span>
        </div>
      </div>
      <div class="login-tools">
        <ThemeSwitch />
        <LocaleDropdown />
      </div>
    </header>

    <main class="login-main">
      <div class="login-card">
        <div class="login-card__glow" aria-hidden="true" />
        <div class="login-card__header">
          <div class="login-card__badge">
            <span class="login-card__dot" />
            {{ t('login.systemOnline') }}
          </div>
          <span class="login-card__eyebrow">{{ t('login.secureAccess') }}</span>
          <h2 class="login-card__title">{{ t('login.login') }}</h2>
          <p class="login-card__hint">{{ t('login.subtitle') }}</p>
        </div>
        <LoginForm />
      </div>
    </main>
  </div>
</template>

<style lang="less" scoped>
.login-page {
  position: relative;
  min-height: 100vh;
  overflow: hidden;
  color: #e8f4ff;
  background: #050816;
}

.login-bg {
  position: fixed;
  inset: 0;
  z-index: 0;
  pointer-events: none;

  &__grid {
    position: absolute;
    inset: 0;
    background-image:
      linear-gradient(rgba(56, 189, 248, 0.08) 1px, transparent 1px),
      linear-gradient(90deg, rgba(56, 189, 248, 0.08) 1px, transparent 1px);
    background-size: 48px 48px;
    mask-image: radial-gradient(circle at center, #000 35%, transparent 90%);
  }

  &__orb {
    position: absolute;
    border-radius: 50%;
    filter: blur(60px);
    opacity: 0.55;
    animation: float 10s ease-in-out infinite;

    &--1 {
      top: -8%;
      left: -6%;
      width: 420px;
      height: 420px;
      background: rgba(14, 165, 233, 0.35);
    }

    &--2 {
      right: 8%;
      bottom: -10%;
      width: 360px;
      height: 360px;
      background: rgba(99, 102, 241, 0.28);
      animation-delay: -3s;
    }

    &--3 {
      top: 35%;
      left: 42%;
      width: 240px;
      height: 240px;
      background: rgba(34, 211, 238, 0.18);
      animation-delay: -6s;
    }
  }

  &__scanline {
    position: absolute;
    inset: 0;
    background: linear-gradient(
      180deg,
      transparent 0%,
      rgba(56, 189, 248, 0.04) 50%,
      transparent 100%
    );
    background-size: 100% 8px;
    animation: scan 8s linear infinite;
    opacity: 0.35;
  }

  &__core {
    position: absolute;
    top: 50%;
    left: 50%;
    width: min(520px, 70vw);
    height: min(520px, 70vw);
    transform: translate(-50%, -50%);
    opacity: 0.22;
  }
}

.login-core__ring {
  position: absolute;
  inset: 0;
  border: 1px solid rgba(56, 189, 248, 0.35);
  border-radius: 50%;

  &--1 {
    animation: spin 18s linear infinite;
  }

  &--2 {
    inset: 12%;
    border-color: rgba(99, 102, 241, 0.3);
    animation: spin-reverse 24s linear infinite;
  }

  &--3 {
    inset: 24%;
    border-color: rgba(34, 211, 238, 0.35);
    animation: spin 30s linear infinite;
  }
}

.login-header {
  position: relative;
  z-index: 2;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 24px 32px;
}

.login-brand {
  display: flex;
  gap: 14px;
  align-items: center;

  &__logo {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 52px;
    height: 52px;
  }

  &__img {
    position: relative;
    z-index: 1;
    width: 42px;
    height: 42px;
    object-fit: contain;
  }

  &__ring {
    position: absolute;
    inset: 0;
    border: 1px solid rgba(56, 189, 248, 0.45);
    border-radius: 14px;
    box-shadow:
      0 0 18px rgba(56, 189, 248, 0.25),
      inset 0 0 18px rgba(56, 189, 248, 0.08);
    animation: pulse 3s ease-in-out infinite;
  }

  &__text {
    display: flex;
    flex-direction: column;
    gap: 4px;
  }

  &__title {
    font-size: 20px;
    font-weight: 700;
    letter-spacing: 0.04em;
  }

  &__tag {
    font-size: 12px;
    color: rgba(125, 211, 252, 0.85);
    letter-spacing: 0.18em;
    text-transform: uppercase;
  }
}

.login-tools {
  display: flex;
  gap: 12px;
  align-items: center;
}

.login-main {
  position: relative;
  z-index: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: calc(100vh - 100px);
  padding: 0 16px 40px;
}

.login-card {
  position: relative;
  width: min(100%, 460px);
  padding: 34px 32px 28px;
  overflow: hidden;
  background: rgba(8, 15, 30, 0.78);
  border: 1px solid rgba(56, 189, 248, 0.22);
  border-radius: 24px;
  box-shadow:
    0 24px 80px rgba(2, 8, 23, 0.55),
    inset 0 1px 0 rgba(255, 255, 255, 0.06);
  backdrop-filter: blur(18px);

  &__glow {
    position: absolute;
    top: -80px;
    right: -40px;
    width: 180px;
    height: 180px;
    background: radial-gradient(circle, rgba(34, 211, 238, 0.28) 0%, transparent 70%);
    pointer-events: none;
  }

  &__header {
    position: relative;
    margin-bottom: 8px;
    text-align: center;
  }

  &__badge {
    display: inline-flex;
    gap: 8px;
    align-items: center;
    padding: 6px 12px;
    margin-bottom: 16px;
    font-size: 11px;
    color: #7dd3fc;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    background: rgba(14, 165, 233, 0.12);
    border: 1px solid rgba(56, 189, 248, 0.25);
    border-radius: 999px;
  }

  &__dot {
    width: 7px;
    height: 7px;
    background: #22d3ee;
    border-radius: 50%;
    box-shadow: 0 0 10px #22d3ee;
    animation: pulse 2s ease-in-out infinite;
  }

  &__eyebrow {
    display: block;
    margin-bottom: 10px;
    font-size: 12px;
    color: #67e8f9;
    letter-spacing: 0.22em;
    text-transform: uppercase;
  }

  &__title {
    margin: 0;
    font-size: 30px;
    font-weight: 700;
    color: #f8fafc;
  }

  &__hint {
    margin: 8px 0 0;
    font-size: 13px;
    line-height: 1.6;
    color: rgba(148, 163, 184, 0.95);
  }
}

@media (width <= 768px) {
  .login-header {
    padding: 16px;
  }

  .login-card {
    padding: 28px 22px 22px;
    border-radius: 20px;
  }
}

@keyframes float {
  0%,
  100% {
    transform: translate3d(0, 0, 0);
  }

  50% {
    transform: translate3d(0, -18px, 0);
  }
}

@keyframes scan {
  0% {
    background-position: 0 0;
  }

  100% {
    background-position: 0 100%;
  }
}

@keyframes pulse {
  0%,
  100% {
    opacity: 1;
    transform: scale(1);
  }

  50% {
    opacity: 0.65;
    transform: scale(0.96);
  }
}

@keyframes spin {
  from {
    transform: rotate(0deg);
  }

  to {
    transform: rotate(360deg);
  }
}

@keyframes spin-reverse {
  from {
    transform: rotate(360deg);
  }

  to {
    transform: rotate(0deg);
  }
}
</style>

<style lang="less">
.login-page {
  .login-tools .el-dropdown,
  .login-tools .v-theme-switch {
    color: #e2e8f0;
  }

  .login-card {
    .el-form-item__label {
      color: rgba(203, 213, 225, 0.92) !important;
      font-weight: 500;
    }

    .el-input__wrapper,
    .el-input__wrapper.is-focus {
      background: rgba(15, 23, 42, 0.72) !important;
      border: 1px solid rgba(56, 189, 248, 0.18) !important;
      box-shadow: inset 0 0 0 1px rgba(56, 189, 248, 0.04) !important;
    }

    .el-input__inner {
      color: #f8fafc !important;
    }

    .el-input__inner::placeholder {
      color: rgba(148, 163, 184, 0.75) !important;
    }

    .el-input__wrapper:hover,
    .el-input__wrapper.is-focus {
      border-color: rgba(34, 211, 238, 0.45) !important;
      box-shadow:
        0 0 0 1px rgba(34, 211, 238, 0.18),
        0 0 18px rgba(34, 211, 238, 0.12) !important;
    }

    .el-checkbox__label {
      color: rgba(203, 213, 225, 0.9) !important;
    }

    .el-link {
      color: #67e8f9 !important;
    }

    .login-tech-btn.el-button--primary {
      height: 46px;
      font-size: 15px;
      font-weight: 600;
      letter-spacing: 0.08em;
      background: linear-gradient(135deg, #0284c7 0%, #6366f1 100%) !important;
      border: none !important;
      box-shadow:
        0 10px 30px rgba(14, 165, 233, 0.28),
        inset 0 1px 0 rgba(255, 255, 255, 0.18);
      transition:
        transform 0.2s ease,
        box-shadow 0.2s ease;
    }

    .login-tech-btn.el-button--primary:hover {
      transform: translateY(-1px);
      box-shadow:
        0 14px 36px rgba(14, 165, 233, 0.34),
        inset 0 1px 0 rgba(255, 255, 255, 0.22);
    }

    .login-tech-title {
      display: none;
    }
  }
}
</style>
