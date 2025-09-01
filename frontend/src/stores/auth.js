import { defineStore } from 'pinia'
import { authAPI, tokenManager } from '@/utils/api'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: JSON.parse(localStorage.getItem('user_data') || 'null'),
    token: tokenManager.getToken(),
    isAuthenticated: !!tokenManager.getToken() && !tokenManager.isTokenExpired(tokenManager.getToken()),
    loading: false
  }),

  getters: {
    isLoggedIn: (state) => state.isAuthenticated && state.token !== null,
    currentUser: (state) => state.user
  },

  actions: {
    // 로그인
    async login(credentials) {
      this.loading = true
      
      try {
        const response = await authAPI.login(credentials.email, credentials.password)
        
        if (response.success) {
          this.token = response.access_token
          this.user = response.user
          this.isAuthenticated = true
          
          // tokenManager를 통해 토큰과 사용자 데이터 저장
          tokenManager.setToken(this.token)
          localStorage.setItem('user_data', JSON.stringify(this.user))
          
          // 자동 토큰 갱신 예약
          this.scheduleTokenRefresh()
          
          return { success: true, user: this.user }
        } else {
          return { success: false, message: response.message || '로그인에 실패했습니다.' }
        }
      } catch (error) {
        console.error('로그인 실패:', error)
        
        let message = '로그인에 실패했습니다.'
        if (error.response?.data?.message) {
          message = error.response.data.message
        } else if (error.response?.status === 401) {
          message = '이메일 또는 비밀번호가 올바르지 않습니다.'
        }
        
        return { success: false, message }
      } finally {
        this.loading = false
      }
    },

    // 로그아웃
    async logout() {
      try {
        if (this.token) {
          await authAPI.logout()
        }
      } catch (error) {
        console.error('로그아웃 요청 실패:', error)
      } finally {
        // 로컬 상태 초기화
        this.token = null
        this.user = null
        this.isAuthenticated = false
        
        // tokenManager를 통해 토큰과 사용자 데이터 제거
        tokenManager.removeToken()
      }
    },

    // 토큰 갱신
    async refreshToken() {
      if (!this.token) return false
      
      try {
        const response = await authAPI.refresh()
        
        if (response.success) {
          this.token = response.access_token
          this.user = response.user
          
          // tokenManager를 통해 토큰과 사용자 데이터 저장
          tokenManager.setToken(this.token)
          localStorage.setItem('user_data', JSON.stringify(this.user))
          
          // 자동 토큰 갱신 예약
          this.scheduleTokenRefresh()
          
          return true
        }
      } catch (error) {
        console.error('토큰 갱신 실패:', error)
        this.logout() // 갱신 실패 시 로그아웃
      }
      
      return false
    },

    // 사용자 정보 조회
    async fetchUser() {
      if (!this.token) return false
      
      try {
        const response = await authAPI.me()
        
        if (response.success) {
          this.user = response.user
          this.isAuthenticated = true
          localStorage.setItem('user_data', JSON.stringify(this.user))
          return true
        }
      } catch (error) {
        console.error('사용자 정보 조회 실패:', error)
        
        if (error.response?.status === 401) {
          // 토큰이 만료되었을 가능성, 갱신 시도
          const refreshed = await this.refreshToken()
          if (refreshed) {
            // 토큰 갱신 성공 시 다시 사용자 정보 조회
            try {
              const retryResponse = await authAPI.me()
              if (retryResponse.success) {
                this.user = retryResponse.user
                this.isAuthenticated = true
                localStorage.setItem('user_data', JSON.stringify(this.user))
                return true
              }
            } catch (retryError) {
              console.error('재시도 후에도 사용자 정보 조회 실패:', retryError)
              this.logout()
            }
          } else {
            this.logout()
          }
        }
      }
      
      return false
    },

    // 회원가입
    async register(userData) {
      this.loading = true
      
      try {
        const response = await authAPI.register(
          userData.name, 
          userData.email, 
          userData.password, 
          userData.password_confirmation
        )
        
        if (response.success) {
          return { success: true, message: response.message, user: response.user }
        } else {
          return { success: false, message: response.message || '회원가입에 실패했습니다.' }
        }
      } catch (error) {
        console.error('회원가입 실패:', error)
        
        let message = '회원가입에 실패했습니다.'
        if (error.response?.data?.errors) {
          // 유효성 검사 에러
          const errors = Object.values(error.response.data.errors).flat()
          message = errors.join(', ')
        } else if (error.response?.data?.message) {
          message = error.response.data.message
        }
        
        return { success: false, message }
      } finally {
        this.loading = false
      }
    },

    // 회원가입 후 자동 로그인
    async registerAndLogin(userData) {
      const registerResult = await this.register(userData)
      
      if (registerResult.success) {
        // 회원가입 성공 후 바로 로그인 시도
        const loginResult = await this.login({
          email: userData.email,
          password: userData.password
        })
        
        return {
          success: true,
          message: registerResult.message,
          user: loginResult.success ? loginResult.user : registerResult.user,
          autoLogin: loginResult.success
        }
      }
      
      return registerResult
    },

    // 앱 초기화 시 토큰 복원
    async initializeAuth() {
      const token = tokenManager.getToken()
      
      if (token && !tokenManager.isTokenExpired(token)) {
        this.token = token
        
        console.log('저장된 토큰으로 인증 상태 복원 시도...')
        
        // 저장된 토큰으로 사용자 정보 복원 시도
        const success = await this.fetchUser()
        
        if (success) {
          console.log('인증 상태 복원 성공:', this.user.name)
          // 자동 토큰 갱신 예약
          this.scheduleTokenRefresh()
        } else {
          console.log('인증 상태 복원 실패, 토큰 제거')
          // 복원 실패 시 상태 초기화
          this.clearAuth()
        }
      } else if (token && tokenManager.isTokenExpired(token)) {
        console.log('토큰이 만료됨, 제거')
        this.clearAuth()
      }
    },

    // 인증 상태 완전 초기화
    clearAuth() {
      this.token = null
      this.user = null
      this.isAuthenticated = false
      tokenManager.removeToken()
    },

    // 자동 토큰 갱신 (만료 30분 전)
    scheduleTokenRefresh() {
      if (!this.token) return
      
      // JWT TTL이 10080분(7일)이므로 6일 30분 후에 갱신
      const refreshTime = (10080 - 30) * 60 * 1000 // milliseconds
      
      setTimeout(async () => {
        console.log('자동 토큰 갱신 시도...')
        const refreshed = await this.refreshToken()
        if (refreshed) {
          console.log('자동 토큰 갱신 성공')
          this.scheduleTokenRefresh() // 다음 갱신 예약
        }
      }, refreshTime)
    }
  }
})