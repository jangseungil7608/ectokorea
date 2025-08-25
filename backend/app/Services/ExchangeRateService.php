<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Exception;

class ExchangeRateService
{
    private const CACHE_KEY = 'exchange_rate_jpy_krw';
    private const CACHE_DURATION = 3600; // 1시간 캐시
    
    private string $exchangeApiUrl;
    
    public function __construct()
    {
        $this->exchangeApiUrl = 'https://api.exchangerate-api.com/v4/latest/JPY';
    }
    
    /**
     * JPY to KRW 환율 조회 (실시간)
     */
    public function getJpyToKrwRate(): float
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_DURATION, function () {
            try {
                return $this->fetchExchangeRateFromApi();
            } catch (Exception $e) {
                Log::error('환율 API 호출 실패: ' . $e->getMessage());
                
                // API 실패 시 fallback 환율 (최근 평균값)
                return $this->getFallbackRate();
            }
        });
    }
    
    /**
     * ExchangeRate-API에서 환율 정보 가져오기
     */
    private function fetchExchangeRateFromApi(): float
    {
        $response = Http::timeout(10)->get($this->exchangeApiUrl);
        
        if (!$response->successful()) {
            throw new Exception('환율 API 요청 실패: ' . $response->status());
        }
        
        $data = $response->json();
        
        if (empty($data) || !isset($data['rates']['KRW'])) {
            throw new Exception('KRW 환율 데이터가 없습니다.');
        }
        
        return (float) $data['rates']['KRW'];
    }
    
    /**
     * API 실패 시 fallback 환율
     */
    private function getFallbackRate(): float
    {
        // 최근 평균 환율 (정기적으로 업데이트 필요)
        return 9.5; // 1 JPY = 9.5 KRW (예시)
    }
    
    /**
     * JPY를 KRW로 변환
     */
    public function convertJpyToKrw(float $jpyAmount): float
    {
        $rate = $this->getJpyToKrwRate();
        return round($jpyAmount * $rate, 2);
    }
    
    /**
     * KRW를 JPY로 변환
     */
    public function convertKrwToJpy(float $krwAmount): float
    {
        $rate = $this->getJpyToKrwRate();
        return round($krwAmount / $rate, 2);
    }
    
    /**
     * 환율 정보와 업데이트 시간 반환
     */
    public function getExchangeRateInfo(): array
    {
        $rate = $this->getJpyToKrwRate();
        $lastUpdated = Cache::get(self::CACHE_KEY . '_updated', now());
        
        return [
            'rate' => $rate,
            'from_currency' => 'JPY',
            'to_currency' => 'KRW', 
            'last_updated' => $lastUpdated,
            'cache_expires_in' => Cache::get(self::CACHE_KEY) ? 
                Cache::get(self::CACHE_KEY . '_expires', self::CACHE_DURATION) : 0
        ];
    }
    
    /**
     * 캐시 강제 갱신
     */
    public function refreshExchangeRate(): array
    {
        Cache::forget(self::CACHE_KEY);
        Cache::forget(self::CACHE_KEY . '_updated');
        
        $rate = $this->getJpyToKrwRate();
        Cache::put(self::CACHE_KEY . '_updated', now(), self::CACHE_DURATION);
        
        return $this->getExchangeRateInfo();
    }
}