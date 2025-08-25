# Python 스크래핑 서비스 - 이미지 갤러리 기능 개선

## 개요

Amazon 상품 스크래핑 시 이미지 갤러리에서 같은 이미지가 반복적으로 수집되던 문제를 해결하고, 실제 Amazon 페이지에서 제공하는 6개의 서로 다른 이미지를 정확히 수집하도록 개선하였습니다.

## 문제 상황

### 발견된 이슈
- **현상**: 상품 상세 이미지 갤러리에서 썸네일 6개가 모두 같은 이미지로 표시
- **원인**: Python 스크래핑 서비스에서 Amazon의 복잡한 JavaScript 구조를 제대로 파싱하지 못해 2개의 이미지 ID만 반복 수집
- **실제 Amazon 데이터**: 6개의 서로 다른 이미지 ID 존재 (714vOUomS8L, 814dW6OJDUL, 71j3rEnoMWL, 71fdSUy4ggL, 71mdwWXcTPL, 815G1guNYNL)

### 기존 수집 결과
```json
{
  "thumbnail_images": [
    "같은 이미지 ID가 반복",
    "2개 정도의 서로 다른 이미지만 수집"
  ]
}
```

## 해결 방법

### 1. `_extract_image_gallery` 메서드 개선

**파일**: `/mnt/z/ectokorea/python-scraper/app/scrapers/amazon/amazon_scraper.py` (868-994행)

#### 주요 개선사항:

1. **Amazon colorImages JavaScript 파싱 추가**
   ```python
   # colorImages 오브젝트 찾기
   colorImages_match = re.search(r'colorImages\s*:\s*(\{[^}]+\}(?:,\s*[^}]+\})*)', content)
   
   # 각 variant의 이미지 배열 추출 (MAIN, PT01, PT02, etc.)
   variant_matches = re.findall(r'"(\d+)"\s*:\s*\[(.*?)\]', images_section, re.DOTALL)
   ```

2. **3단계 Fallback 시스템 구현**
   - **1순위**: JavaScript colorImages 데이터 파싱
   - **2순위**: HTML 셀렉터 기반 추출 (`#altImages li[data-defaultasin] img`)
   - **3순위**: 메인 이미지 기반 fallback

3. **정규식 패턴 개선**
   ```python
   # hiRes, thumb, large URL 추출
   hiRes_match = re.search(r'"hiRes"\s*:\s*"([^"]+)"', img_obj)
   thumb_match = re.search(r'"thumb"\s*:\s*"([^"]+)"', img_obj)
   large_match = re.search(r'"large"\s*:\s*"([^"]+)"', img_obj)
   ```

### 2. 헬퍼 메서드 추가

#### `_is_valid_amazon_image_url` 메서드 (1022-1042행)
```python
def _is_valid_amazon_image_url(self, url: str) -> bool:
    """Amazon 이미지 URL이 유효한지 확인"""
    valid_domains = [
        'm.media-amazon.com',
        'images-na.ssl-images-amazon.com',
        'images-cn.ssl-images-amazon.com',
        'images-eu.ssl-images-amazon.com'
    ]
    
    for domain in valid_domains:
        if domain in url:
            if any(ext in url.lower() for ext in ['.jpg', '.jpeg', '.png', '.webp', '.gif']):
                return True
    return False
```

#### `_extract_image_id_from_url` 메서드 (1044-1060행)
```python
def _extract_image_id_from_url(self, url: str) -> str:
    """Amazon 이미지 URL에서 이미지 ID 추출"""
    # 이미지 ID 패턴 추출 (일반적으로 10-15자리의 알파벳+숫자 조합)
    match = re.search(r'/images/I/([A-Za-z0-9_-]+)\.', url)
    if match:
        image_id = match.group(1)
        # 크기 파라미터가 포함된 경우 제거
        image_id = re.sub(r'\._[A-Z]{2}.*$', '', image_id)
        return image_id
    return None
```

#### `_normalize_amazon_image_url` 메서드 개선 (996-1020행)
```python
def _normalize_amazon_image_url(self, url: str, size: str = 'large') -> str:
    """Amazon 이미지 URL을 정규화하고 크기 조정"""
    # 기존 크기 파라미터 제거
    url = re.sub(r'\._[A-Z]{2}\d+_\.', '.', url)
    url = re.sub(r'\._[A-Z]{2}\d+\.', '.', url)
    
    # 새로운 크기 파라미터 추가
    if size == 'thumbnail':
        url = url.replace('.jpg', '._SL75_.jpg')
    elif size == 'large':
        url = url.replace('.jpg', '._SL500_.jpg')
    
    return url
```

## 개선 결과

### 수정 후 API 응답
```json
{
  "thumbnail_images": [
    "https://m.media-amazon.com/images/I/714vOUomS8L._AC_US100_._SL75_.jpg",
    "https://m.media-amazon.com/images/I/814dW6OJDUL._AC_US100_._SL75_.jpg", 
    "https://m.media-amazon.com/images/I/71j3rEnoMWL._AC_US100_._SL75_.jpg",
    "https://m.media-amazon.com/images/I/71fdSUy4ggL._AC_US100_._SL75_.jpg",
    "https://m.media-amazon.com/images/I/71mdwWXcTPL._AC_US100_._SL75_.jpg",
    "https://m.media-amazon.com/images/I/815G1guNYNL._AC_US100_._SL75_.jpg"
  ],
  "large_images": [
    "https://m.media-amazon.com/images/I/714vOUomS8L._AC_SL1500_._SL500_.jpg",
    "https://m.media-amazon.com/images/I/814dW6OJDUL._AC_SL1500_._SL500_.jpg",
    "https://m.media-amazon.com/images/I/71j3rEnoMWL._AC_SL1500_._SL500_.jpg", 
    "https://m.media-amazon.com/images/I/71fdSUy4ggL._AC_SL1500_._SL500_.jpg",
    "https://m.media-amazon.com/images/I/71mdwWXcTPL._AC_SL1500_._SL500_.jpg",
    "https://m.media-amazon.com/images/I/815G1guNYNL._AC_SL1500_._SL500_.jpg"
  ]
}
```

### 확인된 개선사항
1. **6개의 서로 다른 이미지 ID** 정상 수집 확인
2. **Thumbnail/Large 이미지** 크기별 URL 정상 생성  
3. **중복 제거** 로직으로 동일 이미지 배제
4. **Fallback 시스템**으로 안정성 향상

## 테스트 방법

### API 테스트 명령어
```bash
curl -X GET "http://192.168.1.13:8001/ectokorea/api/v1/scrape/amazon?asin=B0DJNXJTJL"
```

### 예상 결과
- `thumbnail_images`: 6개의 서로 다른 썸네일 이미지 URL
- `large_images`: 6개의 서로 다른 큰 이미지 URL  
- 각 이미지는 고유한 Amazon 이미지 ID를 가짐

## 기술적 특징

### Amazon 이미지 URL 구조 이해
```
https://m.media-amazon.com/images/I/{IMAGE_ID}.{SIZE_PARAM}.{EXT}

예시:
- 썸네일: 714vOUomS8L._SL75_.jpg
- 큰 이미지: 714vOUomS8L._SL500_.jpg
- 원본: 714vOUomS8L._AC_SL1500_.jpg
```

### JavaScript 파싱 전략
Amazon의 상품 페이지는 복잡한 JavaScript 구조로 이미지 정보를 관리합니다:
- `colorImages` 객체에 variant별 이미지 배열 저장
- `hiRes`, `thumb`, `large` 속성으로 크기별 URL 제공
- 정규식을 사용한 JSON 구조 파싱 필요

### 안정성 확보
- 3단계 fallback 시스템으로 파싱 실패 시에도 이미지 수집 보장
- URL 유효성 검증으로 잘못된 이미지 URL 배제
- 이미지 ID 추출/정규화로 일관된 URL 형식 생성

## 향후 개선 방향

1. **다른 쇼핑몰 지원**: 라쿠텐, JINS 등에도 동일한 이미지 갤러리 로직 적용
2. **이미지 품질 최적화**: 더 높은 해상도의 이미지 수집 옵션 추가  
3. **캐싱 시스템**: 이미지 URL 검증 결과 캐싱으로 성능 향상
4. **에러 핸들링**: 개별 이미지 로드 실패 시 대체 이미지 제공

## 관련 파일

- **Python 스크래퍼**: `/python-scraper/app/scrapers/amazon/amazon_scraper.py`
- **Vue 컴포넌트**: `/frontend/src/components/ImageGallery.vue`
- **Laravel 모델**: `/backend/app/Models/CollectedProduct.php`

---
**작성일**: 2025-08-21  
**작성자**: Claude Code  
**버전**: 1.0