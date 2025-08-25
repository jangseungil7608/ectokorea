<?php

namespace App\Console\Commands;

use App\Models\CollectionJob;
use App\Models\CollectedProduct;
use App\Services\ProductCollectionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Exception;

class ProcessCollectionJobs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'collection:process-jobs 
                           {--limit=10 : Maximum number of jobs to process}
                           {--timeout=300 : Timeout per job in seconds}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process pending collection jobs';

    private ProductCollectionService $collectionService;

    public function __construct(ProductCollectionService $collectionService)
    {
        parent::__construct();
        $this->collectionService = $collectionService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $limit = (int) $this->option('limit');
        $timeout = (int) $this->option('timeout');

        $this->info("수집 작업 처리 시작 (최대 {$limit}개, 타임아웃 {$timeout}초)");

        // 대기 중인 작업 조회
        $pendingJobs = CollectionJob::where('status', 'PENDING')
            ->orderBy('created_at', 'asc')
            ->limit($limit)
            ->get();

        if ($pendingJobs->isEmpty()) {
            $this->info('처리할 작업이 없습니다.');
            return 0;
        }

        $this->info("처리할 작업 수: {$pendingJobs->count()}개");

        $processed = 0;
        $errors = 0;

        foreach ($pendingJobs as $job) {
            try {
                $this->line("작업 처리 시작: {$job->type} (ID: {$job->id})");
                
                $this->processJob($job, $timeout);
                $processed++;
                
                $this->info("✅ 작업 완료: {$job->type} (ID: {$job->id})");
                
            } catch (Exception $e) {
                $errors++;
                $this->error("❌ 작업 실패: {$job->type} (ID: {$job->id}) - {$e->getMessage()}");
                
                // 작업 실패 상태로 업데이트
                $job->update([
                    'status' => 'FAILED',
                    'error_message' => $e->getMessage(),
                    'completed_at' => now()
                ]);
                
                Log::error("Collection job failed: {$job->id}", [
                    'job_type' => $job->type,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }

        $this->info("처리 완료: 성공 {$processed}개, 실패 {$errors}개");
        
        return 0;
    }

    /**
     * 개별 작업 처리
     */
    private function processJob(CollectionJob $job, int $timeout)
    {
        // 작업 상태를 처리중으로 변경
        $job->update([
            'status' => 'PROCESSING',
            'started_at' => now()
        ]);

        $inputData = $job->input_data;
        $settings = $job->settings ?? [];
        $autoAnalyze = $settings['auto_analyze'] ?? true;

        switch ($job->type) {
            case 'BULK_ASIN':
                $this->processBulkAsinJob($job, $inputData, $autoAnalyze);
                break;
                
            case 'URL':
                $this->processUrlJob($job, $inputData, $autoAnalyze);
                break;
                
            case 'KEYWORD':
                $this->processKeywordJob($job, $inputData, $autoAnalyze);
                break;
                
            default:
                throw new Exception("지원하지 않는 작업 타입: {$job->type}");
        }

        // 작업 완료
        $job->update([
            'status' => 'COMPLETED',
            'progress' => $job->total_items,
            'completed_at' => now()
        ]);
    }

    /**
     * 대량 ASIN 수집 작업 처리
     */
    private function processBulkAsinJob(CollectionJob $job, array $inputData, bool $autoAnalyze)
    {
        $asins = $inputData['asins'] ?? [];
        $totalAsins = count($asins);
        
        $this->output->progressStart($totalAsins);

        foreach ($asins as $index => $asin) {
            try {
                $this->collectionService->collectByAsin($asin, $autoAnalyze);
                
                // 진행률 업데이트
                $progress = $index + 1;
                $job->update(['progress' => $progress]);
                
                $this->output->progressAdvance();
                
            } catch (Exception $e) {
                $this->warn("ASIN {$asin} 수집 실패: {$e->getMessage()}");
                
                // 실패한 ASIN도 진행률에 포함
                $progress = $index + 1;
                $job->update(['progress' => $progress]);
                
                $this->output->progressAdvance();
            }
        }

        $this->output->progressFinish();
    }

    /**
     * URL 수집 작업 처리
     */
    private function processUrlJob(CollectionJob $job, array $inputData, bool $autoAnalyze)
    {
        $asins = $inputData['asins'] ?? [];
        $totalAsins = count($asins);
        
        $this->line("URL 수집 결과로 {$totalAsins}개 상품 처리");
        $this->output->progressStart($totalAsins);

        foreach ($asins as $index => $asin) {
            try {
                $this->collectionService->collectByAsin($asin, $autoAnalyze);
                
                $progress = $index + 1;
                $job->update(['progress' => $progress]);
                
                $this->output->progressAdvance();
                
            } catch (Exception $e) {
                $this->warn("ASIN {$asin} 수집 실패: {$e->getMessage()}");
                
                $progress = $index + 1;
                $job->update(['progress' => $progress]);
                
                $this->output->progressAdvance();
            }
        }

        $this->output->progressFinish();
    }

    /**
     * 키워드 수집 작업 처리
     */
    private function processKeywordJob(CollectionJob $job, array $inputData, bool $autoAnalyze)
    {
        $asins = $inputData['asins'] ?? [];
        $keyword = $inputData['keyword'] ?? '알 수 없음';
        $totalAsins = count($asins);
        
        $this->line("키워드 '{$keyword}' 검색 결과로 {$totalAsins}개 상품 처리");
        $this->output->progressStart($totalAsins);

        foreach ($asins as $index => $asin) {
            try {
                $this->collectionService->collectByAsin($asin, $autoAnalyze);
                
                $progress = $index + 1;
                $job->update(['progress' => $progress]);
                
                $this->output->progressAdvance();
                
            } catch (Exception $e) {
                $this->warn("ASIN {$asin} 수집 실패: {$e->getMessage()}");
                
                $progress = $index + 1;
                $job->update(['progress' => $progress]);
                
                $this->output->progressAdvance();
            }
        }

        $this->output->progressFinish();
    }
}