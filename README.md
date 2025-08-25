# 일본→한국 EC 출품 도구

Amazon Japan에서 Coupang으로 상품을 효율적으로 출품하기 위한 이익계산 및 관리 도구입니다.

## 🚀 주요 기능

### ✅ 완료된 기능
- **💰 이익 계산기**: 일본 구매가부터 한국 판매까지의 모든 비용과 이익을 정확히 계산
- **📊 실시간 환율**: 한국수출입은행 API를 통한 실시간 JPY-KRW 환율 연동
- **🏷️ 카테고리별 관세**: 화장품, 의류, 전자제품 등 카테고리별 정확한 관세율 적용
- **🚚 배송비 계산**: 항공/해상배송 선택과 무게별 자동 배송비 계산
- **🎯 목표 이익률**: 원하는 이익률 기준으로 추천 판매가 자동 계산

### 🔄 계획된 기능
- Amazon 상품 정보 리서치 도구
- 자동 번역 (일본어 → 한국어)
- Coupang 상품 등록 지원
- 재고 관리 시스템

## 💻 기술 스택

- **백엔드**: Laravel 12 + PHP 8.2+
- **프론트엔드**: Vue 3 + Tailwind CSS  
- **데이터베이스**: PostgreSQL
- **환율 API**: 한국수출입은행 Open API

## 📋 설치 및 실행

### 1. 환경 설정

```bash
# 백엔드 환경변수 설정
cd backend
cp .env.example .env
```

`.env` 파일에서 다음 설정을 변경하세요:
```env
# 한국수출입은행 환율 API 키 (필수)
KOREA_EXIMBANK_API_KEY=여기에_발급받은_키_입력

# 데이터베이스 설정 (Docker 사용시)
DB_CONNECTION=pgsql
DB_HOST=db
DB_PORT=5432
DB_DATABASE=coupang
DB_USERNAME=youruser
DB_PASSWORD=yourpass
```

### 2. Docker로 실행 (추천)

```bash
# 전체 서비스 시작
docker-compose up -d

# 서비스 확인
docker-compose ps
```

### 3. 수동 실행

**백엔드:**
```bash
cd backend
composer install
php artisan key:generate
php artisan migrate
php artisan serve --host=0.0.0.0 --port=8080
```

**프론트엔드:**
```bash
cd frontend
npm install
npm run dev
```

## 🌐 접속 URL

- **프론트엔드**: https://devseungil.synology.me
- **백엔드 API**: https://devseungil.mydns.jp
- **PostgreSQL**: localhost:55432

## 📖 사용 방법

### 이익 계산기 사용하기

1. **일본 상품 정보 입력**
   - 상품 가격 (JPY)
   - 일본 배송비
   - 상품 무게
   - 배송 방법 (항공/해상)
   - 상품 카테고리

2. **한국 판매 정보 입력**
   - 쿠팡 판매가 (KRW)
   - 한국 배송비
   - 포장비 (선택사항)

3. **결과 확인**
   - 총 비용 분석
   - 순 이익 계산
   - 이익률 표시
   - 세금/수수료 상세 내역

### 추천 판매가 계산

1. 목표 이익률 설정 (5-50%)
2. "추천 판매가 계산" 버튼 클릭
3. 목표 이익률 달성을 위한 적정 판매가 확인

## 🔑 환율 API 키 발급

1. [한국수출입은행 환율API](https://www.koreaexim.go.kr/site/program/financial/exchangeJSON) 접속
2. 간단한 본인 인증 후 API 키 발급
3. `.env` 파일의 `KOREA_EXIMBANK_API_KEY`에 입력

## 📊 계산 로직

### 총 비용 계산
```
총 비용 = 일본 구매가 + 일본 배송비 + 국제배송비 + 관세 + 부가세 + 한국 배송비 + 플랫폼 수수료
```

### 세금 계산
- **관세**: 카테고리별 (0-30%)
- **부가세**: (구매가 + 관세) × 10%
- **면세**: $150 이하 개인용품

### 플랫폼 수수료 (쿠팡)
- 화장품: 15%
- 의류: 12%  
- 전자제품: 10%
- 식품: 18%
- 도서: 10%
- 기타: 12%

## 🛠️ 개발환경 설정

```bash
# 백엔드 테스트
cd backend
php artisan test

# 프론트엔드 빌드
cd frontend  
npm run build
```

## ⚠️ 주의사항

1. **법적 준수**: Amazon 이용약관을 준수하여 직접 데이터 추출을 하지 않습니다
2. **개인 사용**: 상업적 재판매가 아닌 개인 수입/판매 목적으로 설계되었습니다
3. **정확성**: 계산 결과는 참고용이며, 실제 세금/수수료는 변동될 수 있습니다

## 🤝 기여하기

버그 리포트나 기능 제안은 Issues를 통해 알려주세요.

## 📄 라이선스

개인 및 교육 목적으로 자유롭게 사용 가능합니다.