<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('collection_jobs', function (Blueprint $table) {
            $table->id();
            
            // 수집 작업 유형
            $table->enum('type', [
                'ASIN',         // ASIN 코드로 수집
                'URL',          // URL로 수집
                'KEYWORD',      // 키워드 검색 수집
                'CATEGORY',     // 카테고리 탐색 수집
                'BULK_ASIN'     // 대량 ASIN 수집
            ]);
            
            // 입력 데이터 (JSON)
            $table->json('input_data'); // ASIN 목록, 키워드, URL 등
            
            // 작업 상태
            $table->enum('status', [
                'PENDING',      // 대기중
                'PROCESSING',   // 처리중
                'COMPLETED',    // 완료
                'FAILED',       // 실패
                'CANCELLED'     // 취소됨
            ])->default('PENDING');
            
            // 진행률 정보
            $table->integer('progress')->default(0);      // 현재 처리된 개수
            $table->integer('total_items')->default(0);   // 전체 처리할 개수
            $table->integer('success_count')->default(0); // 성공한 개수
            $table->integer('error_count')->default(0);   // 실패한 개수
            
            // 결과 및 오류
            $table->json('results')->nullable();         // 수집된 상품 ID 목록
            $table->text('error_message')->nullable();   // 오류 메시지
            $table->json('error_details')->nullable();   // 상세 오류 정보
            
            // 작업 시간
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->integer('duration_seconds')->nullable(); // 소요 시간 (초)
            
            // 설정
            $table->json('settings')->nullable(); // 수집 설정 (자동분석 여부 등)
            
            $table->timestamps();
            
            // 인덱스
            $table->index(['status', 'created_at']);
            $table->index(['type', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('collection_jobs');
    }
};