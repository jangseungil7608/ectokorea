<?php

namespace App\Console\Commands;

use App\Services\HaniroShippingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\DomCrawler\Crawler;

class UpdateHaniroRates extends Command
{
    protected $signature = 'haniro:update-rates';
    protected $description = 'HANIRO LINE 배송료 요금표를 업데이트합니다';

    private HaniroShippingService $haniroService;

    public function __construct(HaniroShippingService $haniroService)
    {
        parent::__construct();
        $this->haniroService = $haniroService;
    }

    public function handle(): int
    {
        $this->info('HANIRO LINE 배송료 요금표 업데이트를 시작합니다...');

        try {
            // 웹사이트에서 최신 요금표 스크래핑 (실제로는 API가 있다면 API 사용)
            $newRates = $this->fetchLatestRates();
            
            if ($newRates) {
                $success = $this->haniroService->updateRates($newRates);
                
                if ($success) {
                    $this->info('✅ HANIRO LINE 배송료 요금표가 성공적으로 업데이트되었습니다.');
                    Log::info('HANIRO 배송료 배치 업데이트 성공');
                    return self::SUCCESS;
                } else {
                    $this->error('❌ 요금표 업데이트에 실패했습니다.');
                    return self::FAILURE;
                }
            } else {
                $this->error('❌ 최신 요금표를 가져올 수 없습니다.');
                return self::FAILURE;
            }
        } catch (\Exception $e) {
            $this->error('❌ 배치 처리 중 오류 발생: ' . $e->getMessage());
            Log::error('HANIRO 배송료 배치 업데이트 실패', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return self::FAILURE;
        }
    }

    /**
     * 웹 스크래핑으로 최신 요금표 가져오기
     */
    private function fetchLatestRates(): ?array
    {
        try {
            $this->info('HANIRO LINE 웹사이트에서 최신 요금표를 스크래핑 중...');
            
            // 웹페이지 요청
            $response = Http::timeout(30)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
                ])
                ->get('https://www.ohmyzip.com/how-it-works/shipping-fee/haniro');
            
            if (!$response->successful()) {
                throw new \Exception('웹페이지 요청 실패: ' . $response->status());
            }
            
            $html = $response->body();
            $crawler = new Crawler($html);
            
            // 요금표 스크래핑
            $economyRates = $this->scrapeRateTable($crawler, 'Economy');
            $premiumRates = $this->scrapeRateTable($crawler, 'Premium');
            
            if (empty($economyRates) || empty($premiumRates)) {
                $this->warn('스크래핑된 요금표가 비어있습니다. 기본 요금표를 사용합니다.');
                return $this->getDefaultRates();
            }
            
            $this->info('스크래핑 완료: Economy ' . count($economyRates) . '개, Premium ' . count($premiumRates) . '개 요금 발견');
            
            return [
                "rates" => [
                    "economy" => [
                        "name" => "HANIRO LINE Economy",
                        "description" => "일반 요금제 (1-100건/월)",
                        "base_rate" => 9.00,
                        "increment_rate" => 1.00,
                        "increment_weight" => 0.5,
                        "max_weight" => 70.0,
                        "rates" => $economyRates
                    ],
                    "premium" => [
                        "name" => "HANIRO LINE Premium", 
                        "description" => "프리미엄 요금제 (101-200건/월)",
                        "base_rate" => 8.50,
                        "increment_rate" => 1.00,
                        "increment_weight" => 0.5,
                        "max_weight" => 70.0,
                        "rates" => $premiumRates
                    ]
                ],
                "currency" => "P",
                "exchange_rate_p_to_jpy" => 100
            ];
            
        } catch (\Exception $e) {
            $this->error('웹 스크래핑 실패: ' . $e->getMessage());
            $this->warn('기본 요금표를 사용합니다.');
            return $this->getDefaultRates();
        }
    }
    
    /**
     * 요금표 테이블 스크래핑
     */
    private function scrapeRateTable(Crawler $crawler, string $type): array
    {
        $rates = [];
        
        try {
            $this->info("$type 요금표 스크래핑 시작...");
            
            // 테이블 찾기
            $tables = $crawler->filter('table');
            $this->info("발견된 테이블 수: " . $tables->count());
            
            if ($tables->count() === 0) {
                $this->warn('테이블을 찾을 수 없습니다.');
                return $rates;
            }
            
            $tables->each(function (Crawler $table, $tableIndex) use (&$rates, $type) {
                $this->info("테이블 $tableIndex 분석 중...");
                
                // 모든 행 찾기 (thead, tbody 구분 없이)
                $rows = $table->filter('tr');
                $this->info("발견된 행 수: " . $rows->count());
                
                if ($rows->count() === 0) {
                    return;
                }
                
                // 첫 번째 행에서 헤더 정보 찾기
                $headerRow = $rows->first();
                $headers = [];
                $headerRow->filter('th, td')->each(function (Crawler $cell, $index) use (&$headers) {
                    $text = trim($cell->text());
                    $headers[$index] = $text;
                    $this->line("헤더 $index: $text");
                });
                
                // 고정 컬럼 인덱스 사용 (웹페이지 구조 기준)
                $weightColumnIndex = 0;  // 첫 번째 컬럼: 무게 (Weight)
                $typeColumnIndex = -1;
                
                // 컬럼 구조: Weight | Economy | Premium | Business
                if ($type === 'Economy') {
                    $typeColumnIndex = 1;  // 두 번째 컬럼: Economy (월간 1-100건)
                    $this->info("Economy 컬럼 사용: $typeColumnIndex");
                } elseif ($type === 'Premium') {
                    $typeColumnIndex = 2;  // 세 번째 컬럼: Premium (월간 101-200건)
                    $this->info("Premium 컬럼 사용: $typeColumnIndex");
                } else {
                    $this->warn("지원하지 않는 타입: $type");
                    return;
                }
                
                // 데이터 행 스크래핑 (첫 번째 행은 헤더이므로 제외)
                $dataRows = $rows->slice(1);
                $this->info("데이터 행 수: " . $dataRows->count());
                
                $dataRows->each(function (Crawler $row, $rowIndex) use (&$rates, $typeColumnIndex, $weightColumnIndex, $type) {
                    $cells = $row->filter('td, th');
                    
                    if ($cells->count() > max($typeColumnIndex, $weightColumnIndex)) {
                        $weightText = trim($cells->eq($weightColumnIndex)->text());
                        $rateText = trim($cells->eq($typeColumnIndex)->text());
                        
                        $this->line("행 $rowIndex - 무게: '$weightText', $type 요금: '$rateText'");
                        
                        // 무게 추출 (예: "0.50" -> "0.5")
                        if (preg_match('/(\d+\.?\d*)/', $weightText, $weightMatches)) {
                            $weight = (string) ((float) $weightMatches[1]);
                            
                            // 요금 추출 (예: "9.00" -> 9.00)
                            if (preg_match('/(\d+\.?\d*)/', $rateText, $rateMatches)) {
                                $rate = (float) $rateMatches[1];
                                
                                
                                $rates[$weight] = $rate;
                                $this->info("추가됨: {$weight}kg = {$rate}P");
                            }
                        }
                    }
                });
            });
            
            $this->info("$type 요금표 스크래핑 완료: " . count($rates) . "개 요금");
            
        } catch (\Exception $e) {
            $this->error("$type 요금표 스크래핑 실패: " . $e->getMessage());
        }
        
        return $rates;
    }
    
    /**
     * 기본 요금표 반환 (스크래핑 실패 시)
     */
    private function getDefaultRates(): array
    {
        return [
            "rates" => [
                "economy" => [
                    "name" => "HANIRO LINE Economy",
                    "description" => "일반 요금제 (1-100건/월)",
                    "base_rate" => 9.00,
                    "increment_rate" => 1.00,
                    "increment_weight" => 0.5,
                    "max_weight" => 70.0,
                    "rates" => $this->generateRateTable(9.00, 1.00, 0.5, 70.0)
                ],
                "premium" => [
                    "name" => "HANIRO LINE Premium",
                    "description" => "프리미엄 요금제 (101-200건/월)",
                    "base_rate" => 8.50,
                    "increment_rate" => 1.00,
                    "increment_weight" => 0.5,
                    "max_weight" => 70.0,
                    "rates" => $this->generateRateTable(8.50, 1.00, 0.5, 70.0)
                ]
            ],
            "currency" => "P",
            "exchange_rate_p_to_jpy" => 100
        ];
    }

    /**
     * 요금표 생성
     */
    private function generateRateTable(float $baseRate, float $incrementRate, float $incrementWeight, float $maxWeight): array
    {
        $rates = [];
        
        for ($weight = 0.5; $weight <= $maxWeight; $weight += $incrementWeight) {
            $rate = $baseRate + (($weight - 0.5) / $incrementWeight) * $incrementRate;
            $rates[(string)$weight] = $rate;
        }
        
        return $rates;
    }
}