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
            // 원문 필드들 추가
            $table->text('original_title')->nullable()->after('title')->comment('원문 상품명');
            $table->text('original_category')->nullable()->after('category')->comment('원문 카테고리');
            $table->longText('original_description')->nullable()->after('description')->comment('원문 상품 설명');
            $table->json('original_features')->nullable()->after('features')->comment('원문 상품 특징');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('collected_products', function (Blueprint $table) {
            $table->dropColumn(['original_title', 'original_category', 'original_description', 'original_features']);
        });
    }
};
