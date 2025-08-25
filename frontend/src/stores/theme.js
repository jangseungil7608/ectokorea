import { defineStore } from 'pinia'

export const useThemeStore = defineStore('theme', {
  state: () => ({
    isDark: false,
    theme: 'system' // 'light', 'dark', 'system'
  }),

  getters: {
    currentTheme: (state) => {
      if (state.theme === 'system') {
        return state.isDark ? 'dark' : 'light'
      }
      return state.theme
    },
    
    isSystemTheme: (state) => state.theme === 'system'
  },

  actions: {
    // 테마 초기화 (시스템 테마 감지)
    initTheme() {
      // localStorage에서 저장된 테마 설정 불러오기
      const savedTheme = localStorage.getItem('ectokorea-theme')
      
      if (savedTheme && ['light', 'dark', 'system'].includes(savedTheme)) {
        this.theme = savedTheme
      } else {
        this.theme = 'system' // 기본값: 시스템 테마
      }

      // 시스템 다크모드 설정 감지
      this.detectSystemTheme()
      
      // 시스템 테마 변경 감지 리스너 등록
      this.watchSystemTheme()
      
      // 테마 적용
      this.applyTheme()
    },

    // 시스템 테마 감지
    detectSystemTheme() {
      if (typeof window !== 'undefined' && window.matchMedia) {
        this.isDark = window.matchMedia('(prefers-color-scheme: dark)').matches
      }
    },

    // 시스템 테마 변경 감지
    watchSystemTheme() {
      if (typeof window !== 'undefined' && window.matchMedia) {
        const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)')
        
        const handler = (e) => {
          this.isDark = e.matches
          if (this.theme === 'system') {
            this.applyTheme()
          }
        }

        // 이벤트 리스너 등록
        if (mediaQuery.addEventListener) {
          mediaQuery.addEventListener('change', handler)
        } else {
          // 구형 브라우저 지원
          mediaQuery.addListener(handler)
        }
      }
    },

    // 테마 설정
    setTheme(newTheme) {
      if (!['light', 'dark', 'system'].includes(newTheme)) {
        console.warn('Invalid theme:', newTheme)
        return
      }

      this.theme = newTheme
      
      // localStorage에 저장
      localStorage.setItem('ectokorea-theme', newTheme)
      
      // 테마 적용
      this.applyTheme()
    },

    // 라이트/다크 토글 (시스템 테마가 아닐 때)
    toggleTheme() {
      if (this.theme === 'system') {
        // 시스템 테마일 때는 현재 시스템 테마의 반대로 설정
        this.setTheme(this.isDark ? 'light' : 'dark')
      } else {
        // 라이트/다크 토글
        this.setTheme(this.theme === 'light' ? 'dark' : 'light')
      }
    },

    // 테마를 DOM에 적용
    applyTheme() {
      if (typeof document === 'undefined') return

      const isDarkMode = this.theme === 'dark' || 
                        (this.theme === 'system' && this.isDark)

      // HTML 요소에 클래스 추가/제거
      const html = document.documentElement
      
      if (isDarkMode) {
        html.classList.add('dark')
        html.classList.remove('light')
      } else {
        html.classList.add('light')
        html.classList.remove('dark')
      }

      // 메타 태그로 테마 색상 설정 (모바일 브라우저용)
      this.updateMetaThemeColor(isDarkMode)
    },

    // 메타 테마 색상 업데이트
    updateMetaThemeColor(isDark) {
      if (typeof document === 'undefined') return

      let metaThemeColor = document.querySelector('meta[name="theme-color"]')
      
      if (!metaThemeColor) {
        metaThemeColor = document.createElement('meta')
        metaThemeColor.name = 'theme-color'
        document.getElementsByTagName('head')[0].appendChild(metaThemeColor)
      }

      // 테마에 따른 색상 설정
      metaThemeColor.content = isDark ? '#1f2937' : '#ffffff'
    },

    // 현재 테마가 다크모드인지 확인
    get isDarkMode() {
      return this.theme === 'dark' || (this.theme === 'system' && this.isDark)
    }
  }
})