import re
import asyncio
import json
from typing import Dict, List
import httpx
from bs4 import BeautifulSoup

from app.core.base_scraper import BaseScraper
from app.models.product import Product
from app.core.exceptions import ProductNotFoundError, ParsingError, ScrapingError, ScrapingTimeoutError
from app.utils.smart_extractor import SmartExtractor
from app.services.translation_service import translation_service
import logging

# 로거 설정
logger = logging.getLogger(__name__)


class AmazonScraper(BaseScraper):
    """Amazon.co.jp 스크래퍼"""
    
    def __init__(self):
        super().__init__()
        self.base_url = "https://www.amazon.co.jp"
        self.headers = {
            'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
            'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            'Accept-Language': 'ja,en-US;q=0.9,en;q=0.8',
            'Accept-Encoding': 'gzip, deflate, br',
            'Connection': 'keep-alive',
            'Upgrade-Insecure-Requests': '1',
        }
    
    async def scrape_product(self, asin: str, translate: bool = True, **kwargs) -> Product:
        """ASIN으로 Amazon 상품 정보 스크래핑"""
        url = self.build_product_url(asin=asin)
        
        async with httpx.AsyncClient(headers=self.headers, timeout=30.0) as client:
            try:
                response = await client.get(url)
                response.raise_for_status()
                
                soup = BeautifulSoup(response.text, 'lxml')
                product = self._parse_product_page(soup, asin, url)
                
                # 번역 옵션이 활성화된 경우 번역 수행
                if translate:
                    product = await self._translate_product(product)
                
                return product
                
            except httpx.TimeoutException:
                raise ScrapingTimeoutError(f"Amazon 스크래핑 타임아웃: {asin}")
            except httpx.HTTPStatusError as e:
                if e.response.status_code == 404:
                    raise ProductNotFoundError(f"Amazon 상품을 찾을 수 없습니다: {asin}")
                raise ScrapingError(f"Amazon 스크래핑 실패: {e}")
    
    async def scrape_variants(self, asin: str, **kwargs) -> List[Product]:
        """Amazon 변형 상품 스크래핑"""
        # 기본 상품 먼저 스크래핑
        main_product = await self.scrape_product(asin)
        variants = [main_product]
        
        # TODO: 변형 상품 로직 구현
        return variants
    
    def validate_url(self, url: str) -> bool:
        """Amazon URL 유효성 검증"""
        return bool(re.search(r'amazon\.co\.jp/dp/[A-Z0-9]{10}', url))
    
    def extract_product_params(self, url: str) -> Dict[str, str]:
        """URL에서 ASIN 추출"""
        match = re.search(r'amazon\.co\.jp/dp/([A-Z0-9]{10})', url)
        if match:
            return {'asin': match.group(1)}
        raise ValueError(f"유효하지 않은 Amazon URL: {url}")
    
    def build_product_url(self, asin: str, **kwargs) -> str:
        """ASIN으로 Amazon URL 생성"""
        return f"https://www.amazon.co.jp/dp/{asin}"
    
    def _parse_product_page(self, soup: BeautifulSoup, asin: str, url: str) -> Product:
        """Amazon 상품 페이지 파싱"""
        try:
            # 1순위: JSON-LD 구조화 데이터에서 추출
            structured_data = self._extract_json_ld_data(soup)
            
            # 상품명 추출
            name = self._extract_title(soup, structured_data)
            
            # 가격 추출  
            price = self._extract_price(soup, structured_data)
            
            # 이미지 URL 추출
            image_url = self._extract_image_url(soup, structured_data)
            
            # Amazon 일본 섹션별 정확한 추출 (HTML 태그 포함)
            description = self._extract_description_html_jp(soup)
            features = self._extract_features_jp(soup)
            
            # 설명 영역 이미지 추출
            description_images = self._extract_description_images(soup)
            
            # description의 빈 div들을 실제 A+ Content 이미지로 채우기
            if description and 'aplus-3p-module-b' in description:
                # A+ Content 이미지들 수집 (aplus-media-library-service-media 포함)
                aplus_images = []
                all_img_elements = soup.find_all('img')
                for img in all_img_elements:
                    src = img.get('src') or img.get('data-src') or img.get('data-lazy-src')
                    if src and 'aplus-media-library-service-media' in src:
                        if src.startswith('//'):
                            src = 'https:' + src
                        elif src.startswith('/'):
                            src = 'https://www.amazon.co.jp' + src
                        if src not in aplus_images:
                            aplus_images.append(src)
                
                # 빈 div들을 이미지로 교체
                if aplus_images:
                    from bs4 import BeautifulSoup as BS
                    description_soup = BS(description, 'html.parser')
                    
                    # 빈 div들 찾기 (텍스트가 거의 없는 div)
                    empty_divs = []
                    for div in description_soup.find_all('div', class_='celwidget 3p-module-b'):
                        inner_div = div.find('div')
                        if inner_div and len(inner_div.get_text(strip=True)) < 10:  # 텍스트가 거의 없는 경우
                            empty_divs.append(inner_div)
                    
                    # 빈 div들에 이미지 삽입
                    for i, empty_div in enumerate(empty_divs):
                        if i < len(aplus_images):
                            empty_div.clear()
                            img_tag = description_soup.new_tag('img', src=aplus_images[i])
                            img_tag['alt'] = '商品の説明'
                            img_tag['style'] = 'max-width:100%; height:auto; display:block; margin:10px 0;'
                            empty_div.append(img_tag)
                    
                    description = str(description_soup)
            
            # 이미지 갤러리 추출 (썸네일 + 큰 이미지)
            thumbnail_images, large_images = self._extract_image_gallery(soup)
            
            # 둘 다 실패하면 trafilatura fallback
            if not description and not features:
                html_content = str(soup)
                smart_data = SmartExtractor.extract_with_trafilatura(html_content, url)
                description = description or smart_data.get('description')
                features = features or smart_data.get('features', [])
            
            # 스마트 추출로 무게/치수
            page_text = soup.get_text()
            smart_physical = SmartExtractor.extract_smart_weight_dimensions(page_text)
            
            # 카테고리 추출
            category = self._extract_category(soup)
            
            # 브랜드 추출
            brand = self._extract_brand(soup, structured_data)
            
            # 재고 상태 확인
            in_stock = self._check_stock_status(soup)
            
            # 무게/치수 (스마트 추출 우선)
            weight = smart_physical.get('weight')
            dimensions = smart_physical.get('dimensions')
            
            # 변형 상품 추출
            variants = self._extract_variants(soup, asin)
            
            # Amazon 고유 정보 추출
            site_specific_data = self._extract_amazon_specific_data(soup, structured_data)
            
            return Product(
                site='amazon',
                product_id=asin,
                url=url,
                name=name,
                price=price,
                image_url=image_url,
                image_urls=description_images,
                thumbnail_images=thumbnail_images,
                large_images=large_images,
                description=description,
                features=features,
                category=category,
                brand=brand,
                in_stock=in_stock,
                weight=weight,
                dimensions=dimensions,
                variants=variants,
                site_specific_data=site_specific_data
            )
            
        except Exception as e:
            raise ParsingError(f"Amazon 상품 파싱 실패: {e}")
    
    def _extract_json_ld_data(self, soup: BeautifulSoup) -> Dict:
        """JSON-LD 구조화 데이터 추출"""
        json_ld_scripts = soup.find_all('script', {'type': 'application/ld+json'})
        
        for script in json_ld_scripts:
            try:
                data = json.loads(script.string)
                if isinstance(data, list):
                    data = data[0]
                
                # Product 스키마인지 확인
                if data.get('@type') == 'Product':
                    return data
            except (json.JSONDecodeError, AttributeError):
                continue
        
        return {}
    
    def _extract_title(self, soup: BeautifulSoup, structured_data: Dict = None) -> str:
        """상품명 추출"""
        # 1순위: JSON-LD
        if structured_data and structured_data.get('name'):
            return structured_data['name']
            
        # 2순위: HTML 셀렉터
        selectors = [
            '#productTitle',
            '.product-title',
            '[data-automation-id="title"]'
        ]
        
        for selector in selectors:
            element = soup.select_one(selector)
            if element:
                return element.get_text(strip=True)
        
        raise ParsingError("상품명을 찾을 수 없습니다")
    
    def _extract_price(self, soup: BeautifulSoup, structured_data: Dict = None) -> float:
        """가격 추출"""
        # 1순위: JSON-LD 구조화 데이터
        if structured_data:
            offers = structured_data.get('offers', {})
            if isinstance(offers, list):
                offers = offers[0]
            price = offers.get('price') or offers.get('lowPrice')
            if price:
                return float(price)
        
        # 2순위: HTML 셀렉터
        price_selectors = [
            '.a-price-whole',
            '.a-offscreen',
            '[data-automation-id="price"]',
            '.a-price .a-offscreen'
        ]
        
        for selector in price_selectors:
            element = soup.select_one(selector)
            if element:
                price_text = element.get_text(strip=True)
                # 숫자만 추출 (￥, 콤마 제거)
                price_match = re.search(r'[\d,]+', price_text.replace('￥', '').replace(',', ''))
                if price_match:
                    return float(price_match.group())
        
        return None
    
    def _extract_image_url(self, soup: BeautifulSoup, structured_data: Dict = None) -> str:
        """메인 이미지 URL 추출"""
        image_selectors = [
            '#landingImage',
            '.a-dynamic-image',
            '[data-automation-id="image"]'
        ]
        
        for selector in image_selectors:
            element = soup.select_one(selector)
            if element:
                return element.get('src') or element.get('data-src')
        
        return None
    
    def _extract_description(self, soup: BeautifulSoup) -> str:
        """상품 설명 추출"""
        desc_selectors = [
            '#feature-bullets ul',
            '[data-automation-id="description"]',
            '.a-unordered-list.a-nostyle'
        ]
        
        for selector in desc_selectors:
            element = soup.select_one(selector)
            if element:
                return element.get_text(separator=' ', strip=True)
        
        return None
    
    def _extract_description_jp(self, soup: BeautifulSoup) -> str:
        """Amazon 일본 - 商品の説明 키워드 기반 추출"""
        
        # 1순위: "商品の説明" 키워드 주변에서 찾기
        description_keywords = ['商品の説明', '商品説明', 'Product Description']
        
        for keyword in description_keywords:
            # 키워드를 포함한 요소의 부모/형제에서 찾기
            keyword_elements = soup.find_all(text=re.compile(keyword))
            
            for text_node in keyword_elements:
                parent = text_node.parent
                
                # 부모의 다음 형제나 하위 요소에서 설명 찾기
                for sibling in parent.find_next_siblings():
                    content = self._extract_meaningful_text(sibling)
                    if content:
                        return content
                
                # 부모 요소 내에서 찾기
                while parent and parent.name != 'body':
                    content = self._find_description_content_near(parent)
                    if content:
                        return content
                    parent = parent.parent
        
        # 2순위: 일반적인 description 셀렉터들
        description_selectors = [
            '#productDescription .a-size-base',
            '#productDescription p',
            '#aplus-v2 .aplus-p2', 
            '.a-plus-module .aplus-p2',
            '[data-feature-name="productDescription"] .a-size-base'
        ]
        
        for selector in description_selectors:
            elements = soup.select(selector)
            for element in elements:
                text = element.get_text(strip=True)
                if text and len(text) > 30 and not self._is_code_or_style(text):
                    cleaned_text = self._clean_description_text(text)
                    if cleaned_text:
                        return cleaned_text[:200]
        
        # 3순위: 전체 페이지에서 패턴 매칭
        page_text = soup.get_text()
        
        # "商品の説明" 섹션 다음에 나오는 텍스트들 추출
        sections = page_text.split('商品の説明')
        if len(sections) > 1:
            # 설명 섹션 이후 텍스트에서 첫 번째 문단 추출
            desc_section = sections[1]
            # 다음 주요 섹션까지만 (この商品について 등)
            desc_section = desc_section.split('この商品について')[0]
            desc_section = desc_section.split('商品詳細')[0]
            
            # 첫 번째 의미있는 문장들 추출
            sentences = [s.strip() for s in desc_section.split('。') if s.strip()]
            meaningful_sentences = []
            
            for sentence in sentences[:3]:  # 최대 3문장
                if (len(sentence) > 20 and 
                    not self._is_code_or_style(sentence) and
                    any(char in sentence for char in 'はがのをにでとしてからでもこの')):
                    meaningful_sentences.append(sentence)
            
            if meaningful_sentences:
                return '。'.join(meaningful_sentences) + '。'
        
        return None
    
    def _extract_description_html_jp(self, soup: BeautifulSoup) -> str:
        """Amazon 일본 - 商品の説明 HTML 태그 포함 추출"""
        
        # 1순위: "商品の説明" 헤더가 있는 섹션을 정확히 찾기
        # "商品の説明" 헤더를 포함한 전체 섹션 추출
        product_description_h2 = soup.find('h2', string=re.compile('商品の説明'))
        if product_description_h2:
            # h2 태그의 부모 컨테이너 전체를 가져와서 이미지+텍스트 구조 보존
            parent_container = product_description_h2.find_parent(['div', 'section'])
            if parent_container:
                html_content = self._clean_description_html(parent_container)
                if html_content and len(html_content.strip()) > 100:
                    return html_content
        
        # 2순위: aplus 관련 셀렉터들 (하지만 "この商品について" 제외)
        description_selectors = [
            '#aplus-v2',  # A+ Content (이미지 포함 설명)
            '#aplus',
            '.a-plus-module',
            '[data-feature-name="aplus"]',
            '.aplus-v2',
            '.aplus-module',
            '[id*="aplus"]'
        ]
        
        for selector in description_selectors:
            element = soup.select_one(selector)
            if element:
                # "この商品について"가 아닌 실제 상품 설명 섹션인지 확인
                text_content = element.get_text()
                if ('商品の説明' in text_content and 'この商品について' not in text_content) or 'Product Description' in text_content:
                    # HTML 내용을 정리해서 반환 (이미지+텍스트 구조 보존)
                    html_content = self._clean_description_html(element)
                    if html_content and len(html_content.strip()) > 100:
                        return html_content
        
        # 2순위: "商品の説明" 키워드 주변 영역 (HTML 포함) - 더 광범위한 검색
        description_keywords = ['商品の説明', '商品説明', 'Product Description', '製品の説明', '詳細']
        
        for keyword in description_keywords:
            # 키워드를 포함한 요소들 검색
            keyword_elements = soup.find_all(text=re.compile(keyword, re.IGNORECASE))
            
            for text_node in keyword_elements:
                parent = text_node.parent
                
                # 키워드가 포함된 부모 요소 자체에서 설명 찾기 (この商品について 제외)
                if parent and parent.name in ['div', 'section', 'p']:
                    parent_text = parent.get_text()
                    if 'この商品について' not in parent_text:
                        html_content = self._clean_description_html(parent)
                        if html_content and len(html_content.strip()) > 100:
                            return html_content
                
                # 부모의 다음 형제나 하위 요소에서 설명 찾기
                for sibling in parent.find_next_siblings():
                    if sibling.name in ['div', 'p', 'section'] and sibling.get_text(strip=True):
                        html_content = self._clean_description_html(sibling)
                        if html_content and len(html_content.strip()) > 50:
                            return html_content
                
                # 부모 요소 내에서 찾기
                while parent and parent.name != 'body':
                    description_content = parent.find(['div', 'p'], recursive=False)
                    if description_content and description_content.get_text(strip=True):
                        html_content = self._clean_description_html(description_content)
                        if html_content and len(html_content.strip()) > 50:
                            return html_content
                    parent = parent.parent
        
        # 3순위: A+ Content 또는 상품 설명 이미지가 포함된 섹션 찾기
        aplus_image_sections = soup.find_all('div', {'data-aplus': True}) or soup.find_all('div', class_=re.compile('aplus'))
        for section in aplus_image_sections:
            if section.find('img'):  # 이미지가 포함된 섹션만
                html_content = self._clean_description_html(section)
                if html_content and len(html_content.strip()) > 100:
                    return html_content
        
        # 4순위: 기존 텍스트 추출 방법을 HTML로 대체
        description_text = self._extract_description_jp(soup)
        if description_text:
            # 텍스트를 간단한 HTML로 변환
            return f"<p>{description_text}</p>"
        
        return None
    
    def _clean_description_html(self, element) -> str:
        """HTML 설명 콘텐츠 정리"""
        if not element:
            return None
        
        # BeautifulSoup 복사본 생성
        from copy import copy
        clean_element = copy(element)
        
        # 불필요한 태그 제거
        for tag in clean_element.find_all(['script', 'style', 'noscript']):
            tag.decompose()
        
        # 위험한 속성 제거
        for tag in clean_element.find_all():
            # onclick, onload 등 이벤트 핸들러 제거
            attrs_to_remove = [attr for attr in tag.attrs if attr.startswith('on')]
            for attr in attrs_to_remove:
                del tag[attr]
            
            # javascript: 프로토콜 제거
            for attr in ['href', 'src']:
                if tag.get(attr) and tag[attr].startswith('javascript:'):
                    del tag[attr]
        
        # 이미지 src를 절대 URL로 변환
        for img in clean_element.find_all('img'):
            src = img.get('src') or img.get('data-src')
            if src:
                if src.startswith('//'):
                    img['src'] = 'https:' + src
                elif src.startswith('/'):
                    img['src'] = 'https://www.amazon.co.jp' + src
                elif not src.startswith('http'):
                    img['src'] = 'https://www.amazon.co.jp/' + src
                else:
                    img['src'] = src
                
                # alt 속성 추가 (없는 경우)
                if not img.get('alt'):
                    img['alt'] = '상품 이미지'
        
        # 링크 URL을 절대 URL로 변환
        for link in clean_element.find_all('a'):
            href = link.get('href')
            if href:
                if href.startswith('/'):
                    link['href'] = 'https://www.amazon.co.jp' + href
                elif not href.startswith('http'):
                    link['href'] = 'https://www.amazon.co.jp/' + href
        
        # 불필요한 CSS 클래스 제거 (Amazon 내부 클래스들)
        for tag in clean_element.find_all():
            if tag.get('class'):
                # Amazon 특화 클래스들 제거
                clean_classes = [cls for cls in tag['class'] 
                               if not any(amazon_cls in cls for amazon_cls in 
                                         ['a-', 'aplus-', 'cr-', 'reviews-'])]
                if clean_classes:
                    tag['class'] = clean_classes
                else:
                    del tag['class']
        
        # 빈 태그 제거
        for tag in clean_element.find_all():
            if not tag.get_text(strip=True) and not tag.find('img'):
                tag.decompose()
        
        html_content = str(clean_element)
        
        # JavaScript 코드나 CSS 코드가 포함된 텍스트 필터링
        if self._is_code_or_style(html_content):
            return None
        
        return html_content.strip()
    
    def _extract_meaningful_text(self, element) -> str:
        """요소에서 의미있는 텍스트 추출"""
        if not element:
            return None
            
        text = element.get_text(strip=True)
        
        # CSS, JavaScript, HTML 코드 필터링
        if (self._is_code_or_style(text)):
            return None
            
        if (text and 
            len(text) > 20 and 
            '商品の説明' not in text and
            'この商品について' not in text and
            not text.startswith('詳細') and
            not text.startswith('もっと')):
            return text
        
        return None
    
    def _is_code_or_style(self, text: str) -> bool:
        """텍스트가 코드나 스타일인지 판별"""
        code_indicators = [
            '{', '}', 'function', 'var ', 'window.', 'document.',
            'css', 'javascript', 'px', 'margin', 'padding',
            'width:', 'height:', 'background', '.aplus', 'rgba', 'font-',
            'border:', 'display:', 'position:', 'color:', 'ue.count',
            'logShoppableMetrics', 'innerHTML', 'addEventListener'
        ]
        
        # CSS/JS 패턴 체크
        text_lower = text.lower()
        css_pattern_count = sum(1 for indicator in code_indicators if indicator in text_lower)
        
        # 코드 패턴이 많거나 명확한 코드 구조면 코드로 판별
        return (css_pattern_count >= 2 or 
                text.strip().startswith('.') or
                text.strip().startswith('function') or
                'window.' in text or
                '\\n' in text and '{' in text)
    
    def _clean_description_text(self, text: str) -> str:
        """설명 텍스트 정리"""
        if not text:
            return None
            
        # 불필요한 패턴 제거
        patterns_to_remove = [
            r'\s+', r'^\s*[・\*\-]\s*', r'^\s*\d+[.)]\s*',
            r'詳細.*見る', r'もっと.*見る', r'クリック.*詳細'
        ]
        
        cleaned = text
        for pattern in patterns_to_remove:
            cleaned = re.sub(pattern, ' ', cleaned)
        
        cleaned = ' '.join(cleaned.split())  # 공백 정규화
        
        # 의미있는 일본어 문장인지 확인
        if (len(cleaned) > 20 and 
            any(char in cleaned for char in 'はがのをにでとしてからでもこの') and
            not self._is_code_or_style(cleaned)):
            return cleaned
        
        return None
    
    def _find_description_content_near(self, element) -> str:
        """商品の説明 근처에서 실제 설명 콘텐츠 찾기"""
        # 하위 요소들에서 텍스트 추출
        text_elements = element.find_all(['p', 'div', 'span'], recursive=True)
        
        for elem in text_elements:
            text = elem.get_text(strip=True)
            if (text and 
                len(text) > 20 and 
                '商品の説明' not in text and
                'この商品について' not in text and
                not text.startswith('詳細') and
                ('。' in text or 'です' in text or 'ます' in text or len(text) > 30)):
                return text
        
        return None
    
    def _extract_features_jp(self, soup: BeautifulSoup) -> List[str]:
        """Amazon 일본 - この商品について 섹션 추출"""
        features = []
        
        # 여러 패턴으로 시도
        selectors_to_try = [
            '#feature-bullets ul.a-unordered-list.a-vertical.a-spacing-mini li.a-spacing-mini span.a-list-item',
            '#feature-bullets ul li span.a-list-item',
            '#feature-bullets li span.a-list-item',
            '#feature-bullets span.a-list-item',
            '.a-unordered-list.a-vertical li span',
            '#feature-bullets li span'
        ]
        
        for selector in selectors_to_try:
            feature_items = soup.select(selector)
            
            if feature_items:
                for item in feature_items:
                    text = item.get_text(strip=True)
                    
                    # 유효한 특징만 추가
                    if (text and 
                        len(text) > 10 and 
                        not text.startswith('›') and
                        not 'もっと見る' in text and
                        text not in features):
                        features.append(text)
                
                if features:  # 하나라도 찾으면 멈춤
                    break
        
        return features[:5]
    
    def _extract_features(self, soup: BeautifulSoup) -> List[str]:
        """상품 특징 추출"""
        features = []
        
        # 다양한 특징 섹션 시도
        feature_selectors = [
            '#feature-bullets ul li span',
            '#feature-bullets li span', 
            '[data-feature-name="featureBullets"] span',
            '#featurebullets_feature_div li span',
            '.a-unordered-list.a-nostyle.a-vertical li span',
            '.a-spacing-mini span',
            '#productDescription span'
        ]
        
        for selector in feature_selectors:
            elements = soup.select(selector)
            for element in elements:
                text = element.get_text(strip=True)
                
                # 유효한 특징인지 필터링
                if (text and 
                    len(text) > 15 and len(text) < 200 and  # 적절한 길이
                    not text.startswith('詳細') and  # 불필요한 텍스트 제외
                    not text.startswith('もっと読む') and
                    '商品の説明' not in text and
                    text not in features):  # 중복 제거
                    
                    features.append(text)
                    
                    if len(features) >= 5:  # 최대 5개
                        break
            
            if len(features) >= 5:
                break
        
        # 특징이 없으면 상품 설명에서 문장 단위로 추출
        if not features:
            description_elements = soup.select('#productDescription p, #aplus p')
            for element in description_elements:
                sentences = element.get_text().split('。')
                for sentence in sentences:
                    sentence = sentence.strip()
                    if len(sentence) > 20 and len(sentence) < 150:
                        features.append(sentence + '。')
                        if len(features) >= 3:
                            break
        
        return features
    
    def _extract_category(self, soup: BeautifulSoup) -> str:
        """카테고리 추출"""
        category_selectors = [
            '#wayfinding-breadcrumbs_feature_div',
            '.a-breadcrumb',
            '[data-automation-id="breadcrumb"]'
        ]
        
        for selector in category_selectors:
            element = soup.select_one(selector)
            if element:
                return element.get_text(separator=' > ', strip=True)
        
        return None
    
    def _extract_brand(self, soup: BeautifulSoup, structured_data: Dict = None) -> str:
        """브랜드 추출"""
        # 1순위: JSON-LD 구조화 데이터
        if structured_data:
            brand = structured_data.get('brand')
            if brand:
                if isinstance(brand, dict):
                    return brand.get('name', '')
                return str(brand)
        
        # 2순위: HTML 셀렉터
        brand_selectors = [
            '#bylineInfo',
            '.a-brand',
            '[data-automation-id="brand"]'
        ]
        
        for selector in brand_selectors:
            element = soup.select_one(selector)
            if element:
                return element.get_text(strip=True)
        
        return None
    
    def _check_stock_status(self, soup: BeautifulSoup) -> bool:
        """재고 상태 확인"""
        # 품절 관련 텍스트 확인
        out_of_stock_indicators = [
            '在庫切れ', '一時的に在庫切れ', 'Currently unavailable'
        ]
        
        page_text = soup.get_text()
        for indicator in out_of_stock_indicators:
            if indicator in page_text:
                return False
        
        return True
    
    def _extract_weight(self, soup: BeautifulSoup, structured_data: Dict = None) -> str:
        """무게 정보 추출 (JSON-LD 우선, 텍스트 검색 후순위)"""
        
        # 1순위: JSON-LD 구조화 데이터
        if structured_data:
            weight = (structured_data.get('weight') or 
                     structured_data.get('additionalProperty', {}).get('weight'))
            if weight:
                return str(weight)
        
        # 2순위: 전체 페이지 텍스트에서 스마트 검색
        page_text = soup.get_text()
        
        # 무게 관련 패턴들 (더 유연하게)
        weight_patterns = [
            r'(?:重量|重さ|Weight|商品の重量|梱包重量|発送重量)[：:\s]*([0-9.,]+\s*(?:kg|g|キログラム|グラム|Kg|G))',
            r'([0-9.,]+\s*(?:kg|g|キログラム|グラム|Kg|G))',  # 단순 무게 패턴
        ]
        
        for pattern in weight_patterns:
            matches = re.findall(pattern, page_text, re.IGNORECASE | re.MULTILINE)
            for match in matches:
                if isinstance(match, tuple):
                    weight_str = match[0] if match[0] else match[1]
                else:
                    weight_str = match
                
                # 숫자 부분 추출
                number_match = re.search(r'([0-9.,]+)', weight_str)
                if number_match:
                    number = float(number_match.group(1).replace(',', ''))
                    
                    # kg로 정규화
                    if 'g' in weight_str.lower() and 'kg' not in weight_str.lower():
                        return f"{number/1000:.3f}"
                    else:
                        return f"{number:.3f}"
        
        return None
    
    def _extract_dimensions(self, soup: BeautifulSoup, structured_data: Dict = None) -> str:
        """치수 정보 추출 (JSON-LD 우선, 텍스트 검색 후순위)"""
        
        # 1순위: JSON-LD 구조화 데이터
        if structured_data:
            dimensions = (structured_data.get('depth') or 
                         structured_data.get('width') or
                         structured_data.get('height') or
                         structured_data.get('additionalProperty', {}).get('dimensions'))
            if dimensions:
                return str(dimensions)
        
        # 2순위: 전체 페이지 텍스트에서 스마트 검색
        page_text = soup.get_text()
        
        # 치수 관련 패턴들 (더 유연하게)
        dimension_patterns = [
            r'(?:商品寸法|製品サイズ|梱包サイズ|サイズ|Dimensions)[：:\s]*([0-9.,]+\s*[x×]\s*[0-9.,]+\s*[x×]\s*[0-9.,]+\s*(?:cm|mm))',
            r'([0-9.,]+\s*[x×]\s*[0-9.,]+\s*[x×]\s*[0-9.,]+\s*(?:cm|mm))',  # 단순 치수 패턴
            r'([0-9.,]+\s*[x×]\s*[0-9.,]+\s*(?:cm|mm))',  # 2차원 치수
        ]
        
        for pattern in dimension_patterns:
            matches = re.findall(pattern, page_text, re.IGNORECASE | re.MULTILINE)
            for match in matches:
                if isinstance(match, tuple):
                    dim_str = match[0] if match[0] else match[1]
                else:
                    dim_str = match
                
                # 유효한 치수인지 확인 (숫자가 포함되어 있고 적절한 범위)
                numbers = re.findall(r'([0-9.,]+)', dim_str)
                if numbers and len(numbers) >= 2:
                    # 첫 번째로 찾은 유효한 치수 반환
                    return dim_str.strip()
        
        return None
    
    def _extract_variants(self, soup: BeautifulSoup, base_asin: str) -> List[Product]:
        """Amazon 변형 상품 추출"""
        variants = []
        
        # 색상 변형 찾기
        color_variants = soup.select('#variation_color_name li[data-defaultasin]')
        for variant in color_variants:
            variant_asin = variant.get('data-defaultasin')
            color_name = variant.get('title', '').strip()
            
            if variant_asin and variant_asin != base_asin:
                variants.append(Product(
                    site='amazon',
                    product_id=variant_asin,
                    url=f"https://www.amazon.co.jp/dp/{variant_asin}",
                    name=f"변형상품 - {color_name}",
                    is_variant=True,
                    parent_id=base_asin,
                    variant_type="color",
                    variant_value=color_name
                ))
        
        # 사이즈 변형 찾기
        size_variants = soup.select('#variation_size_name li[data-defaultasin]')
        for variant in size_variants:
            variant_asin = variant.get('data-defaultasin')
            size_name = variant.get('title', '').strip()
            
            if variant_asin and variant_asin != base_asin:
                variants.append(Product(
                    site='amazon',
                    product_id=variant_asin,
                    url=f"https://www.amazon.co.jp/dp/{variant_asin}",
                    name=f"변형상품 - {size_name}",
                    is_variant=True,
                    parent_id=base_asin,
                    variant_type="size",
                    variant_value=size_name
                ))
        
        return variants
    
    def _extract_amazon_specific_data(self, soup: BeautifulSoup, structured_data: Dict = None) -> Dict:
        """Amazon 고유 정보 추출"""
        amazon_data = {}
        
        # 아마존 초이스 확인
        if soup.select_one('[data-csa-c-item-id="amzn1.sym.f3f9dd1d-4c77-4186-add7-9d2c6b15dbf0"]'):
            amazon_data['amazon_choice'] = True
        
        # 프라임 배송 확인
        prime_elements = soup.select('[aria-label*="Prime"], .a-icon-prime')
        if prime_elements:
            amazon_data['prime_eligible'] = True
        
        # 판매자 정보
        seller_element = soup.select_one('#merchant-info, #sellerProfileTriggerId')
        if seller_element:
            amazon_data['seller_name'] = seller_element.get_text(strip=True)
        
        # 리뷰 정보
        rating_element = soup.select_one('[data-hook="average-star-rating"] .a-icon-alt')
        if rating_element:
            rating_text = rating_element.get_text(strip=True)
            amazon_data['review_summary'] = rating_text
        
        review_count_element = soup.select_one('[data-hook="total-review-count"]')
        if review_count_element:
            amazon_data['review_count'] = review_count_element.get_text(strip=True)
        
        # 배송 옵션
        delivery_elements = soup.select('#deliveryBlockMessage, #mir-layout-DELIVERY_BLOCK')
        if delivery_elements:
            delivery_texts = [elem.get_text(strip=True) for elem in delivery_elements]
            amazon_data['delivery_options'] = delivery_texts
        
        # ASIN 정보
        amazon_data['asin'] = soup.select_one('[data-asin]')
        if amazon_data['asin']:
            amazon_data['asin'] = amazon_data['asin'].get('data-asin')
        
        return amazon_data
    
    def _extract_description_images(self, soup: BeautifulSoup) -> List[str]:
        """상품 설명 영역의 이미지들 추출"""
        description_images = []
        
        # 상품 설명 관련 영역들의 이미지 수집
        description_areas = [
            '#productDescription img',
            '#aplus-v2 img',
            '#aplus img', 
            '.a-plus-module img',
            '[data-feature-name="productDescription"] img',
            '.product-description img'
        ]
        
        for selector in description_areas:
            img_elements = soup.select(selector)
            for img in img_elements:
                src = img.get('src') or img.get('data-src') or img.get('data-lazy-src')
                if src:
                    # 상대 URL을 절대 URL로 변환
                    if src.startswith('//'):
                        src = 'https:' + src
                    elif src.startswith('/'):
                        src = 'https://www.amazon.co.jp' + src
                    
                    # 이미지 URL 정제 (크기 파라미터 제거 등)
                    src = src.split('?')[0]  # 쿼리 파라미터 제거
                    
                    # 유효한 이미지 URL인지 확인
                    if (src.startswith('http') and 
                        any(ext in src.lower() for ext in ['.jpg', '.jpeg', '.png', '.gif', '.webp']) and
                        src not in description_images):
                        description_images.append(src)
        
        # 중복 제거 및 최대 10개로 제한
        return list(dict.fromkeys(description_images))[:10]
    
    def _extract_image_gallery(self, soup: BeautifulSoup) -> tuple[List[str], List[str]]:
        """Amazon 이미지 갤러리 추출 (썸네일 + 큰 이미지)"""
        thumbnail_images = []
        large_images = []
        
        # 1순위: Amazon JavaScript에서 colorImages 데이터 추출 (가장 정확한 방법)
        script_tags = soup.find_all('script')
        for script in script_tags:
            if script.string and 'colorImages' in script.string:
                try:
                    # colorImages JSON 구조에서 이미지 데이터 추출
                    content = script.string
                    
                    # colorImages 오브젝트 찾기 - 더 유연한 패턴 (다양한 구조 지원)
                    colorImages_patterns = [
                        r'colorImages\s*:\s*\{([^}]*(?:\{[^}]*\}[^}]*)*)\}',
                        r'"colorImages"\s*:\s*\{([^}]*(?:\{[^}]*\}[^}]*)*)\}',
                        r'colorImages\s*=\s*\{([^}]*(?:\{[^}]*\}[^}]*)*)\}',
                        r'images\s*:\s*\{[^{]*colorImages[^}]*\{([^}]*(?:\{[^}]*\}[^}]*)*)\}'
                    ]
                    
                    colorImages_match = None
                    for pattern in colorImages_patterns:
                        colorImages_match = re.search(pattern, content, re.DOTALL)
                        if colorImages_match:
                            break
                    if colorImages_match:
                        images_section = colorImages_match.group(1)
                        
                        # 각 variant의 이미지 배열 추출 - 더 광범위한 패턴
                        variant_matches = re.findall(r'"((?:\d+|initial|MAIN|PT\d+))"\s*:\s*\[(.*?)\]', images_section, re.DOTALL)
                        
                        for variant_id, images_data in variant_matches:
                            # 각 variant의 이미지 객체들에서 hiRes, thumb, large 추출
                            image_objects = re.findall(r'\{([^}]+)\}', images_data)
                            
                            for img_obj in image_objects:
                                # hiRes, thumb, large URL 추출
                                hiRes_match = re.search(r'"hiRes"\s*:\s*"([^"]+)"', img_obj)
                                thumb_match = re.search(r'"thumb"\s*:\s*"([^"]+)"', img_obj)
                                large_match = re.search(r'"large"\s*:\s*"([^"]+)"', img_obj)
                                
                                if hiRes_match and thumb_match:
                                    hiRes_url = hiRes_match.group(1)
                                    thumb_url = thumb_match.group(1)
                                    large_url = large_match.group(1) if large_match else hiRes_url
                                    
                                    # 유효한 이미지 URL인지 확인
                                    if self._is_valid_amazon_image_url(hiRes_url):
                                        # 썸네일과 큰 이미지 URL 생성
                                        final_thumb = self._normalize_amazon_image_url(thumb_url, size='thumbnail')
                                        final_large = self._normalize_amazon_image_url(hiRes_url, size='large')
                                        
                                        if final_thumb not in thumbnail_images:
                                            thumbnail_images.append(final_thumb)
                                            large_images.append(final_large)
                        
                        if thumbnail_images:
                            break
                    
                    # 대안: 모든 hiRes 패턴으로 직접 검색
                    if not thumbnail_images:
                        # 더 광범위한 이미지 URL 패턴 검색
                        hiRes_patterns = re.findall(r'"hiRes"\s*:\s*"([^"]+)"', content)
                        
                        # large 패턴도 추가로 검색
                        large_patterns = re.findall(r'"large"\s*:\s*"([^"]+)"', content)
                        hiRes_patterns.extend(large_patterns)
                        
                        # main 패턴 검색
                        main_patterns = re.findall(r'"main"\s*:\s*"([^"]+)"', content)
                        hiRes_patterns.extend(main_patterns)
                        
                        # thumb에서도 고해상도 변환 가능한 것들 검색
                        thumb_patterns = re.findall(r'"thumb"\s*:\s*"([^"]+)"', content)
                        hiRes_patterns.extend(thumb_patterns)
                        
                        if not hiRes_patterns:
                            # 일반 Amazon 이미지 URL 패턴 검색 (더 광범위)
                            general_patterns = re.findall(r'"(https://[^"]*media-amazon\.com[^"]*\.(jpg|jpeg|png))"', content)
                            hiRes_patterns.extend([pattern[0] for pattern in general_patterns])
                            
                            # data-dynamic-image 패턴도 검색
                            dynamic_patterns = re.findall(r'"(https://[^"]*images-amazon\.com[^"]*\.(jpg|jpeg|png))"', content)
                            hiRes_patterns.extend([pattern[0] for pattern in dynamic_patterns])
                            
                            # asin 코드를 포함한 이미지 URL도 검색
                            asin_patterns = re.findall(r'"(https://[^"]*amazon\.com/[^"]*\.(jpg|jpeg|png))"', content)
                            hiRes_patterns.extend([pattern[0] for pattern in asin_patterns])
                            
                            # I/로 시작하는 이미지 ID 패턴 검색 (+ 기호 포함, 더 광범위)
                            image_id_patterns = re.findall(r'"([A-Za-z0-9_\-+\.%]{10,})\.(jpg|jpeg|png|webp|gif)"', content)
                            for img_id, ext in image_id_patterns:
                                if len(img_id) >= 10:  # Amazon 이미지 ID는 보통 10자 이상
                                    hiRes_patterns.append(f"https://m.media-amazon.com/images/I/{img_id}.{ext}")
                            
                            # 특별히 + 기호가 포함된 Amazon 이미지 ID 검색
                            plus_patterns = re.findall(r'"(https://m\.media-amazon\.com/images/I/[A-Za-z0-9+_\-\.%]+\.[^"]*)"', content)
                            hiRes_patterns.extend(plus_patterns)
                            
                            # 더 일반적인 패턴 검색 - colorImages 외부의 이미지들
                            all_image_patterns = re.findall(r'https://[^"\s]*(?:media-amazon|images-amazon|amazon)[^"\s]*\.(jpg|jpeg|png|webp)', content)
                            hiRes_patterns.extend(all_image_patterns)
                            
                            # A+ Content 이미지나 기타 제품 이미지 검색
                            aplus_patterns = re.findall(r'"(https://[^"]*images-[^"]*amazon[^"]*\.(?:jpg|jpeg|png|webp))"', content)
                            hiRes_patterns.extend(aplus_patterns)
                            
                            # main image와 관련된 모든 variant 검색
                            main_image_patterns = re.findall(r'"(https://m\.media-amazon\.com/images/I/[A-Za-z0-9+_\-\.]+\.[^"]*)"', content)
                            hiRes_patterns.extend(main_image_patterns)
                            
                            # 특수 문자(+)가 포함된 이미지 ID 검색 - 더 광범위한 패턴
                            special_char_patterns = re.findall(r'(https://[^"\s]*media-amazon\.com/images/I/[^"\s,}]*\+[^"\s,}]*\.[^"\s,}]*)', content)
                            hiRes_patterns.extend(special_char_patterns)
                            
                            # 모든 Amazon 이미지 URL을 포괄적으로 수집 (따옴표 없이도)
                            comprehensive_patterns = re.findall(r'(https://[^\s,}"\']*media-amazon\.com/images/I/[^\s,}"\']*\.(jpg|jpeg|png|webp))', content)
                            hiRes_patterns.extend([pattern[0] for pattern in comprehensive_patterns])
                        
                        # 중복 제거를 위한 set 사용
                        seen_image_ids = set()
                        
                        for hiRes_url in hiRes_patterns:  # 모든 이미지 확인
                            if self._is_valid_amazon_image_url(hiRes_url):
                                # hiRes에서 이미지 ID 추출
                                image_id = self._extract_image_id_from_url(hiRes_url)
                                if image_id and image_id not in seen_image_ids:
                                    seen_image_ids.add(image_id)
                                    thumb_url = f"https://m.media-amazon.com/images/I/{image_id}._AC_US100_.jpg"
                                    large_url = f"https://m.media-amazon.com/images/I/{image_id}._AC_SL1500_.jpg"
                                    
                                    final_thumb = self._normalize_amazon_image_url(thumb_url, size='thumbnail')
                                    final_large = self._normalize_amazon_image_url(large_url, size='large')
                                    
                                    thumbnail_images.append(final_thumb)
                                    large_images.append(final_large)
                                    
                                    # 최대 10개까지 수집 (더 많은 이미지 확보)
                                    if len(thumbnail_images) >= 10:
                                        break
                        
                        if thumbnail_images:
                            break
                            
                except Exception as e:
                    continue
        
        # 2순위: HTML 셀렉터 기반 추출
        if not thumbnail_images:
            # 다양한 셀렉터로 이미지 찾기 - 더 광범위한 패턴
            image_selectors = [
                '#altImages li[data-defaultasin] img',
                '#altImages li img', 
                '#altImages img',
                '#imageBlock img',
                '.a-dynamic-image',
                '.imageThumbnail img',
                'img[data-a-dynamic-image]',
                'img[src*="media-amazon.com"]',
                'img[data-src*="media-amazon.com"]',
                '.image img',
                '[id*="image"] img',
                'div[data-csa-c-content-id*="image"] img',
                '.s-image',
                '.imageBlockThumbs img'
            ]
            
            alt_images = []
            for selector in image_selectors:
                alt_images = soup.select(selector)
                if alt_images:
                    break
                    
            if alt_images:
                seen_ids = set()
                for img in alt_images:
                    # src와 data-src 모두 확인
                    src = img.get('src') or img.get('data-src') or img.get('data-a-dynamic-image')
                    
                    if src and self._is_valid_amazon_image_url(src):
                        image_id = self._extract_image_id_from_url(src)
                        if image_id and image_id not in seen_ids:
                            seen_ids.add(image_id)
                            thumb_url = f"https://m.media-amazon.com/images/I/{image_id}._AC_US100_.jpg"
                            large_url = f"https://m.media-amazon.com/images/I/{image_id}._AC_SL1500_.jpg"
                            
                            final_thumb = self._normalize_amazon_image_url(thumb_url, size='thumbnail')
                            final_large = self._normalize_amazon_image_url(large_url, size='large')
                            
                            if final_thumb not in thumbnail_images:
                                thumbnail_images.append(final_thumb)
                                large_images.append(final_large)
                                
                            # 더 많은 이미지 수집을 위해 제한 증가
                            if len(thumbnail_images) >= 10:
                                break
        
        # 3순위: 메인 이미지 기반 fallback
        if not thumbnail_images:
            main_image_selectors = [
                '#landingImage',
                '.a-dynamic-image',
                '#main-image img',
                '.image-wrapper img'
            ]
            
            for selector in main_image_selectors:
                img_elements = soup.select(selector)
                for img in img_elements:
                    src = img.get('src') or img.get('data-src')
                    if src and self._is_valid_amazon_image_url(src):
                        image_id = self._extract_image_id_from_url(src)
                        if image_id:
                            thumb_url = f"https://m.media-amazon.com/images/I/{image_id}._AC_US100_.jpg"
                            large_url = f"https://m.media-amazon.com/images/I/{image_id}._AC_SL1500_.jpg"
                            
                            final_thumb = self._normalize_amazon_image_url(thumb_url, size='thumbnail')
                            final_large = self._normalize_amazon_image_url(large_url, size='large')
                            
                            if final_thumb not in thumbnail_images:
                                thumbnail_images.append(final_thumb)
                                large_images.append(final_large)
                            
                        if len(thumbnail_images) >= 10:
                            break
                
                if thumbnail_images:
                    break
        
        return thumbnail_images[:10], large_images[:10]  # 최대 10개로 제한
    
    def _normalize_amazon_image_url(self, url: str, size: str = 'large') -> str:
        """Amazon 이미지 URL을 정규화하고 크기 조정"""
        if not url or not url.startswith('http'):
            return None
        
        # URL이 //로 시작하면 https: 추가
        if url.startswith('//'):
            url = 'https:' + url
        
        # Amazon 이미지 URL 패턴 분석 및 크기 조정
        # 예: https://m.media-amazon.com/images/I/71abc123._SL160_.jpg
        
        # 기존 크기 파라미터 제거
        url = re.sub(r'\._[A-Z]{2}\d+_\.', '.', url)
        url = re.sub(r'\._[A-Z]{2}\d+\.', '.', url)
        
        # 새로운 크기 파라미터 추가
        if size == 'thumbnail':
            # 썸네일: 75x75 또는 100x100
            url = url.replace('.jpg', '._SL75_.jpg').replace('.jpeg', '._SL75_.jpeg').replace('.png', '._SL75_.png').replace('.webp', '._SL75_.webp')
        elif size == 'large':
            # 큰 이미지: 500x500 또는 원본 크기
            url = url.replace('.jpg', '._SL500_.jpg').replace('.jpeg', '._SL500_.jpeg').replace('.png', '._SL500_.png').replace('.webp', '._SL500_.webp')
        
        return url
    
    def _is_valid_amazon_image_url(self, url: str) -> bool:
        """Amazon 이미지 URL이 유효한지 확인"""
        if not url or not isinstance(url, str):
            return False
        
        # Amazon 이미지 도메인 확인
        valid_domains = [
            'm.media-amazon.com',
            'images-na.ssl-images-amazon.com',
            'images-cn.ssl-images-amazon.com',
            'images-eu.ssl-images-amazon.com'
        ]
        
        # URL이 유효한 Amazon 이미지 도메인을 포함하는지 확인
        for domain in valid_domains:
            if domain in url:
                # 이미지 파일 확장자 확인
                if any(ext in url.lower() for ext in ['.jpg', '.jpeg', '.png', '.webp', '.gif']):
                    return True
        
        return False
    
    def _extract_image_id_from_url(self, url: str) -> str:
        """Amazon 이미지 URL에서 이미지 ID 추출"""
        if not url:
            return None
        
        # Amazon 이미지 URL 패턴: https://m.media-amazon.com/images/I/{IMAGE_ID}.{SIZE}.{EXT}
        # 예: https://m.media-amazon.com/images/I/714vOUomS8L._AC_SL1500_.jpg
        
        # 이미지 ID 패턴 추출 (+ 기호 포함, 일반적으로 10-15자리의 알파벳+숫자 조합)
        match = re.search(r'/images/I/([A-Za-z0-9_\-+%]+)\.', url)
        if match:
            image_id = match.group(1)
            # 크기 파라미터가 포함된 경우 제거 (예: 714vOUomS8L._AC_SL1500_ -> 714vOUomS8L)
            image_id = re.sub(r'\._[A-Z]{2}.*$', '', image_id)
            return image_id
        
        return None
    
    async def _translate_product(self, product: Product) -> Product:
        """상품 정보를 한국어로 번역"""
        translation_services_used = []
        
        try:
            # Product 모델을 딕셔너리로 변환하여 번역 서비스에 전달
            product_dict = {
                'name': product.name,
                'category': product.category,
                'description': product.description,
                'features': product.features
            }
            
            # 번역 수행 및 서비스 정보 수집
            translated_dict, services_info = await translation_service.translate_product_data_with_info(product_dict)
            translation_services_used = services_info
            
            # 번역된 데이터를 Product 객체에 반영
            if translated_dict.get('name'):
                product.name = translated_dict['name']
            
            if translated_dict.get('category'):
                product.category = translated_dict['category']
            
            if translated_dict.get('description'):
                product.description = translated_dict['description']
            
            if translated_dict.get('features'):
                product.features = translated_dict['features']
            
            # 번역 서비스 정보를 site_specific_data에 추가
            if not hasattr(product, 'site_specific_data') or product.site_specific_data is None:
                product.site_specific_data = {}
            
            product.site_specific_data['translation_services'] = translation_services_used
                
            return product
            
        except Exception as e:
            # 번역 실패 시 원본 데이터 반환 및 로그 출력
            logger.error(f"번역 실패 - ASIN: {product.product_id}, 오류: {e}")
            
            # 번역 실패 정보도 추가
            if not hasattr(product, 'site_specific_data') or product.site_specific_data is None:
                product.site_specific_data = {}
            
            product.site_specific_data['translation_services'] = [{'field': 'all', 'service': 'translation_failed', 'error': str(e)}]
            
            return product