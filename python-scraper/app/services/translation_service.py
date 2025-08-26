import asyncio
import logging
import httpx
from typing import Optional, Dict, List
from dataclasses import dataclass

# 로거 설정
logger = logging.getLogger(__name__)

@dataclass
class TranslationResult:
    """번역 결과"""
    original_text: str
    translated_text: str
    source_language: str
    target_language: str
    service_used: str
    success: bool
    error_message: Optional[str] = None

class GoogleTranslationService:
    """Google Translate 기반 번역 서비스"""
    
    def __init__(self):
        logger.info("🌐 Google Translate 번역 서비스 초기화 완료")
    
    async def translate_text(self, text: str, target_lang: str = 'ko', source_lang: str = 'ja') -> TranslationResult:
        """텍스트 번역"""
        if not text or not text.strip():
            return TranslationResult(
                original_text=text,
                translated_text=text,
                source_language=source_lang,
                target_language=target_lang,
                service_used="google",
                success=True
            )
        
        try:
            logger.info(f"🔄 Google로 번역 중: '{text[:50]}...'")
            
            async with httpx.AsyncClient() as client:
                response = await client.get(
                    "https://translate.googleapis.com/translate_a/single",
                    params={
                        'client': 'gtx',
                        'sl': source_lang,
                        'tl': target_lang,
                        'dt': 't',
                        'q': text
                    },
                    timeout=15
                )
                
                if response.status_code == 200:
                    result = response.json()
                    translated = ''.join([part[0] for part in result[0] if part[0]])
                    
                    logger.info(f"✅ Google 번역 성공:")
                    logger.info(f"   원문: '{text[:100]}...' " if len(text) > 100 else f"   원문: '{text}'")
                    logger.info(f"   번역: '{translated[:100]}...' " if len(translated) > 100 else f"   번역: '{translated}'")
                    
                    return TranslationResult(
                        original_text=text,
                        translated_text=translated,
                        source_language=source_lang,
                        target_language=target_lang,
                        service_used="google",
                        success=True
                    )
                else:
                    logger.error(f"❌ Google API 오류: {response.status_code}")
                    
        except Exception as e:
            logger.error(f"❌ Google 번역 실패: {str(e)}")
        
        # 번역 실패시 원문 반환
        return TranslationResult(
            original_text=text,
            translated_text=text,
            source_language=source_lang,
            target_language=target_lang,
            service_used="google",
            success=False,
            error_message="Google 번역 실패"
        )
    
    async def translate_list(self, texts: List[str], target_lang: str = 'ko', source_lang: str = 'ja') -> List[str]:
        """텍스트 리스트 번역 (개별 번역)"""
        if not texts:
            return []
        
        logger.info(f"🔄 Google로 {len(texts)}개 항목 개별 번역 중...")
        
        results = []
        for i, text in enumerate(texts):
            if text and text.strip():
                result = await self.translate_text(text.strip(), target_lang, source_lang)
                results.append(result.translated_text if result.success else text)
                logger.info(f"   {i+1}/{len(texts)} 완료")
            else:
                results.append(text)
        
        logger.info(f"✅ Google 개별 번역 완료: {len(results)}개 항목")
        return results

class TranslationService:
    """Google Translate 전용 번역 서비스"""
    
    def __init__(self):
        self.google_service = GoogleTranslationService()
        logger.info("🚀 번역 서비스 초기화 완료 (Google Translate)")
    
    async def translate_product_name(self, name: str) -> str:
        """상품명 번역"""
        if not name or not name.strip():
            return name
        
        logger.info("📦 상품명 번역 시작")
        result = await self.google_service.translate_text(name.strip())
        return result.translated_text if result.success else name
    
    async def translate_category(self, category: str) -> str:
        """카테고리 번역"""
        if not category or not category.strip():
            return category
        
        logger.info("🏷️ 카테고리 번역 시작")
        result = await self.google_service.translate_text(category.strip())
        return result.translated_text if result.success else category
    
    async def translate_description(self, description: str) -> str:
        """상품 설명 번역"""
        if not description or not description.strip():
            return description
        
        logger.info("📄 상품 설명 번역 시작")
        result = await self.google_service.translate_text(description.strip())
        return result.translated_text if result.success else description
    
    async def translate_features(self, features: List[str]) -> List[str]:
        """상품 특징 리스트 번역"""
        if not features:
            return features
        
        logger.info("✨ 상품 특징 번역 시작")
        # 빈 문자열 제거
        non_empty_features = [f.strip() for f in features if f and f.strip()]
        if not non_empty_features:
            return features
        
        # 개별 번역 사용
        translated_features = await self.google_service.translate_list(non_empty_features)
        return translated_features
    
    async def translate_product_data_with_info(self, product_dict: Dict) -> tuple[Dict, List[Dict]]:
        """상품 데이터 번역 및 서비스 정보 반환 (Amazon 스크래퍼 호환)"""
        logger.info("🔄 상품 데이터 전체 번역 시작")
        translated_dict = product_dict.copy()
        services_info = []
        
        try:
            # 상품명 번역
            if product_dict.get('name'):
                translated_name = await self.translate_product_name(product_dict['name'])
                translated_dict['name'] = translated_name
                services_info.append({
                    "field": "name",
                    "service": "google",
                    "original": product_dict['name'][:50] + "..." if len(product_dict['name']) > 50 else product_dict['name'],
                    "translated": translated_name[:50] + "..." if len(translated_name) > 50 else translated_name
                })
            
            # 카테고리 번역
            if product_dict.get('category'):
                translated_category = await self.translate_category(product_dict['category'])
                translated_dict['category'] = translated_category
                services_info.append({
                    "field": "category", 
                    "service": "google",
                    "original": product_dict['category'],
                    "translated": translated_category
                })
            
            # 상품 설명 번역
            if product_dict.get('description'):
                translated_description = await self.translate_description(product_dict['description'])
                translated_dict['description'] = translated_description
                services_info.append({
                    "field": "description",
                    "service": "google", 
                    "original": product_dict['description'][:100] + "..." if len(product_dict['description']) > 100 else product_dict['description'],
                    "translated": translated_description[:100] + "..." if len(translated_description) > 100 else translated_description
                })
            
            # 상품 특징 번역
            if product_dict.get('features') and isinstance(product_dict['features'], list):
                translated_features = await self.translate_features(product_dict['features'])
                translated_dict['features'] = translated_features
                services_info.append({
                    "field": "features",
                    "service": "google",
                    "original": f"{len(product_dict['features'])}개 특징",
                    "translated": f"{len(translated_features)}개 특징 번역 완료"
                })
            
            logger.info(f"✅ 전체 번역 완료 - {len(services_info)}개 필드 번역")
            
        except Exception as e:
            logger.error(f"❌ 제품 데이터 번역 중 오류: {str(e)}")
            services_info.append({
                "field": "all",
                "service": "translation_failed", 
                "error": str(e)
            })
        
        return translated_dict, services_info

# 전역 번역 서비스 인스턴스
translation_service = TranslationService()