<?php

namespace App\Http\Controllers;

use App\Services\ExchangeRateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExchangeRateController extends Controller
{
    private ExchangeRateService $exchangeRateService;
    
    public function __construct(ExchangeRateService $exchangeRateService)
    {
        $this->exchangeRateService = $exchangeRateService;
    }
    
    /**
     * 현재 JPY-KRW 환율 조회
     */
    public function getCurrentRate(): JsonResponse
    {
        try {
            $rateInfo = $this->exchangeRateService->getExchangeRateInfo();
            
            return response()->json([
                'success' => true,
                'data' => $rateInfo
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '환율 정보 조회에 실패했습니다.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 환율 강제 갱신
     */
    public function refreshRate(): JsonResponse
    {
        try {
            $rateInfo = $this->exchangeRateService->refreshExchangeRate();
            
            return response()->json([
                'success' => true,
                'message' => '환율이 성공적으로 갱신되었습니다.',
                'data' => $rateInfo
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '환율 갱신에 실패했습니다.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 통화 변환
     */
    public function convert(Request $request): JsonResponse
    {
        $request->validate([
            'amount' => 'required|numeric|min:0',
            'from' => 'required|in:JPY,KRW',
            'to' => 'required|in:JPY,KRW'
        ]);
        
        try {
            $amount = $request->input('amount');
            $from = $request->input('from');
            $to = $request->input('to');
            
            if ($from === $to) {
                $convertedAmount = $amount;
            } elseif ($from === 'JPY' && $to === 'KRW') {
                $convertedAmount = $this->exchangeRateService->convertJpyToKrw($amount);
            } else {
                $convertedAmount = $this->exchangeRateService->convertKrwToJpy($amount);
            }
            
            $rateInfo = $this->exchangeRateService->getExchangeRateInfo();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'original_amount' => $amount,
                    'converted_amount' => $convertedAmount,
                    'from_currency' => $from,
                    'to_currency' => $to,
                    'exchange_rate' => $rateInfo['rate'],
                    'converted_at' => now()->toDateTimeString()
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '통화 변환에 실패했습니다.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}