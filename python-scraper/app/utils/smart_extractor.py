import re
from typing import List, Dict, Optional
from bs4 import BeautifulSoup
import trafilatura


class SmartExtractor:
    """AI 기반 스마트 데이터 추출기"""
    
    @staticmethod
    def extract_with_trafilatura(html_content: str, url: str) -> Dict[str, any]:
        """trafilatura로 메인 콘텐츠 추출"""
        try:
            # 메인 콘텐츠 추출
            main_content = trafilatura.extract(html_content, include_comments=False)
            
            # 메타데이터 추출
            metadata = trafilatura.extract_metadata(html_content)
            
            if main_content:
                # 문장별로 분리
                sentences = [s.strip() for s in main_content.split('\n') if s.strip()]
                
                # 특징과 설명 구분
                features = []
                descriptions = []
                
                for sentence in sentences:
                    if SmartExtractor._is_feature_sentence(sentence):
                        features.append(sentence)
                    elif SmartExtractor._is_description_sentence(sentence):
                        descriptions.append(sentence)
                
                return {
                    'description': ' '.join(descriptions[:3]) if descriptions else None,
                    'features': features[:5] if features else [],
                    'title': metadata.title if metadata else None,
                    'author': metadata.author if metadata else None
                }
        except Exception as e:
            print(f"trafilatura 추출 실패: {e}")
        
        return {'description': None, 'features': []}
    
    @staticmethod
    def _is_feature_sentence(sentence: str) -> bool:
        """문장이 특징인지 판별 (개선된 로직)"""
        
        # 특징 패턴들
        feature_patterns = [
            r'^【.*】',        # 【특징】 형태
            r'^\*\s*',        # * 불릿
            r'^・\s*',        # ・ 불릿  
            r'^-\s*',         # - 불릿
            r'^\d+[.)]',      # 1. 숫자 리스트
        ]
        
        # 특징 키워드 (일본어)
        feature_keywords = [
            '素材', '材質', '機能', '特徴', '仕様', '性能', '効果',
            '対応', '搭載', '採用', '使用', '設計', '製造', '開発',
            '防水', '防塵', '耐久', '軽量', '高品質', 'プレミアム',
            'サイズ', '重量', '容量', '時間', '速度', '温度', '圧力'
        ]
        
        # 패턴 체크
        for pattern in feature_patterns:
            if re.match(pattern, sentence):
                return True
        
        # 키워드 밀도 체크 (30글자당 1개 이상)
        keyword_count = sum(1 for kw in feature_keywords if kw in sentence)
        keyword_density = keyword_count / max(len(sentence) / 30, 1)
        
        return (keyword_density >= 1.0 and 
                20 <= len(sentence) <= 150 and
                not sentence.endswith('：') and  # 제목 형태 제외
                '。' in sentence or '!' in sentence or sentence.endswith('ます'))
    
    @staticmethod
    def _is_description_sentence(sentence: str) -> bool:
        """문장이 설명인지 판별"""
        
        description_indicators = [
            'について', 'です', 'ます', 'である', 'であり',
            '商品', '製品', 'ブランド', '会社', 'メーカー'
        ]
        
        return (len(sentence) > 50 and
                any(ind in sentence for ind in description_indicators) and
                not SmartExtractor._is_feature_sentence(sentence))
    
    @staticmethod
    def extract_smart_features_and_description(soup: BeautifulSoup) -> Dict[str, any]:
        """텍스트 분석으로 description과 features 지능적 구분"""
        
        # 모든 텍스트 블록 수집
        text_blocks = []
        
        # 주요 콘텐츠 영역들
        content_selectors = [
            '#feature-bullets span',
            '#productDescription p',
            '#aplus-v2 p',
            '.a-spacing-medium span',
            '.a-list-item span',
            '.a-row span'
        ]
        
        for selector in content_selectors:
            elements = soup.select(selector)
            for element in elements:
                text = element.get_text(strip=True)
                if text and len(text) > 10:
                    text_blocks.append(text)
        
        # 텍스트 분류
        description_texts = []
        feature_texts = []
        
        for text in text_blocks:
            if SmartExtractor._is_feature_text(text):
                feature_texts.append(text)
            elif SmartExtractor._is_description_text(text):
                description_texts.append(text)
        
        # 중복 제거 및 정리
        features = list(dict.fromkeys(feature_texts))[:5]  # 최대 5개
        description = ' '.join(description_texts[:3])  # 최대 3개 문장
        
        return {
            'description': description if description else None,
            'features': features
        }
    
    @staticmethod
    def _is_feature_text(text: str) -> bool:
        """텍스트가 특징(feature)인지 판별"""
        
        # 특징을 나타내는 패턴들
        feature_indicators = [
            r'^【.*】',  # 【브랜드】, 【특징】 형태
            r'^\*',     # * 불릿 포인트
            r'^・',     # ・ 불릿 포인트
            r'^-',      # - 불릿 포인트
            r'^\d+\.',  # 1. 숫자 리스트
        ]
        
        # 특징 키워드들
        feature_keywords = [
            '素材', '材質', '機能', '特徴', '仕様', '性能',
            '対応', '搭載', '採用', '使用', '設計', '製造',
            'サイズ', '重量', '容量', '時間', '速度', '温度'
        ]
        
        # 패턴 매칭
        for pattern in feature_indicators:
            if re.match(pattern, text):
                return True
        
        # 키워드 매칭 (2개 이상 포함시 특징으로 판별)
        keyword_count = sum(1 for keyword in feature_keywords if keyword in text)
        if keyword_count >= 2:
            return True
        
        # 짧고 구체적인 텍스트는 특징
        if 20 <= len(text) <= 100 and any(kw in text for kw in feature_keywords):
            return True
        
        return False
    
    @staticmethod  
    def _is_description_text(text: str) -> bool:
        """텍스트가 설명(description)인지 판별"""
        
        # 설명을 나타내는 키워드들
        description_keywords = [
            '商品の説明', '製品について', '概要', 'について',
            'です', 'ます', 'である', 'であり'
        ]
        
        # 긴 문장이면서 설명 키워드 포함
        if (len(text) > 50 and 
            any(kw in text for kw in description_keywords) and
            not SmartExtractor._is_feature_text(text)):
            return True
        
        return False
    
    @staticmethod
    def extract_smart_weight_dimensions(text: str) -> Dict[str, Optional[str]]:
        """전체 텍스트에서 무게/치수 지능적 추출"""
        
        # 무게 추출 (더 광범위한 패턴)
        weight_patterns = [
            r'(?:重量|重さ|重み|Weight|商品重量|梱包重量|本体重量|質量)[：:\s]*([0-9.,]+\s*(?:kg|g|キログラム|グラム|ｋｇ|ｇ))',
            r'([0-9.,]+\s*(?:kg|g|キログラム|グラム|ｋｇ|ｇ))(?=\s|$|、|。)',
        ]
        
        weight = None
        for pattern in weight_patterns:
            matches = re.findall(pattern, text, re.IGNORECASE)
            for match in matches:
                if isinstance(match, tuple):
                    weight_str = match[0]
                else:
                    weight_str = match
                
                # 숫자 추출 및 정규화
                number_match = re.search(r'([0-9.,]+)', weight_str)
                if number_match:
                    number = float(number_match.group(1).replace(',', ''))
                    
                    # 현실적인 무게 범위 체크 (0.001kg ~ 1000kg)
                    if 'g' in weight_str.lower() and 'kg' not in weight_str.lower():
                        kg_weight = number / 1000
                        if 0.001 <= kg_weight <= 1000:
                            weight = f"{kg_weight:.3f}"
                            break
                    elif 0.001 <= number <= 1000:
                        weight = f"{number:.3f}"
                        break
        
        # 치수 추출 (더 광범위한 패턴)
        dimension_patterns = [
            r'(?:寸法|サイズ|大きさ|Dimensions|Size|外形|外寸)[：:\s]*([0-9.,]+\s*[x××]\s*[0-9.,]+(?:\s*[x××]\s*[0-9.,]+)?\s*(?:cm|mm|ｃｍ|ｍｍ))',
            r'([0-9.,]+\s*[x××]\s*[0-9.,]+(?:\s*[x××]\s*[0-9.,]+)?\s*(?:cm|mm|ｃｍ|ｍｍ))(?=\s|$|、|。)',
        ]
        
        dimensions = None
        for pattern in dimension_patterns:
            matches = re.findall(pattern, text, re.IGNORECASE)
            for match in matches:
                if isinstance(match, tuple):
                    dim_str = match[0]
                else:
                    dim_str = match
                
                # 현실적인 치수 범위 체크
                numbers = re.findall(r'([0-9.,]+)', dim_str)
                if numbers and all(0.1 <= float(n.replace(',', '')) <= 1000 for n in numbers):
                    dimensions = dim_str.strip()
                    break
            
            if dimensions:
                break
        
        return {
            'weight': weight,
            'dimensions': dimensions
        }