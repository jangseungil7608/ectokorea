import re
from typing import Dict, Tuple, List
from app.core.base_scraper import BaseScraper
from app.core.exceptions import UnsupportedSiteError


class ScraperFactory:
    """스크래퍼 팩토리 클래스"""
    
    # 사이트별 URL 패턴 정의
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
    
    @classmethod
    def create_scraper(cls, site: str) -> BaseScraper:
        """사이트명으로 스크래퍼 인스턴스 생성"""
        if site == 'amazon':
            from app.scrapers.amazon.amazon_scraper import AmazonScraper
            return AmazonScraper()
        elif site == 'rakuten':
            # TODO: 라쿠텐 스크래퍼 구현 후 임포트
            raise UnsupportedSiteError(f"라쿠텐 스크래퍼는 아직 구현되지 않았습니다")
        elif site == 'jins':
            # TODO: JINS 스크래퍼 구현 후 임포트  
            raise UnsupportedSiteError(f"JINS 스크래퍼는 아직 구현되지 않았습니다")
        else:
            raise UnsupportedSiteError(f"지원하지 않는 사이트: {site}")
    
    @classmethod
    def detect_site_from_url(cls, url: str) -> Tuple[str, Dict[str, str]]:
        """URL에서 사이트와 상품 파라미터 추출
        
        Returns:
            Tuple[site_name, params_dict]
        """
        for site, config in cls.SITE_PATTERNS.items():
            match = re.search(config['regex'], url)
            if match:
                params = {}
                for i, param in enumerate(config['params']):
                    params[param] = match.group(i + 1)
                return site, params
        
        raise UnsupportedSiteError(f"지원하지 않는 URL: {url}")
    
    @classmethod
    def build_url(cls, site: str, **kwargs) -> str:
        """사이트와 파라미터로 URL 생성"""
        if site not in cls.SITE_PATTERNS:
            raise UnsupportedSiteError(f"지원하지 않는 사이트: {site}")
        
        config = cls.SITE_PATTERNS[site]
        try:
            return config['url_template'].format(**kwargs)
        except KeyError as e:
            missing_param = str(e).strip("'")
            raise ValueError(f"{site} 사이트에는 {missing_param} 파라미터가 필요합니다")
    
    @classmethod
    def get_supported_sites(cls) -> List[str]:
        """지원하는 사이트 목록 반환"""
        return list(cls.SITE_PATTERNS.keys())