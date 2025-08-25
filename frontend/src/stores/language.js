import { defineStore } from 'pinia'

export const useLanguageStore = defineStore('language', {
  state: () => ({
    currentLanguage: 'ko', // 기본값: 한국어
    availableLanguages: [
      { code: 'ko', name: '한국어', flag: '🇰🇷' },
      { code: 'ja', name: '日本語', flag: '🇯🇵' }
    ]
  }),

  getters: {
    getCurrentLanguageInfo: (state) => {
      return state.availableLanguages.find(lang => lang.code === state.currentLanguage)
    }
  },

  actions: {
    // 언어 초기화
    initLanguage() {
      // localStorage에서 저장된 언어 설정 불러오기
      const savedLanguage = localStorage.getItem('ectokorea-language')
      
      if (savedLanguage && ['ko', 'ja'].includes(savedLanguage)) {
        this.currentLanguage = savedLanguage
      } else {
        // 브라우저 언어 감지
        const browserLanguage = navigator.language || navigator.userLanguage
        if (browserLanguage.startsWith('ja')) {
          this.currentLanguage = 'ja'
        } else {
          this.currentLanguage = 'ko' // 기본값
        }
      }

      // localStorage에 저장
      localStorage.setItem('ectokorea-language', this.currentLanguage)
    },

    // 언어 설정
    setLanguage(languageCode) {
      if (!['ko', 'ja'].includes(languageCode)) {
        console.warn('Invalid language code:', languageCode)
        return
      }

      this.currentLanguage = languageCode
      
      // localStorage에 저장
      localStorage.setItem('ectokorea-language', languageCode)
      
      // 페이지 새로고침 없이 언어 적용을 위해 이벤트 발생
      window.dispatchEvent(new CustomEvent('languageChanged', { 
        detail: { language: languageCode } 
      }))
    },

    // 언어 토글 (한국어 ↔ 일본어)
    toggleLanguage() {
      const newLanguage = this.currentLanguage === 'ko' ? 'ja' : 'ko'
      this.setLanguage(newLanguage)
    }
  }
})