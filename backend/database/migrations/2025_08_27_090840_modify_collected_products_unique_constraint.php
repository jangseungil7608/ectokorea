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
            // 기존 asin unique 제약조건 제거
            $table->dropUnique(['asin']);
            
            // user_id와 asin 조합의 unique 제약조건 추가
            $table->unique(['user_id', 'asin']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('collected_products', function (Blueprint $table) {
            // user_id, asin 조합 unique 제약조건 제거
            $table->dropUnique(['user_id', 'asin']);
            
            // 기존 asin unique 제약조건 복원
            $table->unique(['asin']);
        });
    }
};
