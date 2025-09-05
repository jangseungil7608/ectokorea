# Laravel Queue 시스템 사양서 (QUEUE_SYSTEM.md)

## 📋 개요

**EctoKorea Queue 시스템**은 Laravel Queue를 기반으로 대량 상품 수집 작업을 백그라운드에서 안정적으로 처리하는 시스템입니다. 사용자 경험을 향상시키고 시스템 안정성을 보장하기 위해 구현되었습니다.

## 🎯 주요 기능

### ✅ 구현된 기능

- **대량 ASIN 수집**: 최대 100개 ASIN 동시 처리
- **URL 수집 처리**: 베스트셀러/검색 결과 페이지에서 ASIN 추출 후 개별 처리  
- **키워드 검색 수집**: 키워드 검색 결과에서 상품 자동 수집
- **실시간 진행률**: 작업 진행 상황 실시간 업데이트
- **에러 핸들링**: 개별 실패가 전체 작업을 중단하지 않음
- **자동 재시도**: 실패 시 최대 3회 재시도
- **상세 로깅**: 디버깅을 위한 포괄적 로그 시스템

## 🏗️ 아키텍처

### Queue Job 클래스
```php
App\Jobs\ProcessBulkCollectionJob
├── handle()           # 메인 실행 메서드
├── processBulkAsin()  # BULK_ASIN 타입 처리
├── processUrlCollection()  # URL 타입 처리  
├── processKeywordCollection()  # KEYWORD 타입 처리
└── failed()           # 실패 처리 메서드
```

### 데이터베이스 스키마

#### CollectionJob 테이블
```sql
CREATE TABLE collection_jobs (
    id BIGINT PRIMARY KEY,
    user_id BIGINT NOT NULL,
    type VARCHAR(20) NOT NULL,  -- BULK_ASIN, URL, KEYWORD
    status VARCHAR(20) DEFAULT 'PENDING',  -- PENDING, PROCESSING, COMPLETED, FAILED
    input_data JSON NOT NULL,   -- ASIN 배열, URL 정보 등
    settings JSON,              -- auto_analyze, target_margin 등
    progress INT DEFAULT 0,     -- 처리된 아이템 수
    total_items INT DEFAULT 0,  -- 전체 아이템 수
    success_count INT DEFAULT 0,
    error_count INT DEFAULT 0,
    results JSON,               -- 개별 처리 결과
    started_at TIMESTAMP,
    completed_at TIMESTAMP,
    duration_seconds INT,       -- 소요 시간(초)
    error_message TEXT,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

#### Laravel Jobs 테이블
```sql
CREATE TABLE jobs (
    id BIGINT PRIMARY KEY,
    queue VARCHAR(255) NOT NULL DEFAULT 'default',
    payload LONGTEXT NOT NULL,
    attempts TINYINT UNSIGNED NOT NULL,
    reserved_at INT UNSIGNED,
    available_at INT UNSIGNED NOT NULL,
    created_at INT UNSIGNED NOT NULL
);

CREATE TABLE failed_jobs (
    id BIGINT PRIMARY KEY,
    connection TEXT NOT NULL,
    queue TEXT NOT NULL,
    payload LONGTEXT NOT NULL,
    exception LONGTEXT NOT NULL,
    failed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

## 🔄 처리 흐름

### 1. 작업 생성 (Controller Level)
```php
// 1단계: ProductCollectionService에서 CollectionJob 생성
$job = $this->collectionService->createBulkCollectionJob(
    'BULK_ASIN',
    ['asins' => $validated['asins']],
    ['auto_analyze' => $validated['auto_analyze'] ?? true]
);

// 2단계: Queue에 Job 디스패치 (Controller에서 수행)
ProcessBulkCollectionJob::dispatch($job->id, auth('api')->id());
```

### 2. 백그라운드 처리 (Queue Worker)
```php
// ProcessBulkCollectionJob::handle() 메서드 실행 흐름
1. CollectionJob 상태를 PROCESSING으로 변경
2. 타입별 처리 메서드 호출
   - BULK_ASIN: processBulkAsin()
   - URL: processUrlCollection() 
   - KEYWORD: processKeywordCollection()
3. 각 ASIN을 개별 처리
   - ProductCollectionService::collectByAsinForUser() 호출
   - 성공/실패 카운트 업데이트
   - 진행률 실시간 업데이트
4. 완료 시 CollectionJob::markAsCompleted() 호출
```

### 3. 에러 처리
```php
try {
    // 개별 ASIN 처리
    $service->collectByAsinForUser($asin, $userId, ...);
    $successCount++;
} catch (Exception $e) {
    // 개별 실패는 전체 작업을 중단하지 않음
    $errorCount++;
    // 실패 결과를 results 배열에 저장
    $results[] = [
        'asin' => $asin,
        'status' => 'error',
        'error' => $e->getMessage()
    ];
}
```

## ⚙️ 설정 및 관리

### Laravel Scheduler 설정 (권장)
```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    // 매분마다 Queue 작업 처리 (자동 종료)
    $schedule->command('queue:work --stop-when-empty --timeout=300')
             ->everyMinute()
             ->withoutOverlapping();

    // 30분 이상 PENDING 작업 정리 (매 시간)
    $schedule->call(function () {
        CollectionJob::where('status', 'PENDING')
                     ->where('created_at', '<', now()->subMinutes(30))
                     ->update(['status' => 'FAILED', 'error_message' => 'Timeout: Job expired']);
    })->hourly();

    // 실패한 Queue 작업 정리 (주 1회)
    $schedule->command('queue:prune-failed --hours=168')->weekly();
}
```

### 수동 Queue Worker 실행
```bash
# 일회성 실행 (모든 작업 처리 후 종료)
php artisan queue:work --stop-when-empty

# 지속적 실행 (새 작업 대기)
php artisan queue:work --timeout=300

# Queue 상태 모니터링
php artisan queue:monitor database

# 실패한 작업 확인
php artisan queue:failed

# 실패한 작업 재시도
php artisan queue:retry {job-id}
```

### Docker 환경에서 관리
```bash
# Queue Worker 상태 확인
docker exec -it ectokorea-backend-1 ps aux | grep queue:work

# Queue Worker 강제 종료 (코드 변경 시)
docker exec -it ectokorea-backend-1 pkill -f "queue:work"

# Laravel Scheduler 실행 (권장)
docker exec -it ectokorea-backend-1 php artisan schedule:run
```

## 📊 모니터링 및 통계

### CollectionJob 모델 메서드
```php
// 성공률 계산
public function getSuccessRateAttribute(): float
{
    $total = $this->success_count + $this->error_count;
    return $total > 0 ? round(($this->success_count / $total) * 100, 1) : 0.0;
}

// 소요시간 계산
public function calculateDurationSeconds(): int
{
    if (!$this->started_at || !$this->completed_at) {
        return 0;
    }
    
    $duration = $this->completed_at->diffInSeconds($this->started_at);
    if ($duration < 0) {
        throw new Exception("Duration calculation error: negative value");
    }
    
    return $duration;
}

// 완료 처리
public function markAsCompleted(): void
{
    $this->update([
        'status' => 'COMPLETED',
        'completed_at' => now(),
        'duration_seconds' => $this->calculateDurationSeconds()
    ]);
}
```

### 통계 데이터
- **진행률**: `progress / total_items * 100`
- **성공률**: `success_count / (success_count + error_count) * 100`
- **소요시간**: `completed_at - started_at` (초)
- **처리속도**: `success_count / duration_seconds` (개/초)

## 🚨 트러블슈팅

### 일반적인 문제들

#### 1. Queue Worker가 실행되지 않음
```bash
# 해결: Laravel Scheduler 사용 (권장)
php artisan schedule:run

# 또는 수동 실행
php artisan queue:work --stop-when-empty
```

#### 2. 작업이 PENDING 상태로 멈춤
```bash
# 원인: Queue Job이 디스패치되지 않음
# 해결: 컨트롤러에서 dispatch() 호출 확인
ProcessBulkCollectionJob::dispatch($job->id, $userId);
```

#### 3. 코드 변경 후 Queue Worker가 구버전 실행
```bash
# 해결: Queue Worker 재시작 필수
docker exec -it ectokorea-backend-1 pkill -f "queue:work"
docker exec -it ectokorea-backend-1 php artisan queue:work --stop-when-empty
```

#### 4. 시간대 불일치로 인한 duration 오류
```bash
# 해결: config/app.php에서 timezone 통일
'timezone' => 'Asia/Tokyo',

# 설정 캐시 클리어
php artisan config:clear
```

#### 5. 메모리 부족으로 인한 실패
```bash
# 해결: memory_limit 증가
php -d memory_limit=512M artisan queue:work --stop-when-empty
```

## 🔮 향후 개선 계획

### 성능 최적화
- Redis Queue Driver 도입 검토
- Queue Worker 다중 프로세스 실행
- 우선순위 Queue 시스템

### 기능 확장
- 사용자별 동시 실행 작업 수 제한
- 작업 취소 기능
- 예약 작업 시스템
- 실시간 알림 시스템

### 모니터링 강화
- Queue Dashboard 구축
- 성능 메트릭 수집
- 알람 시스템 구축
- 자동 스케일링

## 📈 성능 지표

### 현재 처리 성능
- **ASIN 당 평균 처리 시간**: 2-3초 (API 호출 간격 포함)
- **최대 동시 처리**: 100개 ASIN
- **성공률**: 95% 이상 (네트워크 상태 양호 시)
- **메모리 사용량**: 평균 64MB per job

### 권장 설정
- **Queue Worker 타임아웃**: 300초 (5분)
- **재시도 횟수**: 3회
- **API 호출 간격**: 2초 (서버 부하 방지)
- **배치 크기**: 50개 이하 (안정성 확보)

---

*이 문서는 EctoKorea v1.0 기준으로 작성되었습니다. (2025-09-05 업데이트)*