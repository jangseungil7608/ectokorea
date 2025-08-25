from fastapi import APIRouter, HTTPException, Query
from typing import Optional

from app.core.scraper_factory import ScraperFactory
from app.core.exceptions import UnsupportedSiteError, ProductNotFoundError, ScrapingError

router = APIRouter(tags=["scraper"])


@router.get("/scrape")
async def scrape_by_url(
    url: str = Query(..., description="스크래핑할 상품 URL"),
    translate: bool = Query(True, description="한국어 번역 여부")
):
    """URL로 자동 사이트 감지 후 상품 스크래핑"""
    try:
        # URL에서 사이트와 파라미터 추출
        site, params = ScraperFactory.detect_site_from_url(url)
        
        # 스크래퍼 생성 및 실행
        scraper = ScraperFactory.create_scraper(site)
        
        # 번역 옵션 추가
        params['translate'] = translate
        result = await scraper.scrape_product(**params)
        
        return {
            "success": True,
            "site": site,
            "translated": translate,
            "data": result.to_laravel_format()
        }
        
    except UnsupportedSiteError as e:
        raise HTTPException(status_code=400, detail=str(e))
    except ProductNotFoundError as e:
        raise HTTPException(status_code=404, detail=str(e))
    except ScrapingError as e:
        raise HTTPException(status_code=500, detail=f"스크래핑 실패: {str(e)}")


@router.get("/scrape/{site}")
async def scrape_by_site_params(
    site: str,
    asin: Optional[str] = Query(None, description="Amazon ASIN"),
    shopId: Optional[str] = Query(None, description="Rakuten Shop ID"),
    itemCode: Optional[str] = Query(None, description="Rakuten Item Code"),
    productId: Optional[str] = Query(None, description="JINS Product ID"),
    translate: bool = Query(True, description="한국어 번역 여부")
):
    """사이트별 파라미터로 직접 스크래핑"""
    try:
        scraper = ScraperFactory.create_scraper(site)
        
        # 사이트별 파라미터 설정
        if site == 'amazon':
            if not asin:
                raise HTTPException(status_code=400, detail="Amazon은 asin 파라미터가 필요합니다")
            result = await scraper.scrape_product(asin=asin, translate=translate)
            
        elif site == 'rakuten':
            if not shopId or not itemCode:
                raise HTTPException(status_code=400, detail="Rakuten은 shopId와 itemCode 파라미터가 필요합니다")
            result = await scraper.scrape_product(shopId=shopId, itemCode=itemCode, translate=translate)
            
        elif site == 'jins':
            if not productId:
                raise HTTPException(status_code=400, detail="JINS는 productId 파라미터가 필요합니다")
            result = await scraper.scrape_product(productId=productId, translate=translate)
            
        else:
            raise HTTPException(status_code=400, detail=f"지원하지 않는 사이트: {site}")
        
        return {
            "success": True,
            "site": site,
            "translated": translate,
            "data": result.to_laravel_format()
        }
        
    except UnsupportedSiteError as e:
        raise HTTPException(status_code=400, detail=str(e))
    except ProductNotFoundError as e:
        raise HTTPException(status_code=404, detail=str(e))
    except ScrapingError as e:
        raise HTTPException(status_code=500, detail=f"스크래핑 실패: {str(e)}")


@router.get("/sites")
async def get_supported_sites():
    """지원하는 사이트 목록 조회"""
    return {
        "supported_sites": ScraperFactory.get_supported_sites(),
        "url_patterns": {
            "amazon": "https://www.amazon.co.jp/dp/{asin}",
            "rakuten": "https://item.rakuten.co.jp/{shopId}/{itemCode}",
            "jins": "https://www.jins.com/jp/item/{productId}.html"
        }
    }