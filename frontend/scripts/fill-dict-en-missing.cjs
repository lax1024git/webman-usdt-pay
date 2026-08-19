/**
 * Fill English values for dict keys that still equal Chinese (identity fallback).
 * Usage: node scripts/fill-dict-en-missing.cjs
 */
const fs = require('fs')
const path = require('path')

const enPath = path.resolve(__dirname, '../src/locales/dict.en.ts')
const zhPath = path.resolve(__dirname, '../src/locales/dict.zh-CN.ts')

const translations = {
  按编码获取字典: 'Get dictionary by code',
  白名单列表: 'Whitelist list',
  保存参数配置: 'Save parameter config',
  保存文案: 'Save copy',
  保存字典项: 'Save dictionary items',
  编辑管理员需验证: 'Google verification is required to edit admin',
  编辑角色需验证: 'Google verification is required to edit role',
  参数配置详情: 'Parameter config detail',
  创建导出任务: 'Create export job',
  创建管理员: 'Create admin',
  创建角色: 'Create role',
  创建权限: 'Create permission',
  创建通知: 'Create notification',
  创建语言: 'Create language',
  创建字典类型: 'Create dictionary type',
  '当前为纯管理壳：管理员、角色权限、系统配置、字典、日志、上传等。':
    'This is a pure admin shell: admins, RBAC, system settings, dict, logs, upload, etc.',
  导出翻译: 'Export translations',
  导出任务进度: 'Export job progress',
  导出任务列表: 'Export job list',
  导入翻译: 'Import translations',
  翻译预览: 'Translation preview',
  更新白名单: 'Update whitelist',
  更新管理员: 'Update admin',
  更新角色: 'Update role',
  更新权限: 'Update permission',
  更新语言: 'Update language',
  更新字典类型: 'Update dictionary type',
  管理员列表: 'Admin list',
  欢迎使用管理后台: 'Welcome to the admin console',
  角色列表: 'Role list',
  角色授权需验证: 'Google verification is required to assign role permissions',
  控制台: 'Console',
  品牌配置: 'Branding',
  权限管理: 'Permission management',
  日志列表: 'Log list',
  删除白名单: 'Delete whitelist',
  删除导出任务: 'Delete export job',
  删除管理员: 'Delete admin',
  删除管理员需验证: 'Google verification is required to delete admin',
  删除角色: 'Delete role',
  删除角色需验证: 'Google verification is required to delete role',
  删除权限: 'Delete permission',
  删除文案: 'Delete copy',
  删除语言: 'Delete language',
  删除字典类型: 'Delete dictionary type',
  数据字典: 'Data dictionary',
  通知列表: 'Notification list',
  推送配置: 'Push config',
  未读数量: 'Unread count',
  文案列表: 'Copy list',
  文案详情: 'Copy detail',
  系统管理: 'System',
  系统通知: 'System notifications',
  新增管理员需验证: 'Google verification is required to create admin',
  新增角色需验证: 'Google verification is required to create role',
  语言列表: 'Language list',
  语言详情: 'Language detail',
  语言选项: 'Language options',
  重置管理员密码: 'Reset admin password',
  重置管理员密码需验证: 'Google verification is required to reset admin password',
  字典类型列表: 'Dictionary type list',
  字典项列表: 'Dictionary item list',
  'admin_google_auth_status：开启后，后台敏感操作需校验谷歌验证码；关闭后跳过校验。不影响管理员登录验码。':
    'admin_google_auth_status: When enabled, sensitive admin actions require Google Auth; login verification is unaffected.',
  'admin_icon：浏览器标签页图标（favicon）。': 'admin_icon: Browser favicon.',
  'logo：后台侧栏、登录页与浏览器图标。': 'logo: Sidebar, login page and browser icon.',
  'name：应用名称，展示在后台标题等位置。': 'name: Application name shown in admin titles.',
  's3_config：对象存储（S3 兼容）凭证与桶配置，用于上传文件；presign_expires 为预签名有效秒数。':
    's3_config: S3-compatible object storage credentials and bucket settings; presign_expires is the pre-sign TTL in seconds.',
  'system_default_lang：后台与多语言模块默认语言。':
    'system_default_lang: Default language for admin and i18n module.',
}

// Drop leftover CMS labels not part of the admin shell
const dropKeys = new Set(['内容管理', '文章管理', '文章列表'])

function parseDictionary(filePath) {
  const source = fs.readFileSync(filePath, 'utf8')
  const objectLiteral = source
    .replace(/^[\s\S]*?export default\s*/, 'return ')
    .replace(/\s+as Record<string, string>\s*$/, '')
  return Function(objectLiteral)()
}

function escapeKey(key) {
  return key.replace(/\\/g, '\\\\').replace(/"/g, '\\"')
}

function escapeValue(value) {
  return String(value).replace(/\\/g, '\\\\').replace(/"/g, '\\"')
}

function writeDict(filePath, header, dict, keys) {
  const lines = [header, 'export default {']
  for (const key of keys) {
    lines.push(`  "${escapeKey(key)}": "${escapeValue(dict[key])}",`)
  }
  lines.push('}', '')
  fs.writeFileSync(filePath, lines.join('\n'), 'utf8')
}

const enDict = parseDictionary(enPath)
const zhDict = parseDictionary(zhPath)

for (const key of dropKeys) {
  delete enDict[key]
  delete zhDict[key]
}

let filled = 0
for (const [key, en] of Object.entries(translations)) {
  if (key in enDict && enDict[key] === key) {
    enDict[key] = en
    filled++
  } else if (!(key in enDict) && key in zhDict) {
    enDict[key] = en
    filled++
  }
}

const keys = Object.keys(enDict).sort((a, b) => a.localeCompare(b, 'zh-CN'))
for (const key of keys) {
  if (!(key in zhDict)) zhDict[key] = key
}

writeDict(
  enPath,
  '/**\n * Admin UI dictionary: Chinese key → English value\n * Pruned after business strip; only keys still referenced by admin shell.\n */\n',
  enDict,
  keys
)
writeDict(
  zhPath,
  '/**\n * Admin UI dictionary: Chinese key → Chinese value (identity map)\n * Pruned after business strip; only keys still referenced by admin shell.\n */\n',
  zhDict,
  keys
)

const cjkLeft = keys.filter((k) => /[\u3400-\u9fff]/.test(enDict[k]))
console.log(JSON.stringify({ keys: keys.length, filled, cjkLeft }, null, 2))
