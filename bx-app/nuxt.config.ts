import tailwindcss from '@tailwindcss/vite'

// https://nuxt.com/docs/api/configuration/nuxt-config
export default defineNuxtConfig({
  /**
   * @memo App work under frame
   * Nuxt DevTools: Failed to check parent window
   * SecurityError: Failed to read a named property '__NUXT_DEVTOOLS_DISABLE__' from 'Window'
   */
  app: {
    baseURL: '/telegram.tasks24/bx-app/dist/',
  },
  target: 'static', // Использует статическую генерацию
  generate: {
    fallback: '404.html' // Добавляет поддержку SPA fallback
  },

  devtools: { enabled: false },
  
  modules: [
    '@bitrix24/b24ui-nuxt',
    '@nuxt/eslint',
    '@bitrix24/b24jssdk-nuxt'
  ],

  css: ['~/assets/css/main.css'],
  
  vite: {
    plugins: [
      tailwindcss()
    ]
  },
  
  future: {
    compatibilityVersion: 4
  },

  compatibilityDate: '2024-11-27'
})