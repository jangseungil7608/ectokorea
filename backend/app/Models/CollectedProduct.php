<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CollectedProduct extends Model
{
    use HasFactory;

    protected $fillable = [
        'asin',
        'title',
        'price_jpy',
        'weight_g',
        'dimensions',
        'category',
        'subcategory',
        'images',
        'thumbnail_images',
        'large_images',
        'description_images',
        'description',
        'features',
        'specifications',
        'status',
        'profit_analysis',
        'recommended_price',
        'profit_margin',
        'is_profitable',
        'source_url',
        'error_message',
        'collected_at',
        'analyzed_at',
        'is_favorite',
        'notes',
        'user_id'
    ];

    protected $casts = [
        'images' => 'array',
        'thumbnail_images' => 'array',
        'large_images' => 'array',
        'description_images' => 'array',
        'features' => 'array',
        'specifications' => 'array',
        'profit_analysis' => 'array',
        'price_jpy' => 'decimal:2',
        'profit_margin' => 'decimal:2',
        'is_profitable' => 'boolean',
        'is_favorite' => 'boolean',
        'collected_at' => 'datetime',
        'analyzed_at' => 'datetime',
    ];

    /**
     * JSON 직렬화에 포함할 추가 속성들
     */
    protected $appends = [
        'main_image',
        'amazon_url',
        'status_name',
        'profit_color'
    ];

    /**
     * 상태별 스코프
     */
    public function scopePending($query)
    {
        return $query->where('status', 'PENDING');
    }

    public function scopeCollected($query)
    {
        return $query->where('status', 'COLLECTED');
    }

    public function scopeAnalyzed($query)
    {
        return $query->where('status', 'ANALYZED');
    }

    public function scopeProfitable($query)
    {
        return $query->where('is_profitable', true);
    }

    public function scopeFavorite($query)
    {
        return $query->where('is_favorite', true);
    }

    /**
     * 수집된 상품을 소유한 사용자
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 아마존 상품 URL 생성
     */
    public function getAmazonUrlAttribute(): string
    {
        return "https://www.amazon.co.jp/dp/{$this->asin}";
    }

    /**
     * 메인 이미지 URL
     */
    public function getMainImageAttribute(): ?string
    {
        return $this->images[0] ?? null;
    }

    /**
     * 상태 한글명
     */
    public function getStatusNameAttribute(): string
    {
        $statusNames = [
            'PENDING' => '수집대기',
            'COLLECTING' => '수집중',
            'COLLECTED' => '수집완료',
            'ANALYZED' => '분석완료',
            'READY_TO_LIST' => '등록대기',
            'LISTED' => '판매중',
            'ERROR' => '오류'
        ];

        return $statusNames[$this->status] ?? $this->status;
    }

    /**
     * 수익성 색상 클래스
     */
    public function getProfitColorAttribute(): string
    {
        if (!$this->profit_margin) return 'text-gray-500';
        
        if ($this->profit_margin >= 20) return 'text-green-600';
        if ($this->profit_margin >= 10) return 'text-yellow-600';
        return 'text-red-600';
    }
}