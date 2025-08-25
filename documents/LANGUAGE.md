# 언어 전환 기능 계획서 (LANGUAGE.md)

## 📋 프로젝트 개요

**EctoKorea 다국어 지원 시스템** - 한국어와 일본어를 지원하는 완전한 국제화(i18n) 기능

## 🎯 구현 목표

### 핵심 기능
1. **2개 언어 지원**: 한국어(ko) ↔ 일본어(ja) 실시간 전환
2. **자동 언어 감지**: 브라우저 언어 설정 자동 인식
3. **설정 영구 저장**: localStorage를 통한 사용자 선택 유지
4. **실시간 언어 전환**: 페이지 새로고침 없이 즉시 적용
5. **완전한 UI 번역**: 모든 텍스트, 메시지, 에러까지 완벽 지원

### 사용자 경험 목표
- 직관적인 언어 선택 인터페이스 (플래그 + 언어명)
- 부드러운 전환 애니메이션
- 설정 영구 저장으로 재방문 시 유지
- 접근성 고려 (키보드 네비게이션, 스크린 리더)
- 모바일/데스크톱 반응형 디자인

## 🏗️ 아키텍처 설계

### 1. 다국어 프레임워크 (Vue I18n)
```
📁 Vue I18n 9 설정
├── 모드: Composition API 지원
├── 폴백 언어: 한국어(ko)
├── 전역 주입: $t 함수 사용 가능
└── 동적 로케일 변경 지원
```

### 2. 언어 관리 스토어 (Pinia)
```
📁 src/stores/language.js
├── state: { currentLanguage, availableLanguages }
├── getters: { getCurrentLanguageInfo }
└── actions: {
    ├── initLanguage() - 앱 시작 시 언어 초기화
    ├── setLanguage(code) - 언어 설정 변경  
    └── toggleLanguage() - 한/일 간편 토글
}
```

### 3. 번역 파일 구조
```
📁 src/locales/
├── ko.js - 한국어 번역
├── ja.js - 일본어 번역
└── index.js - i18n 설정
```

### 4. UI 컴포넌트
```
📁 src/components/LanguageToggle.vue
├── 모바일: 간단 플래그 토글 (🇰🇷/🇯🇵)
├── 데스크톱: 드롭다운 메뉴
│   ├── 🇰🇷 한국어
│   └── 🇯🇵 日本語
└── 외부 클릭 시 자동 닫기
```

## 🔧 기술 구현 세부사항

### 1. Vue I18n 설정
```javascript
// src/i18n.js
import { createI18n } from 'vue-i18n'
import ko from './locales/ko.js'
import ja from './locales/ja.js'

export const i18n = createI18n({
  legacy: false, // Composition API 사용
  locale: getDefaultLocale(),
  fallbackLocale: 'ko',
  messages: { ko, ja },
  globalInjection: true
})
```

### 2. 자동 언어 감지
```javascript
const getDefaultLocale = () => {
  // 1. localStorage에서 저장된 설정 확인
  const saved = localStorage.getItem('ectokorea-language')
  if (saved && ['ko', 'ja'].includes(saved)) return saved
  
  // 2. 브라우저 언어 감지
  const browserLang = navigator.language || navigator.userLanguage
  return browserLang.startsWith('ja') ? 'ja' : 'ko'
}
```

### 3. 동적 언어 변경
```javascript
// 언어 변경 이벤트 시스템
window.addEventListener('languageChanged', (event) => {
  setI18nLanguage(event.detail.language)
})

export const setI18nLanguage = (locale) => {
  i18n.global.locale.value = locale
  document.querySelector('html').setAttribute('lang', locale)
}
```

### 4. 컴포넌트에서 사용법
```vue
<!-- Template에서 -->
<template>
  <div>{{ $t('common.loading') }}</div>
  <button>{{ $t('products.registerProduct') }}</button>
</template>

<!-- Composition API에서 -->
<script setup>
import { useI18n } from 'vue-i18n'
const { t } = useI18n()

const message = t('products.fetchSuccess')
</script>
```

## 📝 번역 파일 구조

### 한국어 번역 (src/locales/ko.js)
```javascript
export default {
  // 공통
  common: {
    loading: '처리 중...',
    success: '성공',
    error: '오류',
    save: '저장',
    cancel: '취소'
  },
  
  // 헤더
  header: {
    title: '🇯🇵🇰🇷 일본→한국 EC 출품 도구',
    subtitle: '일본 상품을 한국 쿠팡에 효율적으로 출품하기 위한 도구입니다.'
  },
  
  // 인증
  auth: {
    login: '로그인',
    logout: '로그아웃',
    register: '회원가입'
  },
  
  // 이익 계산기
  calculator: {
    title: '일본 → 한국 이익 계산기',
    productInfo: '일본 상품 정보',
    results: '📊 계산 결과'
  },
  
  // 상품 관리
  products: {
    title: '상품 관리',
    addProduct: 'Amazon 상품 등록',
    registerSuccess: '상품이 성공적으로 등록되었습니다'
  }
}
```

### 일본어 번역 (src/locales/ja.js)
```javascript
export default {
  // 共通
  common: {
    loading: '処理中...',
    success: '成功',
    error: 'エラー',
    save: '保存',
    cancel: 'キャンセル'
  },
  
  // ヘッダー
  header: {
    title: '🇯🇵🇰🇷 日本→韓国 EC出品ツール',
    subtitle: '日本商品を韓国クパンに効率的に出品するためのツールです。'
  },
  
  // 認証
  auth: {
    login: 'ログイン',
    logout: 'ログアウト',
    register: '会員登録'
  },
  
  // 利益計算機
  calculator: {
    title: '日本 → 韓国 利益計算機',
    productInfo: '日本商品情報',
    results: '📊 計算結果'
  },
  
  // 商品管理
  products: {
    title: '商品管理',
    addProduct: 'Amazon商品登録',
    registerSuccess: '商品が正常に登録されました'
  }
}
```

## 🎨 UI/UX 디자인

### 언어 토글 컴포넌트
```vue
<!-- 모바일용 - 간단한 플래그 토글 -->
<div class="md:hidden">
  <button @click="toggleLanguage()">
    <span>{{ getCurrentLanguageInfo?.flag }}</span>
  </button>
</div>

<!-- 데스크톱용 - 드롭다운 메뉴 -->
<div class="hidden md:block relative">
  <button @click="toggleDropdown()">
    <span>{{ getCurrentLanguageInfo?.flag }}</span>
    <span>{{ getCurrentLanguageInfo?.name }}</span>
    <ChevronDownIcon />
  </button>
  
  <div v-show="isOpen" class="dropdown-menu">
    <button v-for="lang in availableLanguages" 
            @click="setLanguage(lang.code)">
      <span>{{ lang.flag }}</span>
      {{ lang.name }}
      <CheckIcon v-if="currentLanguage === lang.code" />
    </button>
  </div>
</div>
```

### 스타일링 (Tailwind CSS)
```css
.language-toggle {
  @apply flex items-center space-x-2 px-3 py-2 rounded-md;
  @apply hover:bg-gray-100 dark:hover:bg-gray-800;
  @apply transition-colors cursor-pointer;
}

.dropdown-menu {
  @apply absolute right-0 mt-2 w-40 bg-white dark:bg-gray-800;
  @apply rounded-md shadow-lg ring-1 ring-black ring-opacity-5;
  @apply z-50;
}
```

## 📱 반응형 설계

### 모바일 (< 768px)
- 간단한 플래그 토글 버튼
- 현재 언어의 플래그만 표시
- 클릭 시 즉시 언어 전환
- 터치 친화적인 크기 (44px+)

### 데스크톱 (≥ 768px)  
- 드롭다운 메뉴 형태
- 플래그 + 언어명 표시
- 현재 선택된 언어에 체크 마크
- 마우스 호버 효과
- 키보드 네비게이션 지원

## 🔄 사용자 시나리오

### 시나리오 1: 첫 방문 사용자 (일본어 브라우저)
1. 사용자가 일본어 브라우저로 접속
2. 시스템이 `navigator.language`에서 'ja' 감지
3. 자동으로 일본어로 UI 표시
4. localStorage에 'ja' 저장
5. 이후 방문 시 일본어 유지

### 시나리오 2: 수동 언어 변경
1. 한국어 사용자가 헤더의 언어 토글 클릭
2. 드롭다운에서 "🇯🇵 日本語" 선택  
3. 즉시 모든 텍스트가 일본어로 변경
4. localStorage에 'ja' 저장
5. 새 탭/재방문 시에도 일본어 유지

### 시나리오 3: 모바일에서 빠른 전환
1. 모바일 사용자가 플래그 버튼 터치
2. 즉시 언어가 토글됨 (한국어 ↔ 일본어)
3. 부드러운 애니메이션과 함께 전환
4. 설정 자동 저장

## ✅ 구현 완료 체크리스트

### 🛠️ 개발 환경 설정
- [x] **Vue I18n 9 설치** 및 설정
- [x] **Pinia 언어 스토어** 구현
- [x] **i18n 설정 파일** 작성
- [x] **main.js에 i18n 플러그인** 등록

### 🌐 번역 시스템
- [x] **한국어 번역 파일** (137개 키)
- [x] **일본어 번역 파일** (137개 키)
- [x] **템플릿 번역** ({0} 형식 지원)
- [x] **동적 메시지 번역** (computed 속성)

### 🎨 UI 컴포넌트
- [x] **LanguageToggle 컴포넌트** 완성
  - [x] 모바일용 간단 토글
  - [x] 데스크톱용 드롭다운
  - [x] 플래그 아이콘 표시
  - [x] 현재 언어 하이라이트
  - [x] 외부 클릭 시 닫기

### 📱 반응형 및 접근성
- [x] **모바일/데스크톱 최적화**
- [x] **키보드 네비게이션** 지원
- [x] **터치 친화적** 크기
- [x] **다크모드** 지원
- [x] **애니메이션** 효과

### 🔧 기능 구현
- [x] **자동 언어 감지** (브라우저 설정)
- [x] **localStorage 영구 저장**
- [x] **실시간 언어 전환**
- [x] **언어 변경 이벤트** 시스템
- [x] **HTML lang 속성** 자동 설정

### 📄 완전한 번역 커버리지
- [x] **헤더 및 네비게이션** (제목, 메뉴)
- [x] **인증 시스템** (로그인, 회원가입, 로그아웃)
- [x] **이익 계산기** (모든 필드, 라벨, 결과)
- [x] **상품 관리** (폼, 버튼, 메시지)
- [x] **계산 결과 팝업** (상세 항목들)
- [x] **에러 및 성공 메시지**
- [x] **카테고리 옵션**
- [x] **면세 관련 메시지**

### ✅ 테스트 완료
- [x] **한국어 → 일본어 전환** 테스트
- [x] **일본어 → 한국어 전환** 테스트
- [x] **페이지 새로고침 후 설정 유지**
- [x] **모바일 반응형** 테스트  
- [x] **드롭다운 상호작용** 테스트
- [x] **자동 언어 감지** 테스트

## 🚀 배포 및 모니터링

### 성능 고려사항
- 번역 파일 크기 최적화 (gzip 압축)
- 동적 import를 통한 번역 파일 지연 로딩 (향후)
- 메모리 효율적인 언어 전환
- DOM 업데이트 최소화

### SEO 최적화
- HTML `lang` 속성 자동 설정
- 메타 태그 언어별 설정
- 언어별 URL 구조 고려 (향후)

### 브라우저 호환성
- Vue I18n 9 요구사항 충족
- ES6+ 지원 브라우저
- 모던 브라우저 최적화

## 📈 향후 개선 계획

### Phase 2: 추가 언어 지원
- [ ] 영어(en) 번역 추가
- [ ] 중국어(zh) 번역 추가  
- [ ] 언어별 날짜/시간 포맷
- [ ] 언어별 통화 포맷

### Phase 3: 고급 기능
- [ ] 번역 관리 대시보드
- [ ] 동적 번역 로딩
- [ ] 번역 품질 검증
- [ ] A/B 테스트 지원
- [ ] 사용자 번역 기여 시스템

### Phase 4: 백엔드 통합
- [ ] 서버사이드 언어 설정 저장
- [ ] API 응답 메시지 다국어화
- [ ] 언어별 콘텐츠 관리
- [ ] 번역 통계 및 분석

---

## 📝 구현 완료 날짜
**2025년 8월 12일** - 다국어 지원 시스템 완전 구현 완료

### 최종 구현 결과
- ✅ **완벽한 2개 언어 지원** (한국어/일본어)
- ✅ **137개 번역 키** 완전 커버
- ✅ **실시간 언어 전환** 및 영구 저장
- ✅ **자동 브라우저 언어 감지**
- ✅ **모바일/데스크톱 반응형** UI
- ✅ **모든 컴포넌트 번역** 적용
- ✅ **동적 메시지 번역** 지원
- ✅ **접근성 및 사용성** 최적화

**개발자**: Claude Code  
**테스트 환경**: Chrome, 로컬 개발 서버 (http://192.168.1.13:5173/ectokorea)  
**프레임워크**: Vue 3 + Vue I18n 9 + Pinia + Tailwind CSS