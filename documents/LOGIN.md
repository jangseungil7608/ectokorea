# 🔐 로그인 기능 구현 계획서

## 📋 요구사항 분석

### 기본 요구사항 (CLAUDE.md 기반)
- **인증 방식**: JWT Token 기반 인증
- **로그인 유지**: 7일간 자동 로그인
- **패스워드 관리**: 초기에는 관리자 직접 리셋 → 향후 이메일 재설정
- **접근 제어**: 로그인 필수 페이지 설정

### 접속 환경
- **로컬**: `http://192.168.1.13:5173/ectokorea`
- **외부**: `https://devseungil.synology.me/ectokorea`
- **백엔드 API (로컬)**: `http://192.168.1.13:8080/ectokorea/*`
- **백엔드 API (외부)**: `https://devseungil.mydns.jp/ectokorea/*`

## 🏗️ 시스템 아키텍처

### 1. 백엔드 (Laravel) 구조

#### A. JWT 패키지 및 설정
```bash
# JWT 패키지 설치
composer require tymon/jwt-auth

# 설정 파일 발행
php artisan vendor:publish --provider="Tymon\JWTAuth\Providers\LaravelServiceProvider"

# JWT 시크릿 키 생성
php artisan jwt:secret
```

#### B. 인증 API 엔드포인트
| 메서드 | 엔드포인트 | 설명 | 보호 수준 |
|--------|------------|------|-----------|
| `POST` | `/ectokorea/auth/login` | 로그인 | Public |
| `POST` | `/ectokorea/auth/logout` | 로그아웃 | Protected |
| `POST` | `/ectokorea/auth/refresh` | 토큰 갱신 | Protected |
| `GET` | `/ectokorea/auth/me` | 사용자 정보 조회 | Protected |

#### C. 데이터베이스 구조
```sql
-- users 테이블 (기존)
CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    email_verified_at TIMESTAMP NULL,
    password VARCHAR(255) NOT NULL,
    remember_token VARCHAR(100) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);
```

#### D. 미들웨어 적용 계획
```php
// 보호된 라우트 그룹
Route::middleware(['auth:api'])->group(function () {
    Route::get('/products', [ProductController::class, 'index']);
    Route::post('/products', [ProductController::class, 'store']);
    Route::put('/products/{id}', [ProductController::class, 'update']);
    Route::delete('/products/{id}', [ProductController::class, 'destroy']);
    // 기타 보호된 API들...
});
```

### 2. 프론트엔드 (Vue 3) 구조

#### A. 인증 상태 관리 (Pinia Store)
```javascript
// stores/auth.js
export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,
    token: localStorage.getItem('jwt_token'),
    isAuthenticated: false
  }),
  
  actions: {
    async login(credentials) { /* ... */ },
    async logout() { /* ... */ },
    async refreshToken() { /* ... */ },
    async fetchUser() { /* ... */ }
  }
})
```

#### B. 컴포넌트 구조
```
src/
├── components/
│   ├── LoginForm.vue          # 로그인 폼
│   ├── AuthGuard.vue          # 인증 가드 컴포넌트
│   └── UserMenu.vue           # 사용자 메뉴 (로그아웃 등)
├── views/
│   ├── LoginView.vue          # 로그인 페이지
│   └── DashboardView.vue      # 메인 대시보드 (보호됨)
├── stores/
│   └── auth.js               # 인증 상태 관리
└── router/
    └── guards.js             # 라우트 가드
```

#### C. Axios 인터셉터 설정
```javascript
// API 요청 시 자동 토큰 첨부
axios.interceptors.request.use(config => {
  const token = localStorage.getItem('jwt_token')
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

// 토큰 만료 시 자동 갱신
axios.interceptors.response.use(
  response => response,
  async error => {
    if (error.response?.status === 401) {
      // 토큰 갱신 또는 로그인 페이지로 이동
    }
    return Promise.reject(error)
  }
)
```

## 🔄 구현 단계별 계획

### Phase 1: 백엔드 기반 구축
1. **JWT 패키지 설치 및 설정**
   - `tymon/jwt-auth` 패키지 설치
   - JWT 설정 파일 구성
   - User 모델에 JWT 트레이트 추가

2. **AuthController 구현**
   - 로그인/로그아웃 메서드
   - 토큰 갱신 메서드
   - 사용자 정보 조회 메서드

3. **라우트 및 미들웨어 설정**
   - 인증 라우트 정의
   - 보호된 API 라우트 설정

### Phase 2: 프론트엔드 인증 시스템
1. **Pinia Store 설치 및 설정**
   - 인증 상태 관리 스토어 구현
   - 로컬스토리지 토큰 관리

2. **로그인 컴포넌트 개발**
   - 로그인 폼 UI/UX
   - 유효성 검사 및 에러 처리

3. **Axios 설정 및 인터셉터**
   - 자동 토큰 첨부
   - 토큰 만료 처리

### Phase 3: 라우트 보호 및 가드
1. **Vue Router 가드 구현**
   - 인증 필요 라우트 보호
   - 자동 리디렉션 로직

2. **사용자 경험 개선**
   - 로딩 상태 표시
   - 자동 로그인 복원

### Phase 4: UI/UX 완성
1. **내비게이션 업데이트**
   - 로그인/로그아웃 버튼
   - 사용자 정보 표시

2. **세션 관리**
   - 토큰 만료 알림
   - 자동 갱신 처리

## 🔒 보안 고려사항

### 토큰 관리 전략
```javascript
const tokenConfig = {
  accessToken: {
    duration: '1h',      // 짧은 만료시간
    storage: 'memory'    // XSS 방어
  },
  refreshToken: {
    duration: '7d',      // 7일 자동 로그인
    storage: 'httpOnly', // CSRF 방어 (향후)
    current: 'localStorage' // 현재 구현
  }
}
```

### API 보안 설정
- **CORS**: 현재 설정 유지 (로컬/외부 도메인)
- **CSRF**: `/ectokorea/*` 경로 예외 처리
- **Rate Limiting**: 로그인 시도 제한 고려
- **Password Hashing**: Laravel 기본 bcrypt 사용

## 📊 데이터 흐름

```
1. 사용자 로그인 → JWT 토큰 발급 → localStorage 저장
2. API 요청 시 → Axios 인터셉터로 토큰 자동 첨부
3. 토큰 만료 시 → 자동 갱신 또는 로그인 페이지 이동
4. 로그아웃 시 → 토큰 삭제 → 로그인 페이지 이동
```

## 🧪 테스트 계획

### 백엔드 테스트
- **Unit Tests**: AuthController 메서드별 테스트
- **Feature Tests**: 인증 API 엔드포인트 테스트
- **Integration Tests**: JWT 미들웨어 테스트

### 프론트엔드 테스트
- **Component Tests**: 로그인 폼 컴포넌트
- **Store Tests**: 인증 상태 관리 로직
- **E2E Tests**: 로그인 → 보호된 페이지 접근 플로우

## 🚀 배포 고려사항

### 환경별 설정
```javascript
// 환경 감지 및 API URL 설정
const apiBaseURL = window.location.hostname === '192.168.1.13' 
  ? 'http://192.168.1.13:8080/ectokorea'
  : 'https://devseungil.mydns.jp/ectokorea'
```

### JWT 설정
```php
// config/jwt.php
'ttl' => env('JWT_TTL', 60),           // Access Token: 1시간
'refresh_ttl' => env('JWT_REFRESH_TTL', 10080), // Refresh Token: 7일
```

## 📝 추후 개선 사항

1. **이메일 비밀번호 재설정**
   - 메일 발송 기능
   - 재설정 토큰 관리

2. **2단계 인증 (2FA)**
   - TOTP 또는 SMS 인증

3. **소셜 로그인**
   - Google/Kakao 로그인 연동

4. **세션 관리 개선**
   - Multiple Device 지원
   - 강제 로그아웃 기능

---

*이 문서는 EctoKorea 프로젝트의 로그인 기능 구현을 위한 상세 계획서입니다.*