from abc import ABC, abstractmethod
from typing import Dict, List, Optional, Union
from app.models.product import Product


class BaseScraper(ABC):
    """모든 사이트 스크래퍼의 기본 인터페이스"""
    
    def __init__(self):
        self.site_name = self.__class__.__name__.replace('Scraper', '').lower()
    
    @abstractmethod
    async def scrape_product(self, **kwargs) -> Product:
        """상품 정보 스크래핑
        
        Args:
            **kwargs: 사이트별 필요한 파라미터
                - Amazon: asin
                - Rakuten: shopId, itemCode  
                - JINS: productId
        """
        pass
    
    @abstractmethod
    async def scrape_variants(self, **kwargs) -> List[Product]:
        """변형 상품 스크래핑"""
        pass
    
    @abstractmethod
    def validate_url(self, url: str) -> bool:
        """URL 유효성 검증"""
        pass
    
    @abstractmethod
    def extract_product_params(self, url: str) -> Dict[str, str]:
        """URL에서 상품 파라미터 추출"""
        pass
    
    @abstractmethod
    def build_product_url(self, **kwargs) -> str:
        """파라미터로 상품 URL 생성"""
        pass