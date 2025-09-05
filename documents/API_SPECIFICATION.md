# EctoKorea API 명세서 (API_SPECIFICATION.md)

## 📋 개요

**EctoKorea REST API**는 일본 상품 수익성 계산 및 상품 수집 관리를 위한 RESTful API입니다. JWT 인증을 기반으로 하며, JSON 형태로 데이터를 주고받습니다.

## 🌐 기본 정보

### Base URLs
- **로컬**: `http://192.168.1.13:8080/ectokorea`
- **외부**: `https://devseungil.mydns.jp/ectokorea`

### 인증 방식
- **타입**: JWT Bearer Token
- **헤더**: `Authorization: Bearer {token}`

### 공통 응답 형태
```json
{
    "success": true|false,
    "message": "응답 메시지",
    "data": { ... },
    "error": "에러 메시지 (실패 시)"
}
```

## 🔐 인증 API

### POST /auth/login
사용자 로그인

**Request Body:**
```json
{
    "email": "user@example.com",
    "password": "password123"
}
```

**Response (200):**
```json
{
    "success": true,
    "message": "로그인이 완료되었습니다.",
    "data": {
        "access_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
        "token_type": "bearer",
        "expires_in": 3600,
        "user": {
            "id": 1,
            "name": "사용자명",
            "email": "user@example.com"
        }
    }
}
```

### POST /auth/register  
사용자 회원가입

**Request Body:**
```json
{
    "name": "사용자명",
    "email": "user@example.com", 
    "password": "password123",
    "password_confirmation": "password123"
}
```

**Response (201):**
```json
{
    "success": true,
    "message": "회원가입이 완료되었습니다.",
    "data": {
        "user": {
            "id": 1,
            "name": "사용자명",
            "email": "user@example.com"
        }
    }
}
```

### POST /auth/logout
로그아웃 (인증 필요)

**Response (200):**
```json
{
    "success": true,
    "message": "로그아웃되었습니다."
}
```

### POST /auth/refresh
토큰 갱신 (인증 필요)

**Response (200):**
```json
{
    "success": true,
    "data": {
        "access_token": "새로운_토큰",
        "token_type": "bearer",
        "expires_in": 3600
    }
}
```

### GET /auth/me
현재 사용자 정보 조회 (인증 필요)

**Response (200):**
```json
{
    "success": true,
    "data": {
        "id": 1,
        "name": "사용자명", 
        "email": "user@example.com",
        "created_at": "2025-09-05T10:30:00.000000Z"
    }
}
```

## 📦 수집 상품 관리 API

### GET /collected-products
수집된 상품 목록 조회 (인증 필요)

**Query Parameters:**
- `status` (string): 상품 상태 필터 (PENDING, COLLECTED, ANALYZED, READY_TO_LIST, LISTED, ERROR)
- `profitable` (boolean): 수익성 필터 (true/false)
- `favorite` (boolean): 즐겨찾기 필터 (true/false) 
- `category` (string): 카테고리 필터
- `search` (string): 검색어 (상품명, ASIN)
- `sort_by` (string): 정렬 기준 (created_at, title, price_jpy, profit_margin, collected_at)
- `sort_order` (string): 정렬 순서 (asc, desc)
- `per_page` (integer): 페이지당 항목 수 (기본: 20)

**Response (200):**
```json
{
    "success": true,
    "data": {
        "current_page": 1,
        "data": [
            {
                "id": 1,
                "user_id": 1,
                "asin": "B0DJNXJTJL",
                "title": "Nintendo Switch Pro Controller",
                "price_jpy": 6578.0,
                "thumbnail_images": ["image1.jpg", "image2.jpg"],
                "large_images": ["large1.jpg", "large2.jpg"],
                "description": "<div>상품 설명</div>",
                "features": ["특징1", "특징2"],
                "weight": "246",
                "dimensions": "15.6 x 10.6 x 6.0 cm",
                "category": "비디오 게임",
                "brand": "Nintendo",
                "is_profitable": true,
                "profit_margin": 15.2,
                "is_favorite": false,
                "status": "ANALYZED",
                "collected_at": "2025-09-05T10:30:00.000000Z",
                "created_at": "2025-09-05T10:30:00.000000Z"
            }
        ],
        "total": 50,
        "per_page": 20,
        "last_page": 3
    }
}
```

### GET /collected-products/{id}
수집된 상품 상세 조회 (인증 필요)

**Response (200):**
```json
{
    "success": true,
    "data": {
        "id": 1,
        "user_id": 1,
        "asin": "B0DJNXJTJL",
        "title": "Nintendo Switch Pro Controller",
        "price_jpy": 6578.0,
        "thumbnail_images": ["image1.jpg", "image2.jpg", "image3.jpg"],
        "large_images": ["large1.jpg", "large2.jpg", "large3.jpg"],
        "description": "<div>상세한 상품 설명 HTML</div>",
        "features": ["고정밀 조이스틱", "진동 기능", "amiibo 지원"],
        "weight": "246",
        "dimensions": "15.6 x 10.6 x 6.0 cm",
        "category": "비디오 게임",
        "brand": "Nintendo",
        "is_profitable": true,
        "profit_margin": 15.2,
        "target_margin": 10.0,
        "japan_shipping_jpy": 500,
        "korea_shipping_krw": 3000,
        "is_favorite": false,
        "notes": "사용자 메모",
        "status": "ANALYZED",
        "collected_at": "2025-09-05T10:30:00.000000Z",
        "analyzed_at": "2025-09-05T10:32:00.000000Z"
    }
}
```

### POST /collected-products/collect/asin
ASIN으로 단일 상품 수집 (인증 필요)

**Request Body:**
```json
{
    "asin": "B0DJNXJTJL",
    "auto_analyze": true,
    "target_margin": 15.0,
    "japan_shipping_jpy": 500,
    "korea_shipping_krw": 3000
}
```

**Response (200):**
```json
{
    "success": true,
    "message": "상품 수집이 시작되었습니다.",
    "data": {
        "id": 1,
        "asin": "B0DJNXJTJL",
        "status": "PENDING"
    }
}
```

### POST /collected-products/collect/bulk-asin
ASIN 대량 수집 (인증 필요)

**Request Body:**
```json
{
    "asins": ["B0DJNXJTJL", "B08H93ZRZ9", "B07VGRJDFY"],
    "auto_analyze": true
}
```

**Response (200):**
```json
{
    "success": true,
    "message": "대량 수집 작업이 Queue에 추가되었습니다. 백그라운드에서 처리됩니다.",
    "data": {
        "id": 123,
        "type": "BULK_ASIN",
        "status": "PENDING",
        "total_items": 3,
        "created_at": "2025-09-05T10:30:00.000000Z"
    }
}
```

### POST /collected-products/collect/url
URL로 상품 수집 (인증 필요)

**Request Body:**
```json
{
    "url": "https://www.amazon.co.jp/gp/bestsellers/videogames",
    "auto_analyze": true,
    "max_results": 20
}
```

**Response (200):**
```json
{
    "success": true,
    "message": "검색 결과에서 20개 상품을 찾아 수집 작업을 Queue에 추가되었습니다. 백그라운드에서 처리됩니다.",
    "data": {
        "type": "job",
        "job": {
            "id": 124,
            "type": "URL",
            "status": "PENDING",
            "total_items": 20
        },
        "found_count": 20,
        "url_type": "BESTSELLER"
    }
}
```

### POST /collected-products/collect/keyword
키워드로 상품 수집 (인증 필요)

**Request Body:**
```json
{
    "keyword": "Nintendo Switch 게임",
    "max_results": 30,
    "auto_analyze": true
}
```

**Response (200):**
```json
{
    "success": true,
    "message": "'Nintendo Switch 게임' 키워드로 상품 검색 및 수집 작업이 Queue에 추가되었습니다. 백그라운드에서 처리됩니다.",
    "data": {
        "id": 125,
        "type": "KEYWORD",
        "status": "PENDING",
        "input_data": {
            "keyword": "Nintendo Switch 게임"
        }
    }
}
```

### PUT /collected-products/{id}
수집된 상품 정보 수정 (인증 필요)

**Request Body:**
```json
{
    "is_favorite": true,
    "notes": "관심 상품으로 표시",
    "category": "게임 액세서리",
    "subcategory": "컨트롤러"
}
```

**Response (200):**
```json
{
    "success": true,
    "message": "상품 정보가 업데이트되었습니다.",
    "data": {
        "id": 1,
        "is_favorite": true,
        "notes": "관심 상품으로 표시",
        "category": "게임 액세서리",
        "updated_at": "2025-09-05T11:00:00.000000Z"
    }
}
```

### POST /collected-products/{id}/reanalyze
상품 수익성 재분석 (인증 필요)

**Request Body:**
```json
{
    "target_margin": 20.0,
    "japan_shipping_jpy": 800,
    "korea_shipping_krw": 4000
}
```

**Response (200):**
```json
{
    "success": true,
    "message": "수익성 분석이 완료되었습니다.",
    "data": {
        "id": 1,
        "is_profitable": false,
        "profit_margin": 8.5,
        "target_margin": 20.0,
        "analyzed_at": "2025-09-05T11:05:00.000000Z"
    }
}
```

### DELETE /collected-products/{id}
수집된 상품 삭제 (인증 필요)

**Response (200):**
```json
{
    "success": true,
    "message": "상품이 삭제되었습니다."
}
```

## 📊 수집 작업 관리 API

### GET /collected-products/jobs/list
수집 작업 목록 조회 (인증 필요)

**Query Parameters:**
- `status` (string): 작업 상태 (PENDING, PROCESSING, COMPLETED, FAILED)
- `type` (string): 작업 타입 (BULK_ASIN, URL, KEYWORD)
- `per_page` (integer): 페이지당 항목 수 (기본: 10)

**Response (200):**
```json
{
    "success": true,
    "data": {
        "current_page": 1,
        "data": [
            {
                "id": 123,
                "type": "BULK_ASIN",
                "status": "COMPLETED",
                "progress": 10,
                "total_items": 10,
                "success_count": 8,
                "error_count": 2,
                "success_rate": 80.0,
                "duration_seconds": 45,
                "started_at": "2025-09-05T10:30:00.000000Z",
                "completed_at": "2025-09-05T10:30:45.000000Z",
                "created_at": "2025-09-05T10:29:30.000000Z"
            }
        ],
        "total": 25,
        "per_page": 10
    }
}
```

### GET /collected-products/jobs/{id}
수집 작업 상세 조회 (인증 필요)

**Response (200):**
```json
{
    "success": true,
    "data": {
        "id": 123,
        "user_id": 1,
        "type": "URL",
        "status": "COMPLETED",
        "input_data": {
            "url": "https://www.amazon.co.jp/gp/bestsellers/videogames",
            "url_type": "BESTSELLER",
            "asins": ["B0DJNXJTJL", "B08H93ZRZ9"],
            "found_count": 20
        },
        "settings": {
            "auto_analyze": true,
            "max_results": 20
        },
        "progress": 20,
        "total_items": 20,
        "success_count": 18,
        "error_count": 2,
        "success_rate": 90.0,
        "results": [
            {
                "asin": "B0DJNXJTJL",
                "status": "success",
                "processed_at": "2025-09-05T10:30:15.000000Z"
            },
            {
                "asin": "B08H93ZRZ9",
                "status": "error",
                "error": "Product not found",
                "processed_at": "2025-09-05T10:30:17.000000Z"
            }
        ],
        "duration_seconds": 65,
        "started_at": "2025-09-05T10:30:00.000000Z",
        "completed_at": "2025-09-05T10:31:05.000000Z",
        "created_at": "2025-09-05T10:29:45.000000Z"
    }
}
```

### GET /collected-products/stats/overview
사용자 수집 통계 (인증 필요)

**Response (200):**
```json
{
    "success": true,
    "data": {
        "total_products": 150,
        "by_status": {
            "pending": 5,
            "collected": 20,
            "analyzed": 100,
            "ready_to_list": 20,
            "listed": 5,
            "error": 0
        },
        "profitable_count": 95,
        "favorite_count": 25,
        "recent_jobs": {
            "pending": 2,
            "processing": 1,
            "completed_today": 8
        }
    }
}
```

## 💰 이익 계산 API

### POST /profit-calculator/calculate
이익률 계산

**Request Body:**
```json
{
    "japan_price_jpy": 6578.0,
    "japan_shipping_jpy": 500.0,
    "weight_grams": 246,
    "shipping_method": "air",
    "category": "전자제품",
    "korea_price_krw": 95000.0,
    "korea_shipping_krw": 3000.0,
    "packaging_krw": 1000.0
}
```

**Response (200):**
```json
{
    "success": true,
    "data": {
        "total_cost_krw": 78540.5,
        "selling_price_krw": 95000.0,
        "net_profit_krw": 16459.5,
        "profit_margin": 17.33,
        "cost_breakdown": {
            "product_cost_krw": 65780.0,
            "japan_shipping_krw": 5000.0,
            "international_shipping_krw": 2460.0,
            "customs_krw": 5257.6,
            "vat_krw": 7108.36,
            "korea_shipping_krw": 3000.0,
            "packaging_krw": 1000.0,
            "platform_fee_krw": 9500.0
        },
        "exchange_rate": 10.0,
        "is_duty_free": false,
        "customs_rate": 8.0,
        "vat_rate": 10.0,
        "platform_fee_rate": 10.0
    }
}
```

### POST /profit-calculator/recommend-price
추천 판매가 계산

**Request Body:**
```json
{
    "japan_price_jpy": 6578.0,
    "japan_shipping_jpy": 500.0,
    "weight_grams": 246,
    "shipping_method": "air",
    "category": "전자제품",
    "target_margin": 20.0,
    "korea_shipping_krw": 3000.0,
    "packaging_krw": 1000.0
}
```

**Response (200):**
```json
{
    "success": true,
    "data": {
        "recommended_price_krw": 102735.0,
        "target_margin": 20.0,
        "expected_profit_krw": 20547.0,
        "total_cost_krw": 82188.0,
        "cost_breakdown": {
            "product_cost_krw": 65780.0,
            "japan_shipping_krw": 5000.0,
            "international_shipping_krw": 2460.0,
            "customs_krw": 5257.6,
            "vat_krw": 7108.36,
            "korea_shipping_krw": 3000.0,
            "packaging_krw": 1000.0,
            "platform_fee_krw": 10274.0
        }
    }
}
```

### GET /profit-calculator/categories
지원 카테고리 목록 조회

**Response (200):**
```json
{
    "success": true,
    "data": [
        {
            "id": "general",
            "name": "일반상품",
            "customs_rate": 8.0,
            "platform_fee_rate": 12.0
        },
        {
            "id": "cosmetics",
            "name": "화장품", 
            "customs_rate": 8.0,
            "platform_fee_rate": 15.0
        },
        {
            "id": "clothing",
            "name": "의류",
            "customs_rate": 13.0,
            "platform_fee_rate": 12.0
        },
        {
            "id": "electronics",
            "name": "전자제품",
            "customs_rate": 8.0,
            "platform_fee_rate": 10.0
        },
        {
            "id": "food",
            "name": "식품",
            "customs_rate": 30.0,
            "platform_fee_rate": 18.0
        },
        {
            "id": "books",
            "name": "도서",
            "customs_rate": 0.0,
            "platform_fee_rate": 10.0
        }
    ]
}
```

### GET /profit-calculator/shipping-options
배송 옵션 목록 조회

**Response (200):**
```json
{
    "success": true,
    "data": [
        {
            "method": "air",
            "name": "항공편",
            "cost_per_kg_jpy": 1000,
            "delivery_days": "3-7일"
        },
        {
            "method": "sea",
            "name": "해상운송",
            "cost_per_kg_jpy": 300,
            "delivery_days": "14-21일"
        }
    ]
}
```

## 💱 환율 API

### GET /exchange-rate/current
현재 환율 조회

**Response (200):**
```json
{
    "success": true,
    "data": {
        "rate": 10.15,
        "currency_pair": "JPY/KRW",
        "updated_at": "2025-09-05T09:00:00.000000Z",
        "source": "한국수출입은행"
    }
}
```

### POST /exchange-rate/refresh
환율 정보 갱신

**Response (200):**
```json
{
    "success": true,
    "message": "환율 정보가 업데이트되었습니다.",
    "data": {
        "old_rate": 10.10,
        "new_rate": 10.15,
        "updated_at": "2025-09-05T12:00:00.000000Z"
    }
}
```

### POST /exchange-rate/convert
환율 변환

**Request Body:**
```json
{
    "amount": 6578.0,
    "from_currency": "JPY",
    "to_currency": "KRW"
}
```

**Response (200):**
```json
{
    "success": true,
    "data": {
        "original_amount": 6578.0,
        "converted_amount": 66766.7,
        "exchange_rate": 10.15,
        "from_currency": "JPY",
        "to_currency": "KRW"
    }
}
```

## 📦 기존 상품 관리 API (레거시)

### GET /products
상품 목록 조회 (인증 필요)

### GET /products/{id}
상품 상세 조회 (인증 필요)

### POST /products
상품 등록 (인증 필요)

### PUT /products/{id}
상품 수정 (인증 필요)

### DELETE /products/{id}
상품 삭제 (인증 필요)

## 🚨 에러 코드

### HTTP 상태 코드
- `200` OK - 요청 성공
- `201` Created - 리소스 생성 성공  
- `400` Bad Request - 잘못된 요청
- `401` Unauthorized - 인증 필요
- `403` Forbidden - 권한 없음
- `404` Not Found - 리소스 없음
- `422` Unprocessable Entity - 유효성 검증 실패
- `500` Internal Server Error - 서버 오류

### 공통 에러 응답
```json
{
    "success": false,
    "message": "에러 메시지",
    "error": "상세 에러 정보",
    "errors": {
        "field_name": ["유효성 검증 에러 메시지"]
    }
}
```

### 유효성 검증 에러 예시
```json
{
    "success": false,
    "message": "The given data was invalid.",
    "errors": {
        "asin": ["The asin field is required."],
        "email": ["The email must be a valid email address."]
    }
}
```

## 🔒 보안 및 제한사항

### Rate Limiting
- **일반 API**: 분당 60회
- **인증 API**: 분당 10회  
- **수집 API**: 분당 10회

### 인증 토큰
- **만료 시간**: 1시간
- **갱신**: `/auth/refresh` 엔드포인트 사용
- **자동 갱신**: 프론트엔드에서 자동 처리

### 데이터 제한
- **ASIN 대량 수집**: 최대 100개
- **URL 수집**: 최대 100개 상품
- **키워드 검색**: 최대 100개 결과
- **파일 업로드**: 지원하지 않음 (이미지는 URL 형태)

## 📋 변경 이력

### v1.0.0 (2025-09-05)
- ✅ JWT 인증 시스템 구현
- ✅ 수집 상품 관리 API 완성
- ✅ Queue 기반 대량 처리 시스템
- ✅ URL 수집 기능 추가  
- ✅ 이익 계산기 API 완성
- ✅ 환율 API 구현
- ✅ 작업 모니터링 시스템

### 향후 계획
- 🔄 실시간 알림 API (WebSocket)
- 🔄 파일 업로드 API
- 🔄 자동 번역 API
- 🔄 Coupang 연동 API

---

*이 문서는 EctoKorea v1.0 기준으로 작성되었습니다. (2025-09-05 업데이트)*