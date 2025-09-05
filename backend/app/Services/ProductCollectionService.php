<?php

namespace App\Services;

use App\Models\CollectedProduct;
use App\Models\CollectionJob;
use App\Services\AmazonUrlParserService;
use App\Services\ProfitCalculatorService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Exception;

class ProductCollectionService
{
    private AmazonUrlParserService $urlParserService;
    private ProfitCalculatorService $profitCalculatorService;
    private string $pythonScraperUrl;

    public function __construct(
        AmazonUrlParserService $urlParserService,
        ProfitCalculatorService $profitCalculatorService
    ) {
        $this->urlParserService = $urlParserService;
        $this->profitCalculatorService = $profitCalculatorService;
        $this->pythonScraperUrl = env('PYTHON_SCRAPER_URL', 'http://192.168.1.13:8001/ectokorea/api/v1');
    }

    /**
     * ASIN으로 상품 수집
     */
    public function collectByAsin(string $asin, bool $autoAnalyze = true, float $targetMargin = 10.0, float $japanShippingJpy = 0, float $koreaShippingKrw = 0): CollectedProduct
    {
        // 현재 사용자가 이미 수집한 상품인지 확인
        $existingProduct = CollectedProduct::where('asin', $asin)
                                          ->where('user_id', auth('api')->id())
                                          ->first();
        if ($existingProduct) {
            // 기존 상품이 있으면 재수집
            return $this->recollectProduct($existingProduct, $autoAnalyze, $targetMargin, $japanShippingJpy, $koreaShippingKrw);
        }

        // 새 상품 수집
        return $this->collectNewProduct($asin, $autoAnalyze, $targetMargin, $japanShippingJpy, $koreaShippingKrw);
    }

    /**
     * ASIN으로 Amazon 상품 수집 (특정 사용자용 - Queue Job에서 사용)
     */
    public function collectByAsinForUser(string $asin, int $userId, bool $autoAnalyze = true, float $targetMargin = 10.0, float $japanShippingJpy = 0, float $koreaShippingKrw = 0): CollectedProduct
    {
        // 해당 사용자가 이미 수집한 상품인지 확인
        $existingProduct = CollectedProduct::where('asin', $asin)
                                          ->where('user_id', $userId)
                                          ->first();
        if ($existingProduct) {
            // 기존 상품이 있으면 재수집
            return $this->recollectProductForUser($existingProduct, $userId, $autoAnalyze, $targetMargin, $japanShippingJpy, $koreaShippingKrw);
        }

        // 새 상품 수집
        return $this->collectNewProductForUser($asin, $userId, $autoAnalyze, $targetMargin, $japanShippingJpy, $koreaShippingKrw);
    }

    /**
     * 새 상품 수집
     */
    private function collectNewProduct(string $asin, bool $autoAnalyze, float $targetMargin = 10.0, float $japanShippingJpy = 0, float $koreaShippingKrw = 0): CollectedProduct
    {
        // 수집 대기 상태로 상품 생성
        $product = CollectedProduct::create([
            'asin' => $asin,
            'title' => '수집 중...',
            'status' => 'COLLECTING',
            'source_url' => "https://www.amazon.co.jp/dp/{$asin}",
            'user_id' => auth('api')->id()
        ]);

        try {
            // Python 스크래퍼로 Amazon 상품 정보 스크래핑
            $productData = $this->scrapeProductFromPython('amazon', ['asin' => $asin]);
            
            // 카테고리 및 서브카테고리 자동 판정
            $category = $this->determineCategory($product, $productData);
            $subcategory = $this->determineSubcategory($category, $product, $productData);

            // 원문 데이터 저장 전 로그 (새 상품)
            Log::info("새 상품 데이터베이스 저장 전 원문 데이터 확인", [
                'asin' => $asin,
                'original_name' => $productData['original_name'] ?? 'NULL',
                'original_category' => $productData['original_category'] ?? 'NULL',
                'has_original_description' => isset($productData['original_description']),
                'has_original_features' => isset($productData['original_features']) && is_array($productData['original_features']) ? count($productData['original_features']) . '개' : 'NULL'
            ]);

            // 상품 정보 업데이트 (원문 필드 포함)
            $product->update([
                'title' => $productData['title'] ?? $productData['name'] ?? '제목 없음',
                'original_title' => $productData['original_name'] ?? null,
                'price_jpy' => $this->extractPriceFromData($productData),
                'weight_g' => $this->extractWeight($productData),
                'dimensions' => $this->extractDimensions($productData),
                'category' => $category,
                'original_category' => $productData['original_category'] ?? null,
                'subcategory' => $subcategory,
                'images' => $this->extractImages($productData),
                'thumbnail_images' => $this->extractThumbnailImages($productData),
                'large_images' => $this->extractLargeImages($productData),
                'description_images' => $this->extractDescriptionImages($productData),
                'description' => $productData['description'] ?? '',
                'original_description' => $productData['original_description'] ?? null,
                'features' => $this->extractFeatures($productData),
                'original_features' => $productData['original_features'] ?? null,
                'specifications' => $this->extractSpecifications($productData),
                'status' => 'COLLECTED',
                'collected_at' => now()
            ]);

            Log::info("상품 수집 완료: {$asin}", ['title' => $product->title]);

            // 자동 분석 실행
            if ($autoAnalyze) {
                $this->analyzeProfitability($product, $targetMargin, $japanShippingJpy, $koreaShippingKrw);
            }

            return $product;

        } catch (Exception $e) {
            // 오류 상태로 업데이트
            $product->update([
                'status' => 'ERROR',
                'error_message' => $e->getMessage()
            ]);

            Log::error("상품 수집 실패: {$asin}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    /**
     * 새 상품 수집 (특정 사용자용)
     */
    private function collectNewProductForUser(string $asin, int $userId, bool $autoAnalyze, float $targetMargin = 10.0, float $japanShippingJpy = 0, float $koreaShippingKrw = 0): CollectedProduct
    {
        // 수집 대기 상태로 상품 생성
        $product = CollectedProduct::create([
            'asin' => $asin,
            'title' => '수집 중...',
            'status' => 'COLLECTING',
            'source_url' => "https://www.amazon.co.jp/dp/{$asin}",
            'user_id' => $userId
        ]);

        try {
            // Python 스크래퍼로 Amazon 상품 정보 스크래핑
            $productData = $this->scrapeProductFromPython('amazon', ['asin' => $asin]);
            
            // 카테고리 및 서브카테고리 자동 판정
            $category = $this->determineCategory($product, $productData);
            $subcategory = $this->determineSubcategory($category, $product, $productData);

            // 원문 데이터 저장 전 로그 (새 상품)
            Log::info("새 상품 데이터베이스 저장 전 원문 데이터 확인", [
                'asin' => $asin,
                'user_id' => $userId,
                'original_name' => $productData['original_name'] ?? 'NULL',
                'original_category' => $productData['original_category'] ?? 'NULL',
                'has_original_description' => isset($productData['original_description']),
                'has_original_features' => isset($productData['original_features']) && is_array($productData['original_features']) ? count($productData['original_features']) . '개' : 'NULL'
            ]);

            // 상품 정보 업데이트 (원문 필드 포함)
            $product->update([
                'title' => $productData['title'] ?? $productData['name'] ?? '제목 없음',
                'original_title' => $productData['original_name'] ?? null,
                'price_jpy' => $this->extractPriceFromData($productData),
                'weight_g' => $this->extractWeight($productData),
                'dimensions' => $this->extractDimensions($productData),
                'category' => $category,
                'original_category' => $productData['original_category'] ?? null,
                'subcategory' => $subcategory,
                'images' => $this->extractImages($productData),
                'thumbnail_images' => $this->extractThumbnailImages($productData),
                'large_images' => $this->extractLargeImages($productData),
                'description_images' => $this->extractDescriptionImages($productData),
                'description' => $productData['description'] ?? '',
                'original_description' => $productData['original_description'] ?? null,
                'features' => $this->extractFeatures($productData),
                'original_features' => $productData['original_features'] ?? null,
                'specifications' => $this->extractSpecifications($productData),
                'status' => 'COLLECTED',
                'collected_at' => now()
            ]);

            Log::info("상품 수집 완료: {$asin}", ['title' => $product->title, 'user_id' => $userId]);

            // 자동 분석 실행
            if ($autoAnalyze) {
                $this->analyzeProfitability($product, $targetMargin, $japanShippingJpy, $koreaShippingKrw);
            }

            return $product;

        } catch (Exception $e) {
            // 오류 상태로 업데이트
            $product->update([
                'status' => 'ERROR',
                'error_message' => $e->getMessage()
            ]);

            Log::error("상품 수집 실패: {$asin}", [
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    /**
     * 기존 상품 재수집 (특정 사용자용)
     */
    private function recollectProductForUser(CollectedProduct $product, int $userId, bool $autoAnalyze, float $targetMargin = 10.0, float $japanShippingJpy = 0, float $koreaShippingKrw = 0): CollectedProduct
    {
        $product->update([
            'status' => 'COLLECTING',
            'error_message' => null
        ]);

        try {
            $productData = $this->scrapeProductFromPython('amazon', ['asin' => $product->asin]);
            
            // 카테고리 및 서브카테고리 재판정
            $category = $this->determineCategory($product, $productData);
            $subcategory = $this->determineSubcategory($category, $product, $productData);
            
            // 원문 데이터 저장 전 로그
            Log::info("데이터베이스 저장 전 원문 데이터 확인", [
                'asin' => $product->asin,
                'user_id' => $userId,
                'original_name' => $productData['original_name'] ?? 'NULL',
                'original_category' => $productData['original_category'] ?? 'NULL',
                'has_original_description' => isset($productData['original_description']),
                'has_original_features' => isset($productData['original_features']) && is_array($productData['original_features']) ? count($productData['original_features']) . '개' : 'NULL'
            ]);

            // 상품 정보 재업데이트
            $product->update([
                'title' => $productData['title'] ?? $productData['name'] ?? '제목 없음',
                'original_title' => $productData['original_name'] ?? null,
                'price_jpy' => $this->extractPriceFromData($productData),
                'weight_g' => $this->extractWeight($productData),
                'dimensions' => $this->extractDimensions($productData),
                'category' => $category,
                'original_category' => $productData['original_category'] ?? null,
                'subcategory' => $subcategory,
                'images' => $this->extractImages($productData),
                'thumbnail_images' => $this->extractThumbnailImages($productData),
                'large_images' => $this->extractLargeImages($productData),
                'description_images' => $this->extractDescriptionImages($productData),
                'description' => $productData['description'] ?? '',
                'original_description' => $productData['original_description'] ?? null,
                'features' => $this->extractFeatures($productData),
                'original_features' => $productData['original_features'] ?? null,
                'specifications' => $this->extractSpecifications($productData),
                'status' => 'COLLECTED',
                'collected_at' => now()
            ]);

            Log::info("상품 재수집 완료: {$product->asin}", ['title' => $product->title, 'user_id' => $userId]);

            // 자동 분석 실행
            if ($autoAnalyze) {
                $this->analyzeProfitability($product, $targetMargin, $japanShippingJpy, $koreaShippingKrw);
            }

            return $product;

        } catch (Exception $e) {
            $product->update([
                'status' => 'ERROR', 
                'error_message' => $e->getMessage()
            ]);
            
            Log::error("상품 재수집 실패: {$product->asin}", [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    /**
     * 기존 상품 재수집
     */
    private function recollectProduct(CollectedProduct $product, bool $autoAnalyze, float $targetMargin = 10.0, float $japanShippingJpy = 0, float $koreaShippingKrw = 0): CollectedProduct
    {
        $product->update([
            'status' => 'COLLECTING',
            'error_message' => null
        ]);

        try {
            $productData = $this->scrapeProductFromPython('amazon', ['asin' => $product->asin]);
            
            // 카테고리 및 서브카테고리 재판정
            $category = $this->determineCategory($product, $productData);
            $subcategory = $this->determineSubcategory($category, $product, $productData);
            
            // 원문 데이터 저장 전 로그
            Log::info("데이터베이스 저장 전 원문 데이터 확인", [
                'asin' => $product->asin,
                'original_name' => $productData['original_name'] ?? 'NULL',
                'original_category' => $productData['original_category'] ?? 'NULL',
                'has_original_description' => isset($productData['original_description']),
                'has_original_features' => isset($productData['original_features']) && is_array($productData['original_features']) ? count($productData['original_features']) . '개' : 'NULL'
            ]);
            
            $product->update([
                'title' => $productData['title'] ?? $productData['name'] ?? $product->title,
                'original_title' => $productData['original_name'] ?? null,
                'price_jpy' => $this->extractPrice($productData['price'] ?? '') ?? $product->price_jpy,
                'weight_g' => $this->extractWeight($productData) ?? $product->weight_g,
                'dimensions' => $this->extractDimensions($productData) ?? $product->dimensions,
                'category' => $category,
                'original_category' => $productData['original_category'] ?? null,
                'subcategory' => $subcategory,
                'images' => $this->extractImages($productData) ?: $product->images,
                'thumbnail_images' => $this->extractThumbnailImages($productData) ?: $product->thumbnail_images,
                'large_images' => $this->extractLargeImages($productData) ?: $product->large_images,
                'description_images' => $this->extractDescriptionImages($productData) ?: $product->description_images,
                'description' => $productData['description'] ?? $product->description,
                'original_description' => $productData['original_description'] ?? null,
                'features' => $this->extractFeatures($productData) ?: $product->features,
                'original_features' => $productData['original_features'] ?? null,
                'specifications' => $this->extractSpecifications($productData) ?: $product->specifications,
                'status' => 'COLLECTED',
                'collected_at' => now(),
                'error_message' => null
            ]);

            if ($autoAnalyze) {
                $this->analyzeProfitability($product, $targetMargin, $japanShippingJpy, $koreaShippingKrw);
            }

            return $product;

        } catch (Exception $e) {
            $product->update([
                'status' => 'ERROR', 
                'error_message' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * 수익성 분석
     */
    public function analyzeProfitability(CollectedProduct $product, float $targetMargin = 10.0, float $japanShippingJpy = 0, float $koreaShippingKrw = 0): CollectedProduct
    {
        try {
            if (!$product->price_jpy || !$product->weight_g) {
                throw new Exception('가격 또는 무게 정보가 없어 수익성 분석을 할 수 없습니다.');
            }

            // 사용자 입력 배송비 또는 기본값 사용
            $calculationData = [
                'product_price_jpy' => $product->price_jpy,
                'japan_shipping_jpy' => $japanShippingJpy, // 사용자 입력값 (기본 0)
                'product_weight_g' => $product->weight_g,
                'shipping_method' => 'economy',
                'category' => $product->category ?? 'daily_necessities',
                'subcategory' => $product->subcategory,
                'korea_shipping_krw' => $koreaShippingKrw, // 사용자 입력값 (기본 0)
                'packaging_fee_krw' => 0
            ];

            // 사용자 지정 목표 이익률로 추천 가격 계산
            $recommendation = $this->profitCalculatorService->calculateRecommendedPrice(
                $calculationData, 
                $targetMargin
            );

            if (isset($recommendation['error'])) {
                throw new Exception($recommendation['error']);
            }

            $recommendedPrice = $recommendation['recommended_price'];
            $actualMargin = $recommendation['actual_profit_margin'];

            // 수익성 분석 결과 저장
            $product->update([
                'profit_analysis' => $recommendation,
                'recommended_price' => $recommendedPrice,
                'profit_margin' => $actualMargin,
                'is_profitable' => $actualMargin >= 10, // 10% 이상이면 수익성 있음
                'status' => 'ANALYZED',
                'analyzed_at' => now()
            ]);

            Log::info("수익성 분석 완료: {$product->asin}", [
                'recommended_price' => $recommendedPrice,
                'profit_margin' => $actualMargin
            ]);

            return $product;

        } catch (Exception $e) {
            $product->update([
                'status' => 'ERROR',
                'error_message' => '수익성 분석 실패: ' . $e->getMessage()
            ]);

            Log::error("수익성 분석 실패: {$product->asin}", [
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * URL로 상품 수집
     */
    public function collectByUrl(string $url, bool $autoAnalyze = true, int $maxResults = 20): array
    {
        // URL 유효성 검사
        if (!$this->urlParserService->isValidAmazonUrl($url)) {
            throw new Exception('유효하지 않은 Amazon URL입니다.');
        }

        $urlType = $this->urlParserService->getUrlType($url);

        switch ($urlType) {
            case 'PRODUCT':
                // 단일 상품 URL
                $asin = $this->urlParserService->extractAsinFromUrl($url);
                if (!$asin) {
                    throw new Exception('URL에서 ASIN을 추출할 수 없습니다.');
                }
                
                $product = $this->collectByAsin($asin, $autoAnalyze);
                return ['type' => 'single', 'products' => [$product]];

            case 'SEARCH':
                // 검색 결과 페이지
                $asins = $this->urlParserService->extractProductsFromSearchPage($url, $maxResults);
                $job = $this->createBulkCollectionJob('URL', [
                    'url' => $url,
                    'asins' => $asins,
                    'url_type' => 'SEARCH'
                ], ['auto_analyze' => $autoAnalyze]);
                
                return ['type' => 'job', 'job' => $job, 'found_count' => count($asins)];

            case 'CATEGORY':
                // 카테고리 페이지
                $asins = $this->urlParserService->extractProductsFromCategoryPage($url, $maxResults);
                $job = $this->createBulkCollectionJob('URL', [
                    'url' => $url,
                    'asins' => $asins,
                    'url_type' => 'CATEGORY'
                ], ['auto_analyze' => $autoAnalyze]);
                
                return ['type' => 'job', 'job' => $job, 'found_count' => count($asins)];

            case 'BESTSELLER':
                // 베스트셀러 페이지 - ASIN 목록만 추출 후 Queue Job 생성 (CATEGORY와 동일한 방식)
                $asins = $this->extractBestsellerAsins($url, $maxResults);
                $job = $this->createBulkCollectionJob('URL', [
                    'url' => $url,
                    'asins' => $asins,
                    'url_type' => 'BESTSELLER'
                ], ['auto_analyze' => $autoAnalyze]);
                
                return ['type' => 'job', 'job' => $job, 'found_count' => count($asins)];

            default:
                throw new Exception('지원하지 않는 URL 타입입니다.');
        }
    }

    /**
     * 키워드로 상품 검색 및 수집
     */
    public function collectByKeyword(string $keyword, int $maxResults = 50, bool $autoAnalyze = true): CollectionJob
    {
        // Amazon 검색 URL 생성
        $searchUrl = "https://www.amazon.co.jp/s?k=" . urlencode($keyword) . "&ref=sr_pg_1";

        // 검색 결과에서 상품 추출
        $asins = $this->urlParserService->extractProductsFromSearchPage($searchUrl, $maxResults);

        if (empty($asins)) {
            throw new Exception('검색 결과에서 상품을 찾을 수 없습니다.');
        }

        // 대량 수집 작업 생성
        return $this->createBulkCollectionJob('KEYWORD', [
            'keyword' => $keyword,
            'search_url' => $searchUrl,
            'asins' => $asins
        ], ['auto_analyze' => $autoAnalyze]);
    }

    /**
     * 대량 수집 작업 생성
     */
    public function createBulkCollectionJob(string $type, array $inputData, array $settings = []): CollectionJob
    {
        $totalItems = match ($type) {
            'BULK_ASIN' => count($inputData['asins'] ?? []),
            'URL' => count($inputData['asins'] ?? []),
            'KEYWORD' => count($inputData['asins'] ?? []),
            'CATEGORY' => $inputData['max_results'] ?? 100,
            default => 1
        };

        return CollectionJob::create([
            'user_id' => auth('api')->id(),
            'type' => $type,
            'input_data' => $inputData,
            'total_items' => $totalItems,
            'settings' => $settings,
            'status' => 'PENDING'
        ]);
    }

    /**
     * 가격 추출
     */
    private function extractPrice(?string $priceText): ?float
    {
        if (!$priceText) return null;
        
        // 1. 일본 엔화 가격 패턴 매칭 (￥1,234 또는 ¥1,234)
        if (preg_match('/[￥¥]\s*([0-9,]+)/', $priceText, $matches)) {
            return (float) str_replace(',', '', $matches[1]);
        }
        
        // 2. 숫자만 있는 경우 (Amazon 스크래핑에서 ¥ 기호 없이 반환되는 경우)
        if (preg_match('/^([0-9,]+)$/', $priceText, $matches)) {
            return (float) str_replace(',', '', $matches[1]);
        }
        
        // 3. 원화 패턴 (1,234원)
        if (preg_match('/([0-9,]+)\s*원/', $priceText, $matches)) {
            return (float) str_replace(',', '', $matches[1]);
        }
        
        return null;
    }
    
    /**
     * 상품 데이터에서 가격 추출 (Mock 데이터 및 스크래핑 데이터 모두 지원)
     */
    private function extractPriceFromData(array $productData): ?float
    {
        // 1. 직접 숫자 가격이 있는 경우 (Mock 데이터)
        if (isset($productData['price']) && is_numeric($productData['price'])) {
            return (float) $productData['price'];
        }
        
        // 2. 문자열 가격이 있는 경우 (스크래핑 데이터)
        if (isset($productData['price']) && is_string($productData['price'])) {
            return $this->extractPrice($productData['price']);
        }
        
        return null;
    }

    /**
     * 무게 추출 (추정)
     */
    private function extractWeight(array $productData): ?int
    {
        // 먼저 스크래핑된 weight 데이터 확인
        if (isset($productData['weight']) && $productData['weight'] !== 'N/A' && !empty($productData['weight'])) {
            $weight = (float) $productData['weight'];
            
            // AmazonScraperService에서 반환하는 weight는 kg 단위이므로 g로 변환
            // 0이 아닌 유효한 값이면 g 단위로 변환하여 사용
            if ($weight > 0) {
                return (int) ($weight * 1000); // kg를 g로 변환
            }
        }
        
        // 상품 설명, 특징, 사양에서 무게 정보 추출 시도
        $description = $productData['description'] ?? '';
        $features = $productData['features'] ?? [];
        $specifications = $productData['specifications'] ?? [];
        $title = $productData['title'] ?? '';
        
        // 모든 텍스트를 합쳐서 검색
        $searchTexts = [
            $title,
            $description,
            implode(' ', $features),
            implode(' ', array_values($specifications))
        ];
        
        foreach ($searchTexts as $searchText) {
            if (empty($searchText)) continue;
            
            // 다양한 무게 패턴 매칭 (우선순위별)
            $weightPatterns = [
                // 1. 정확한 무게 표현 (일본어)
                '/重量[:：]?\s*(\d+(?:\.\d+)?)\s*(g|kg|グラム|キログラム)/ui',
                '/重さ[:：]?\s*(\d+(?:\.\d+)?)\s*(g|kg|グラム|キログラム)/ui',
                '/本体重量[:：]?\s*(\d+(?:\.\d+)?)\s*(g|kg|グラム|キログラム)/ui',
                
                // 2. 영어 표현
                '/weight[:：]?\s*(\d+(?:\.\d+)?)\s*(g|kg|gram|kilogram)/ui',
                '/mass[:：]?\s*(\d+(?:\.\d+)?)\s*(g|kg|gram|kilogram)/ui',
                
                // 3. 일반적인 숫자+단위 패턴
                '/(\d+(?:\.\d+)?)\s*(g|kg|グラム|キログラム)(?![a-zA-Z])/ui',
                
                // 4. 괄호 안의 무게 정보
                '/\((\d+(?:\.\d+)?)\s*(g|kg|グラム|キログラム)\)/ui',
                
                // 5. 콤마가 포함된 숫자 (예: 1,200g)
                '/(\d{1,3}(?:,\d{3})*(?:\.\d+)?)\s*(g|kg|グラム|キログラム)/ui',
                
                // 6. 약 또는 대략을 나타내는 표현
                '/(?:約|およそ|around|approximately)[:：]?\s*(\d+(?:\.\d+)?)\s*(g|kg|グラム|キログラム)/ui',
            ];
            
            foreach ($weightPatterns as $pattern) {
                if (preg_match($pattern, $searchText, $matches)) {
                    $weightValue = str_replace(',', '', $matches[1]); // 콤마 제거
                    $weight = (float) $weightValue;
                    $unit = strtolower($matches[2]);
                    
                    // 단위 정규화
                    $unit = str_replace(['グラム', 'gram', 'kilogram'], ['g', 'g', 'kg'], $unit);
                    $unit = str_replace('キログラム', 'kg', $unit);
                    
                    // kg를 g로 변환
                    if ($unit === 'kg') {
                        $weight = $weight * 1000;
                    }
                    
                    // 합리적인 범위인지 확인 (1g ~ 100kg)
                    if ($weight >= 1 && $weight <= 100000) {
                        return (int) $weight;
                    }
                }
            }
        }
        
        // 무게 정보를 찾지 못한 경우 기본값 500g 적용
        Log::info("무게 정보를 찾지 못하여 기본값 적용: 500g", [
            'asin' => $productData['asin'] ?? 'unknown',
            'title' => $productData['title'] ?? 'unknown'
        ]);
        
        return 500; // 기본값 500그램
    }

    /**
     * 치수 추출
     */
    private function extractDimensions(array $productData): ?string
    {
        // 이미 dimensions 필드가 있으면 사용
        if (!empty($productData['dimensions'])) {
            return $productData['dimensions'];
        }

        $description = $productData['description'] ?? '';
        $features = $productData['features'] ?? [];
        $title = $productData['title'] ?? '';
        
        // 모든 텍스트를 합쳐서 검색
        $searchText = $title . ' ' . $description . ' ' . implode(' ', $features);
        
        // 다양한 치수 패턴 매칭 (우선순위별)
        $dimensionPatterns = [
            // 1. 일반적인 × 형식 (cm, mm)
            '/(\d+(?:\.\d+)?)\s*[×xX]\s*(\d+(?:\.\d+)?)\s*[×xX]\s*(\d+(?:\.\d+)?)\s*(cm|mm|センチ|ミリ)/ui',
            // 2. W x H x D 형식
            '/(?:幅|width|w)?\s*(\d+(?:\.\d+)?)\s*[×xX]\s*(?:高さ|height|h)?\s*(\d+(?:\.\d+)?)\s*[×xX]\s*(?:奥行き|depth|d)?\s*(\d+(?:\.\d+)?)\s*(cm|mm|センチ|ミリ)/ui',
            // 3. 길이 x 폭 x 높이 형식 (일본어)
            '/(?:長さ|幅|高さ).*?(\d+(?:\.\d+)?)\s*[×xX]\s*(\d+(?:\.\d+)?)\s*[×xX]\s*(\d+(?:\.\d+)?)\s*(cm|mm|センチ|ミリ)/ui',
            // 4. 제품寸法 형식
            '/(?:寸法|サイズ|dimensions?)[:：]\s*(\d+(?:\.\d+)?)\s*[×xX]\s*(\d+(?:\.\d+)?)\s*[×xX]\s*(\d+(?:\.\d+)?)\s*(cm|mm|センチ|ミリ)/ui',
            // 5. 단순한 숫자 x 숫자 x 숫자 형식
            '/(\d{1,3}(?:\.\d{1,2})?)\s*[×xX]\s*(\d{1,3}(?:\.\d{1,2})?)\s*[×xX]\s*(\d{1,3}(?:\.\d{1,2})?)\s*(cm|mm|センチ|ミリ)/ui',
        ];
        
        foreach ($dimensionPatterns as $pattern) {
            if (preg_match($pattern, $searchText, $matches)) {
                $unit = strtolower($matches[4]);
                $unit = str_replace(['センチ', 'ミリ'], ['cm', 'mm'], $unit);
                return "{$matches[1]} x {$matches[2]} x {$matches[3]} {$unit}";
            }
        }
        
        // 2차원 치수 패턴도 시도
        $twoDPatterns = [
            '/(\d+(?:\.\d+)?)\s*[×xX]\s*(\d+(?:\.\d+)?)\s*(cm|mm|センチ|ミリ)/ui',
        ];
        
        foreach ($twoDPatterns as $pattern) {
            if (preg_match($pattern, $searchText, $matches)) {
                $unit = strtolower($matches[3]);
                $unit = str_replace(['センチ', 'ミリ'], ['cm', 'mm'], $unit);
                return "{$matches[1]} x {$matches[2]} {$unit}";
            }
        }
        
        return null;
    }

    /**
     * 이미지 URL 추출
     */
    private function extractImages(array $productData): array
    {
        $images = [];
        
        // AmazonScraperService에서 반환하는 'image' 키 확인
        if (isset($productData['image']) && !empty($productData['image']) && $productData['image'] !== 'N/A') {
            $images[] = $productData['image'];
        }
        
        // 추가 이미지들도 확인
        if (isset($productData['image_url']) && !empty($productData['image_url'])) {
            $images[] = $productData['image_url'];
        }
        
        if (isset($productData['additional_images'])) {
            $images = array_merge($images, $productData['additional_images']);
        }
        
        // 빈 값이나 'N/A' 제거
        $images = array_filter($images, function($img) {
            return !empty($img) && $img !== 'N/A';
        });
        
        return array_unique($images);
    }

    /**
     * 썸네일 이미지 추출
     */
    private function extractThumbnailImages(array $productData): array
    {
        if (isset($productData['thumbnail_images']) && is_array($productData['thumbnail_images'])) {
            return array_filter($productData['thumbnail_images'], function($img) {
                return !empty($img) && $img !== 'N/A';
            });
        }
        
        return [];
    }

    /**
     * 큰 이미지 추출
     */
    private function extractLargeImages(array $productData): array
    {
        if (isset($productData['large_images']) && is_array($productData['large_images'])) {
            return array_filter($productData['large_images'], function($img) {
                return !empty($img) && $img !== 'N/A';
            });
        }
        
        return [];
    }

    /**
     * 설명 영역 이미지 추출
     */
    private function extractDescriptionImages(array $productData): array
    {
        if (isset($productData['image_urls']) && is_array($productData['image_urls'])) {
            return array_filter($productData['image_urls'], function($img) {
                return !empty($img) && $img !== 'N/A';
            });
        }
        
        return [];
    }

    /**
     * 특징 추출
     */
    private function extractFeatures(array $productData): array
    {
        return $productData['features'] ?? [];
    }

    /**
     * 사양 추출
     */
    private function extractSpecifications(array $productData): array
    {
        return $productData['specifications'] ?? [];
    }

    /**
     * 상품 카테고리 자동 판정
     */
    private function determineCategory(CollectedProduct $product, array $productData = []): string
    {
        // 스크래핑 데이터에서 카테고리가 있으면 우선 사용
        if (!empty($productData['category'])) {
            return $productData['category'];
        }
        
        // 이미 저장된 카테고리가 있으면 사용
        if ($product->category) {
            return $product->category;
        }

        // 제목 기반 카테고리 자동 판정
        $title = strtolower($product->title);
        
        // 키워드 매핑 (영어, 일본어, 한국어 포함)
        $categoryKeywords = [
            'electronics' => [
                'ipad', 'iphone', 'samsung', 'sony', 'nintendo', 'switch', 'camera', 'monitor', 'tv', 'computer', 'mouse', 'keyboard',
                'headphone', 'イヤホン', 'ヘッドホン', 'スマートフォン', 'タブレット', 'カメラ', 'テレビ', 'パソコン',
                '아이패드', '아이폰', '삼성', '소니', '닌텐도', '스위치', '카메라', '모니터', '컴퓨터', '마우스', '키보드'
            ],
            'beauty' => [
                'cosmetic', 'cream', 'lotion', 'makeup', 'beauty', 'perfume', 'skincare',
                '化粧品', 'スキンケア', 'コスメ', '美容', '香水',
                '화장품', '스킨케어', '코스메틱', '미용', '향수', '크림', '로션'
            ],
            'toys_hobbies' => [
                'toy', 'figure', 'rc', 'hobby', 'game', 'puzzle', 'doll',
                'おもちゃ', 'フィギュア', 'ゲーム', 'パズル', '人形',
                '장난감', '피규어', '게임', '퍼즐', '인형', '취미'
            ],
            'fashion' => [
                'clothing', 'shirt', 'pants', 'shoes', 'bag', 'watch', 'dress', 'jacket',
                '服', '靴', 'バッグ', '時計', 'ドレス', 'ジャケット',
                '옷', '신발', '가방', '시계', '드레스', '자켓', '셔츠', '바지'
            ],
            'food' => [
                'food', 'snack', 'tea', 'coffee', 'chocolate', 'candy',
                '食品', 'お菓子', '茶', 'コーヒー', 'チョコレート',
                '식품', '과자', '차', '커피', '초콜릿', '사탕'
            ],
            'books' => [
                'book', 'manga', 'novel', 'magazine',
                '本', '漫画', '小説', '雑誌',
                '책', '만화', '소설', '잡지'
            ],
            'baby' => [
                'baby', 'diaper', 'milk', 'stroller',
                '赤ちゃん', 'ベビー', 'おむつ', 'ベビーカー',
                '아기', '베이비', '기저귀', '유모차'
            ],
            'automotive' => [
                'car', 'auto', 'tire', 'motor',
                '車', '自動車', 'タイヤ', 'モーター',
                '자동차', '차', '타이어', '모터'
            ],
            'sports' => [
                'sport', 'fitness', 'outdoor', 'exercise', 'ball',
                'スポーツ', 'アウトドア', '運動', 'ボール',
                '스포츠', '아웃도어', '운동', '볼', '피트니스'
            ]
        ];

        foreach ($categoryKeywords as $category => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($title, $keyword) !== false) {
                    return $category;
                }
            }
        }

        return 'daily_necessities'; // 기본값
    }

    /**
     * 서브카테고리 자동 판정
     */
    private function determineSubcategory(string $category, CollectedProduct $product, array $productData = []): ?string
    {
        // 스크래핑 데이터에서 서브카테고리가 있으면 사용
        if (!empty($productData['subcategory'])) {
            return $productData['subcategory'];
        }

        $title = strtolower($product->title);
        
        // 카테고리별 서브카테고리 키워드 매핑
        $subcategoryKeywords = [
            'electronics' => [
                'computers' => ['computer', 'pc', 'laptop', 'パソコン', '컴퓨터', '노트북'],
                'keyboards_mouse' => ['keyboard', 'mouse', 'キーボード', 'マウス', '키보드', '마우스'],
                'cameras' => ['camera', 'カメラ', '카메라'],
                'tablets' => ['ipad', 'tablet', 'タブレット', '태블릿', '아이패드'],
                'games' => ['nintendo', 'switch', 'game', 'ゲーム', '게임', '닌텐도', '스위치'],
                'monitors' => ['monitor', 'display', 'モニター', '모니터', '디스플레이'],
                'tv' => ['tv', 'television', 'テレビ', '텔레비전', 'TV']
            ],
            'toys_hobbies' => [
                'rc_toys' => ['rc', 'remote', 'ラジコン', 'RC', '리모컨'],
                'figures' => ['figure', 'フィギュア', '피규어'],
                'games' => ['game', 'ゲーム', '게임']
            ],
            'fashion' => [
                'clothing' => ['shirt', 'pants', 'dress', 'jacket', '服', 'シャツ', '셔츠', '바지'],
                'accessories' => ['bag', 'watch', 'バッグ', '時計', '가방', '시계']
            ]
        ];

        if (isset($subcategoryKeywords[$category])) {
            foreach ($subcategoryKeywords[$category] as $subcategory => $keywords) {
                foreach ($keywords as $keyword) {
                    if (strpos($title, $keyword) !== false) {
                        return $subcategory;
                    }
                }
            }
        }

        return null;
    }

    /**
     * 베스트셀러 페이지에서 ASIN 목록 추출
     */
    private function extractBestsellerAsins(string $url, int $maxResults = 20): array
    {
        try {
            $response = Http::timeout(30)->get($this->pythonScraperUrl . '/scrape/amazon/bestsellers/asins', [
                'url' => $url,
                'limit' => min($maxResults, 50)
            ]);

            if (!$response->successful()) {
                throw new Exception("Python 스크래퍼 API 호출 실패: " . $response->status() . " - " . $response->body());
            }

            $data = $response->json();

            if (!$data['success']) {
                throw new Exception("베스트셀러 ASIN 추출 실패: " . ($data['message'] ?? 'Unknown error'));
            }

            $asins = $data['asins'] ?? [];
            
            if (empty($asins)) {
                throw new Exception('베스트셀러 페이지에서 상품을 찾을 수 없습니다.');
            }

            Log::info("베스트셀러 페이지에서 " . count($asins) . "개 ASIN 추출 성공", [
                'url' => $url,
                'asins' => $asins
            ]);

            return $asins;

        } catch (Exception $e) {
            Log::error("베스트셀러 ASIN 추출 실패", [
                'url' => $url,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Python 스크래퍼로 상품 정보 스크래핑
     */
    private function scrapeProductFromPython(string $site, array $params): array
    {
        try {
            $url = $this->pythonScraperUrl . "/scrape/{$site}";
            
            // 번역 파라미터 추가
            $params['translate'] = true;
            
            $response = Http::timeout(60)->get($url, $params);
            
            if (!$response->successful()) {
                throw new Exception("Python 스크래퍼 API 호출 실패: " . $response->status() . " - " . $response->body());
            }
            
            $data = $response->json();
            
            if (!$data['success']) {
                throw new Exception("Python 스크래퍼에서 오류 반환: " . ($data['message'] ?? 'Unknown error'));
            }
            
            // 원문 데이터 포함 여부 로그
            Log::info("Python 스크래퍼 응답 데이터 확인", [
                'has_original_name' => isset($data['data']['original_name']),
                'has_original_category' => isset($data['data']['original_category']),
                'has_original_description' => isset($data['data']['original_description']),
                'has_original_features' => isset($data['data']['original_features']),
                'original_name_sample' => isset($data['data']['original_name']) ? substr($data['data']['original_name'], 0, 50) . '...' : null
            ]);
            
            return $data['data'];
            
        } catch (Exception $e) {
            Log::error("Python 스크래퍼 호출 실패", [
                'site' => $site,
                'params' => $params,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

}