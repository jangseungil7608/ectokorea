<?php

namespace App\Http\Controllers;

use App\Models\CollectedProduct;
use App\Models\CollectionJob;
use App\Services\ProductCollectionService;
use App\Jobs\ProcessBulkCollectionJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CollectedProductController extends Controller
{
    private ProductCollectionService $collectionService;

    public function __construct(ProductCollectionService $collectionService)
    {
        $this->collectionService = $collectionService;
    }

    /**
     * 수집 상품 목록 조회
     */
    public function index(Request $request): JsonResponse
    {
        $query = CollectedProduct::where('user_id', auth('api')->id());

        // 상태 필터
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // 수익성 필터
        if ($request->has('profitable')) {
            $query->where('is_profitable', $request->boolean('profitable'));
        }

        // 즐겨찾기 필터
        if ($request->has('favorite')) {
            $query->where('is_favorite', $request->boolean('favorite'));
        }

        // 카테고리 필터
        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        // 검색
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('title', 'LIKE', "%{$search}%")
                  ->orWhere('asin', 'LIKE', "%{$search}%");
            });
        }

        // 정렬
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        
        $allowedSorts = ['created_at', 'title', 'price_jpy', 'profit_margin', 'collected_at'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortOrder);
        }

        $products = $query->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $products
        ]);
    }

    /**
     * 수집 상품 상세 조회
     */
    public function show(CollectedProduct $collectedProduct): JsonResponse
    {
        // 사용자 소유권 확인
        if ($collectedProduct->user_id !== auth('api')->id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        
        return response()->json([
            'success' => true,
            'data' => $collectedProduct
        ]);
    }

    /**
     * ASIN으로 상품 수집
     */
    public function collectByAsin(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'asin' => 'required|string|regex:/^[A-Z0-9]{10}$/',
            'auto_analyze' => 'boolean',
            'target_margin' => 'nullable|numeric|min:5|max:50',
            'japan_shipping_jpy' => 'nullable|numeric|min:0|max:10000',
            'korea_shipping_krw' => 'nullable|numeric|min:0|max:50000'
        ]);

        try {
            $product = $this->collectionService->collectByAsin(
                $validated['asin'],
                $validated['auto_analyze'] ?? true,
                $validated['target_margin'] ?? 10.0,
                $validated['japan_shipping_jpy'] ?? 0,
                $validated['korea_shipping_krw'] ?? 0
            );

            return response()->json([
                'success' => true,
                'message' => '상품 수집이 시작되었습니다.',
                'data' => $product
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '상품 수집에 실패했습니다.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 대량 ASIN 수집 작업 생성
     */
    public function collectBulkAsin(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'asins' => 'required|array|min:1|max:100',
            'asins.*' => 'required|string|regex:/^[A-Z0-9]{10}$/',
            'auto_analyze' => 'boolean'
        ]);

        try {
            $job = $this->collectionService->createBulkCollectionJob(
                'BULK_ASIN',
                ['asins' => $validated['asins']],
                ['auto_analyze' => $validated['auto_analyze'] ?? true]
            );

            // Queue Job 디스패치 (인증된 사용자 ID 전달)
            ProcessBulkCollectionJob::dispatch($job->id, auth('api')->id());

            return response()->json([
                'success' => true,
                'message' => '대량 수집 작업이 Queue에 추가되었습니다. 백그라운드에서 처리됩니다.',
                'data' => $job
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '대량 수집 작업 생성에 실패했습니다.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * URL로 상품 수집
     */
    public function collectByUrl(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'url' => 'required|url',
            'auto_analyze' => 'boolean',
            'max_results' => 'integer|min:1|max:100'
        ]);

        try {
            $result = $this->collectionService->collectByUrl(
                $validated['url'],
                $validated['auto_analyze'] ?? true,
                $validated['max_results'] ?? 20
            );

            $message = match ($result['type']) {
                'single' => '상품 수집이 시작되었습니다.',
                'job' => "검색 결과에서 {$result['found_count']}개 상품을 찾아 수집 작업을 생성했습니다.",
                default => '수집이 시작되었습니다.'
            };

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'URL 수집에 실패했습니다.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 키워드로 상품 수집
     */
    public function collectByKeyword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'keyword' => 'required|string|max:100',
            'max_results' => 'integer|min:1|max:100',
            'auto_analyze' => 'boolean'
        ]);

        try {
            $job = $this->collectionService->collectByKeyword(
                $validated['keyword'],
                $validated['max_results'] ?? 50,
                $validated['auto_analyze'] ?? true
            );

            // Queue Job 디스패치 (인증된 사용자 ID 전달)
            ProcessBulkCollectionJob::dispatch($job->id, auth('api')->id());

            return response()->json([
                'success' => true,
                'message' => "'{$validated['keyword']}' 키워드로 상품 검색 및 수집 작업이 Queue에 추가되었습니다. 백그라운드에서 처리됩니다.",
                'data' => $job
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '키워드 수집에 실패했습니다.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 상품 정보 업데이트
     */
    public function update(Request $request, CollectedProduct $collectedProduct): JsonResponse
    {
        // 사용자 소유권 확인
        if ($collectedProduct->user_id !== auth('api')->id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        
        $validated = $request->validate([
            'is_favorite' => 'boolean',
            'notes' => 'nullable|string|max:1000',
            'category' => 'nullable|string|max:50',
            'subcategory' => 'nullable|string|max:50'
        ]);

        $collectedProduct->update($validated);

        return response()->json([
            'success' => true,
            'message' => '상품 정보가 업데이트되었습니다.',
            'data' => $collectedProduct
        ]);
    }

    /**
     * 수익성 재분석
     */
    public function reanalyze(Request $request, CollectedProduct $collectedProduct): JsonResponse
    {
        // 사용자 소유권 확인
        if ($collectedProduct->user_id !== auth('api')->id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        
        $validated = $request->validate([
            'target_margin' => 'nullable|numeric|min:5|max:50',
            'japan_shipping_jpy' => 'nullable|numeric|min:0|max:10000',
            'korea_shipping_krw' => 'nullable|numeric|min:0|max:50000'
        ]);

        try {
            $result = $this->collectionService->analyzeProfitability(
                $collectedProduct, 
                $validated['target_margin'] ?? 10.0,
                $validated['japan_shipping_jpy'] ?? 0,
                $validated['korea_shipping_krw'] ?? 0
            );

            return response()->json([
                'success' => true,
                'message' => '수익성 분석이 완료되었습니다.',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '수익성 분석에 실패했습니다.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 상품 삭제
     */
    public function destroy(CollectedProduct $collectedProduct): JsonResponse
    {
        // 사용자 소유권 확인
        if ($collectedProduct->user_id !== auth('api')->id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        
        $collectedProduct->delete();

        return response()->json([
            'success' => true,
            'message' => '상품이 삭제되었습니다.'
        ]);
    }

    /**
     * 수집 작업 목록 조회
     */
    public function getJobs(Request $request): JsonResponse
    {
        $query = CollectionJob::where('user_id', auth('api')->id());

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        $jobs = $query->orderBy('created_at', 'desc')
                     ->paginate($request->get('per_page', 10));

        return response()->json([
            'success' => true,
            'data' => $jobs
        ]);
    }

    /**
     * 수집 작업 상세 조회
     */
    public function getJob(CollectionJob $collectionJob): JsonResponse
    {
        // 사용자 소유권 확인
        if ($collectionJob->user_id !== auth('api')->id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        
        return response()->json([
            'success' => true,
            'data' => $collectionJob
        ]);
    }

    /**
     * 통계 정보
     */
    public function getStats(): JsonResponse
    {
        $userProducts = CollectedProduct::where('user_id', auth('api')->id());
        
        $stats = [
            'total_products' => $userProducts->count(),
            'by_status' => [
                'pending' => $userProducts->where('status', 'PENDING')->count(),
                'collected' => $userProducts->where('status', 'COLLECTED')->count(),
                'analyzed' => $userProducts->where('status', 'ANALYZED')->count(),
                'ready_to_list' => $userProducts->where('status', 'READY_TO_LIST')->count(),
                'listed' => $userProducts->where('status', 'LISTED')->count(),
                'error' => $userProducts->where('status', 'ERROR')->count(),
            ],
            'profitable_count' => $userProducts->where('is_profitable', true)->count(),
            'favorite_count' => $userProducts->where('is_favorite', true)->count(),
            'recent_jobs' => [
                'pending' => CollectionJob::where('user_id', auth('api')->id())->where('status', 'PENDING')->count(),
                'processing' => CollectionJob::where('user_id', auth('api')->id())->where('status', 'PROCESSING')->count(),
                'completed_today' => CollectionJob::where('user_id', auth('api')->id())
                    ->where('status', 'COMPLETED')
                    ->whereDate('completed_at', today())->count(),
            ]
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

}