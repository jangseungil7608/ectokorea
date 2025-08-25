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
            // category 컬럼을 varchar(50)에서 varchar(500)로 확장
            $table->string('category', 500)->nullable()->change();
            
            // subcategory 컬럼도 함께 확장 (나중에 긴 서브카테고리가 올 수 있음)
            $table->string('subcategory', 300)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('collected_products', function (Blueprint $table) {
            // 원래 크기로 되돌리기 (주의: 데이터 손실 가능)
            $table->string('category', 50)->nullable()->change();
            $table->string('subcategory', 50)->nullable()->change();
        });
    }
};
