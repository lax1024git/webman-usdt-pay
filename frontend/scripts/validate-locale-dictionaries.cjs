const fs = require('fs')
const path = require('path')

const dictionaryPaths = {
  english: path.resolve(__dirname, '../src/locales/dict.en.ts'),
  chinese: path.resolve(__dirname, '../src/locales/dict.zh-CN.ts'),
}
const cjkPattern = /[\u3400-\u9fff]/
const interpolationPattern = /\{[A-Za-z_][A-Za-z0-9_]*\}|&#10;/g

function parseDictionary(filePath) {
  const source = fs.readFileSync(filePath, 'utf8')
  const objectLiteral = source
    .replace(/^\/\*[\s\S]*?\*\/\s*export default\s*/, 'return ')
    .replace(/\s+as Record<string, string>\s*$/, '')

  return Function(objectLiteral)()
}

function getInterpolationTokens(value) {
  return [...value.matchAll(interpolationPattern)].map((match) => match[0])
}

function main() {
  const chineseDictionary = parseDictionary(dictionaryPaths.chinese)
  const englishDictionary = parseDictionary(dictionaryPaths.english)
  const chineseKeys = Object.keys(chineseDictionary)
  const englishKeys = Object.keys(englishDictionary)
  const englishKeySet = new Set(englishKeys)
  const chineseKeySet = new Set(chineseKeys)

  const missingEnglishKeys = chineseKeys.filter((key) => !englishKeySet.has(key))
  const extraEnglishKeys = englishKeys.filter((key) => !chineseKeySet.has(key))
  const cjkEnglishValues = englishKeys.filter((key) => cjkPattern.test(englishDictionary[key]))
  const placeholderMismatches = chineseKeys.filter((key) => {
    if (key.includes('class_name')) return false
    const sourceTokens = getInterpolationTokens(key)
    const translatedTokens = getInterpolationTokens(englishDictionary[key] ?? '')
    return sourceTokens.some((token) => !translatedTokens.includes(token))
  })

  const result = {
    chineseKeys: chineseKeys.length,
    englishKeys: englishKeys.length,
    missingEnglishKeys,
    extraEnglishKeys,
    cjkEnglishValues,
    placeholderMismatches,
  }

  console.log(JSON.stringify(result, null, 2))
  if (missingEnglishKeys.length || extraEnglishKeys.length || cjkEnglishValues.length || placeholderMismatches.length) {
    process.exitCode = 1
  }
}

main()
