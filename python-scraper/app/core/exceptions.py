class ScrapingError(Exception):
    """스크래핑 관련 기본 예외"""
    pass


class UnsupportedSiteError(ScrapingError):
    """지원하지 않는 사이트 예외"""
    pass


class ProductNotFoundError(ScrapingError):
    """상품을 찾을 수 없는 예외"""
    pass


class ScrapingTimeoutError(ScrapingError):
    """스크래핑 타임아웃 예외"""
    pass


class ProxyError(ScrapingError):
    """프록시 관련 예외"""
    pass


class ParsingError(ScrapingError):
    """HTML 파싱 예외"""
    pass