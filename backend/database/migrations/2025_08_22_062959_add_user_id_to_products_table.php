<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // 먼저 nullable로 컬럼 추가
            $table->unsignedBigInteger('user_id')->nullable();
        });
        
        // 기존 데이터에 기본 user_id 설정 (user_id = 3)
        DB::table('products')->whereNull('user_id')->update(['user_id' => 3]);
        
        Schema::table('products', function (Blueprint $table) {
            // NOT NULL 제약조건과 외래키 추가
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'created_at']);
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });
    }
};
