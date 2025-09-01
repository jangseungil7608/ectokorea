<?php

namespace App\Console\Commands;

use App\Models\CollectionJob;
use Illuminate\Console\Command;

class CleanupStuckJobs extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'jobs:cleanup-stuck {--timeout=30 : Timeout in minutes} {--force : Force cleanup all PROCESSING jobs} {--fix-time : Fix invalid time data}';

    /**
     * The console command description.
     */
    protected $description = 'Cleanup stuck PROCESSING jobs that have been running too long';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $timeoutMinutes = $this->option('timeout');
        $timeoutTime = now()->subMinutes($timeoutMinutes);

        // 먼저 모든 PROCESSING 작업 확인
        $allProcessingJobs = CollectionJob::where('status', 'PROCESSING')->get();
        $this->info("Total PROCESSING jobs: {$allProcessingJobs->count()}");
        
        foreach ($allProcessingJobs as $job) {
            $this->line("- Job ID: {$job->id}, Started: " . ($job->started_at ?? 'null') . ", Created: {$job->created_at}");
        }

        // PROCESSING 상태이면서 시작시간이 timeout분 이전인 작업들 찾기
        if ($this->option('force')) {
            $stuckJobs = $allProcessingJobs; // --force 옵션이면 모든 PROCESSING 작업 정리
            $this->warn("Force mode: Will cleanup ALL PROCESSING jobs!");
        } else {
            $stuckJobs = CollectionJob::where('status', 'PROCESSING')
                ->where('started_at', '<', $timeoutTime)
                ->orWhere(function($query) {
                    // started_at이 null이지만 status가 PROCESSING인 경우도 포함
                    $query->where('status', 'PROCESSING')
                          ->whereNull('started_at');
                })
                ->get();
        }

        if ($stuckJobs->isEmpty()) {
            $this->info('No stuck jobs found.');
            return;
        }

        $this->info("Found {$stuckJobs->count()} stuck jobs:");

        foreach ($stuckJobs as $job) {
            $this->line("- Job ID: {$job->id}, Started: " . ($job->started_at ?? 'null'));
            
            // 소요 시간 계산
            $durationSeconds = $job->started_at ? now()->diffInSeconds($job->started_at) : 0;
            
            // FAILED로 상태 변경
            $job->update([
                'status' => 'FAILED',
                'error_message' => "Job cleanup: Timeout after {$timeoutMinutes} minutes",
                'completed_at' => now(),
                'duration_seconds' => $durationSeconds
            ]);
            
            $this->info("  → Updated to FAILED status");
        }

        $this->info("Cleanup completed. {$stuckJobs->count()} jobs updated.");

        // 시간 역전 데이터 수정
        if ($this->option('fix-time')) {
            $this->fixInvalidTimeData();
        }
    }

    /**
     * 시간 역전 데이터 수정
     */
    private function fixInvalidTimeData()
    {
        $this->info("\nChecking for invalid time data...");
        
        // started_at > completed_at인 경우 찾기
        $invalidJobs = CollectionJob::whereNotNull('started_at')
            ->whereNotNull('completed_at')
            ->whereRaw('started_at > completed_at')
            ->get();

        if ($invalidJobs->isEmpty()) {
            $this->info('No invalid time data found.');
            return;
        }

        $this->warn("Found {$invalidJobs->count()} jobs with invalid time data:");

        foreach ($invalidJobs as $job) {
            $this->line("- Job ID: {$job->id}");
            $this->line("  Started: {$job->started_at}");
            $this->line("  Completed: {$job->completed_at}");
            
            // completed_at을 started_at 이후로 수정 (예: 1초 후)
            $newCompletedAt = $job->started_at->addSecond();
            $newDurationSeconds = 1; // 최소 1초로 설정
            
            $job->update([
                'completed_at' => $newCompletedAt,
                'duration_seconds' => $newDurationSeconds
            ]);
            
            $this->info("  → Fixed: Started: {$job->started_at}, Completed: {$newCompletedAt}");
        }

        $this->info("Time data fix completed. {$invalidJobs->count()} jobs updated.");
    }
}