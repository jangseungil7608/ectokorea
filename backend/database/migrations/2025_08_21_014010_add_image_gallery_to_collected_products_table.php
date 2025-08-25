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
        Schema::table('collected_products', function (Blueprint $table) {
            // 이미지 갤러리 관련 컬럼 추가
            $table->json('thumbnail_images')->nullable()->after('images'); // 썸네일 이미지 URL 배열
            $table->json('large_images')->nullable()->after('thumbnail_images'); // 큰 이미지 URL 배열 (썸네일과 1:1 대응)
            $table->json('description_images')->nullable()->after('large_images'); // 설명 영역 이미지 URL 배열
            
            // description 컬럼을 LONGTEXT로 변경 (HTML 저장용)
            $table->longText('description')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('collected_products', function (Blueprint $table) {
            // 추가된 컬럼들 제거
            $table->dropColumn(['thumbnail_images', 'large_images', 'description_images']);
            
            // description 컬럼을 원래 TEXT로 되돌리기
            $table->text('description')->nullable()->change();
        });
    }
};
