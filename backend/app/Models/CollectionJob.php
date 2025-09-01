<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CollectionJob extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
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
     * JSON 직렬화시 포함할 추가 속성들
     */
    protected $appends = [
        'progress_percent',
        'success_rate',
        'status_name',
        'type_name',
        'duration_minutes'
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
        if (!$this->total_items || $this->total_items <= 0) return 0;
        $progress = $this->progress ?? 0;
        return round(($progress / $this->total_items) * 100);
    }

    /**
     * 성공률
     */
    public function getSuccessRateAttribute(): float
    {
        $successCount = $this->success_count ?? 0;
        $errorCount = $this->error_count ?? 0;
        $totalProcessed = $successCount + $errorCount;
        
        if ($totalProcessed <= 0) return 0;
        
        return round(($successCount / $totalProcessed) * 100, 1);
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
        // 완료된 경우: duration_seconds 사용
        if ($this->duration_seconds) {
            return round($this->duration_seconds / 60, 1);
        }
        
        // 진행 중인 경우: 시작시간부터 현재까지 계산
        if ($this->started_at && in_array($this->status, ['PROCESSING', 'FAILED'])) {
            $durationSeconds = now()->diffInSeconds($this->started_at);
            return round($durationSeconds / 60, 1);
        }
        
        return null;
    }

    /**
     * 작업 완료/실패 시 duration 계산 (초 단위)
     */
    public function calculateDurationSeconds($completedAt = null): int
    {
        $completedAt = $completedAt ?? now();
        
        if (!$this->started_at) {
            return 0;
        }
        
        // Carbon 3: started_at에서 completed_at까지의 차이 (양수 기대)
        $durationSeconds = $this->started_at->diffInSeconds($completedAt);
        
        if ($durationSeconds < 0) {
            throw new \Exception("Invalid time calculation: started_at ({$this->started_at}) > completed_at ({$completedAt}). Duration: {$durationSeconds}");
        }
        
        return intval($durationSeconds);
    }
    
    /**
     * 작업 완료 처리
     */
    public function markAsCompleted($completedAt = null): void
    {
        $completedAt = $completedAt ?? now();
        $durationSeconds = $this->calculateDurationSeconds($completedAt);
        
        $this->update([
            'status' => 'COMPLETED',
            'completed_at' => $completedAt,
            'progress' => $this->total_items,
            'duration_seconds' => $durationSeconds
        ]);
    }
    
    /**
     * 작업 실패 처리
     */
    public function markAsFailed($errorMessage, $completedAt = null): void
    {
        $completedAt = $completedAt ?? now();
        
        try {
            $durationSeconds = $this->calculateDurationSeconds($completedAt);
        } catch (\Exception $e) {
            // 시간 계산 실패 시 최소 1초로 설정
            $durationSeconds = 1;
            \Log::warning("Time calculation failed, using fallback", [
                'job_id' => $this->id,
                'error' => $e->getMessage()
            ]);
        }
        
        $this->update([
            'status' => 'FAILED',
            'error_message' => $errorMessage,
            'completed_at' => $completedAt,
            'duration_seconds' => $durationSeconds
        ]);
    }

    /**
     * 관계: 작업을 생성한 사용자
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}