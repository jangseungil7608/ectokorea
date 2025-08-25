# CLAUDE.md

언어
한국말로 개발

개발구성
windows(11)-wsl(2.3.26)-synology nas(DSM7.2)-docker(container manager)

claude code 설치는 wsl(2.3.26) 에 했음 

기본적으로 설치(install)는 유저가 직접하도록 유도
 
 📁 전체 프로젝트 구조

  /mnt/z/ectokorea/
  ├── backend/                    # Laravel 백엔드
  │   ├── app/
  │   ├── config/
  │   ├── database/
  │   └── ...
  ├── frontend/                   # Vue.js 프론트엔드
  │   ├── src/
  │   ├── public/
  │   └── ...
  ├── python-scraper/             # Python 서비스 (Python 스크래퍼 + FastAP)
  │   ├── app/
  │   │   ├── __init__.py
  │   │   ├── main.py            # FastAPI 앱
  │   │   ├── scrapers/
  │   │   │   ├── __init__.py
  │   │   │   ├── amazon_scraper.py
  │   │   │   ├── base_scraper.py
  │   │   │   └── utils.py
  │   │   ├── models/
  │   │   │   ├── __init__.py
  │   │   │   └── product_models.py
  │   │   ├── services/
  │   │   │   ├── __init__.py
  │   │   │   ├── cache_service.py
  │   │   │   └── proxy_service.py
  │   │   └── config/
  │   │       ├── __init__.py
  │   │       └── settings.py
  │   ├── tests/
  │   │   ├── __init__.py
  │   │   └── test_scrapers.py
  │   ├── requirements.txt
  │   ├── Dockerfile
  │   └── README.md
  ├── docker-compose.yml          # 전체 서비스 오케스트레이션
  ├── .env
  └── README.md


This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**EctoKorea** - 일본 아마존 상품 수익성 계산 및 관리 시스템

일본 아마존에서 상품을 구매해서 한국(쿠팡)에서 판매할 때의 수익성을 계산하고 상품을 관리하는 웹 애플리케이션입니다.

## Project Requirements

### 🎯 핵심 기능
1. **이익 계산기**: 일본 상품 구매 → 한국 판매 시 수익률 계산
2. **다중 사이트 스크래핑**: Amazon, 라쿠텐, JINS 상품 정보 자동 수집
3. **상품 관리**: 관심 상품 등록/수정/삭제/조회
4. **환율 정보**: 실시간 JPY-KRW 환율 조회 및 갱신
5. **이미지 갤러리**: Amazon 스타일 6개 이미지 갤러리 지원
6. **로그인 시스템**: JWT 기반 사용자 인증 (예정)

### 🌐 접속 요구사항
- **프론트엔드 (로컬)**: `http://192.168.1.13:5173/ectokorea`
- **프론트엔드 (외부)**: `https://devseungil.synology.me/ectokorea`
- **백엔드 API (로컬)**: `http://192.168.1.13:8080/ectokorea/*`
- **백엔드 API (외부)**: `https://devseungil.mydns.jp/ectokorea/*`
- **Python 스크래퍼 (로컬)**: `http://192.168.1.13:8001/ectokorea/api/v1/*`
- **Python 스크래퍼 (외부)**: `https://devseungil.mydns.jp:8001/ectokorea/api/v1/*`

### 📊 이익 계산 요소
- **일본 비용**: 상품가 + 일본 내 배송비 + 국제배송비
- **한국 비용**: 관세 + 부가세 + 한국 배송비 + 포장비 + 플랫폼 수수료
- **수익 계산**: 판매가 - 총 비용 = 순이익 (이익률 %)
- **목표 이익률**: 사용자 설정 값에 따른 추천 판매가 계산

### 🔐 인증 요구사항 (예정)
- **방식**: JWT Token 기반 인증
- **로그인 유지**: 7일간 자동 로그인
- **패스워드 찾기**: 관리자 직접 리셋 (초기) → 이메일 재설정 (향후)
- **접근 제어**: 로그인 필수 페이지 설정

## Project Architecture

This is a full-stack application built with Laravel (PHP backend), Vue 3 (frontend), and Python (scraping service), orchestrated with Docker Compose. The application includes a multi-site product scraping service that extracts product information from Amazon, Rakuten, and JINS.

### Backend (Laravel)
- **Framework**: Laravel 12 with PHP 8.2+
- **Database**: PostgreSQL (via Docker)
- **Authentication**: JWT Token 기반 (예정)
- **Key Services**: 
  - `ProductCollectionService` - Python 스크래퍼와 연동하여 상품 수집
  - `ExchangeRateService` - 한국수출입은행 환율 API 연동
  - `ProfitCalculatorService` - 이익률 계산 로직
  - `HaniroShippingService` - 한일 배송비 계산
- **Structure**:
  - Controllers: `app/Http/Controllers/` (ProductController, CollectedProductController, ProfitCalculatorController)
  - Models: `app/Models/` (Product, CollectedProduct, CollectionJob, User)
  - Services: `app/Services/` (ProductCollectionService, ExchangeRateService, ProfitCalculatorService, HaniroShippingService)
  - Routes: API routes defined in `routes/web.php` under `/ectokorea` prefix
- **CORS**: 로컬(192.168.1.13:5173) 및 외부 도메인 허용 설정
- **CSRF**: `/ectokorea/*` 경로 CSRF 보호 예외 처리

### Frontend (Vue 3)
- **Framework**: Vue 3 with Composition API + Vite
- **Build Tool**: Vite (base: '/ectokorea')
- **HTTP Client**: Axios (자동 로컬/외부 URL 감지)
- **Components**: 
  - `ProfitCalculator.vue` - 이익 계산기 메인 컴포넌트
  - `ProductCollector.vue` - 다중 사이트 상품 수집
  - `CollectedProductList.vue` - 수집된 상품 목록 관리
  - `ImageGallery.vue` - Amazon 스타일 이미지 갤러리
  - `ProductDescription.vue` - HTML 상품 설명 렌더링
  - `ProductForm.vue` - 상품 등록 폼 (기존)
- **State Management**: Vue 3 Composition API (Pinia 예정)

### Python Scraper Service
- **Framework**: FastAPI (비동기 처리)
- **Port**: 8001
- **Architecture**: 팩토리 패턴 기반 확장 가능한 구조
- **Supported Sites**: Amazon (구현완료), Rakuten (예정), JINS (예정)
- **Key Features**:
  - URL 자동 감지 및 사이트별 파라미터 추출
  - Amazon 이미지 갤러리 (6개 썸네일/큰 이미지)
  - JavaScript colorImages 파싱을 통한 정확한 이미지 수집
  - HTML 상품 설명 및 특징 추출 (trafilatura AI 백업)
  - 3단계 fallback 시스템으로 안정성 확보
- **Libraries**: httpx, BeautifulSoup4, lxml, trafilatura
- **Data Model**: Pydantic 기반 통합 Product 모델

### Database Schema
- **Primary**: PostgreSQL (containerized)
- **Development**: SQLite (for testing)
- **Tables**:
  - `users` - 사용자 정보 (name, email, password)
  - `products` - 기본 상품 정보 (name, price, asin, url, image_url)
  - `collected_products` - Python 스크래퍼로 수집된 상품 (asin, title, price_jpy, images, thumbnail_images, large_images, description, features, weight, dimensions, category, brand)
  - `collection_jobs` - 상품 수집 작업 관리
- **Migrations**: Located in `database/migrations/`

## Development Commands

### Backend (Laravel)
```bash
# Development server with hot reloading (backend directory)
composer dev

# Run tests
composer test
# or
php artisan test

# Lint/Format code
vendor/bin/pint

# Database migrations
php artisan migrate

# Clear application cache
php artisan config:clear
```

### Frontend (Vue)
```bash
# Development server (frontend directory)
npm run dev

# Build for production
npm run build

# Preview production build
npm run preview
```

### Python Scraper
```bash
# Development server (python-scraper directory)
uvicorn main:app --host 0.0.0.0 --port 8001 --reload

# Install dependencies
pip install -r requirements.txt

# Test API
curl "http://192.168.1.13:8001/ectokorea/api/v1/scrape/amazon?asin=B0DJNXJTJL"
```

### Docker Environment
```bash
# Start all services (includes Python scraper)
docker-compose up -d

# Rebuild containers
docker-compose up --build

# Stop services
docker-compose down
```

### Services Access
- **Frontend (로컬)**: http://192.168.1.13:5173/ectokorea
- **Frontend (외부)**: https://devseungil.synology.me/ectokorea  
- **Backend API (로컬)**: http://192.168.1.13:8080/ectokorea/*
- **Backend API (외부)**: https://devseungil.mydns.jp/ectokorea/*
- **Python Scraper (로컬)**: http://192.168.1.13:8001/ectokorea/api/v1/*
- **Python Scraper (외부)**: https://devseungil.mydns.jp:8001/ectokorea/api/v1/*
- **PostgreSQL**: localhost:55432

## API Endpoints

### 상품 관리
- `GET /ectokorea/products` - 상품 목록 조회
- `POST /ectokorea/products` - 상품 등록
- `GET /ectokorea/products/{id}` - 상품 상세 조회
- `PUT /ectokorea/products/{id}` - 상품 수정
- `DELETE /ectokorea/products/{id}` - 상품 삭제

### Python 스크래핑 서비스 (Laravel → Python)
- `GET /ectokorea/api/v1/scrape/amazon?asin={asin}` - Amazon 상품 스크래핑
- `GET /ectokorea/api/v1/scrape/rakuten?shopId={shopId}&itemCode={itemCode}` - 라쿠텐 스크래핑 (예정)
- `GET /ectokorea/api/v1/scrape/jins?productId={productId}` - JINS 스크래핑 (예정)
- `GET /ectokorea/api/v1/scrape?url={product_url}` - URL 자동 감지 스크래핑
- `GET /ectokorea/api/v1/sites` - 지원 사이트 목록

### 수집된 상품 관리 (Laravel)
- `GET /ectokorea/collected-products` - 수집된 상품 목록 조회
- `POST /ectokorea/collected-products/collect` - 상품 수집 요청 (Python 스크래퍼 호출)
- `GET /ectokorea/collected-products/{id}` - 수집된 상품 상세 조회
- `DELETE /ectokorea/collected-products/{id}` - 수집된 상품 삭제

### 환율 정보
- `GET /ectokorea/exchange-rate/current` - 현재 환율 조회
- `POST /ectokorea/exchange-rate/refresh` - 환율 정보 갱신
- `POST /ectokorea/exchange-rate/convert` - 환율 변환

### 이익 계산
- `POST /ectokorea/profit-calculator/calculate` - 이익률 계산
- `POST /ectokorea/profit-calculator/recommend-price` - 추천 판매가 계산
- `GET /ectokorea/profit-calculator/categories` - 상품 카테고리 목록
- `GET /ectokorea/profit-calculator/shipping-options` - 배송 옵션 목록

### 인증 (예정)
- `POST /ectokorea/auth/login` - 로그인
- `POST /ectokorea/auth/logout` - 로그아웃  
- `POST /ectokorea/auth/refresh` - 토큰 갱신
- `GET /ectokorea/auth/me` - 사용자 정보 조회

## Testing

- **Backend**: PHPUnit tests in `tests/` directory
- **Test Command**: `composer test` or `php artisan test`
- **Test Database**: SQLite in-memory database
- **Configuration**: `phpunit.xml`

## Development Notes

### 기술적 특징
- **멀티 사이트 스크래핑**: Amazon, 라쿠텐, JINS (Python FastAPI)
- **고급 이미지 처리**: Amazon colorImages JavaScript 파싱으로 6개 갤러리 이미지 수집
- **Laravel ↔ Python 연동**: ProductCollectionService를 통한 비동기 상품 수집
- **AI 기반 콘텐츠 추출**: trafilatura를 활용한 스마트 데이터 추출
- **3단계 Fallback 시스템**: JSON-LD → HTML 셀렉터 → AI 추출
- **실시간 환율**: 한국수출입은행 API 연동
- **Docker 멀티 서비스**: PostgreSQL, Laravel, Vue, Python 스크래퍼 통합 환경
- **CORS/CSRF**: 로컬/외부 도메인 허용 및 보안 설정

### 계산 로직
- 국제배송비: 항공(¥1000/kg) vs 해상(¥300/kg) 
- 관세율: 카테고리별 차등 (일반 8%, 화장품 8%, 의류 13%, 전자제품 8%, 식품 30%, 도서 0%)
- 부가세: 10% (관세 포함 금액 기준)
- 플랫폼 수수료: 쿠팡 기준 15-30% (카테고리별)
- 면세 기준: $150 이하 상품

### Python 스크래핑 사용 예시

#### 1. Amazon 상품 스크래핑
```bash
# ASIN으로 직접 스크래핑
curl "http://192.168.1.13:8001/ectokorea/api/v1/scrape/amazon?asin=B0DJNXJTJL"

# URL 자동 감지
curl "http://192.168.1.13:8001/ectokorea/api/v1/scrape?url=https://www.amazon.co.jp/dp/B0DJNXJTJL"
```

#### 2. Laravel에서 Python 스크래퍼 호출
```php
// ProductCollectionService 사용
$service = app(ProductCollectionService::class);
$product = $service->collectProduct('amazon', ['asin' => 'B0DJNXJTJL']);
```

#### 3. 수집된 데이터 구조
```json
{
  "success": true,
  "site": "amazon",
  "data": {
    "name": "상품명",
    "price": 3990.0,
    "thumbnail_images": ["썸네일1", "썸네일2", ...],
    "large_images": ["큰이미지1", "큰이미지2", ...],
    "description": "<div>HTML 상품설명</div>",
    "features": ["특징1", "특징2", ...],
    "weight": "0.310",
    "dimensions": "20.3 x 18.4 x 13.5 cm"
  }
}
```

### 참고 링크
- **Coupang Developers**: https://developers.coupangcorp.com/hc/ko
- **한국수출입은행 환율 API**: https://www.koreaexim.go.kr