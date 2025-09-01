import axios from 'axios'

// API 기본 URL 설정 (로컬/외부 자동 감지)
const isLocalhost = window.location.hostname === 'localhost' || 
                   window.location.hostname === '127.0.0.1' ||
                   window.location.hostname === '192.168.1.13'

const API_BASE_URL = isLocalhost 
  ? 'http://192.168.1.13:8080/ectokorea'
  : 'https://devseungil.mydns.jp/ectokorea'

// Axios 인스턴스 생성
const api = axios.create({
  baseURL: API_BASE_URL,
  timeout: 30000,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json'
  }
})

// 토큰 관리 함수들
export const tokenManager = {
  getToken() {
    return localStorage.getItem('auth_token')
  },
  
  setToken(token) {
    localStorage.setItem('auth_token', token)
  },
  
  removeToken() {
    localStorage.removeItem('auth_token')
    localStorage.removeItem('user_data')
  },
  
  isTokenExpired(token) {
    if (!token) return true
    
    try {
      const payload = JSON.parse(atob(token.split('.')[1]))
      return payload.exp * 1000 < Date.now()
    } catch (error) {
      return true
    }
  }
}

// 요청 인터셉터: JWT 토큰을 헤더에 자동 추가
api.interceptors.request.use(
  (config) => {
    const token = tokenManager.getToken()
    if (token && !tokenManager.isTokenExpired(token)) {
      config.headers.Authorization = `Bearer ${token}`
    }
    return config
  },
  (error) => {
    return Promise.reject(error)
  }
)

// 응답 인터셉터: 인증 오류 처리 및 토큰 갱신
api.interceptors.response.use(
  (response) => {
    return response
  },
  async (error) => {
    const originalRequest = error.config

    // 401 에러 (Unauthorized) 처리
    if (error.response?.status === 401 && !originalRequest._retry) {
      originalRequest._retry = true

      const token = tokenManager.getToken()
      
      // 토큰이 있고 만료되지 않았다면 토큰 갱신 시도
      if (token && !tokenManager.isTokenExpired(token)) {
        try {
          const refreshResponse = await axios.post(`${API_BASE_URL}/auth/refresh`, {}, {
            headers: { Authorization: `Bearer ${token}` }
          })
          
          const newToken = refreshResponse.data.access_token
          tokenManager.setToken(newToken)
          
          // 원래 요청 재시도
          originalRequest.headers.Authorization = `Bearer ${newToken}`
          return api(originalRequest)
        } catch (refreshError) {
          // 토큰 갱신 실패 시 로그아웃 처리
          tokenManager.removeToken()
          window.dispatchEvent(new CustomEvent('auth:logout'))
          return Promise.reject(refreshError)
        }
      } else {
        // 토큰이 없거나 만료된 경우 로그아웃 처리
        tokenManager.removeToken()
        window.dispatchEvent(new CustomEvent('auth:logout'))
      }
    }

    return Promise.reject(error)
  }
)

// 인증 관련 API 함수들
export const authAPI = {
  async login(email, password) {
    const response = await api.post('/auth/login', { email, password })
    return response.data
  },

  async register(name, email, password, password_confirmation) {
    const response = await api.post('/auth/register', { 
      name, 
      email, 
      password, 
      password_confirmation 
    })
    return response.data
  },

  async logout() {
    try {
      await api.post('/auth/logout')
    } finally {
      tokenManager.removeToken()
    }
  },

  async me() {
    const response = await api.get('/auth/me')
    return response.data
  },

  async refresh() {
    const response = await api.post('/auth/refresh')
    return response.data
  }
}

export default api