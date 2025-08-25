<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class Cors
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $allowedOrigins = [
            'https://devseungil.mydns.jp',
            'https://devseungil.synology.me', 
            'http://localhost:5173', // 개발 환경
            'http://127.0.0.1:5173',  // 개발 환경
            'http://192.168.1.13:5173', // 로컬 네트워크
            'http://192.168.1.13:8080', // 백엔드 자체 호출
            null // 브라우저 직접 접근 시
        ];
        
        $origin = $request->header('Origin');
        
        // 디버그 로깅 (비활성화)
        // \Log::info('CORS Middleware - Request:', [
        //     'method' => $request->getMethod(),
        //     'origin' => $origin,
        //     'url' => $request->fullUrl(),
        //     'user_agent' => $request->header('User-Agent')
        // ]);
        
        // 모든 요청에 대해 CORS 헤더 설정
        $headers = [
            'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
            'Access-Control-Allow-Headers' => 'Origin, Content-Type, Accept, Authorization, X-Requested-With, X-CSRF-TOKEN',
            'Access-Control-Allow-Credentials' => 'true',
            'Access-Control-Max-Age' => '86400', // 24 hours
        ];

        // 오리진 체크 및 CORS 헤더 설정
        if ($origin && (in_array($origin, $allowedOrigins) || str_contains($origin, 'devseungil'))) {
            $headers['Access-Control-Allow-Origin'] = $origin;
            $headers['Access-Control-Allow-Credentials'] = 'true';
        } else {
            // Origin이 없거나 허용되지 않은 경우 - 모든 오리진 허용
            $headers['Access-Control-Allow-Origin'] = '*';
            $headers['Access-Control-Allow-Credentials'] = 'false';
        }
        
        // Preflight OPTIONS 요청 처리
        if ($request->getMethod() === 'OPTIONS') {
            return response('', 200, $headers);
        }

        // 실제 요청 처리
        $response = $next($request);
        
        // 응답에 CORS 헤더 추가
        foreach ($headers as $key => $value) {
            $response->headers->set($key, $value);
        }

        return $response;
    }
}