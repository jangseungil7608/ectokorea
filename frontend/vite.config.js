import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import path from 'path'

export default defineConfig({
  plugins: [vue()],
  base: '/ectokorea',
  resolve: {
    alias: {
      '@': path.resolve(__dirname, 'src')
    }
  },
  server: {
    host: '0.0.0.0',
    port: 5173,
    allowedHosts: ['devseungil.synology.me'], // 허용할 외부 도메인
    strictPort: true,  // 포트가 사용 중일 때 다른 포트를 찾지 않음
    proxy: {
      '/api': 'http://backend:80/ectokorea',
    },
  },
})