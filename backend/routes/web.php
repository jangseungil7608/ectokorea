<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ExchangeRateController;
use App\Http\Controllers\ProfitCalculatorController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CollectedProductController;

// ectokorea 경로 그룹으로 모든 API 라우트를 묶음
Route::prefix('ectokorea')->group(function () {
    // OPTIONS 요청을 모든 경로에 대해 허용
    Route::options('/{any}', function () {
        return response('', 200);
    })->where('any', '.*');
    // 인증 관련 라우트
    Route::prefix('auth')->group(function () {
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/register', [AuthController::class, 'register']);
        
        // JWT 인증이 필요한 라우트
        Route::middleware(['auth:api'])->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::post('/refresh', [AuthController::class, 'refresh']);
            Route::get('/me', [AuthController::class, 'me']);
        });
    });

    // 보호된 라우트 그룹 (JWT 인증 필요)
    Route::middleware(['auth:api'])->group(function () {
        // 상품 관련 라우트
        Route::get('/products', [ProductController::class, 'index']);
        Route::get('/products/{product}', [ProductController::class, 'show']);
        Route::post('/products', [ProductController::class, 'store']);
        Route::put('/products/{product}', [ProductController::class, 'update']);
        Route::delete('/products/{product}', [ProductController::class, 'destroy']);
    });

    // 공개 라우트 (인증 불필요) - Python 스크래퍼로 대체됨

    // 환율 관련 라우트
    Route::prefix('exchange-rate')->group(function () {
        Route::get('/current', [ExchangeRateController::class, 'getCurrentRate']);
        Route::post('/refresh', [ExchangeRateController::class, 'refreshRate']);
        Route::post('/convert', [ExchangeRateController::class, 'convert']);
    });

    // 이익 계산 관련 라우트
    Route::prefix('profit-calculator')->group(function () {
        Route::post('/calculate', [ProfitCalculatorController::class, 'calculate']);
        Route::post('/recommend-price', [ProfitCalculatorController::class, 'recommendPrice']);
        Route::get('/categories', [ProfitCalculatorController::class, 'getCategories']);
        Route::get('/shipping-options', [ProfitCalculatorController::class, 'getShippingOptions']);
        Route::get('/debug-recommend', [ProfitCalculatorController::class, 'debugRecommendPrice']);
        Route::get('/debug-shipping', [ProfitCalculatorController::class, 'debugShipping']);
    });

    // 수집 상품 관리 라우트 (인증 필요 없이 테스트)
    Route::prefix('collected-products')->group(function () {
        // 상품 수집
        Route::post('/collect/asin', [CollectedProductController::class, 'collectByAsin']);
        Route::post('/collect/bulk-asin', [CollectedProductController::class, 'collectBulkAsin']);
        Route::post('/collect/url', [CollectedProductController::class, 'collectByUrl']);
        Route::post('/collect/keyword', [CollectedProductController::class, 'collectByKeyword']);
        
        // 상품 관리
        Route::get('/', [CollectedProductController::class, 'index']);
        Route::get('/{collectedProduct}', [CollectedProductController::class, 'show']);
        Route::put('/{collectedProduct}', [CollectedProductController::class, 'update']);
        Route::delete('/{collectedProduct}', [CollectedProductController::class, 'destroy']);
        Route::post('/{collectedProduct}/reanalyze', [CollectedProductController::class, 'reanalyze']);
        
        // 통계 및 작업 관리
        Route::get('/stats/overview', [CollectedProductController::class, 'getStats']);
        Route::get('/jobs/list', [CollectedProductController::class, 'getJobs']);
        Route::get('/jobs/{collectionJob}', [CollectedProductController::class, 'getJob']);
    });

    // 변형 상품 관리 라우트 - Python 스크래퍼로 대체됨
});
