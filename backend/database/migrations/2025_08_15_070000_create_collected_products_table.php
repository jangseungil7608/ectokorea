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
        Schema::create('collected_products', function (Blueprint $table) {
            $table->id();
            
            // 아마존 상품 기본 정보
            $table->string('asin', 20)->unique();
            $table->text('title');
            $table->decimal('price_jpy', 10, 2)->nullable();
            $table->integer('weight_g')->nullable();
            $table->string('dimensions', 100)->nullable();
            $table->string('category', 50)->nullable();
            $table->string('subcategory', 50)->nullable();
            
            // 상품 상세 정보 (JSON)
            $table->json('images')->nullable(); // 이미지 URL 배열
            $table->text('description')->nullable();
            $table->json('features')->nullable(); // 특징/기능 배열
            $table->json('specifications')->nullable(); // 상품 사양
            
            // 수집 상태 관리
            $table->enum('status', [
                'PENDING',      // 수집대기
                'COLLECTING',   // 수집중
                'COLLECTED',    // 수집완료
                'ANALYZED',     // 분석완료
                'READY_TO_LIST',// 등록대기
                'LISTED',       // 판매중
                'ERROR'         // 오류
            ])->default('PENDING');
            
            // 수익성 분석 결과
            $table->json('profit_analysis')->nullable();
            $table->integer('recommended_price')->nullable();
            $table->decimal('profit_margin', 5, 2)->nullable();
            $table->boolean('is_profitable')->default(false);
            
            // 수집 메타데이터
            $table->text('source_url')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('collected_at')->nullable();
            $table->timestamp('analyzed_at')->nullable();
            
            // 사용자 관리
            $table->boolean('is_favorite')->default(false);
            $table->text('notes')->nullable();
            
            $table->timestamps();
            
            // 인덱스
            $table->index(['status', 'created_at']);
            $table->index(['is_profitable', 'profit_margin']);
            $table->index(['category', 'subcategory']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('collected_products');
    }
};