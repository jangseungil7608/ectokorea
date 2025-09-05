<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class AmazonUrlParserService
{
    /**
     * Amazon URL에서 ASIN 추출
     */
    public function extractAsinFromUrl(string $url): ?string
    {
        // URL 정규화
        $url = $this->normalizeUrl($url);
        
        // ASIN 패턴 매칭
        $patterns = [
            // 표준 상품 페이지: /dp/ASIN
            '/\/dp\/([A-Z0-9]{10})/',
            // 짧은 형태: /gp/product/ASIN
            '/\/gp\/product\/([A-Z0-9]{10})/',
            // 오래된 형태: /exec/obidos/ASIN/ASIN
            '/\/exec\/obidos\/ASIN\/([A-Z0-9]{10})/',
            // 검색 결과에서: &asin=ASIN
            '/[&?]asin=([A-Z0-9]{10})/',
            // 리뷰 페이지: /product-reviews/ASIN
            '/\/product-reviews\/([A-Z0-9]{10})/',
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $matches)) {
                return $matches[1];
            }
        }
        
        return null;
    }
    
    /**
     * Amazon 검색 URL에서 키워드 추출
     */
    public function extractKeywordFromSearchUrl(string $url): ?string
    {
        $parsed = parse_url($url);
        
        if (!isset($parsed['query'])) {
            return null;
        }
        
        parse_str($parsed['query'], $params);
        
        // 다양한 검색 파라미터 확인
        $searchParams = ['k', 'field-keywords', 'keywords'];
        
        foreach ($searchParams as $param) {
            if (isset($params[$param]) && !empty($params[$param])) {
                return urldecode($params[$param]);
            }
        }
        
        return null;
    }
    
    /**
     * URL 타입 판별
     */
    public function getUrlType(string $url): string
    {
        $url = $this->normalizeUrl($url);
        
        // 상품 페이지
        if ($this->extractAsinFromUrl($url)) {
            return 'PRODUCT';
        }
        
        // 검색 결과 페이지
        if ($this->extractKeywordFromSearchUrl($url)) {
            return 'SEARCH';
        }
        
        // 카테고리 페이지
        if (preg_match('/\/s\?.*node=/', $url) || preg_match('/\/b\//', $url)) {
            return 'CATEGORY';
        }
        
        // 베스트셀러 페이지 (카테고리 포함)
        if (strpos($url, '/gp/bestsellers') !== false || strpos($url, '/zgbs/') !== false) {
            return 'BESTSELLER';
        }
        
        return 'UNKNOWN';
    }
    
    /**
     * 검색 결과 페이지에서 상품 목록 추출
     */
    public function extractProductsFromSearchPage(string $url, int $maxResults = 20): array
    {
        $maxRetries = 3;
        $retryDelay = 2; // 초기 대기 시간 (초)
        
        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                Log::info("검색 페이지 스크래핑 시도 {$attempt}/{$maxRetries}: {$url}");
                
                // 요청 간격 조절 (503 에러 방지)
                if ($attempt > 1) {
                    $delay = $retryDelay * $attempt; // 지수적 백오프
                    Log::info("재시도를 위해 {$delay}초 대기 중...");
                    sleep($delay);
                } else {
                    sleep(2); // 초기 2초 대기
                }
                
                $response = Http::timeout(30)
                    ->withHeaders([
                        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                        'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                        'Accept-Language' => 'ja-JP,ja;q=0.8,en;q=0.6',
                        'Accept-Encoding' => 'gzip, deflate, br',
                        'DNT' => '1',
                        'Connection' => 'keep-alive',
                        'Upgrade-Insecure-Requests' => '1',
                        'Cache-Control' => 'no-cache',
                        'Pragma' => 'no-cache',
                    ])
                    ->get($url);
                    
                // 503 Service Unavailable 에러 체크
                if ($response->status() === 503) {
                    if ($attempt < $maxRetries) {
                        Log::warning("503 에러 발생, 재시도 예정: 시도 {$attempt}/{$maxRetries}");
                        continue; // 다음 시도로
                    } else {
                        throw new Exception("Amazon에서 접근을 차단했습니다 (503 Service Unavailable). 잠시 후 다시 시도해주세요.");
                    }
                }
                
                if (!$response->successful()) {
                    throw new Exception("페이지 요청 실패: " . $response->status());
                }
                
                $html = $response->body();
                
                // 상품 ASIN 추출 (여러 패턴)
                $asins = [];
                
                // 패턴 1: data-asin 속성
                if (preg_match_all('/data-asin="([A-Z0-9]{10})"/', $html, $matches)) {
                    $asins = array_merge($asins, $matches[1]);
                }
                
                // 패턴 2: href="/dp/ASIN" 링크
                if (preg_match_all('/href="[^"]*\/dp\/([A-Z0-9]{10})[^"]*"/', $html, $matches)) {
                    $asins = array_merge($asins, $matches[1]);
                }
                
                // 패턴 3: href="/gp/product/ASIN" 링크
                if (preg_match_all('/href="[^"]*\/gp\/product\/([A-Z0-9]{10})[^"]*"/', $html, $matches)) {
                    $asins = array_merge($asins, $matches[1]);
                }
                
                // 중복 제거 및 제한
                $asins = array_unique($asins);
                $asins = array_slice($asins, 0, $maxResults);
                
                Log::info("검색 페이지에서 " . count($asins) . "개 상품 발견 (시도 {$attempt}번 성공)");
                
                return $asins;
                
            } catch (Exception $e) {
                if ($attempt === $maxRetries) {
                    Log::error("검색 페이지 스크래핑 최종 실패: {$url}", [
                        'error' => $e->getMessage(),
                        'attempts' => $attempt
                    ]);
                    throw $e;
                } else {
                    Log::warning("검색 페이지 스크래핑 시도 {$attempt} 실패: {$e->getMessage()}, 재시도 예정");
                }
            }
        }
    }
    
    /**
     * 카테고리 페이지에서 상품 목록 추출
     */
    public function extractProductsFromCategoryPage(string $url, int $maxResults = 50): array
    {
        $maxRetries = 3;
        $retryDelay = 2;
        
        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                Log::info("카테고리 페이지 스크래핑 시도 {$attempt}/{$maxRetries}: {$url}");
                
                // 요청 간격 조절
                if ($attempt > 1) {
                    $delay = $retryDelay * $attempt;
                    Log::info("재시도를 위해 {$delay}초 대기 중...");
                    sleep($delay);
                } else {
                    sleep(2);
                }
                
                $response = Http::timeout(30)
                    ->withHeaders([
                        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                        'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                        'Accept-Language' => 'ja-JP,ja;q=0.8,en;q=0.6',
                        'Accept-Encoding' => 'gzip, deflate, br',
                        'DNT' => '1',
                        'Connection' => 'keep-alive',
                        'Upgrade-Insecure-Requests' => '1',
                        'Cache-Control' => 'no-cache',
                        'Pragma' => 'no-cache',
                    ])
                    ->get($url);
                    
                // 503 Service Unavailable 에러 체크
                if ($response->status() === 503) {
                    if ($attempt < $maxRetries) {
                        Log::warning("503 에러 발생, 재시도 예정: 시도 {$attempt}/{$maxRetries}");
                        continue;
                    } else {
                        throw new Exception("Amazon에서 접근을 차단했습니다 (503 Service Unavailable). 잠시 후 다시 시도해주세요.");
                    }
                }
                
                if (!$response->successful()) {
                    throw new Exception("페이지 요청 실패: " . $response->status());
                }
                
                $html = $response->body();
                $asins = [];
                
                // 카테고리 페이지는 검색 결과와 유사한 구조
                if (preg_match_all('/data-asin="([A-Z0-9]{10})"/', $html, $matches)) {
                    $asins = array_merge($asins, $matches[1]);
                }
                
                if (preg_match_all('/href="[^"]*\/dp\/([A-Z0-9]{10})[^"]*"/', $html, $matches)) {
                    $asins = array_merge($asins, $matches[1]);
                }
                
                $asins = array_unique($asins);
                $asins = array_slice($asins, 0, $maxResults);
                
                Log::info("카테고리 페이지에서 " . count($asins) . "개 상품 발견 (시도 {$attempt}번 성공)");
                
                return $asins;
                
            } catch (Exception $e) {
                if ($attempt === $maxRetries) {
                    Log::error("카테고리 페이지 스크래핑 최종 실패: {$url}", [
                        'error' => $e->getMessage(),
                        'attempts' => $attempt
                    ]);
                    throw $e;
                } else {
                    Log::warning("카테고리 페이지 스크래핑 시도 {$attempt} 실패: {$e->getMessage()}, 재시도 예정");
                }
            }
        }
    }
    
    /**
     * URL 정규화
     */
    private function normalizeUrl(string $url): string
    {
        // 프로토콜 추가 (필요시)
        if (!preg_match('/^https?:\/\//', $url)) {
            $url = 'https://' . $url;
        }
        
        // amazon.co.jp 도메인으로 변경 (필요시)
        $url = preg_replace('/amazon\.(com|de|fr|it|es|ca|com\.au)/', 'amazon.co.jp', $url);
        
        // 불필요한 파라미터 제거
        $parsed = parse_url($url);
        
        if (isset($parsed['query'])) {
            parse_str($parsed['query'], $params);
            
            // 보존할 파라미터만 유지
            $keepParams = ['k', 'field-keywords', 'keywords', 'node', 'rh', 's', 'ref'];
            $filteredParams = array_intersect_key($params, array_flip($keepParams));
            
            $query = http_build_query($filteredParams);
            $parsed['query'] = $query;
        }
        
        // URL 재구성
        $scheme = $parsed['scheme'] ?? 'https';
        $host = $parsed['host'] ?? '';
        $path = $parsed['path'] ?? '';
        $query = isset($parsed['query']) && !empty($parsed['query']) ? '?' . $parsed['query'] : '';
        
        return $scheme . '://' . $host . $path . $query;
    }
    
    /**
     * URL 유효성 검사
     */
    public function isValidAmazonUrl(string $url): bool
    {
        $url = $this->normalizeUrl($url);
        
        // amazon.co.jp 도메인 확인
        if (!preg_match('/amazon\.co\.jp/', $url)) {
            return false;
        }
        
        // 지원하는 URL 타입 확인
        $type = $this->getUrlType($url);
        return in_array($type, ['PRODUCT', 'SEARCH', 'CATEGORY', 'BESTSELLER']);
    }
}