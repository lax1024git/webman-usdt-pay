import { resolve } from 'path'
import { loadEnv } from 'vite'
import type { UserConfig, ConfigEnv, PluginOption } from 'vite'
import Vue from '@vitejs/plugin-vue'
import VueJsx from '@vitejs/plugin-vue-jsx'
import progress from 'vite-plugin-progress'
import EslintPlugin from 'vite-plugin-eslint'
import { ViteEjsPlugin } from 'vite-plugin-ejs'
import PurgeIcons from 'vite-plugin-purge-icons'
import ServerUrlCopy from 'vite-plugin-url-copy'
import VueI18nPlugin from '@intlify/unplugin-vue-i18n/vite'
import { createSvgIconsPlugin } from 'vite-plugin-svg-icons'
import { createStyleImportPlugin, ElementPlusResolve } from 'vite-plugin-style-import'
import UnoCSS from 'unocss/vite'
import { visualizer } from 'rollup-plugin-visualizer'

const root = process.cwd()

function pathResolve(dir: string) {
  return resolve(root, '.', dir)
}

export default ({ command, mode }: ConfigEnv): UserConfig => {
  const env = loadEnv(mode, root)
  const isBuild = command === 'build'
  // 生产构建默认开启语法检查；开发默认关闭（.env.development 可改 VITE_ESLINT）
  const enableEslint = isBuild
    ? env.VITE_ESLINT !== 'false'
    : env.VITE_ESLINT === 'true'

  if (isBuild && enableEslint) {
    // 构建只做语法/规范检查，不跑 prettier 格式规则
    process.env.ESLINT_SYNTAX_ONLY = '1'
  }

  const plugins: PluginOption[] = [
    Vue({
      script: {
        defineModel: true
      }
    }),
    VueJsx(),
    // 仅开发复制本地 URL
    !isBuild ? ServerUrlCopy() : undefined,
    progress(),
    env.VITE_USE_ALL_ELEMENT_PLUS_STYLE === 'false'
      ? createStyleImportPlugin({
          resolves: [ElementPlusResolve()],
          libs: [
            {
              libraryName: 'element-plus',
              esModule: true,
              resolveStyle: (name) => {
                if (name === 'click-outside') {
                  return ''
                }
                return `element-plus/es/components/${name.replace(/^el-/, '')}/style/css`
              }
            }
          ]
        })
      : undefined,
    enableEslint
      ? EslintPlugin({
          cache: true,
          failOnWarning: false,
          // 真正阻断构建的是前面的 vue-tsc；ESLint 负责输出问题，不因存量告警卡死打包
          failOnError: false,
          include: ['src/**/*.vue', 'src/**/*.ts', 'src/**/*.tsx']
        })
      : undefined,
    VueI18nPlugin({
      runtimeOnly: true,
      compositionOnly: true,
      include: [resolve(__dirname, 'src/locales/**/*.{json,json5,yaml,yml}')]
    }),
    createSvgIconsPlugin({
      iconDirs: [pathResolve('src/assets/svgs')],
      symbolId: 'icon-[dir]-[name]',
      // 构建时再做 SVGO，开发启动更快
      svgoOptions: isBuild
    }),
    PurgeIcons(),
    ViteEjsPlugin({
      title: env.VITE_APP_TITLE
    }),
    UnoCSS(),
    isBuild && env.VITE_USE_BUNDLE_ANALYZER === 'true'
      ? (visualizer({ open: false, gzipSize: true }) as PluginOption)
      : undefined
  ]

  return {
    base: env.VITE_BASE_PATH,
    plugins,
    css: {
      preprocessorOptions: {
        less: {
          additionalData: '@import "./src/styles/variables.module.less";',
          javascriptEnabled: true
        }
      }
    },
    resolve: {
      extensions: ['.mjs', '.js', '.ts', '.jsx', '.tsx', '.json', '.less', '.css'],
      alias: [
        {
          find: 'vue-i18n',
          replacement: 'vue-i18n/dist/vue-i18n.cjs.js'
        },
        {
          find: /\@\//,
          replacement: `${pathResolve('src')}/`
        }
      ]
    },
    esbuild: {
      pure: env.VITE_DROP_CONSOLE === 'true' ? ['console.log'] : undefined,
      drop: env.VITE_DROP_DEBUGGER === 'true' ? ['debugger'] : undefined,
      legalComments: 'none'
    },
    build: {
      // 后台管理无需兼容极老浏览器，降低转译成本
      target: ['es2020', 'chrome87', 'safari14', 'firefox78'],
      cssTarget: ['chrome87'],
      outDir: env.VITE_OUT_DIR || 'dist',
      sourcemap: env.VITE_SOURCEMAP === 'true',
      minify: 'esbuild',
      reportCompressedSize: false,
      chunkSizeWarningLimit: 1500,
      rollupOptions: {
        output: {
          manualChunks: {
            'vue-chunks': ['vue', 'vue-router', 'pinia', 'vue-i18n'],
            'element-plus': ['element-plus'],
            'wang-editor': ['@wangeditor/editor', '@wangeditor/editor-for-vue']
          }
        }
      },
      cssCodeSplit: !(env.VITE_USE_CSS_SPLIT === 'false')
    },
    server: {
      port: 4000,
      proxy: {
        '/api': {
          target: 'http://127.0.0.1:8000',
          changeOrigin: true,
          rewrite: (path) => path.replace(/^\/api/, '')
        }
      },
      hmr: {
        overlay: false
      },
      host: '0.0.0.0'
    },
    optimizeDeps: {
      entries: [pathResolve('index.html')],
      include: [
        'vue',
        'vue-router',
        'vue-types',
        'element-plus/es/locale/lang/zh-cn',
        'element-plus/es/locale/lang/en',
        '@iconify/iconify',
        '@vueuse/core',
        'axios',
        'qs',
        '@wangeditor/editor',
        '@wangeditor/editor-for-vue',
        '@zxcvbn-ts/core',
        'dayjs',
        'cropperjs'
      ]
    }
  }
}
