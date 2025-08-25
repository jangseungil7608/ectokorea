import { createApp } from 'vue'
import { createPinia } from 'pinia'
import axios from 'axios'
import './style.css'
import App from './App.vue'
import { i18n } from './i18n.js'

// 로컬/외부 접속 자동 감지를 위한 기본 URL 설정
const isLocalNetwork = window.location.hostname === '192.168.1.13' || window.location.hostname === 'localhost'
const baseURL = isLocalNetwork ? 'http://192.168.1.13:8080/ectokorea' : 'https://devseungil.mydns.jp/ectokorea'

// Axios 기본 설정
axios.defaults.baseURL = baseURL
axios.defaults.headers.common['Accept'] = 'application/json'
axios.defaults.headers.common['Content-Type'] = 'application/json'
axios.defaults.withCredentials = true // CORS credentials 설정

// Axios 인터셉터 설정 (auth store에서 관리하므로 단순화)
axios.interceptors.response.use(
  (response) => {
    return response
  },
  async (error) => {
    // 401 에러 시 auth store의 토큰 갱신 로직에 맡김
    return Promise.reject(error)
  }
)

const app = createApp(App)
const pinia = createPinia()

app.use(pinia)
app.use(i18n)
app.config.globalProperties.$http = axios
app.mount('#app')
