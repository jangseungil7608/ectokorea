<?php

namespace App\Services;

use App\Services\ExchangeRateService;
use App\Services\HaniroShippingService;

class ProfitCalculatorService
{
    private ExchangeRateService $exchangeRateService;
    private HaniroShippingService $haniroShippingService;
    
    public function __construct(ExchangeRateService $exchangeRateService, HaniroShippingService $haniroShippingService)
    {
        $this->exchangeRateService = $exchangeRateService;
        $this->haniroShippingService = $haniroShippingService;
    }
    
    /**
     * 전체 이익 계산
     */
    public function calculateProfit(array $data): array
    {
        // 입력 데이터 검증
        $validated = $this->validateInput($data);
        
        // 1. 일본 측 비용 계산 (JPY)
        $japanCosts = $this->calculateJapanCosts($validated);
        
        // 2. 국제배송비 계산 (JPY)
        $internationalShipping = $this->calculateInternationalShipping($validated);
        
        // 3. 총 JPY 비용 계산
        $totalJpyCost = $japanCosts + $internationalShipping;
        
        // 4. KRW로 환산
        $exchangeRate = $this->exchangeRateService->getJpyToKrwRate();
        $krwCostBeforeTax = $totalJpyCost * $exchangeRate;
        
        // 5. 관세 및 세금 계산 (KRW)
        $taxes = $this->calculateTaxes($krwCostBeforeTax, $validated['category']);
        
        // 6. 한국 내 비용 계산 (KRW)
        $koreaLocalCosts = $this->calculateKoreaLocalCosts($validated);
        
        // 7. 총 비용 계산 (관세+부가세는 구매자 부담이므로 제외)
        $totalCostKrw = $krwCostBeforeTax + $koreaLocalCosts;
        
        // 8. 플랫폼 수수료 계산
        $platformFees = $this->calculatePlatformFees($validated['sell_price_krw'], $validated['category'], $validated['subcategory']);
        
        // 9. 최종 이익 계산
        $netProfit = $validated['sell_price_krw'] - $totalCostKrw - $platformFees['total_fee'];
        $profitMargin = $validated['sell_price_krw'] > 0 ? 
            ($netProfit / $validated['sell_price_krw']) * 100 : 0;
        
        return [
            'input' => $validated,
            'exchange_rate' => $exchangeRate,
            'costs' => [
                'japan_costs_jpy' => $japanCosts,
                'international_shipping_jpy' => $internationalShipping,
                'total_jpy_cost' => $totalJpyCost,
                'krw_cost_before_tax' => $krwCostBeforeTax,
                'korea_local_costs' => $koreaLocalCosts,
                'platform_fees' => $platformFees,
                'total_cost_krw' => $totalCostKrw
            ],
            'profit' => [
                'sell_price_krw' => $validated['sell_price_krw'],
                'net_profit' => $netProfit,
                'profit_margin_percent' => round($profitMargin, 2)
            ],
            'calculated_at' => now()->toDateTimeString()
        ];
    }
    
    /**
     * 입력 데이터 검증
     */
    private function validateInput(array $data): array
    {
        // 하위 호환성: 기존 카테고리를 새 카테고리로 매핑
        $categoryMapping = [
            'cosmetics' => 'beauty',
            'general' => 'daily_necessities'
        ];
        
        $category = $data['category'] ?? 'daily_necessities';
        if (isset($categoryMapping[$category])) {
            $category = $categoryMapping[$category];
        }
        
        return [
            'product_price_jpy' => $data['product_price_jpy'] ?? 0,
            'japan_shipping_jpy' => $data['japan_shipping_jpy'] ?? 500,
            'product_weight_g' => $data['product_weight_g'] ?? 100,
            'shipping_method' => $data['shipping_method'] ?? 'economy', // 'economy' or 'premium'
            'category' => $category,
            'subcategory' => $data['subcategory'] ?? null,
            'sell_price_krw' => $data['sell_price_krw'] ?? 0,
            'korea_shipping_krw' => $data['korea_shipping_krw'] ?? 3000,
            'packaging_fee_krw' => $data['packaging_fee_krw'] ?? 0
        ];
    }
    
    /**
     * 일본 측 비용 계산 (JPY)
     */
    private function calculateJapanCosts(array $data): float
    {
        return $data['product_price_jpy'] + $data['japan_shipping_jpy'];
    }
    
    /**
     * 국제배송비 계산 (JPY) - HANIRO LINE 배송료표 기준
     */
    private function calculateInternationalShipping(array $data): float
    {
        $weightG = $data['product_weight_g'];
        $method = $data['shipping_method'];
        
        if (!in_array($method, ['economy', 'premium'])) {
            throw new \InvalidArgumentException('지원하지 않는 배송방법입니다. Economy 또는 Premium만 지원됩니다.');
        }
        
        // HaniroShippingService를 통해 배송비 계산
        return $this->haniroShippingService->calculateShippingFee($weightG, $method);
    }
    
    /**
     * 관세 및 세금 계산 (KRW)
     * 주의: 관세와 부가세는 구매자(수입자)가 부담하는 비용으로, 판매자의 이익 계산에서는 제외됩니다.
     * 이 메서드는 구매자가 지불해야 할 세금을 참고용으로 계산합니다.
     */
    private function calculateTaxes(float $krwCostBeforeTax, string $category): array
    {
        // 카테고리별 관세율
        $customsRates = [
            'electronics' => 0.08,        // 가전디지털 8%
            'beauty' => 0.08,            // 뷰티 8%
            'toys_hobbies' => 0.08,      // 완구/취미 8%
            'fashion' => 0.13,           // 패션 13%
            'food' => 0.30,             // 식품 30%
            'books' => 0.00,            // 서적 0%
            'daily_necessities' => 0.08, // 생활용품 8%
            'automotive' => 0.08,        // 자동차용품 8%
            'sports' => 0.08,           // 스포츠/레저 8%
            'baby' => 0.08              // 출산/유아 8%
        ];
        
        $customsRate = $customsRates[$category] ?? 0.08;
        
        // 관세 면제 기준 ($150 = 약 200,000원)
        $taxExemptLimit = 200000;
        
        if ($krwCostBeforeTax <= $taxExemptLimit) {
            return [
                'customs_duty' => 0,
                'vat' => 0,
                'total_tax' => 0,
                'tax_exempt' => true,
                'reason' => '개인용품 면세 기준 이하'
            ];
        }
        
        $customsDuty = $krwCostBeforeTax * $customsRate;
        $vat = ($krwCostBeforeTax + $customsDuty) * 0.1; // 부가세 10%
        
        return [
            'customs_duty' => round($customsDuty, 0),
            'vat' => round($vat, 0),
            'total_tax' => round($customsDuty + $vat, 0),
            'tax_exempt' => false,
            'customs_rate_percent' => $customsRate * 100
        ];
    }
    
    /**
     * 한국 내 비용 계산 (KRW)
     */
    private function calculateKoreaLocalCosts(array $data): float
    {
        return $data['korea_shipping_krw'] + $data['packaging_fee_krw'];
    }
    
    /**
     * 플랫폼 수수료 계산
     */
    private function calculatePlatformFees(float $sellPrice, string $category, ?string $subcategory = null): array
    {
        $coupangFeeRate = $this->getCoupangFeeRate($category, $subcategory);
        $coupangFee = $sellPrice * $coupangFeeRate;
        
        // 쿠팡은 결제수수료를 이중으로 부과하지 않음 (판매수수료에 포함)
        
        return [
            'coupang_fee' => round($coupangFee, 0),
            'payment_fee' => 0, // 별도 결제수수료 없음
            'total_fee' => round($coupangFee, 0),
            'coupang_fee_rate_percent' => $coupangFeeRate * 100,
            'category_used' => $category,
            'subcategory_used' => $subcategory
        ];
    }
    
    /**
     * 카테고리 및 서브카테고리에 따른 쿠팡 수수료율 반환
     */
    private function getCoupangFeeRate(string $category, ?string $subcategory = null): float
    {
        // 서브카테고리별 수수료율 (우선순위 높음)
        $subcategoryRates = [
            // 가전디지털 서브카테고리
            'computers' => 0.05,           // 컴퓨터 5%
            'keyboards_mouse' => 0.065,    // 마우스/키보드 6.5%
            'cameras' => 0.058,            // 카메라 5.8%
            'tablets' => 0.05,             // 태블릿PC 5%
            'games' => 0.068,              // 게임 6.8%
            'monitors' => 0.045,           // 모니터 4.5%
            'tv' => 0.058,                 // TV 5.8%
            
            // 완구/취미 서브카테고리
            'rc_toys' => 0.078,            // RC완구 7.8%
            'figures' => 0.108,            // 피규어/장난감 10.8%
            
            // 패션 서브카테고리
            'clothing' => 0.105,           // 패션의류 10.5%
            'accessories' => 0.105         // 패션잡화 10.5%
        ];
        
        // 서브카테고리가 있고 해당 수수료율이 정의되어 있으면 우선 사용
        if ($subcategory && isset($subcategoryRates[$subcategory])) {
            return $subcategoryRates[$subcategory];
        }
        
        // 대분류 기본 수수료율
        $categoryDefaultRates = [
            'electronics' => 0.078,        // 가전디지털 7.8%
            'beauty' => 0.096,             // 뷰티 9.6%
            'toys_hobbies' => 0.108,       // 완구/취미 10.8%
            'fashion' => 0.105,            // 패션 10.5%
            'food' => 0.106,               // 식품 10.6%
            'books' => 0.108,              // 서적 10.8%
            'daily_necessities' => 0.078,  // 생활용품 7.8%
            'automotive' => 0.10,          // 자동차용품 10%
            'sports' => 0.108,             // 스포츠/레저 10.8%
            'baby' => 0.10                 // 출산/유아 10%
        ];
        
        return $categoryDefaultRates[$category] ?? 0.078; // 기본값 7.8%
    }
    
    /**
     * 목표 이익률 기반 추천 판매가 계산
     */
    public function calculateRecommendedPrice(array $data, float $targetProfitMargin = 20): array
    {
        // 기본 계산 수행 (플랫폼 수수료율 계산을 위해)
        $calculation = $this->calculateProfit(array_merge($data, ['sell_price_krw' => 10000])); // 임시 가격
        
        $totalCost = $calculation['costs']['total_cost_krw'];
        
        // 카테고리별 플랫폼 수수료율 직접 계산 (임시가격에 의존하지 않음)
        $coupangFeeRate = $this->getCoupangFeeRate($data['category'] ?? 'daily_necessities', $data['subcategory'] ?? null);
        $platformFeeRate = $coupangFeeRate; // 쿠팡 수수료만 (결제수수료 포함됨)
        
        // 목표 이익률을 위한 판매가 계산
        // sell_price * (1 - target_margin/100 - platform_fee_rate) = total_cost
        
        $netMarginRate = 1 - ($targetProfitMargin / 100) - $platformFeeRate;
        
        if ($netMarginRate <= 0) {
            return [
                'error' => '목표 이익률이 너무 높습니다. 플랫폼 수수료를 고려하여 조정해주세요.',
                'max_possible_margin' => round((1 - $platformFeeRate) * 100 - 5, 1) // 여유분 5% 제외
            ];
        }
        
        $recommendedPrice = ceil(($totalCost / $netMarginRate) / 100) * 100; // 100원 단위로 반올림
        
        // 추천 가격으로 재계산
        $finalCalculation = $this->calculateProfit(array_merge($data, ['sell_price_krw' => $recommendedPrice]));
        
        return [
            'recommended_price' => $recommendedPrice,
            'target_profit_margin' => $targetProfitMargin,
            'actual_profit_margin' => $finalCalculation['profit']['profit_margin_percent'],
            'calculation' => $finalCalculation
        ];
    }
}