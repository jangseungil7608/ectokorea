<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CollectionJob extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'input_data',
        'status',
        'progress',
        'total_items',
        'success_count',
        'error_count',
        'results',
        'error_message',
        'error_details',
        'started_at',
        'completed_at',
        'duration_seconds',
        'settings'
    ];

    protected $casts = [
        'input_data' => 'array',
        'results' => 'array',
        'error_details' => 'array',
        'settings' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * 상태별 스코프
     */
    public function scopePending($query)
    {
        return $query->where('status', 'PENDING');
    }

    public function scopeProcessing($query)
    {
        return $query->where('status', 'PROCESSING');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'COMPLETED');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'FAILED');
    }

    /**
     * 진행률 퍼센티지
     */
    public function getProgressPercentAttribute(): int
    {
        if ($this->total_items === 0) return 0;
        return round(($this->progress / $this->total_items) * 100);
    }

    /**
     * 성공률
     */
    public function getSuccessRateAttribute(): float
    {
        if ($this->progress === 0) return 0;
        return round(($this->success_count / $this->progress) * 100, 1);
    }

    /**
     * 상태 한글명
     */
    public function getStatusNameAttribute(): string
    {
        $statusNames = [
            'PENDING' => '대기중',
            'PROCESSING' => '처리중',
            'COMPLETED' => '완료',
            'FAILED' => '실패',
            'CANCELLED' => '취소됨'
        ];

        return $statusNames[$this->status] ?? $this->status;
    }

    /**
     * 작업 유형 한글명
     */
    public function getTypeNameAttribute(): string
    {
        $typeNames = [
            'ASIN' => 'ASIN 수집',
            'URL' => 'URL 수집',
            'KEYWORD' => '키워드 검색',
            'CATEGORY' => '카테고리 탐색',
            'BULK_ASIN' => '대량 ASIN 수집'
        ];

        return $typeNames[$this->type] ?? $this->type;
    }

    /**
     * 소요 시간 (분)
     */
    public function getDurationMinutesAttribute(): ?float
    {
        return $this->duration_seconds ? round($this->duration_seconds / 60, 1) : null;
    }
}