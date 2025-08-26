from typing import Optional, List, Dict, Any
from pydantic import BaseModel, HttpUrl
from datetime import datetime


class Product(BaseModel):
    """통합 상품 데이터 모델"""
    
    # 기본 정보
    site: str                           # 사이트명 (amazon, rakuten, jins)
    product_id: str                     # 상품 ID (ASIN, itemCode, productId)
    url: str                           # 상품 URL
    
    # 상품 상세
    name: str                          # 상품명
    original_name: Optional[str] = None # 원문 상품명
    price: Optional[float] = None      # 가격 (JPY)
    original_price: Optional[float] = None  # 원가 (할인전)
    currency: str = "JPY"              # 통화
    
    # 이미지 및 미디어
    image_url: Optional[str] = None    # 메인 이미지
    image_urls: List[str] = []         # 설명 영역의 추가 이미지들
    
    # 이미지 갤러리 (Amazon 스타일)
    thumbnail_images: List[str] = []   # 썸네일 이미지들
    large_images: List[str] = []       # 큰 이미지들 (썸네일과 1:1 대응)
    
    # 상세 정보
    description: Optional[str] = None   # 상품 설명
    original_description: Optional[str] = None # 원문 상품 설명
    features: List[str] = []           # 주요 특징
    original_features: List[str] = []  # 원문 주요 특징
    specifications: Dict[str, Any] = {}  # 사양 정보
    
    # 물리적 정보
    weight: Optional[str] = None       # 무게 (kg 단위 문자열)
    dimensions: Optional[str] = None   # 치수 (cm 단위)
    
    # 카테고리 및 분류
    category: Optional[str] = None     # 카테고리
    original_category: Optional[str] = None # 원문 카테고리
    brand: Optional[str] = None        # 브랜드
    
    # 재고 및 배송
    in_stock: bool = True              # 재고 여부
    shipping_info: Optional[str] = None # 배송 정보
    
    # 평점 및 리뷰
    rating: Optional[float] = None     # 평점
    review_count: Optional[int] = None # 리뷰 수
    
    # 변형 상품
    variants: List['Product'] = []     # 변형 상품들
    is_variant: bool = False           # 변형 상품 여부
    parent_id: Optional[str] = None    # 부모 상품 ID
    variant_type: Optional[str] = None # 변형 타입 (색상, 사이즈 등)
    variant_value: Optional[str] = None # 변형 값
    
    # 메타데이터
    scraped_at: datetime = datetime.now()  # 스크래핑 시간
    site_specific_data: Dict[str, Any] = {}  # 사이트별 추가 데이터
    
    class Config:
        # 순환 참조 허용 (variants 필드)
        arbitrary_types_allowed = True
        
    def to_laravel_format(self) -> Dict[str, Any]:
        """Laravel 호환 형식으로 변환"""
        return {
            'site': self.site,
            'product_id': self.product_id,
            'url': self.url,
            'name': self.name,
            'original_name': self.original_name,
            'price': self.price,
            'image_url': self.image_url,
            'image_urls': self.image_urls,
            'thumbnail_images': self.thumbnail_images,
            'large_images': self.large_images,
            'description': self.description,
            'original_description': self.original_description,
            'features': self.features,
            'original_features': self.original_features,
            'weight': self.weight,
            'dimensions': self.dimensions,
            'category': self.category,
            'original_category': self.original_category,
            'brand': self.brand,
            'in_stock': self.in_stock,
            'rating': self.rating,
            'review_count': self.review_count,
            'variants': [v.to_laravel_format() for v in self.variants],
            'scraped_at': self.scraped_at.isoformat(),
            'site_specific_data': self.site_specific_data
        }