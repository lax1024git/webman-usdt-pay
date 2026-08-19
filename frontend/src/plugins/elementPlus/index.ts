import type { App } from 'vue'
import { ElDialog, ElDrawer, ElLoading, ElMessageBox, ElScrollbar } from 'element-plus'

const plugins = [ElLoading]

const components = [ElScrollbar]

/** 全局禁止点击遮罩关闭弹层 */
function disableCloseOnClickModal(component: any) {
  const prop = component?.props?.closeOnClickModal
  if (prop && typeof prop === 'object') {
    prop.default = false
  }
}

function patchMessageBoxNoMaskClose() {
  ;(['confirm', 'alert', 'prompt'] as const).forEach((method) => {
    const original = ElMessageBox[method].bind(ElMessageBox) as (...args: any[]) => any
    ;(ElMessageBox as any)[method] = (message: any, title?: any, options?: any) => {
      // confirm(message, options) 或 confirm(message, title, options)
      if (typeof title === 'object' && title !== null) {
        return original(message, { closeOnClickModal: false, ...title })
      }
      return original(message, title, { closeOnClickModal: false, ...options })
    }
  })
}

export const setupElementPlus = (app: App) => {
  plugins.forEach((plugin) => {
    app.use(plugin)
  })

  disableCloseOnClickModal(ElDialog)
  disableCloseOnClickModal(ElDrawer)
  patchMessageBoxNoMaskClose()

  // 为了开发环境启动更快，一次性引入所有样式
  if (import.meta.env.VITE_USE_ALL_ELEMENT_PLUS_STYLE === 'true') {
    import('element-plus/dist/index.css')
    return
  }

  components.forEach((component) => {
    app.component(component.name!, component)
  })
}
