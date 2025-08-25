import { createI18n } from 'vue-i18n'
import ko from './locales/ko.js'
import ja from './locales/ja.js'

const messages = {
  ko,
  ja
}

// 기본 로케일 설정 (localStorage 또는 브라우저 언어 감지)
const getDefaultLocale = () => {
  const saved = localStorage.getItem('ectokorea-language')
  if (saved && ['ko', 'ja'].includes(saved)) {
    return saved
  }
  
  const browserLang = navigator.language || navigator.userLanguage
  if (browserLang.startsWith('ja')) {
    return 'ja'
  }
  
  return 'ko' // 기본값
}

export const i18n = createI18n({
  legacy: false, // Vue 3 Composition API 사용
  locale: getDefaultLocale(),
  fallbackLocale: 'ko',
  messages,
  globalInjection: true // $t를 전역으로 사용 가능하게 설정
})

// 언어 변경 함수
export const setI18nLanguage = (locale) => {
  if (i18n.mode === 'legacy') {
    i18n.global.locale = locale
  } else {
    i18n.global.locale.value = locale
  }
  document.querySelector('html').setAttribute('lang', locale)
}

// 언어 변경 이벤트 리스너
if (typeof window !== 'undefined') {
  window.addEventListener('languageChanged', (event) => {
    setI18nLanguage(event.detail.language)
  })
}