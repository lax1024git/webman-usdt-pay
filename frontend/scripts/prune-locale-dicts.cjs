/**
 * Prune unused Chinese→English dict entries after business strip.
 * Usage: node scripts/prune-locale-dicts.cjs
 */
const fs = require('fs')
const path = require('path')

const root = path.resolve(__dirname, '..')
const srcRoot = path.join(root, 'src')
const enPath = path.join(srcRoot, 'locales', 'dict.en.ts')
const zhPath = path.join(srcRoot, 'locales', 'dict.zh-CN.ts')
const schemaPath = path.resolve(root, '..', 'config', 'system_config_schema.php')

function walk(dir, acc = []) {
  for (const ent of fs.readdirSync(dir, { withFileTypes: true })) {
    const p = path.join(dir, ent.name)
    if (ent.isDirectory()) {
      if (['node_modules', 'dist', '.git'].includes(ent.name)) continue
      walk(p, acc)
    } else if (/\.(vue|ts|tsx|js|jsx)$/.test(ent.name)) {
      acc.push(p)
    }
  }
  return acc
}

function parseDictionary(filePath) {
  const source = fs.readFileSync(filePath, 'utf8')
  const objectLiteral = source
    .replace(/^[\s\S]*?export default\s*/, 'return ')
    .replace(/\s+as Record<string, string>\s*$/, '')
  return Function(objectLiteral)()
}

function unescapeJsString(raw) {
  return raw
    .replace(/\\n/g, '\n')
    .replace(/\\r/g, '\r')
    .replace(/\\t/g, '\t')
    .replace(/\\'/g, "'")
    .replace(/\\"/g, '"')
    .replace(/\\\\/g, '\\')
}

function collectUsedKeys() {
  const used = new Set()
  // t('...') / t("...") / t(`...`) — only capture simple string literals
  const callRe = /\b(?:t|displayText)\(\s*(['"])((?:\\.|(?!\1).)*)\1/g

  for (const file of walk(srcRoot)) {
    if (file.includes(`${path.sep}locales${path.sep}`)) continue
    const src = fs.readFileSync(file, 'utf8')
    let m
    while ((m = callRe.exec(src))) {
      const key = unescapeJsString(m[2])
      if (/[\u3400-\u9fff]/.test(key)) used.add(key)
    }
  }

  if (fs.existsSync(schemaPath)) {
    const php = fs.readFileSync(schemaPath, 'utf8')
    const fieldRe = /'(?:label|help|placeholder)'\s*=>\s*'((?:\\'|[^'])*)'/g
    let m
    while ((m = fieldRe.exec(php))) {
      const key = m[1].replace(/\\'/g, "'")
      if (/[\u3400-\u9fff]/.test(key)) used.add(key)
    }
  }

  // Admin menuConfig Chinese names (fallback t(title) path)
  const controllerDir = path.resolve(root, '..', 'app', 'admin', 'controller')
  if (fs.existsSync(controllerDir)) {
    const walkPhp = (dir) => {
      for (const ent of fs.readdirSync(dir, { withFileTypes: true })) {
        const p = path.join(dir, ent.name)
        if (ent.isDirectory()) walkPhp(p)
        else if (ent.name.endsWith('.php')) {
          // Strip comments so docblock examples (e.g. 内容管理) are not collected
          const src = fs
            .readFileSync(p, 'utf8')
            .replace(/\/\*[\s\S]*?\*\//g, '')
            .replace(/\/\/.*$/gm, '')
          const nameRe = /'name'\s*=>\s*'((?:\\'|[^'])*)'/g
          let m
          while ((m = nameRe.exec(src))) {
            const key = m[1].replace(/\\'/g, "'")
            if (/[\u3400-\u9fff]/.test(key)) used.add(key)
          }
        }
      }
    }
    walkPhp(controllerDir)
  }

  return used
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

function main() {
  const used = collectUsedKeys()
  const enDict = parseDictionary(enPath)
  const zhDict = parseDictionary(zhPath)

  const keep = [...used].sort((a, b) => a.localeCompare(b, 'zh-CN'))

  // Always keep keys that are used even if missing from one dict (fill with identity)
  const missingInEn = []
  const missingInZh = []
  for (const key of keep) {
    if (!(key in enDict)) {
      enDict[key] = key
      missingInEn.push(key)
    }
    if (!(key in zhDict)) {
      zhDict[key] = key
      missingInZh.push(key)
    }
  }

  const beforeEn = Object.keys(enDict).length
  const beforeZh = Object.keys(zhDict).length

  writeDict(
    enPath,
    '/**\n * Admin UI dictionary: Chinese key → English value\n * Pruned after business strip; only keys still referenced by admin shell.\n */\n',
    enDict,
    keep
  )
  writeDict(
    zhPath,
    '/**\n * Admin UI dictionary: Chinese key → Chinese value (identity map)\n * Pruned after business strip; only keys still referenced by admin shell.\n */\n',
    zhDict,
    keep
  )

  console.log(
    JSON.stringify(
      {
        usedKeys: used.size,
        kept: keep.length,
        beforeEn,
        beforeZh,
        removedEnApprox: beforeEn - keep.length,
        removedZhApprox: beforeZh - keep.length,
        missingInEn,
        missingInZh,
        sampleKept: keep.slice(0, 20),
      },
      null,
      2
    )
  )
}

main()
