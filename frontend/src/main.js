import { createApp } from 'vue'
import { createPinia } from 'pinia'
import './style.css'
import App from './App.vue'
import { i18n } from './i18n.js'
import api from '@/utils/api'

const app = createApp(App)
const pinia = createPinia()

app.use(pinia)
app.use(i18n)

// 중앙화된 API 인스턴스를 전역 프로퍼티로 등록
app.config.globalProperties.$api = api

app.mount('#app')
