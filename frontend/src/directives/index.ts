import type { App } from 'vue'
import { setupPermissionDirective } from './permission/hasPermi'
import { setupPermissionDirective as setupVPermission } from './permission/permission'

export const setupPermission = (app: App<Element>) => {
  setupPermissionDirective(app)
  setupVPermission(app)
}
