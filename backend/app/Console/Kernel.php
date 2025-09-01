<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // 매일 오전 9시에 하니로 배송료 갱신
        $schedule->command('haniro:update-rates')
                 ->dailyAt('09:00')
                 ->withoutOverlapping()
                 ->runInBackground();

        // Queue Worker는 Supervisor로 관리 (supervisor-ectokorea-worker.conf 참조)
        
        // PENDING 상태로 오래 남아있는 작업 정리 (매 10분)
        $schedule->call(function () {
            \App\Models\CollectionJob::where('status', 'PENDING')
                ->where('created_at', '<', now()->subMinutes(30))
                ->update([
                    'status' => 'FAILED',
                    'error_message' => '작업 처리 시간 초과로 자동 취소됨'
                ]);
        })->everyTenMinutes();

        // 실패한 Queue 작업 정리 (매일 자정)
        $schedule->command('queue:prune-failed --hours=168') // 1주일 후 삭제
                 ->daily();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}