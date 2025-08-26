from fastapi import FastAPI, HTTPException
from fastapi.middleware.cors import CORSMiddleware
import uvicorn
import logging
import sys
from pathlib import Path

from app.api.scraper import router as scraper_router

# 로깅 설정
def setup_logging():
    """로깅 설정 - 콘솔과 파일에 동시 출력"""
    
    # 루트 로거 설정
    root_logger = logging.getLogger()
    root_logger.setLevel(logging.INFO)
    
    # 기존 핸들러 제거
    for handler in root_logger.handlers[:]:
        root_logger.removeHandler(handler)
    
    # 포맷터 설정
    formatter = logging.Formatter(
        '%(asctime)s - %(name)s - %(levelname)s - %(message)s',
        datefmt='%Y-%m-%d %H:%M:%S'
    )
    
    # 콘솔 출력 핸들러
    console_handler = logging.StreamHandler(sys.stdout)
    console_handler.setLevel(logging.INFO)
    console_handler.setFormatter(formatter)
    root_logger.addHandler(console_handler)
    
    # 파일 출력 핸들러
    log_file = Path("test.log")
    file_handler = logging.FileHandler(log_file, mode='a', encoding='utf-8')
    file_handler.setLevel(logging.DEBUG)  # 파일에는 더 자세한 로그
    file_handler.setFormatter(formatter)
    root_logger.addHandler(file_handler)
    
    logging.info("로깅 설정 완료 - 콘솔 및 test.log 파일 출력")

# 로깅 초기화
setup_logging()

app = FastAPI(
    title="EctoKorea Multi-Site Scraper",
    description="일본 쇼핑몰 상품 정보 스크래핑 API",
    version="1.0.0"
)

# CORS 설정 (Laravel 및 프론트엔드에서 호출 허용)
app.add_middleware(
    CORSMiddleware,
    allow_origins=[
        "http://192.168.1.13:8080",      # Laravel 로컬
        "https://devseungil.mydns.jp",   # Laravel 외부
        "http://localhost:8080",
        "http://127.0.0.1:8080",
        "http://192.168.1.13:5173",      # 프론트엔드 로컬
        "https://devseungil.synology.me", # 프론트엔드 외부
        "http://localhost:5173",
        "http://127.0.0.1:5173"
    ],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# API 라우터 등록
app.include_router(scraper_router, prefix="/ectokorea/api/v1")

@app.get("/")
async def root():
    return {
        "message": "EctoKorea Multi-Site Scraper API",
        "version": "1.0.0",
        "supported_sites": ["amazon", "rakuten", "jins"]
    }

@app.get("/ectokorea")
async def ectokorea_root():
    return {
        "message": "EctoKorea Multi-Site Scraper API",
        "version": "1.0.0",
        "supported_sites": ["amazon", "rakuten", "jins"],
        "endpoints": {
            "scrape_by_url": "/ectokorea/api/v1/scrape?url={product_url}",
            "scrape_amazon": "/ectokorea/api/v1/scrape/amazon?asin={asin}",
            "scrape_rakuten": "/ectokorea/api/v1/scrape/rakuten?shopId={shopId}&itemCode={itemCode}",
            "scrape_jins": "/ectokorea/api/v1/scrape/jins?productId={productId}",
            "supported_sites": "/ectokorea/api/v1/sites"
        }
    }

@app.get("/health")
async def health_check():
    return {"status": "healthy"}

if __name__ == "__main__":
    uvicorn.run("main:app", host="0.0.0.0", port=8001, reload=True)