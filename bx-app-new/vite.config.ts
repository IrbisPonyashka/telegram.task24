import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import bitrix24UIPluginVite from '@bitrix24/b24ui-nuxt/vite'

export default defineConfig({
  base: '/telegram.tasks24/bx-app-new/dist/',
  plugins: [
    vue(),
    bitrix24UIPluginVite ({
      colorMode: false
    })
  ]
})
