# 테마 전환 기능 계획서 (THEMA.md)

## 📋 프로젝트 개요

**EctoKorea 테마 전환 시스템** - 사용자가 라이트 모드, 다크 모드, 시스템 설정 모드 중 선택할 수 있는 테마 전환 기능

## 🎯 구현 목표

### 핵심 기능
1. **3가지 테마 모드**: 라이트, 다크, 시스템 자동 감지
2. **사용자 설정 저장**: localStorage를 통한 영구 저장
3. **반응형 UI**: 모바일/데스크톱 최적화된 테마 토글
4. **실시간 전환**: 페이지 새로고침 없이 즉시 테마 변경
5. **시스템 연동**: OS 다크모드 설정 자동 감지 및 추적

### 사용자 경험 목표
- 직관적인 테마 선택 인터페이스
- 부드러운 전환 애니메이션
- 설정 영구 저장으로 재방문 시 유지
- 접근성 고려 (색상 대비, 키보드 네비게이션)

## 🏗️ 아키텍처 설계

### 1. 상태 관리 (Pinia Store)
```
📁 src/stores/theme.js
├── state: { isDark, theme }
├── getters: { currentTheme, isSystemTheme, isDarkMode }
└── actions: {
    ├── initTheme() - 앱 시작 시 테마 초기화
    ├── setTheme(newTheme) - 테마 설정 변경
    ├── toggleTheme() - 간단한 토글
    ├── detectSystemTheme() - 시스템 다크모드 감지
    ├── watchSystemTheme() - 시스템 변경 추적
    └── applyTheme() - DOM에 테마 클래스 적용
}
```

### 2. UI 컴포넌트
```
📁 src/components/ThemeToggle.vue
├── 모바일: 심플 토글 버튼 (태양/달 아이콘)
├── 데스크톱: 드롭다운 메뉴
│   ├── 라이트 모드 (☀️ 라이트 모드)
│   ├── 다크 모드 (🌙 다크 모드)  
│   └── 시스템 설정 (💻 시스템 설정 - 현재상태)
└── 애니메이션: Vue transition으로 부드러운 전환
```

### 3. CSS 프레임워크 통합
```
📁 tailwind.config.js
├── darkMode: 'class' - 클래스 기반 다크모드
└── theme.extend.colors - 커스텀 다크모드 색상 팔레트
```

## 🔧 기술 구현 세부사항

### 1. 테마 감지 및 적용
```javascript
// 시스템 테마 감지
const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)')
this.isDark = mediaQuery.matches

// DOM 클래스 적용
const html = document.documentElement
isDarkMode ? html.classList.add('dark') : html.classList.remove('dark')
```

### 2. 영구 저장
```javascript
// 설정 저장
localStorage.setItem('ectokorea-theme', theme)

// 설정 복원
const savedTheme = localStorage.getItem('ectokorea-theme')
```

### 3. 실시간 시스템 테마 추적
```javascript
mediaQuery.addEventListener('change', (e) => {
  this.isDark = e.matches
  if (this.theme === 'system') this.applyTheme()
})
```

## 🎨 디자인 시스템

### 테마 색상 팔레트
```css
/* 라이트 모드 */
--bg-primary: #ffffff
--bg-secondary: #f9fafb
--text-primary: #1f2937
--text-secondary: #6b7280

/* 다크 모드 */
--bg-primary: #1f2937
--bg-secondary: #111827
--text-primary: #f9fafb
--text-secondary: #d1d5db
```

### 컴포넌트별 다크모드 스타일
- **헤더**: `bg-white dark:bg-gray-800`
- **네비게이션**: `border-gray-200 dark:border-gray-700`
- **카드**: `bg-white dark:bg-gray-800`
- **텍스트**: `text-gray-900 dark:text-white`
- **버튼**: `hover:bg-gray-100 dark:hover:bg-gray-700`

## 📱 반응형 설계

### 모바일 (< 768px)
- 간단한 토글 버튼
- 현재 테마에 따른 아이콘 표시
- 터치 친화적인 크기 (44px+)

### 데스크톱 (≥ 768px)
- 드롭다운 메뉴
- 현재 테마명 텍스트 표시
- 시스템 모드일 때 현재 상태 표시
- 마우스 호버 효과

## 🔄 사용자 시나리오

### 시나리오 1: 첫 방문 사용자
1. 앱 로드 → 시스템 테마 자동 감지
2. OS가 다크모드면 다크 테마 적용
3. 설정이 localStorage에 'system'으로 저장

### 시나리오 2: 수동 테마 변경
1. 헤더의 테마 토글 클릭
2. 드롭다운에서 "다크 모드" 선택
3. 즉시 다크 테마 적용
4. localStorage에 'dark' 저장
5. 재방문 시 다크 테마 유지

### 시나리오 3: 시스템 테마 변경 추적
1. 시스템 설정 모드 선택 상태
2. OS에서 라이트→다크 모드 변경
3. 앱이 자동으로 다크 테마 적용
4. 테마 토글에 "(다크)" 상태 표시

## ✅ 구현 완료 체크리스트

### 백엔드
- [ ] 테마 설정 API (선택사항 - 현재는 프론트엔드만)
- [ ] 사용자별 테마 설정 저장 (향후 기능)

### 프론트엔드
- [x] **Pinia 테마 스토어 구현**
  - [x] 상태 관리 (isDark, theme)
  - [x] 테마 감지 및 적용 액션
  - [x] localStorage 저장/복원

- [x] **ThemeToggle 컴포넌트**
  - [x] 모바일용 토글 버튼
  - [x] 데스크톱용 드롭다운 메뉴
  - [x] 아이콘 및 텍스트 라벨
  - [x] 현재 선택 표시

- [x] **CSS 및 스타일링**
  - [x] Tailwind darkMode 설정
  - [x] 모든 컴포넌트에 dark: 클래스 적용
  - [x] 커스텀 다크 색상 팔레트

- [x] **앱 통합**
  - [x] App.vue에서 테마 초기화
  - [x] 헤더에 테마 토글 배치
  - [x] 시스템 테마 변경 리스너

### 테스트
- [x] **기능 테스트**
  - [x] 라이트/다크 모드 전환
  - [x] 시스템 테마 자동 감지
  - [x] 설정 영구 저장
  - [x] 페이지 새로고침 후 테마 유지

- [x] **UI/UX 테스트**
  - [x] 모바일/데스크톱 반응형
  - [x] 드롭다운 외부 클릭 닫기
  - [x] 애니메이션 부드러움
  - [x] 아이콘 및 텍스트 가독성

## 🚀 배포 및 모니터링

### 성능 고려사항
- 테마 전환 시 리플로우 최소화
- CSS 트랜지션으로 부드러운 전환
- 시스템 테마 리스너의 메모리 누수 방지

### 브라우저 호환성
- `matchMedia` API 지원 확인
- 구형 브라우저 대체 방법 제공
- `addEventListener` vs `addListener` 호환

### 접근성 (Accessibility)
- 색상 대비 WCAG 가이드라인 준수
- 키보드 네비게이션 지원
- 스크린 리더 친화적 라벨

## 📈 향후 개선 계획

### Phase 2 기능
- [ ] 테마별 커스텀 색상 선택
- [ ] 애니메이션 효과 옵션
- [ ] 고대비 모드 지원
- [ ] 테마 프리셋 기능

### Phase 3 기능  
- [ ] 사용자별 테마 설정 서버 저장
- [ ] 테마 변경 통계 수집
- [ ] A/B 테스트를 통한 기본 테마 최적화

---

## 📝 구현 완료 날짜
**2025년 8월 12일** - 테마 전환 기능 완전 구현 완료

### 최종 구현 결과
- ✅ 3가지 테마 모드 (라이트/다크/시스템) 완벽 동작
- ✅ localStorage 영구 저장 및 복원
- ✅ 실시간 시스템 테마 변경 추적
- ✅ 반응형 UI (모바일 토글 + 데스크톱 드롭다운)
- ✅ 모든 컴포넌트 다크모드 스타일 적용
- ✅ 부드러운 전환 애니메이션
- ✅ 브라우저 호환성 및 접근성 고려

**개발자**: Claude Code  
**테스트 환경**: Chrome, 로컬 개발 서버 (http://192.168.1.13:5173/ectokorea)