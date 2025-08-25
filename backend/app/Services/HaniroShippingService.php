<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class HaniroShippingService
{
    private const RATES_FILE = 'haniro_shipping_rates.json';
    
    /**
     * 배송료 계산
     */
    public function calculateShippingFee(float $weightG, string $type = 'economy'): float
    {
        $rates = $this->loadRates();
        
        if (!isset($rates['rates'][$type])) {
            throw new \InvalidArgumentException("지원하지 않는 배송 타입입니다: {$type}");
        }
        
        $rateInfo = $rates['rates'][$type];
        
        // 무게를 KG로 변환하고 0.5KG 단위로 반올림
        $weightKg = ceil($weightG / 500) * 0.5;
        
        // 최대 무게 제한
        if ($weightKg > $rateInfo['max_weight']) {
            throw new \InvalidArgumentException("최대 배송 가능 무게({$rateInfo['max_weight']}KG)를 초과했습니다.");
        }
        
        // 정확한 무게의 요금이 있는지 확인
        // JSON 키는 정수형으로 저장되어 있으므로 소수점이 .0인 경우 정수로 변환
        $weightKey = $weightKg == floor($weightKg) ? (string)intval($weightKg) : (string)$weightKg;
        
        if (isset($rateInfo['rates'][$weightKey])) {
            $rateP = $rateInfo['rates'][$weightKey];
        } else {
            // 계산식으로 요금 산출
            $rateP = $rateInfo['base_rate'] + (($weightKg - 0.5) / $rateInfo['increment_weight']) * $rateInfo['increment_rate'];
        }
        
        // P단위를 엔화로 변환 (1P = 100엔)
        $rateJpy = $rateP * $rates['exchange_rate_p_to_jpy'];
        
        return round($rateJpy, 0);
    }
    
    /**
     * 배송 타입별 정보 조회
     */
    public function getShippingTypes(): array
    {
        $rates = $this->loadRates();
        
        $result = [];
        foreach ($rates['rates'] as $type => $info) {
            $result[$type] = [
                'name' => $info['name'],
                'description' => $info['description'],
                'max_weight' => $info['max_weight'],
                'sample_rates' => [
                    '0.5kg' => $info['rates']['0.5'] . 'P (¥' . ($info['rates']['0.5'] * 100) . ')',
                    '1.0kg' => $info['rates']['1'] . 'P (¥' . ($info['rates']['1'] * 100) . ')',
                    '2.0kg' => $info['rates']['2'] . 'P (¥' . ($info['rates']['2'] * 100) . ')',
                    '5.0kg' => $info['rates']['5'] . 'P (¥' . ($info['rates']['5'] * 100) . ')'
                ]
            ];
        }
        
        return $result;
    }
    
    /**
     * 요금표 로드 (캐시 무효화)
     */
    private function loadRates(): array
    {
        $filePath = storage_path('app/' . self::RATES_FILE);
        
        if (!file_exists($filePath)) {
            throw new \RuntimeException('배송료 요금표 파일이 존재하지 않습니다.');
        }
        
        // 캐시 무효화를 위해 직접 파일 읽기
        $content = file_get_contents($filePath);
        
        if ($content === false) {
            throw new \RuntimeException('배송료 요금표 파일을 읽을 수 없습니다.');
        }
        
        $rates = json_decode($content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('배송료 요금표 파일 형식이 올바르지 않습니다: ' . json_last_error_msg());
        }
        
        return $rates;
    }
    
    /**
     * 요금표 업데이트
     */
    public function updateRates(array $newRates): bool
    {
        try {
            $newRates['last_updated'] = now()->toISOString();
            $content = json_encode($newRates, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            
            // 파일 업데이트
            $result = file_put_contents(storage_path('app/' . self::RATES_FILE), $content);
            
            if ($result === false) {
                throw new \RuntimeException('배송료 요금표 파일 업데이트에 실패했습니다.');
            }
            
            Log::info('HANIRO 배송료 요금표가 업데이트되었습니다.', [
                'updated_at' => $newRates['last_updated']
            ]);
            
            return true;
        } catch (\Exception $e) {
            Log::error('HANIRO 배송료 요금표 업데이트 실패', [
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }
    
    /**
     * 요금표 마지막 업데이트 시간 조회
     */
    public function getLastUpdated(): ?string
    {
        try {
            $rates = $this->loadRates();
            return $rates['last_updated'] ?? null;
        } catch (\Exception $e) {
            return null;
        }
    }
    
    /**
     * 디버그: 1kg 요금 직접 확인
     */
    public function debug1kgRate(string $type = 'economy'): array
    {
        $rates = $this->loadRates();
        
        return [
            'type' => $type,
            'rates_available' => array_keys($rates['rates'][$type]['rates'] ?? []),
            'looking_for_1' => $rates['rates'][$type]['rates']['1'] ?? 'NOT_FOUND',
            'looking_for_1.0' => $rates['rates'][$type]['rates']['1.0'] ?? 'NOT_FOUND',
            'weight_calculation_600g' => ceil(600 / 500) * 0.5,
            'weight_calculation_1000g' => ceil(1000 / 500) * 0.5,
            'actual_fee_600g' => $this->calculateShippingFee(600, $type),
            'actual_fee_1000g' => $this->calculateShippingFee(1000, $type),
            'file_last_modified' => date('Y-m-d H:i:s', filemtime(storage_path('app/' . self::RATES_FILE)))
        ];
    }
}