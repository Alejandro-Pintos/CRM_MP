import { fileURLToPath } from 'node:url'
import vue from '@vitejs/plugin-vue'
import vueJsx from '@vitejs/plugin-vue-jsx'
import AutoImport from 'unplugin-auto-import/vite'
import Components from 'unplugin-vue-components/vite'
import { VueRouterAutoImports, getPascalCaseRouteName } from 'unplugin-vue-router'
import VueRouter from 'unplugin-vue-router/vite'
import { defineConfig } from 'vite'
import Layouts from 'vite-plugin-vue-layouts'
import vuetify from 'vite-plugin-vuetify'
import svgLoader from 'vite-svg-loader'

export default defineConfig(async ({ mode }) => {
  const plugins = [
    // Debe ir antes que vue
    VueRouter({
      getRouteName: routeNode => {
        return getPascalCaseRouteName(routeNode)
          .replace(/([a-z0-9])([A-Z])/g, '$1-$2')
          .toLowerCase()
      },
    }),

    vue({
      template: {
        compilerOptions: {
          isCustomElement: tag => tag === 'swiper-container' || tag === 'swiper-slide',
        },
      },
    }),

    vueJsx(),

    vuetify({
      styles: { configFile: 'src/assets/styles/variables/_vuetify.scss' },
    }),

    Layouts({ layoutsDirs: './src/layouts/' }),

    Components({
      dirs: ['src/@core/components', 'src/views/demos', 'src/components'],
      dts: true,
      resolvers: [
        name => (name === 'VueApexCharts'
          ? { name: 'default', from: 'vue3-apexcharts', as: 'VueApexCharts' }
          : undefined),
      ],
    }),

    AutoImport({
      imports: ['vue', VueRouterAutoImports, '@vueuse/core', '@vueuse/math', 'vue-i18n', 'pinia'],
      dirs: [
        './src/@core/utils',
        './src/@core/composable/',
        './src/composables/',
        './src/utils/',
        './src/plugins/*/composables/*',
      ],
      vueTemplate: true,
      ignore: ['useCookies', 'useStorage'],
      eslintrc: { enabled: true, filepath: './.eslintrc-auto-import.json' },
    }),

    svgLoader(),
  ]

  // Cargar DevTools SOLO en desarrollo y SOLO si está disponible/compatible
  if (mode === 'development') {
    try {
      const { default: VueDevTools } = await import('vite-plugin-vue-devtools')
      plugins.push(VueDevTools())
    } catch {
      console.warn('vite-plugin-vue-devtools no disponible o incompatible; continúo sin él.')
    }
  }

  return {
    plugins,
    define: { 'process.env': {} },
    resolve: {
      alias: {
        '@': fileURLToPath(new URL('./src', import.meta.url)),
        '@themeConfig': fileURLToPath(new URL('./themeConfig.js', import.meta.url)),
        '@core': fileURLToPath(new URL('./src/@core', import.meta.url)),
        '@layouts': fileURLToPath(new URL('./src/@layouts', import.meta.url)),
        '@images': fileURLToPath(new URL('./src/assets/images/', import.meta.url)),
        '@styles': fileURLToPath(new URL('./src/assets/styles/', import.meta.url)),
        '@configured-variables': fileURLToPath(new URL('./src/assets/styles/variables/_template.scss', import.meta.url)),
        '@db': fileURLToPath(new URL('./src/plugins/fake-api/handlers/', import.meta.url)),
        '@api-utils': fileURLToPath(new URL('./src/plugins/fake-api/utils/', import.meta.url)),
      },
    },
    build: { chunkSizeWarningLimit: 5000 },
    optimizeDeps: { exclude: ['vuetify'], entries: ['./src/**/*.vue'] },
  }
})
