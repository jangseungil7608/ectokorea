<?php

namespace App\Jobs;

use App\Models\CollectionJob;
use App\Services\ProductCollectionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Exception;

class ProcessBulkCollectionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * 작업 시도 횟수
     */
    public int $tries = 3;

    /**
     * 작업 제한 시간 (초)
     */
    public int $timeout = 1800; // 30분

    /**
     * CollectionJob ID
     */
    private int $collectionJobId;
    
    /**
     * User ID
     */
    private int $userId;

    /**
     * Create a new job instance.
     */
    public function __construct(int $collectionJobId, int $userId)
    {
        $this->collectionJobId = $collectionJobId;
        $this->userId = $userId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $collectionJob = CollectionJob::find($this->collectionJobId);
        
        if (!$collectionJob) {
            Log::error("CollectionJob not found: {$this->collectionJobId}");
            return;
        }

        Log::info("Starting bulk collection job: {$collectionJob->id}");

        try {
            // 작업 상태를 PROCESSING으로 변경
            $collectionJob->update([
                'status' => 'PROCESSING',
                'started_at' => now(),
                'progress' => 0
            ]);

            $productCollectionService = app(ProductCollectionService::class);

            switch ($collectionJob->type) {
                case 'BULK_ASIN':
                    Log::info("=== CALLING processBulkAsin ===", ['job_id' => $collectionJob->id]);
                    $this->processBulkAsin($collectionJob, $productCollectionService);
                    break;
                case 'URL':
                    Log::info("=== CALLING processUrlCollection ===", ['job_id' => $collectionJob->id]);
                    $this->processUrlCollection($collectionJob, $productCollectionService);
                    break;
                case 'KEYWORD':
                    Log::info("=== CALLING processKeywordCollection ===", ['job_id' => $collectionJob->id]);
                    $this->processKeywordCollection($collectionJob, $productCollectionService);
                    break;
                default:
                    throw new Exception("Unknown collection job type: {$collectionJob->type}");
            }

            Log::info("All processing completed, updating job to COMPLETED", ['job_id' => $collectionJob->id]);

            // 리팩토링된 완료 처리 메서드 사용
            $collectionJob->markAsCompleted();

            Log::info("Bulk collection job completed: {$collectionJob->id}");

        } catch (Exception $e) {
            Log::error("Bulk collection job failed: {$collectionJob->id}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'current_status' => $collectionJob->status,
                'started_at' => $collectionJob->started_at
            ]);

            // 리팩토링된 실패 처리 메서드 사용
            $collectionJob->markAsFailed($e->getMessage());

            throw $e;
        }
    }

    /**
     * BULK_ASIN 타입 작업 처리
     */
    private function processBulkAsin(CollectionJob $job, ProductCollectionService $service): void
    {
        $asins = $job->input_data['asins'] ?? [];
        $settings = $job->settings ?? [];
        $autoAnalyze = $settings['auto_analyze'] ?? true;

        Log::info("Starting processBulkAsin", [
            'job_id' => $job->id,
            'asin_count' => count($asins),
            'asins' => $asins
        ]);

        $processed = 0;
        $successCount = 0;
        $errorCount = 0;
        $results = []; // 수집 결과 저장

        if (empty($asins)) {
            Log::warning("No ASINs found in job input_data", ['job_id' => $job->id]);
            // 빈 배열이어도 return하지 않고 계속 진행 (완료 처리를 위해)
        }

        foreach ($asins as $asin) {
            try {
                Log::info("Processing ASIN: {$asin} for job: {$job->id}");

                // 개별 상품 수집 (사용자 ID 전달)
                $service->collectByAsinForUser(
                    $asin,
                    $this->userId,
                    $autoAnalyze,
                    $settings['target_margin'] ?? 10.0,
                    $settings['japan_shipping_jpy'] ?? 0,
                    $settings['korea_shipping_krw'] ?? 0
                );

                $processed++;
                $successCount++;

                // 성공 결과 저장
                $results[] = [
                    'asin' => $asin,
                    'status' => 'success',
                    'processed_at' => now()->toISOString()
                ];

                // 진행률 및 통계 업데이트
                $job->update([
                    'progress' => $processed,
                    'success_count' => $successCount,
                    'error_count' => $errorCount,
                    'results' => $results
                ]);

                Log::info("Successfully processed ASIN: {$asin}");

                // API 호출 간격 (과도한 요청 방지)
                sleep(2);

            } catch (Exception $e) {
                Log::warning("Failed to process ASIN: {$asin}", [
                    'error' => $e->getMessage()
                ]);
                
                // 개별 실패는 전체 작업을 중단시키지 않음
                $processed++;
                $errorCount++;
                
                // 실패 결과 저장
                $results[] = [
                    'asin' => $asin,
                    'status' => 'error',
                    'error' => $e->getMessage(),
                    'processed_at' => now()->toISOString()
                ];
                
                $job->update([
                    'progress' => $processed,
                    'success_count' => $successCount,
                    'error_count' => $errorCount,
                    'results' => $results
                ]);
            }
        }

        Log::info("Finished processBulkAsin loop", [
            'job_id' => $job->id,
            'processed' => $processed,
            'success_count' => $successCount,
            'error_count' => $errorCount
        ]);
    }

    /**
     * URL 타입 작업 처리
     */
    private function processUrlCollection(CollectionJob $job, ProductCollectionService $service): void
    {
        // 강제 로그 - 메소드 호출 확인
        Log::info("=== processUrlCollection METHOD CALLED ===", ['job_id' => $job->id]);
        
        $asins = $job->input_data['asins'] ?? [];
        $settings = $job->settings ?? [];
        $autoAnalyze = $settings['auto_analyze'] ?? true;

        Log::info("Starting processUrlCollection", [
            'job_id' => $job->id,
            'asin_count' => count($asins),
            'url_type' => $job->input_data['url_type'] ?? 'unknown',
            'url' => $job->input_data['url'] ?? ''
        ]);

        $processed = 0;
        $successCount = 0;
        $errorCount = 0;
        $results = []; // 수집 결과 저장

        if (empty($asins)) {
            Log::warning("No ASINs found in URL collection job input_data", ['job_id' => $job->id]);
            // 빈 배열이어도 return하지 않고 계속 진행 (완료 처리를 위해)
        }

        foreach ($asins as $asin) {
            try {
                Log::info("Processing ASIN from URL collection: {$asin} for job: {$job->id}");

                // 개별 상품 수집 (사용자 ID 전달)
                $service->collectByAsinForUser(
                    $asin,
                    $this->userId,
                    $autoAnalyze,
                    $settings['target_margin'] ?? 10.0,
                    $settings['japan_shipping_jpy'] ?? 0,
                    $settings['korea_shipping_krw'] ?? 0
                );

                $processed++;
                $successCount++;

                // 성공 결과 저장
                $results[] = [
                    'asin' => $asin,
                    'status' => 'success',
                    'processed_at' => now()->toISOString()
                ];

                // 진행률 및 통계 업데이트
                $job->update([
                    'progress' => $processed,
                    'success_count' => $successCount,
                    'error_count' => $errorCount,
                    'results' => $results
                ]);

                Log::info("Successfully processed URL collection ASIN: {$asin}");

                // API 호출 간격 (과도한 요청 방지)
                sleep(2);

            } catch (Exception $e) {
                Log::warning("Failed to process URL collection ASIN: {$asin}", [
                    'error' => $e->getMessage()
                ]);
                
                // 개별 실패는 전체 작업을 중단시키지 않음
                $processed++;
                $errorCount++;
                
                // 실패 결과 저장
                $results[] = [
                    'asin' => $asin,
                    'status' => 'error',
                    'error' => $e->getMessage(),
                    'processed_at' => now()->toISOString()
                ];
                
                $job->update([
                    'progress' => $processed,
                    'success_count' => $successCount,
                    'error_count' => $errorCount,
                    'results' => $results
                ]);
            }
        }

        Log::info("Finished processUrlCollection", [
            'job_id' => $job->id,
            'processed' => $processed,
            'success_count' => $successCount,
            'error_count' => $errorCount
        ]);
    }

    /**
     * KEYWORD 타입 작업 처리
     */
    private function processKeywordCollection(CollectionJob $job, ProductCollectionService $service): void
    {
        $asins = $job->input_data['asins'] ?? [];
        $settings = $job->settings ?? [];
        $autoAnalyze = $settings['auto_analyze'] ?? true;

        $processed = 0;
        $successCount = 0;
        $errorCount = 0;

        foreach ($asins as $asin) {
            try {
                Log::info("Processing ASIN from keyword search: {$asin} for job: {$job->id}");

                // 개별 상품 수집 (사용자 ID 전달)
                $service->collectByAsinForUser(
                    $asin,
                    $this->userId,
                    $autoAnalyze,
                    $settings['target_margin'] ?? 10.0,
                    $settings['japan_shipping_jpy'] ?? 0,
                    $settings['korea_shipping_krw'] ?? 0
                );

                $processed++;
                $successCount++;

                // 진행률 및 통계 업데이트
                $job->update([
                    'progress' => $processed,
                    'success_count' => $successCount,
                    'error_count' => $errorCount
                ]);

                Log::info("Successfully processed keyword search ASIN: {$asin}");

                // API 호출 간격 (과도한 요청 방지)
                sleep(2);

            } catch (Exception $e) {
                Log::warning("Failed to process keyword search ASIN: {$asin}", [
                    'error' => $e->getMessage()
                ]);
                
                // 개별 실패는 전체 작업을 중단시키지 않음
                $processed++;
                $errorCount++;
                
                $job->update([
                    'progress' => $processed,
                    'success_count' => $successCount,
                    'error_count' => $errorCount
                ]);
            }
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(Exception $exception): void
    {
        try {
            $collectionJob = CollectionJob::find($this->collectionJobId);
            
            if ($collectionJob) {
                // 리팩토링된 실패 처리 메서드 사용
                $collectionJob->markAsFailed($exception->getMessage());
            }
        } catch (Exception $e) {
            // failed() 메서드에서도 예외가 발생하면 로그만 남기고 넘어감
            Log::error("Failed to update CollectionJob status in failed() method", [
                'job_id' => $this->collectionJobId,
                'original_error' => $exception->getMessage(),
                'update_error' => $e->getMessage()
            ]);
        }

        Log::error("ProcessBulkCollectionJob failed permanently", [
            'job_id' => $this->collectionJobId,
            'error' => $exception->getMessage()
        ]);
    }
}