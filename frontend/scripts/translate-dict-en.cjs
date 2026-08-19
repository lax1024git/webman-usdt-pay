const fs = require('fs')
const path = require('path')

const dictionaryPath = path.resolve(__dirname, '../src/locales/dict.en.ts')
const cjkPattern = /[\u3400-\u9fff]/
const placeholderPattern = /\{[^{}]+\}|&#10;/g

function parseDictionary(source) {
  const objectLiteral = source
    .replace(/^\/\*[\s\S]*?\*\/\s*export default\s*/, 'return ')
    .replace(/\s+as Record<string, string>\s*$/, '')

  return Function(objectLiteral)()
}

function protectPlaceholders(sourceText) {
  const protectedTokens = []
  const protectedText = sourceText.replace(placeholderPattern, (token) => {
    const tokenName = `__LOCALE_TOKEN_${protectedTokens.length}__`
    protectedTokens.push([tokenName, token])
    return tokenName
  })

  return { protectedText, protectedTokens }
}

function restorePlaceholders(translatedText, protectedTokens) {
  let restoredText = translatedText
  for (const [tokenName, tokenValue] of protectedTokens) {
    const tokenPattern = new RegExp(tokenName.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'g')
    if (!tokenPattern.test(restoredText)) {
      throw new Error(`Translation lost protected token ${tokenName}`)
    }
    restoredText = restoredText.replace(tokenPattern, tokenValue)
  }
  return restoredText
}

async function requestTranslation(sourceText) {
  const requestUrl = new URL('https://translate.googleapis.com/translate_a/single')
  requestUrl.searchParams.set('client', 'gtx')
  requestUrl.searchParams.set('sl', 'zh-CN')
  requestUrl.searchParams.set('tl', 'en')
  requestUrl.searchParams.set('dt', 't')
  requestUrl.searchParams.set('q', sourceText)

  for (let attempt = 1; attempt <= 4; attempt += 1) {
    const response = await fetch(requestUrl, { headers: { 'User-Agent': 'Mozilla/5.0' } })
    if (response.ok) {
      const payload = await response.json()
      const translatedText = payload?.[0]?.map((sentence) => sentence?.[0] ?? '').join('')
      if (translatedText) return translatedText
    }

    if (attempt === 4) {
      throw new Error(`Google translation request failed with HTTP ${response.status}`)
    }
    await new Promise((resolve) => setTimeout(resolve, attempt * 1000))
  }
}

async function translateValue(key) {
  const { protectedText, protectedTokens } = protectPlaceholders(key)
  const translatedText = await requestTranslation(protectedText)
  const value = restorePlaceholders(translatedText, protectedTokens)

  if (cjkPattern.test(value)) {
    throw new Error(`Translation still contains CJK characters: ${key}`)
  }
  return value
}

async function mapWithConcurrency(items, limit, callback) {
  const results = new Array(items.length)
  let nextIndex = 0

  async function worker() {
    while (nextIndex < items.length) {
      const currentIndex = nextIndex
      nextIndex += 1
      results[currentIndex] = await callback(items[currentIndex], currentIndex)
    }
  }

  await Promise.all(Array.from({ length: Math.min(limit, items.length) }, worker))
  return results
}

async function main() {
  const source = fs.readFileSync(dictionaryPath, 'utf8')
  const dictionary = parseDictionary(source)
  const entries = Object.entries(dictionary)
  const untranslatedEntries = entries.filter(([, value]) => cjkPattern.test(value))

  console.log(`Translating ${untranslatedEntries.length} values out of ${entries.length} entries.`)

  const translations = await mapWithConcurrency(untranslatedEntries, 12, async ([key], index) => {
    const translation = await translateValue(key)
    console.log(`[${index + 1}/${untranslatedEntries.length}] ${key} -> ${translation}`)
    return [key, translation]
  })

  for (const [key, translation] of translations) {
    dictionary[key] = translation
  }

  const remainingCjkValues = Object.values(dictionary).filter((value) => cjkPattern.test(value))
  if (remainingCjkValues.length > 0) {
    throw new Error(`Refusing to write: ${remainingCjkValues.length} values still contain CJK characters.`)
  }

  const header = `/**\n * Admin UI dictionary: Chinese key → English value\n * API response texts are translated by backend sy_lang_items / app_lang.\n */\n`
  const body = Object.entries(dictionary)
    .map(([key, value]) => `  ${JSON.stringify(key)}: ${JSON.stringify(value)},`)
    .join('\n')
  const output = `${header}export default {\n${body}\n} as Record<string, string>\n`

  const temporaryPath = `${dictionaryPath}.tmp`
  fs.writeFileSync(temporaryPath, output, 'utf8')
  parseDictionary(fs.readFileSync(temporaryPath, 'utf8'))
  fs.renameSync(temporaryPath, dictionaryPath)

  console.log(`Wrote ${entries.length} entries with ${untranslatedEntries.length} translated values.`)
}

main().catch((error) => {
  console.error(error)
  process.exitCode = 1
})
