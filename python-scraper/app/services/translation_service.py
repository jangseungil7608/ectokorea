import asyncio
import httpx
import logging
from typing import Optional, Dict, List
from dataclasses import dataclass
import re
import json

# 로거 설정
logger = logging.getLogger(__name__)


@dataclass
class TranslationResult:
    """번역 결과 데이터 클래스"""
    original_text: str
    translated_text: str
    source_language: str = "ja"
    target_language: str = "ko"
    service: str = "papago"


class TranslationService:
    """번역 서비스 클래스 - Naver Papago API 사용"""
    
    def __init__(self):
        # 최신 작동하는 Papago 웹 API 방식들
        self.papago_configs = [
            {
                'url': 'https://papago.naver.com/apis/n2mt/translate',
                'method': 'detect_and_translate',
                'requires_token': True
            },
            {
                'url': 'https://papago.naver.com/apis/langs/dect',  # 언어 감지 + 번역
                'method': 'detect_first',
                'requires_token': False
            }
        ]
        
        self.base_headers = {
            'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Accept': '*/*',
            'Accept-Language': 'ko-KR,ko;q=0.9,ja;q=0.8,en;q=0.7',
            'Accept-Encoding': 'gzip, deflate, br',
            'Origin': 'https://papago.naver.com',
            'Referer': 'https://papago.naver.com/',
            'Sec-Ch-Ua': '"Not_A Brand";v="8", "Chromium";v="120", "Google Chrome";v="120"',
            'Sec-Ch-Ua-Mobile': '?0',
            'Sec-Ch-Ua-Platform': '"Windows"',
            'Sec-Fetch-Dest': 'empty',
            'Sec-Fetch-Mode': 'cors',
            'Sec-Fetch-Site': 'same-origin',
            'Cache-Control': 'no-cache',
            'Pragma': 'no-cache'
        }
    
    async def translate_text(self, text: str, target_lang: str = "ko", source_lang: str = "ja") -> Optional[TranslationResult]:
        """텍스트 번역 - Papago API 사용"""
        if not text or not text.strip():
            return None
            
        text = text.strip()
        
        # 이미 한국어인지 확인 (간단한 휴리스틱)
        if self._is_korean_text(text):
            return TranslationResult(
                original_text=text,
                translated_text=text,
                source_language=source_lang,
                target_language=target_lang,
                service="no_translation_needed"
            )
        
        # 텍스트가 너무 길면 분할 처리 (Papago 제한: 5000자)
        if len(text) > 4000:
            return await self._translate_long_text(text, target_lang, source_lang)
        
        # 1순위: DeepL API 시도 (가장 품질 좋음)
        deepl_result = await self._try_deepl_translate(text, target_lang, source_lang)
        if deepl_result:
            return deepl_result
        
        # 2순위: Microsoft Translator 시도
        microsoft_result = await self._try_microsoft_translate(text, target_lang, source_lang)
        if microsoft_result:
            return microsoft_result
        
        # 3순위: Google Translate 사용 (최후 수단)
        logger.info("🔄 모든 고품질 번역 실패, Google Translate 사용...")
        return await self._fallback_translate(text, target_lang, source_lang)
    
    async def _translate_long_text(self, text: str, target_lang: str, source_lang: str) -> Optional[TranslationResult]:
        """긴 텍스트를 분할하여 번역"""
        try:
            # 문장 단위로 분할 (일본어 기준)
            sentences = re.split(r'[。！？]', text)
            translated_sentences = []
            
            current_chunk = ""
            for sentence in sentences:
                sentence = sentence.strip()
                if not sentence:
                    continue
                    
                # 청크 크기 체크 (4000자 제한)
                if len(current_chunk + sentence) > 3500:
                    if current_chunk:
                        # 현재 청크 번역
                        result = await self.translate_text(current_chunk, target_lang, source_lang)
                        if result and result.translated_text:
                            translated_sentences.append(result.translated_text)
                        current_chunk = sentence
                    else:
                        # 단일 문장이 너무 긴 경우 그대로 추가
                        translated_sentences.append(sentence)
                else:
                    current_chunk += sentence + "。"
            
            # 마지막 청크 처리
            if current_chunk:
                result = await self.translate_text(current_chunk, target_lang, source_lang)
                if result and result.translated_text:
                    translated_sentences.append(result.translated_text)
            
            if translated_sentences:
                full_translation = " ".join(translated_sentences)
                return TranslationResult(
                    original_text=text,
                    translated_text=full_translation,
                    source_language=source_lang,
                    target_language=target_lang,
                    service="papago_chunked"
                )
                
        except Exception as e:
            logger.error(f"긴 텍스트 번역 오류: {e}")
            
        return None
    
    async def _fallback_translate(self, text: str, target_lang: str, source_lang: str) -> Optional[TranslationResult]:
        """Papago 실패 시 대체 번역 방법"""
        try:
            # 간단한 Google Translate fallback
            fallback_url = "https://translate.googleapis.com/translate_a/single"
            params = {
                'client': 'gtx',
                'sl': source_lang,
                'tl': target_lang,
                'dt': 't',
                'q': text
            }
            
            fallback_headers = {
                'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
            }
            
            async with httpx.AsyncClient(headers=fallback_headers, timeout=10.0) as client:
                response = await client.get(fallback_url, params=params)
                response.raise_for_status()
                
                result = response.json()
                
                if result and len(result) > 0 and result[0]:
                    translated_text = ""
                    for chunk in result[0]:
                        if chunk and len(chunk) > 0 and chunk[0]:
                            translated_text += chunk[0]
                    
                    if translated_text:
                        logger.info(f"✅ Google 번역 성공: '{text[:50]}...' → '{translated_text.strip()[:50]}...'")
                        return TranslationResult(
                            original_text=text,
                            translated_text=translated_text.strip(),
                            source_language=source_lang,
                            target_language=target_lang,
                            service="google_translate"
                        )
                        
        except Exception as e:
            logger.error(f"Google 번역 오류: {e}")
            
        return None
    
    async def _try_papago_web_translate(self, text: str, target_lang: str, source_lang: str) -> Optional[TranslationResult]:
        """최신 Papago 웹 방식으로 번역 시도"""
        try:
            logger.info("🔍 Papago 웹 방식 번역 시도...")
            
            async with httpx.AsyncClient(timeout=20.0) as client:
                # 1단계: Papago 메인 페이지 접속해서 세션 쿠키 획득
                logger.debug("📡 Papago 메인 페이지 접속...")
                main_response = await client.get(
                    "https://papago.naver.com/",
                    headers={
                        'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                        'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
                        'Accept-Language': 'ko-KR,ko;q=0.9,ja;q=0.8,en;q=0.7',
                        'Accept-Encoding': 'gzip, deflate, br',
                        'Connection': 'keep-alive',
                        'Upgrade-Insecure-Requests': '1',
                        'Sec-Fetch-Dest': 'document',
                        'Sec-Fetch-Mode': 'navigate',
                        'Sec-Fetch-Site': 'none',
                        'Sec-Fetch-User': '?1',
                    }
                )
                
                if main_response.status_code != 200:
                    logger.warning(f"⚠️ Papago 메인 페이지 접속 실패: {main_response.status_code}")
                    return None
                
                # 2단계: 페이지에서 필요한 토큰/정보 추출 (필요시)
                main_html = main_response.text
                
                # 3단계: 정확한 Papago 번역 API 호출
                translate_endpoint = "https://papago.naver.com/apis/n2mt/translate"
                
                try:
                    logger.debug(f"📡 Papago API 호출: {translate_endpoint}")
                    
                    # 실제 브라우저와 동일한 정확한 데이터 형식
                    data = {
                        'deviceId': 'undefined',
                        'locale': 'ko',
                        'dict': 'true',
                        'dictDisplay': '30',
                        'honorific': 'false',
                        'instant': 'false', 
                        'paging': 'false',
                        'source': source_lang,
                        'target': target_lang,
                        'text': text
                    }
                    
                    # 세션 쿠키를 포함한 완벽한 브라우저 헤더
                    headers = {
                        'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                        'Accept': 'application/json, text/plain, */*',
                        'Accept-Language': 'ko-KR,ko;q=0.9,ja;q=0.8,en;q=0.7',
                        'Accept-Encoding': 'gzip, deflate, br',
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                        'Origin': 'https://papago.naver.com',
                        'Referer': 'https://papago.naver.com/',
                        'X-Requested-With': 'XMLHttpRequest',
                        'Sec-Ch-Ua': '"Not_A Brand";v="8", "Chromium";v="120", "Google Chrome";v="120"',
                        'Sec-Ch-Ua-Mobile': '?0',
                        'Sec-Ch-Ua-Platform': '"Windows"',
                        'Sec-Fetch-Dest': 'empty',
                        'Sec-Fetch-Mode': 'cors',
                        'Sec-Fetch-Site': 'same-origin',
                        'Cache-Control': 'no-cache',
                        'Pragma': 'no-cache'
                    }
                    
                    # 추가 우회 시도 - 더 많은 시간 간격과 재시도
                    import asyncio
                    await asyncio.sleep(1)  # 1초 대기
                    
                    response = await client.post(translate_endpoint, data=data, headers=headers)
                    
                    logger.info(f"📡 Papago API 응답 상태: {response.status_code}")
                    logger.debug(f"📝 응답 헤더: {dict(response.headers)}")
                    logger.debug(f"📝 응답 내용: {response.text[:300]}...")
                    
                    if response.status_code == 200:
                        result = response.json()
                        
                        # Papago 응답에서 번역 결과 추출
                        translated_text = None
                        if result.get('translatedText'):
                            translated_text = result['translatedText']
                        
                        if translated_text and translated_text.strip():
                            logger.info(f"✅ Papago API 번역 성공: '{text[:50]}...' → '{translated_text[:50]}...'")
                            return TranslationResult(
                                original_text=text,
                                translated_text=translated_text.strip(),
                                source_language=source_lang,
                                target_language=target_lang,
                                service="papago_api"
                            )
                        else:
                            logger.warning(f"⚠️ Papago API 응답에 번역 결과 없음: {result}")
                    else:
                        logger.warning(f"⚠️ Papago API 요청 실패: {response.status_code} - {response.text}")
                        
                except Exception as e:
                    logger.error(f"❌ Papago API 호출 실패: {e}")
                
                logger.warning("⚠️ 모든 Papago 웹 방식 실패")
                return None
                
        except Exception as e:
            logger.error(f"❌ Papago 웹 방식 전체 실패: {e}")
            return None
    
    async def _try_deepl_translate(self, text: str, target_lang: str, source_lang: str) -> Optional[TranslationResult]:
        """DeepL API 무료 번역 시도 (월 50만자 한도)"""
        try:
            logger.info("🔍 DeepL 무료 API 번역 시도...")
            
            # DeepL 무료 API 엔드포인트
            api_url = "https://api-free.deepl.com/v2/translate"
            
            # API 키 없이 웹 방식으로 시도 (DeepL 웹 번역기)
            web_url = "https://www.deepl.com/translator"
            
            async with httpx.AsyncClient(timeout=15.0) as client:
                # 1단계: DeepL 웹 번역기 시도
                try:
                    logger.debug("📡 DeepL 웹 번역기 시도...")
                    
                    # 언어 코드 변환
                    deepl_source = 'JA' if source_lang == 'ja' else source_lang.upper()
                    deepl_target = 'KO' if target_lang == 'ko' else target_lang.upper()
                    
                    # DeepL 웹 번역 요청
                    data = {
                        'jsonrpc': '2.0',
                        'method': 'LMT_handle_texts',
                        'params': {
                            'texts': [{'text': text}],
                            'splitting': 'newlines',
                            'lang': {
                                'source_lang_user_selected': deepl_source,
                                'target_lang': deepl_target
                            },
                            'timestamp': 1640000000000  # 임시 타임스탬프
                        },
                        'id': 1
                    }
                    
                    headers = {
                        'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                        'Accept': '*/*',
                        'Accept-Language': 'ko-KR,ko;q=0.9,ja;q=0.8,en;q=0.7',
                        'Accept-Encoding': 'gzip, deflate, br',
                        'Content-Type': 'application/json',
                        'Origin': 'https://www.deepl.com',
                        'Referer': 'https://www.deepl.com/translator',
                        'Sec-Ch-Ua': '"Not_A Brand";v="8", "Chromium";v="120", "Google Chrome";v="120"',
                        'Sec-Ch-Ua-Mobile': '?0',
                        'Sec-Ch-Ua-Platform': '"Windows"',
                        'Sec-Fetch-Dest': 'empty',
                        'Sec-Fetch-Mode': 'cors',
                        'Sec-Fetch-Site': 'same-origin'
                    }
                    
                    # DeepL 웹 API 엔드포인트
                    deepl_api_endpoint = "https://www2.deepl.com/jsonrpc"
                    
                    response = await client.post(deepl_api_endpoint, json=data, headers=headers)
                    
                    logger.info(f"📡 DeepL 웹 API 응답 상태: {response.status_code}")
                    logger.debug(f"📝 DeepL 응답 내용: {response.text[:300]}...")
                    
                    if response.status_code == 200:
                        result = response.json()
                        
                        # DeepL 웹 API 응답 파싱
                        if result.get('result') and result['result'].get('texts'):
                            translated_text = result['result']['texts'][0]['text']
                            
                            if translated_text and translated_text.strip():
                                logger.info(f"✅ DeepL 웹 번역 성공: '{text[:50]}...' → '{translated_text[:50]}...'")
                                return TranslationResult(
                                    original_text=text,
                                    translated_text=translated_text.strip(),
                                    source_language=source_lang,
                                    target_language=target_lang,
                                    service="deepl_web"
                                )
                        else:
                            logger.warning(f"⚠️ DeepL 웹 API 응답에 번역 결과 없음: {result}")
                    else:
                        logger.warning(f"⚠️ DeepL 웹 API 요청 실패: {response.status_code}")
                        
                except Exception as web_error:
                    logger.error(f"❌ DeepL 웹 번역 실패: {web_error}")
                
                return None
                
        except Exception as e:
            logger.error(f"❌ DeepL 전체 번역 실패: {e}")
            return None
    
    async def _try_microsoft_translate(self, text: str, target_lang: str, source_lang: str) -> Optional[TranslationResult]:
        """Microsoft Translator 무료 번역 시도 (월 200만자 한도)"""
        try:
            logger.info("🔍 Microsoft Translator 무료 번역 시도...")
            
            # Microsoft Translator 웹 API (무료)
            api_url = "https://api.cognitive.microsofttranslator.com/translate"
            
            async with httpx.AsyncClient(timeout=15.0) as client:
                # 1단계: Microsoft Translator 웹 방식 시도
                try:
                    logger.debug("📡 Microsoft Translator 웹 API 시도...")
                    
                    params = {
                        'api-version': '3.0',
                        'from': source_lang,
                        'to': target_lang
                    }
                    
                    data = [{'Text': text}]
                    
                    headers = {
                        'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebLib/537.36',
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    }
                    
                    response = await client.post(api_url, json=data, params=params, headers=headers)
                    
                    logger.info(f"📡 Microsoft 응답 상태: {response.status_code}")
                    logger.debug(f"📝 Microsoft 응답 내용: {response.text[:300]}...")
                    
                    if response.status_code == 200:
                        result = response.json()
                        
                        if result and len(result) > 0 and result[0].get('translations'):
                            translated_text = result[0]['translations'][0]['text']
                            
                            if translated_text and translated_text.strip():
                                logger.info(f"✅ Microsoft 번역 성공: '{text[:50]}...' → '{translated_text[:50]}...'")
                                return TranslationResult(
                                    original_text=text,
                                    translated_text=translated_text.strip(),
                                    source_language=source_lang,
                                    target_language=target_lang,
                                    service="microsoft_translator"
                                )
                        else:
                            logger.warning(f"⚠️ Microsoft 응답에 번역 결과 없음: {result}")
                    else:
                        logger.warning(f"⚠️ Microsoft API 요청 실패: {response.status_code}")
                        
                except Exception as ms_error:
                    logger.error(f"❌ Microsoft 번역 실패: {ms_error}")
                
                return None
                
        except Exception as e:
            logger.error(f"❌ Microsoft 전체 번역 실패: {e}")
            return None
    
    async def translate_product_name(self, name: str) -> str:
        """상품명 번역"""
        if not name:
            return name
            
        result = await self.translate_text(name)
        if result:
            # 상품명 특화 후처리
            translated = result.translated_text
            
            # 번역 품질 개선을 위한 후처리
            translated = self._improve_product_name_translation(translated)
            
            return translated
        
        return name
    
    async def translate_category(self, category: str) -> str:
        """카테고리 번역"""
        if not category:
            return category
            
        # 카테고리 경로 분리 (예: "가전 > 컴퓨터 > 노트북")
        if " > " in category:
            parts = category.split(" > ")
            translated_parts = []
            
            for part in parts:
                part = part.strip()
                if part:
                    result = await self.translate_text(part)
                    if result:
                        translated_parts.append(result.translated_text)
                    else:
                        translated_parts.append(part)
            
            return " > ".join(translated_parts)
        else:
            result = await self.translate_text(category)
            if result:
                return result.translated_text
        
        return category
    
    async def translate_description(self, description: str) -> str:
        """상품 설명 번역 (HTML 태그 보존)"""
        if not description:
            return description
            
        # HTML 태그가 포함된 경우와 일반 텍스트를 구분
        if "<" in description and ">" in description:
            return await self._translate_html_content(description)
        else:
            result = await self.translate_text(description)
            if result:
                return result.translated_text
            return description
    
    async def translate_features(self, features: List[str]) -> List[str]:
        """상품 특징 리스트 번역"""
        if not features:
            return features
            
        translated_features = []
        
        # 병렬 번역 처리
        tasks = []
        for feature in features:
            if feature and feature.strip():
                tasks.append(self.translate_text(feature.strip()))
        
        if tasks:
            results = await asyncio.gather(*tasks, return_exceptions=True)
            
            for i, result in enumerate(results):
                if isinstance(result, TranslationResult) and result.translated_text:
                    translated_features.append(result.translated_text)
                elif i < len(features):
                    # 번역 실패 시 원본 유지
                    translated_features.append(features[i])
        
        return translated_features
    
    async def _translate_html_content(self, html_content: str) -> str:
        """HTML 콘텐츠 번역 (태그는 보존)"""
        try:
            from bs4 import BeautifulSoup
            
            soup = BeautifulSoup(html_content, 'html.parser')
            
            # 텍스트 노드들을 찾아서 번역
            for element in soup.find_all(text=True):
                text = element.strip()
                if text and not element.parent.name in ['script', 'style']:
                    # 의미있는 텍스트만 번역
                    if len(text) > 3 and not text.isdigit():
                        result = await self.translate_text(text)
                        if result and result.translated_text:
                            element.replace_with(result.translated_text)
            
            return str(soup)
            
        except Exception as e:
            print(f"HTML 번역 오류: {e}")
            # HTML 파싱 실패 시 전체 텍스트를 번역
            result = await self.translate_text(html_content)
            if result:
                return result.translated_text
            return html_content
    
    def _is_korean_text(self, text: str) -> bool:
        """텍스트가 한국어인지 간단히 판별"""
        if not text:
            return False
            
        # 한글 문자가 포함되어 있는지 확인
        korean_pattern = re.compile(r'[ㄱ-ㅎㅏ-ㅣ가-힣]')
        korean_chars = len(korean_pattern.findall(text))
        total_chars = len([c for c in text if c.isalnum() or ord(c) > 127])
        
        # 한글 문자가 전체 문자의 30% 이상이면 한국어로 판별
        if total_chars > 0:
            return (korean_chars / total_chars) > 0.3
            
        return False
    
    def _improve_product_name_translation(self, translated_name: str) -> str:
        """상품명 번역 품질 개선 (Papago 최적화)"""
        if not translated_name:
            return translated_name
            
        # Papago 번역 특화 개선사항
        corrections = {
            # 브랜드명 관련 (Papago가 한글로 번역하는 경우 수정)
            "아마존": "Amazon",
            "애플": "Apple", 
            "삼성": "Samsung",
            "엘지": "LG",
            "소니": "Sony",
            "캐논": "Canon",
            "니콘": "Nikon",
            "파나소닉": "Panasonic",
            
            # 단위 관련
            "그램": "g",
            "킬로그램": "kg", 
            "밀리리터": "ml",
            "리터": "L",
            "센티미터": "cm",
            "밀리미터": "mm",
            
            # 색상 관련 (Papago 특화)
            "검은색": "블랙",
            "검정색": "블랙",
            "흰색": "화이트",
            "하얀색": "화이트",
            "빨간색": "레드",
            "파란색": "블루",
            "노란색": "옐로우",
            "회색": "그레이",
            "갈색": "브라운",
            
            # 일본어 특수 용어
            "세트": "Set",
            "팩": "Pack",
            "피스": "Piece",
            "개들이": "개입",
            "개 들어": "개입",
            "매들이": "매입",
            
            # Papago 오번역 수정
            "상품": "",  # 불필요한 '상품' 제거
            "제품": "",  # 불필요한 '제품' 제거
            "아이템": "",
        }
        
        result = translated_name
        for korean, corrected in corrections.items():
            if corrected:
                result = result.replace(korean, corrected)
            else:
                # 빈 문자열인 경우 제거
                result = result.replace(korean, "")
        
        # 연속된 공백 정리
        result = re.sub(r'\s+', ' ', result).strip()
        
        return result
    
    async def translate_product_data(self, product_data: Dict) -> Dict:
        """상품 데이터 전체 번역"""
        translated_data, _ = await self.translate_product_data_with_info(product_data)
        return translated_data
    
    async def translate_product_data_with_info(self, product_data: Dict) -> tuple[Dict, List[Dict]]:
        """상품 데이터 전체 번역 + 서비스 정보 반환"""
        translated_data = product_data.copy()
        translation_services = []
        
        # 상품명 번역
        if product_data.get('name'):
            result = await self.translate_product_name(product_data['name'])
            if result != product_data['name']:  # 번역이 되었다면
                translated_data['name'] = result
                # 번역 서비스 확인을 위해 실제 번역 결과 확인
                translation_result = await self.translate_text(product_data['name'])
                if translation_result:
                    translation_services.append({
                        'field': 'name',
                        'service': translation_result.service,
                        'original': product_data['name'][:50] + '...' if len(product_data['name']) > 50 else product_data['name'],
                        'translated': result[:50] + '...' if len(result) > 50 else result
                    })
        
        # 카테고리 번역
        if product_data.get('category'):
            result = await self.translate_category(product_data['category'])
            if result != product_data['category']:  # 번역이 되었다면
                translated_data['category'] = result
                translation_result = await self.translate_text(product_data['category'])
                if translation_result:
                    translation_services.append({
                        'field': 'category',
                        'service': translation_result.service,
                        'original': product_data['category'][:50] + '...' if len(product_data['category']) > 50 else product_data['category'],
                        'translated': result[:50] + '...' if len(result) > 50 else result
                    })
        
        # 설명 번역
        if product_data.get('description'):
            result = await self.translate_description(product_data['description'])
            if result != product_data['description']:  # 번역이 되었다면
                translated_data['description'] = result
                # 설명은 길어서 처음 100자만 확인
                sample_text = product_data['description'][:100]
                translation_result = await self.translate_text(sample_text)
                if translation_result:
                    translation_services.append({
                        'field': 'description',
                        'service': translation_result.service,
                        'original': sample_text + '...',
                        'translated': 'HTML content translated'
                    })
        
        # 특징 번역
        if product_data.get('features') and isinstance(product_data['features'], list):
            result = await self.translate_features(product_data['features'])
            if result != product_data['features']:  # 번역이 되었다면
                translated_data['features'] = result
                # 첫 번째 특징으로 서비스 확인
                if product_data['features']:
                    translation_result = await self.translate_text(product_data['features'][0])
                    if translation_result:
                        translation_services.append({
                            'field': 'features',
                            'service': translation_result.service,
                            'original': f'{len(product_data["features"])} features',
                            'translated': f'{len(result)} features translated'
                        })
        
        return translated_data, translation_services


# 전역 번역 서비스 인스턴스
translation_service = TranslationService()