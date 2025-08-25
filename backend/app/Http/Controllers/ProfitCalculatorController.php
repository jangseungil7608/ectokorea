<?php

namespace App\Http\Controllers;

use App\Services\ProfitCalculatorService;
use App\Services\HaniroShippingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProfitCalculatorController extends Controller
{
    private ProfitCalculatorService $profitCalculatorService;
    private HaniroShippingService $haniroShippingService;
    
    public function __construct(ProfitCalculatorService $profitCalculatorService, HaniroShippingService $haniroShippingService)
    {
        $this->profitCalculatorService = $profitCalculatorService;
        $this->haniroShippingService = $haniroShippingService;
    }
    
    /**
     * 이익 계산
     */
    public function calculate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_price_jpy' => 'required|numeric|min:0',
            'japan_shipping_jpy' => 'nullable|numeric|min:0',
            'product_weight_g' => 'nullable|numeric|min:0',
            'shipping_method' => ['nullable', Rule::in(['economy', 'premium'])],
            'category' => ['nullable', Rule::in([
                'electronics', 'beauty', 'toys_hobbies', 'fashion', 'food', 'books', 
                'daily_necessities', 'automotive', 'sports', 'baby',
                // 하위 호환성을 위한 기존 카테고리
                'cosmetics', 'general'
            ])],
            'subcategory' => 'nullable|string',
            'sell_price_krw' => 'required|numeric|min:0',
            'korea_shipping_krw' => 'nullable|numeric|min:0',
            'packaging_fee_krw' => 'nullable|numeric|min:0'
        ]);
        
        try {
            $calculation = $this->profitCalculatorService->calculateProfit($validated);
            
            return response()->json([
                'success' => true,
                'data' => $calculation
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '이익 계산에 실패했습니다.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 추천 판매가 계산
     */
    public function recommendPrice(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_price_jpy' => 'required|numeric|min:0',
            'japan_shipping_jpy' => 'nullable|numeric|min:0',
            'product_weight_g' => 'nullable|numeric|min:0',
            'shipping_method' => ['nullable', Rule::in(['economy', 'premium'])],
            'category' => ['nullable', Rule::in([
                'electronics', 'beauty', 'toys_hobbies', 'fashion', 'food', 'books', 
                'daily_necessities', 'automotive', 'sports', 'baby',
                // 하위 호환성을 위한 기존 카테고리
                'cosmetics', 'general'
            ])],
            'subcategory' => 'nullable|string',
            'target_profit_margin' => 'nullable|numeric|min:5|max:50',
            'korea_shipping_krw' => 'nullable|numeric|min:0',
            'packaging_fee_krw' => 'nullable|numeric|min:0'
        ]);
        
        try {
            $targetMargin = $validated['target_profit_margin'] ?? 20;
            unset($validated['target_profit_margin']);
            
            $recommendation = $this->profitCalculatorService->calculateRecommendedPrice(
                $validated, 
                $targetMargin
            );
            
            return response()->json([
                'success' => true,
                'data' => $recommendation
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '추천 가격 계산에 실패했습니다.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 카테고리별 정보 조회
     */
    public function getCategories(): JsonResponse
    {
        $categories = [
            'electronics' => [
                'name' => '가전디지털',
                'customs_rate' => 8,
                'default_coupang_fee_rate' => 7.8,
                'description' => '전자제품, 컴퓨터, 게임용품 등',
                'subcategories' => [
                    'computers' => [
                        'name' => '컴퓨터',
                        'coupang_fee_rate' => 5.0,
                        'description' => '데스크톱, 노트북 등'
                    ],
                    'keyboards_mouse' => [
                        'name' => '마우스/키보드',
                        'coupang_fee_rate' => 6.5,
                        'description' => '게이밍 키보드, 마우스 등'
                    ],
                    'cameras' => [
                        'name' => '카메라/카메라용품',
                        'coupang_fee_rate' => 5.8,
                        'description' => '디지털카메라, DSLR, 액세서리'
                    ],
                    'tablets' => [
                        'name' => '태블릿PC',
                        'coupang_fee_rate' => 5.0,
                        'description' => '태블릿 및 액세서리'
                    ],
                    'games' => [
                        'name' => '게임',
                        'coupang_fee_rate' => 6.8,
                        'description' => '게임기, 게임 소프트웨어'
                    ],
                    'monitors' => [
                        'name' => '모니터',
                        'coupang_fee_rate' => 4.5,
                        'description' => '컴퓨터 모니터'
                    ],
                    'tv' => [
                        'name' => 'TV',
                        'coupang_fee_rate' => 5.8,
                        'description' => '텔레비전'
                    ]
                ]
            ],
            'beauty' => [
                'name' => '뷰티',
                'customs_rate' => 8,
                'default_coupang_fee_rate' => 9.6,
                'description' => '화장품, 스킨케어, 향수 등',
                'subcategories' => []
            ],
            'toys_hobbies' => [
                'name' => '완구/취미',
                'customs_rate' => 8,
                'default_coupang_fee_rate' => 10.8,
                'description' => '장난감, 피규어, 취미용품 등',
                'subcategories' => [
                    'rc_toys' => [
                        'name' => 'RC완구',
                        'coupang_fee_rate' => 7.8,
                        'description' => 'RC카, 드론 등'
                    ],
                    'figures' => [
                        'name' => '피규어/장난감',
                        'coupang_fee_rate' => 10.8,
                        'description' => '애니메이션 피규어, 장난감'
                    ]
                ]
            ],
            'fashion' => [
                'name' => '패션',
                'customs_rate' => 13,
                'default_coupang_fee_rate' => 10.5,
                'description' => '의류, 신발, 가방, 액세서리',
                'subcategories' => [
                    'clothing' => [
                        'name' => '패션의류',
                        'coupang_fee_rate' => 10.5,
                        'description' => '남성/여성/아동 의류'
                    ],
                    'accessories' => [
                        'name' => '패션잡화',
                        'coupang_fee_rate' => 10.5,
                        'description' => '가방, 지갑, 액세서리'
                    ]
                ]
            ],
            'food' => [
                'name' => '식품',
                'customs_rate' => 30,
                'default_coupang_fee_rate' => 10.6,
                'description' => '과자, 음료, 조미료 등 (수입제한 주의)',
                'subcategories' => []
            ],
            'books' => [
                'name' => '도서',
                'customs_rate' => 0,
                'default_coupang_fee_rate' => 10.8,
                'description' => '서적, 잡지, 만화책 등',
                'subcategories' => []
            ],
            'daily_necessities' => [
                'name' => '생활용품',
                'customs_rate' => 8,
                'default_coupang_fee_rate' => 7.8,
                'description' => '생활잡화, 청소용품, 수납용품 등',
                'subcategories' => []
            ],
            'automotive' => [
                'name' => '자동차용품',
                'customs_rate' => 8,
                'default_coupang_fee_rate' => 10.0,
                'description' => '차량용 전자기기, 액세서리 등',
                'subcategories' => []
            ],
            'sports' => [
                'name' => '스포츠/레저',
                'customs_rate' => 8,
                'default_coupang_fee_rate' => 10.8,
                'description' => '스포츠용품, 레저용품, 캠핑용품 등',
                'subcategories' => []
            ],
            'baby' => [
                'name' => '출산/유아',
                'customs_rate' => 8,
                'default_coupang_fee_rate' => 10.0,
                'description' => '기저귀, 유아용품, 임산부용품 등',
                'subcategories' => []
            ]
        ];
        
        return response()->json([
            'success' => true,
            'data' => $categories
        ]);
    }
    
    /**
     * 배송 옵션 정보
     */
    public function getShippingOptions(): JsonResponse
    {
        $shippingOptions = $this->haniroShippingService->getShippingTypes();
        
        return response()->json([
            'success' => true,
            'data' => $shippingOptions
        ]);
    }
    
    /**
     * 배송비 계산 디버그
     */
    public function debugShipping(): JsonResponse
    {
        try {
            $debug = $this->haniroShippingService->debug1kgRate('economy');
            
            return response()->json([
                'success' => true,
                'debug' => $debug
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 추천 판매가 계산 디버그 (테스트용)
     */
    public function debugRecommendPrice(Request $request): JsonResponse
    {
        // 600g 기본 테스트 데이터
        $testData = [
            'product_price_jpy' => 1000,
            'japan_shipping_jpy' => 500,
            'product_weight_g' => 600,
            'shipping_method' => 'economy',
            'category' => 'general',
            'korea_shipping_krw' => 3000,
            'packaging_fee_krw' => 1000
        ];
        
        // 요청 데이터가 있으면 덮어쓰기
        $data = array_merge($testData, $request->all());
        $targetMargin = $request->get('target_profit_margin', 10);
        
        try {
            // 단계별 계산 과정 디버깅
            $debug = [];
            
            // 1. 국제배송비 계산
            $internationalShipping = $this->haniroShippingService->calculateShippingFee($data['product_weight_g'], $data['shipping_method']);
            $debug['international_shipping'] = $internationalShipping;
            
            // 2. 일본 비용 총합
            $japanCosts = $data['product_price_jpy'] + ($data['japan_shipping_jpy'] ?? 0) + $internationalShipping;
            $debug['japan_costs_jpy'] = $japanCosts;
            
            // 3. 환율 변환 (임시로 1500 사용)
            $exchangeRate = 1500;
            $krwCostBeforeTax = $japanCosts * $exchangeRate;
            $debug['krw_cost_before_tax'] = $krwCostBeforeTax;
            
            // 4. 한국 비용
            $koreaLocalCosts = ($data['korea_shipping_krw'] ?? 3000) + ($data['packaging_fee_krw'] ?? 1000);
            $debug['korea_local_costs'] = $koreaLocalCosts;
            
            // 5. 플랫폼 수수료율
            $categories = [
                'cosmetics' => 0.15, 'fashion' => 0.12, 'electronics' => 0.10,
                'food' => 0.18, 'books' => 0.10, 'general' => 0.12
            ];
            $platformFeeRate = $categories[$data['category']] ?? 0.12;
            $debug['platform_fee_rate'] = $platformFeeRate;
            
            // 6. 총 비용 (세금 제외)
            $totalCost = $krwCostBeforeTax + $koreaLocalCosts;
            $debug['total_cost'] = $totalCost;
            
            // 7. Net Margin Rate 계산
            $netMarginRate = 1 - ($targetMargin / 100) - $platformFeeRate;
            $debug['target_margin'] = $targetMargin;
            $debug['net_margin_rate'] = $netMarginRate;
            
            // 8. 추천 가격 계산
            $recommendedPrice = ceil(($totalCost / $netMarginRate) / 100) * 100;
            $debug['recommended_price'] = $recommendedPrice;
            
            // 9. 실제 이익률 계산
            $platformFees = $recommendedPrice * $platformFeeRate;
            $netProfit = $recommendedPrice - $totalCost - $platformFees;
            $actualMargin = ($netProfit / $recommendedPrice) * 100;
            $debug['platform_fees'] = $platformFees;
            $debug['net_profit'] = $netProfit;
            $debug['actual_margin'] = $actualMargin;
            
            // 10. 정확한 계산식 검증
            $debug['verification'] = [
                'sell_price' => $recommendedPrice,
                'minus_platform_fee' => $recommendedPrice - $platformFees,
                'minus_total_cost' => $recommendedPrice - $platformFees - $totalCost,
                'should_equal_net_profit' => $netProfit
            ];
            
            return response()->json([
                'success' => true,
                'debug' => $debug,
                'input_data' => $data
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}