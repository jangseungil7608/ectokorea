# Python 멀티 사이트 스크래핑 툴

## 개요

일본 주요 쇼핑몰(Amazon, 라쿠텐, JINS)에서 상품 정보를 스크래핑하는 통합 Python 서비스입니다. FastAPI 기반으로 구축되어 있으며 확장 가능한 아키텍처를 제공합니다.

## 시스템 구조

### 🏗️ 전체 아키텍처

```
python-scraper/
├── main.py                    # FastAPI 서버 엔트리포인트
├── app/
│   ├── api/
│   │   └── scraper.py         # API 엔드포인트 정의
│   ├── core/
│   │   ├── base_scraper.py    # 스크래퍼 기본 인터페이스
│   │   ├── scraper_factory.py # 팩토리 패턴 구현
│   │   └── exceptions.py      # 커스텀 예외 정의
│   ├── models/
│   │   └── product.py         # 통합 상품 데이터 모델
│   ├── scrapers/
│   │   ├── amazon/
│   │   │   └── amazon_scraper.py  # Amazon 스크래퍼 구현
│   │   ├── rakuten/           # 라쿠텐 스크래퍼 (TODO)
│   │   └── jins/              # JINS 스크래퍼 (TODO)
│   └── utils/
│       └── smart_extractor.py # AI 기반 스마트 추출
└── requirements.txt
```

### 🔧 핵심 컴포넌트

## 1. API 엔드포인트

### 기본 접근 URL
- **로컬**: `http://192.168.1.13:8001/ectokorea/api/v1`
- **외부**: `https://devseungil.mydns.jp:8001/ectokorea/api/v1`

### 지원 API

#### 1) URL 자동 감지 스크래핑
```bash
GET /ectokorea/api/v1/scrape?url={product_url}
```
**설명**: URL을 넘겨주면 자동으로 사이트를 감지하고 적절한 스크래퍼로 처리

**예시**:
```bash
curl "http://192.168.1.13:8001/ectokorea/api/v1/scrape?url=https://www.amazon.co.jp/dp/B0DJNXJTJL"
```

#### 2) 사이트별 직접 스크래핑
```bash
GET /ectokorea/api/v1/scrape/{site}
```

**Amazon**:
```bash
GET /ectokorea/api/v1/scrape/amazon?asin=B0DJNXJTJL
```

**라쿠텐** (구현 예정):
```bash
GET /ectokorea/api/v1/scrape/rakuten?shopId={shopId}&itemCode={itemCode}
```

**JINS** (구현 예정):
```bash
GET /ectokorea/api/v1/scrape/jins?productId={productId}
```

#### 3) 지원 사이트 목록
```bash
GET /ectokorea/api/v1/sites
```

## 2. 팩토리 패턴 구현

### ScraperFactory 클래스

**파일**: `app/core/scraper_factory.py`

#### 주요 기능:
1. **URL 패턴 자동 감지**
```python
SITE_PATTERNS = {
    'amazon': {
        'regex': r'amazon\.co\.jp/dp/([A-Z0-9]{10})',
        'params': ['asin'],
        'url_template': 'https://www.amazon.co.jp/dp/{asin}'
    },
    'rakuten': {
        'regex': r'item\.rakuten\.co\.jp/([^/]+)/([^/?]+)',
        'params': ['shopId', 'itemCode'],
        'url_template': 'https://item.rakuten.co.jp/{shopId}/{itemCode}'
    },
    'jins': {
        'regex': r'jins\.com/jp/item/([^.]+)\.html',
        'params': ['productId'],
        'url_template': 'https://www.jins.com/jp/item/{productId}.html'
    }
}
```

2. **동적 스크래퍼 생성**
```python
@classmethod
def create_scraper(cls, site: str) -> BaseScraper:
    if site == 'amazon':
        from app.scrapers.amazon.amazon_scraper import AmazonScraper
        return AmazonScraper()
    # 다른 사이트들...
```

3. **URL 파라미터 추출**
```python
@classmethod
def detect_site_from_url(cls, url: str) -> Tuple[str, Dict[str, str]]:
    for site, config in cls.SITE_PATTERNS.items():
        match = re.search(config['regex'], url)
        if match:
            params = {}
            for i, param in enumerate(config['params']):
                params[param] = match.group(i + 1)
            return site, params
```

## 3. 통합 데이터 모델

### Product 클래스

**파일**: `app/models/product.py`

#### 데이터 구조:
```python
class Product(BaseModel):
    # 기본 정보
    site: str                    # 사이트명
    product_id: str              # 상품 ID
    url: str                     # 상품 URL
    
    # 상품 상세
    name: str                    # 상품명
    price: Optional[float]       # 가격 (JPY)
    currency: str = "JPY"        # 통화
    
    # 이미지 및 미디어
    image_url: Optional[str]     # 메인 이미지
    image_urls: List[str] = []   # 설명 영역 이미지
    thumbnail_images: List[str] = []  # 썸네일 갤러리
    large_images: List[str] = []      # 큰 이미지 갤러리
    
    # 상세 정보
    description: Optional[str]   # HTML 포함 상품 설명
    features: List[str] = []     # 주요 특징
    specifications: Dict[str, Any] = {}  # 사양
    
    # 물리적 정보
    weight: Optional[str]        # 무게
    dimensions: Optional[str]    # 치수
    
    # 카테고리 및 평점
    category: Optional[str]      # 카테고리
    brand: Optional[str]         # 브랜드
    rating: Optional[float]      # 평점
    review_count: Optional[int]  # 리뷰 수
    
    # 변형 상품
    variants: List['Product'] = []  # 변형 상품들
    
    # 메타데이터
    scraped_at: datetime         # 스크래핑 시간
    site_specific_data: Dict[str, Any] = {}  # 사이트별 추가 데이터
```

#### Laravel 호환 변환:
```python
def to_laravel_format(self) -> Dict[str, Any]:
    return {
        'site': self.site,
        'product_id': self.product_id,
        'url': self.url,
        'name': self.name,
        'price': self.price,
        'image_url': self.image_url,
        'thumbnail_images': self.thumbnail_images,
        'large_images': self.large_images,
        'description': self.description,
        'features': self.features,
        # ... 기타 필드들
    }
```

## 4. Amazon 스크래퍼 구현

### AmazonScraper 클래스

**파일**: `app/scrapers/amazon/amazon_scraper.py`

#### 주요 특징:

1. **다단계 데이터 추출 전략**
```python
def _parse_product_page(self, soup: BeautifulSoup, asin: str, url: str) -> Product:
    # 1순위: JSON-LD 구조화 데이터
    structured_data = self._extract_json_ld_data(soup)
    
    # 2순위: 특정 HTML 셀렉터
    name = self._extract_title(soup, structured_data)
    price = self._extract_price(soup, structured_data)
    
    # 3순위: AI 기반 trafilatura fallback
    if not description and not features:
        smart_data = SmartExtractor.extract_with_trafilatura(html_content, url)
```

2. **고급 이미지 갤러리 추출**
```python
def _extract_image_gallery(self, soup: BeautifulSoup) -> tuple[List[str], List[str]]:
    # 1순위: Amazon JavaScript colorImages 파싱
    # 2순위: HTML 셀렉터 기반 추출
    # 3순위: 메인 이미지 기반 fallback
    return thumbnail_images[:6], large_images[:6]
```

3. **스마트 이미지 URL 정규화**
```python
def _normalize_amazon_image_url(self, url: str, size: str = 'large') -> str:
    # 기존 크기 파라미터 제거
    url = re.sub(r'\._[A-Z]{2}\d+_\.', '.', url)
    
    # 새로운 크기 파라미터 추가
    if size == 'thumbnail':
        url = url.replace('.jpg', '._SL75_.jpg')
    elif size == 'large':
        url = url.replace('.jpg', '._SL500_.jpg')
    
    return url
```

4. **일본 Amazon 전용 최적화**
```python
def _extract_description_html_jp(self, soup: BeautifulSoup) -> str:
    # A+ 콘텐츠 (aplus-v2)
    aplus_content = soup.find('div', {'cel_widget_id': 'aplus'})
    
    # 상품 설명 섹션
    feature_div = soup.find('div', {'id': 'feature-bullets'})
    
    # 상품 개요
    detail_section = soup.find('div', {'id': 'productDetails_detailBullets_sections1'})
```

## 5. 확장성 및 AI 기반 추출

### SmartExtractor 유틸리티

**파일**: `app/utils/smart_extractor.py`

#### Trafilatura 활용:
```python
class SmartExtractor:
    @staticmethod
    def extract_with_trafilatura(html_content: str, url: str) -> Dict[str, Any]:
        """AI 기반 콘텐츠 추출"""
        return {
            'title': extract_title(html_content),
            'description': extract_content(html_content),
            'metadata': extract_metadata(html_content)
        }
```

## 6. 에러 처리 및 안정성

### 커스텀 예외 계층

**파일**: `app/core/exceptions.py`

```python
class ScrapingError(Exception):
    """기본 스크래핑 에러"""

class ProductNotFoundError(ScrapingError):
    """상품을 찾을 수 없음"""

class ScrapingTimeoutError(ScrapingError):
    """스크래핑 타임아웃"""

class UnsupportedSiteError(ScrapingError):
    """지원하지 않는 사이트"""

class ParsingError(ScrapingError):
    """파싱 오류"""
```

### 안전한 요청 처리:
```python
async def scrape_product(self, asin: str, **kwargs) -> Product:
    async with httpx.AsyncClient(headers=self.headers, timeout=30.0) as client:
        try:
            response = await client.get(url)
            response.raise_for_status()
            
        except httpx.TimeoutException:
            raise ScrapingTimeoutError(f"Amazon 스크래핑 타임아웃: {asin}")
        except httpx.HTTPStatusError as e:
            if e.response.status_code == 404:
                raise ProductNotFoundError(f"상품을 찾을 수 없습니다: {asin}")
```

## 7. 기술 스택

### 주요 라이브러리

**파일**: `requirements.txt`

```txt
fastapi>=0.100.0          # 웹 API 프레임워크
uvicorn>=0.20.0           # ASGI 서버
httpx>=0.24.0             # 비동기 HTTP 클라이언트
beautifulsoup4>=4.11.0    # HTML 파싱
lxml>=4.9.0               # XML/HTML 파서
pydantic>=2.0.0           # 데이터 검증
trafilatura>=1.6.0        # AI 기반 콘텐츠 추출
```

### 비동기 처리:
- **httpx**: 비동기 HTTP 요청
- **asyncio**: 동시성 처리
- **FastAPI**: 비동기 API 서버

### HTML 파싱:
- **BeautifulSoup4**: DOM 탐색 및 파싱
- **lxml**: 고성능 XML/HTML 파서
- **정규식**: JavaScript 데이터 추출

## 8. 배포 및 Docker 설정

### Dockerfile
```dockerfile
FROM python:3.11-slim

WORKDIR /app
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

COPY . .
EXPOSE 8001

CMD ["uvicorn", "main:app", "--host", "0.0.0.0", "--port", "8001"]
```

### Docker Compose 통합
```yaml
python-scraper:
  build: ./python-scraper
  ports:
    - "8001:8001"
  depends_on:
    - postgres
  environment:
    - PYTHONPATH=/app
```

## 9. 사용 예시

### 1) 기본 스크래핑
```bash
# Amazon 상품 스크래핑
curl "http://192.168.1.13:8001/ectokorea/api/v1/scrape/amazon?asin=B0DJNXJTJL"

# URL 자동 감지
curl "http://192.168.1.13:8001/ectokorea/api/v1/scrape?url=https://www.amazon.co.jp/dp/B0DJNXJTJL"
```

### 2) 응답 데이터 구조
```json
{
  "success": true,
  "site": "amazon",
  "data": {
    "site": "amazon",
    "product_id": "B0DJNXJTJL",
    "url": "https://www.amazon.co.jp/dp/B0DJNXJTJL",
    "name": "SEIDO ダブルウォール ジョッキグラス...",
    "price": 3990.0,
    "image_url": "https://m.media-amazon.com/images/I/714vOUomS8L...",
    "thumbnail_images": [
      "https://m.media-amazon.com/images/I/714vOUomS8L._SL75_.jpg",
      "https://m.media-amazon.com/images/I/814dW6OJDUL._SL75_.jpg",
      "..."
    ],
    "large_images": [
      "https://m.media-amazon.com/images/I/714vOUomS8L._SL500_.jpg",
      "https://m.media-amazon.com/images/I/814dW6OJDUL._SL500_.jpg",
      "..."
    ],
    "description": "<div>상품 설명 HTML...</div>",
    "features": ["특징1", "특징2", "..."],
    "weight": "0.310",
    "dimensions": "20.3 x 18.4 x 13.5 cm",
    "category": "ホーム＆キッチン > ...",
    "brand": "SEIDO",
    "scraped_at": "2025-08-21T05:01:40.690280"
  }
}
```

## 10. 향후 확장 계획

### 라쿠텐 스크래퍼 구현
- URL 패턴: `https://item.rakuten.co.jp/{shopId}/{itemCode}`
- 파라미터: `shopId`, `itemCode`
- 라쿠텐 특화 데이터 추출 로직

### JINS 스크래퍼 구현
- URL 패턴: `https://www.jins.com/jp/item/{productId}.html`
- 파라미터: `productId`
- 안경 전문 사이트 특화 데이터

### 추가 기능
1. **변형 상품 자동 수집**
2. **재고 상태 실시간 추적**
3. **가격 변동 모니터링**
4. **리뷰 및 평점 상세 분석**
5. **이미지 다운로드 및 로컬 저장**

## 11. 성능 및 모니터링

### 성능 최적화
- **비동기 처리**: httpx 기반 동시 요청
- **캐싱**: 반복 요청 방지
- **타임아웃 설정**: 30초 제한
- **에러 복구**: 3단계 fallback 시스템

### 모니터링 포인트
- 스크래핑 성공률
- 응답 시간
- 에러 발생률
- 사이트별 차단 감지

---

**작성일**: 2025-08-21  
**작성자**: Claude Code  
**버전**: 1.0  
**상태**: Amazon 구현 완료, 라쿠텐/JINS 구현 예정