<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function ($schedule) {
        // 매일 새벽 4시(KST) = 19:00(UTC)에 하니로 배송료 갱신
        $schedule->command('haniro:update-rates')
                 ->dailyAt('19:00')
                 ->withoutOverlapping()
                 ->runInBackground();
                 
        // 5분마다 수집 작업 처리 (최대 5개씩)
        $schedule->command('collection:process-jobs --limit=5')
                 ->everyFiveMinutes()
                 ->withoutOverlapping()
                 ->runInBackground();
    })
    ->withMiddleware(function (Middleware $middleware): void {
         $middleware->validateCsrfTokens(except: [
            'api/*',
            'products/*',  // products配下의全てのルートをCSRFチェック除外
            'ectokorea/*',  // ectokorea 경로의 모든 라우트를 CSRF 체크에서 제외
        ]);
        
        // CORS 미들웨어를 전역적으로 적용
        $middleware->append(\App\Http\Middleware\Cors::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
