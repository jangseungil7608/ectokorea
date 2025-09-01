<template>
  <div class="collection-job-monitor bg-white rounded-lg shadow-lg p-6 max-w-6xl mx-auto">
    <div class="flex justify-between items-center mb-6">
      <h2 class="text-2xl font-bold text-gray-800">
        🔄 수집 작업 모니터
      </h2>
      <div class="flex space-x-3">
        <button
          @click="refreshJobs"
          :disabled="loading"
          class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 disabled:opacity-50"
        >
          {{ loading ? '로딩중...' : '새로고침' }}
        </button>
        <div class="flex items-center">
          <input
            v-model="autoRefresh"
            type="checkbox"
            id="auto-refresh"
            class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
          />
          <label for="auto-refresh" class="ml-2 text-sm text-gray-700">
            자동 새로고침 (10초)
          </label>
        </div>
      </div>
    </div>

    <!-- 필터 -->
    <div class="mb-6 flex flex-wrap gap-4">
      <select
        v-model="filterStatus"
        @change="applyFilters"
        class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
      >
        <option value="">전체 상태</option>
        <option value="PENDING">대기중</option>
        <option value="PROCESSING">처리중</option>
        <option value="COMPLETED">완료</option>
        <option value="FAILED">실패</option>
      </select>

      <select
        v-model="filterType"
        @change="applyFilters"
        class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
      >
        <option value="">전체 타입</option>
        <option value="BULK_ASIN">대량 ASIN</option>
        <option value="URL">URL 수집</option>
        <option value="KEYWORD">키워드 검색</option>
      </select>
    </div>

    <!-- 작업 목록 -->
    <div v-if="jobs.length === 0 && !loading" class="text-center py-12 text-gray-500">
      수집 작업이 없습니다.
    </div>

    <div v-else class="space-y-4">
      <div
        v-for="job in jobs"
        :key="job.id"
        class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow"
      >
        <div class="flex justify-between items-start mb-3">
          <div class="flex-1">
            <div class="flex items-center space-x-3">
              <span
                :class="getStatusClass(job.status)"
                class="px-2 py-1 text-xs font-medium rounded-full"
              >
                {{ getStatusName(job.status) }}
              </span>
              <span class="text-sm font-medium text-gray-600">
                {{ getTypeName(job.type) }}
              </span>
              <span class="text-xs text-gray-400">
                ID: {{ job.id }}
              </span>
            </div>
            <div class="mt-2">
              <span class="text-xs text-gray-500">
                생성: {{ formatDate(job.created_at) }}
              </span>
              <span v-if="job.started_at" class="ml-4 text-xs text-gray-500">
                시작: {{ formatDate(job.started_at) }}
              </span>
              <span v-if="job.completed_at" class="ml-4 text-xs text-gray-500">
                완료: {{ formatDate(job.completed_at) }}
              </span>
            </div>
          </div>
          
          <!-- 진행률 -->
          <div v-if="job.status !== 'PENDING'" class="flex flex-col items-end min-w-0 ml-4">
            <div class="flex items-center space-x-2">
              <span class="text-sm text-gray-600">
                {{ job.progress || 0 }} / {{ job.total_items }}
              </span>
              <span class="text-xs text-gray-400">
                ({{ getProgressPercentage(job) }}%)
              </span>
            </div>
            <div class="w-32 bg-gray-200 rounded-full h-2 mt-1">
              <div
                class="bg-blue-500 h-2 rounded-full transition-all duration-300"
                :style="{ width: getProgressPercentage(job) + '%' }"
              ></div>
            </div>
          </div>
        </div>

        <!-- 작업 세부 정보 -->
        <div class="bg-gray-50 rounded p-3 text-sm">
          <div v-if="job.type === 'KEYWORD'" class="mb-2">
            <strong>키워드:</strong> {{ job.input_data?.keyword }}
          </div>
          <div v-else-if="job.type === 'URL'" class="mb-2">
            <strong>URL:</strong> 
            <a :href="job.input_data?.url" target="_blank" class="text-blue-500 hover:underline">
              {{ truncateUrl(job.input_data?.url) }}
            </a>
          </div>
          <div v-else-if="job.type === 'BULK_ASIN'" class="mb-2">
            <strong>ASIN 수집:</strong> {{ (job.input_data?.asins || []).length }}개 상품
          </div>
          
          <div class="flex items-center justify-between">
            <div class="flex items-center space-x-4">
              <span class="text-gray-600">
                총 {{ job.total_items }}개 항목
              </span>
              <span v-if="job.settings?.auto_analyze" class="text-green-600 text-xs">
                🔍 자동 분석 활성화
              </span>
            </div>
            
            <div class="flex space-x-2">
              <button
                @click="viewJobDetails(job)"
                class="px-2 py-1 text-xs bg-gray-500 text-white rounded hover:bg-gray-600"
              >
                상세
              </button>
              <button
                v-if="job.status === 'FAILED'"
                @click="retryJob(job)"
                class="px-2 py-1 text-xs bg-orange-500 text-white rounded hover:bg-orange-600"
              >
                재시도
              </button>
            </div>
          </div>
        </div>

        <!-- 에러 메시지 -->
        <div v-if="job.error_message" class="mt-3 p-2 bg-red-50 border border-red-200 rounded text-sm text-red-600">
          <strong>오류:</strong> {{ job.error_message }}
        </div>
      </div>
    </div>

    <!-- 페이지네이션 -->
    <div v-if="pagination.last_page > 1" class="mt-6 flex justify-center">
      <nav class="flex space-x-2">
        <button
          @click="changePage(pagination.current_page - 1)"
          :disabled="pagination.current_page <= 1"
          class="px-3 py-2 border border-gray-300 rounded-md hover:bg-gray-50 disabled:opacity-50"
        >
          이전
        </button>

        <span class="px-3 py-2 text-sm text-gray-700">
          {{ pagination.current_page }} / {{ pagination.last_page }}
        </span>

        <button
          @click="changePage(pagination.current_page + 1)"
          :disabled="pagination.current_page >= pagination.last_page"
          class="px-3 py-2 border border-gray-300 rounded-md hover:bg-gray-50 disabled:opacity-50"
        >
          다음
        </button>
      </nav>
    </div>

    <!-- 로딩 -->
    <div v-if="loading" class="text-center py-8">
      <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500"></div>
      <p class="mt-2 text-gray-600">로딩 중...</p>
    </div>

    <!-- 에러 메시지 -->
    <div v-if="error" class="mt-4 p-4 bg-red-50 border border-red-200 rounded-lg">
      <p class="text-red-600">{{ error }}</p>
    </div>

    <!-- 작업 상세 모달 -->
    <div 
      v-if="showJobDetailsModal" 
      class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50"
      @click="showJobDetailsModal = false"
    >
      <div 
        class="relative top-20 mx-auto p-5 border w-11/12 max-w-4xl shadow-lg rounded-md bg-white"
        @click.stop
      >
        <div class="flex justify-between items-center mb-4">
          <h3 class="text-lg font-medium text-gray-900">
            작업 상세 정보
          </h3>
          <button 
            @click="showJobDetailsModal = false"
            class="text-gray-400 hover:text-gray-600"
          >
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
          </button>
        </div>

        <div v-if="selectedJobDetails" class="space-y-4">
          <!-- 기본 정보 -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="bg-gray-50 p-3 rounded">
              <p class="text-sm font-medium text-gray-700">작업 ID</p>
              <p class="text-lg">{{ selectedJobDetails.id }}</p>
            </div>
            <div class="bg-gray-50 p-3 rounded">
              <p class="text-sm font-medium text-gray-700">작업 타입</p>
              <p class="text-lg">{{ getTypeName(selectedJobDetails.type) }}</p>
            </div>
            <div class="bg-gray-50 p-3 rounded">
              <p class="text-sm font-medium text-gray-700">상태</p>
              <p class="text-lg">
                <span :class="getStatusClass(selectedJobDetails.status)">
                  {{ getStatusName(selectedJobDetails.status) }}
                </span>
              </p>
            </div>
            <div class="bg-gray-50 p-3 rounded">
              <p class="text-sm font-medium text-gray-700">진행률</p>
              <div class="flex items-center">
                <div class="w-full bg-gray-200 rounded-full h-2.5 mr-2">
                  <div 
                    class="bg-blue-600 h-2.5 rounded-full" 
                    :style="`width: ${selectedJobDetails.progress_percent || 0}%`"
                  ></div>
                </div>
                <span class="text-sm">{{ selectedJobDetails.progress || 0 }}/{{ selectedJobDetails.total_items || 0 }}</span>
              </div>
            </div>
          </div>

          <!-- 통계 정보 -->
          <div class="bg-gray-50 p-4 rounded">
            <h4 class="font-medium text-gray-700 mb-2">처리 통계</h4>
            <div class="grid grid-cols-3 gap-4 text-sm">
              <div class="text-center">
                <p class="text-2xl font-bold text-green-600">{{ selectedJobDetails.success_count || 0 }}</p>
                <p class="text-gray-600">성공</p>
              </div>
              <div class="text-center">
                <p class="text-2xl font-bold text-red-600">{{ selectedJobDetails.error_count || 0 }}</p>
                <p class="text-gray-600">실패</p>
              </div>
              <div class="text-center">
                <p class="text-2xl font-bold text-blue-600">{{ selectedJobDetails.success_rate || 0 }}%</p>
                <p class="text-gray-600">성공률</p>
              </div>
            </div>
          </div>

          <!-- 입력 데이터 -->
          <div class="bg-gray-50 p-4 rounded">
            <h4 class="font-medium text-gray-700 mb-2">입력 데이터</h4>
            <pre class="text-xs text-gray-600 bg-white p-2 rounded border overflow-x-auto">{{ JSON.stringify(selectedJobDetails.input_data, null, 2) }}</pre>
          </div>

          <!-- 에러 메시지 (있는 경우) -->
          <div v-if="selectedJobDetails.error_message" class="bg-red-50 p-4 rounded border border-red-200">
            <h4 class="font-medium text-red-700 mb-2">에러 메시지</h4>
            <p class="text-red-600 text-sm">{{ selectedJobDetails.error_message }}</p>
          </div>

          <!-- 처리 결과 (있는 경우) -->
          <div v-if="selectedJobDetails.results && selectedJobDetails.results.length > 0" class="bg-gray-50 p-4 rounded">
            <div class="flex justify-between items-center mb-3">
              <h4 class="font-medium text-gray-700">처리 결과</h4>
              <div class="flex space-x-2">
                <button 
                  @click="resultsFilter = 'all'"
                  :class="resultsFilter === 'all' ? 'bg-blue-500 text-white' : 'bg-gray-200 text-gray-700'"
                  class="px-2 py-1 text-xs rounded"
                >
                  전체 ({{ selectedJobDetails.results.length }})
                </button>
                <button 
                  @click="resultsFilter = 'success'"
                  :class="resultsFilter === 'success' ? 'bg-green-500 text-white' : 'bg-gray-200 text-gray-700'"
                  class="px-2 py-1 text-xs rounded"
                >
                  성공 ({{ getFilteredResults('success').length }})
                </button>
                <button 
                  @click="resultsFilter = 'error'"
                  :class="resultsFilter === 'error' ? 'bg-red-500 text-white' : 'bg-gray-200 text-gray-700'"
                  class="px-2 py-1 text-xs rounded"
                >
                  실패 ({{ getFilteredResults('error').length }})
                </button>
              </div>
            </div>
            
            <div class="max-h-64 overflow-y-auto">
              <div 
                v-for="(result, index) in getFilteredResults(resultsFilter)" 
                :key="index"
                class="flex items-center justify-between p-2 mb-2 bg-white rounded border text-sm"
              >
                <div class="flex items-center space-x-3">
                  <span 
                    :class="result.status === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'"
                    class="px-2 py-1 text-xs font-medium rounded-full"
                  >
                    {{ result.status === 'success' ? '✓' : '✗' }}
                  </span>
                  <span class="font-medium">{{ result.asin }}</span>
                  <span v-if="result.error" class="text-red-600 text-xs">{{ result.error }}</span>
                </div>
                <span class="text-gray-400 text-xs">
                  {{ formatResultDate(result.processed_at) }}
                </span>
              </div>
            </div>
          </div>

          <!-- 시간 정보 -->
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
            <div class="bg-gray-50 p-3 rounded">
              <p class="font-medium text-gray-700">시작 시간</p>
              <p>{{ selectedJobDetails.started_at ? new Date(selectedJobDetails.started_at).toLocaleString('ko-KR') : '-' }}</p>
            </div>
            <div class="bg-gray-50 p-3 rounded">
              <p class="font-medium text-gray-700">완료 시간</p>
              <p>{{ selectedJobDetails.completed_at ? new Date(selectedJobDetails.completed_at).toLocaleString('ko-KR') : '-' }}</p>
            </div>
            <div class="bg-gray-50 p-3 rounded">
              <p class="font-medium text-gray-700">소요 시간</p>
              <p>{{ selectedJobDetails.duration_minutes ? selectedJobDetails.duration_minutes + '분' : '-' }}</p>
            </div>
          </div>
        </div>

        <div class="mt-6 flex justify-end">
          <button 
            @click="showJobDetailsModal = false"
            class="px-4 py-2 bg-gray-500 text-white rounded hover:bg-gray-600"
          >
            닫기
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import api from '@/utils/api'

export default {
  name: 'CollectionJobMonitor',
  data() {
    return {
      jobs: [],
      pagination: {},
      loading: false,
      error: null,
      autoRefresh: false,
      refreshInterval: null,
      
      // 필터
      filterStatus: '',
      filterType: '',
      
      // 상세 모달
      showJobDetailsModal: false,
      selectedJobDetails: null,
      resultsFilter: 'all', // 'all', 'success', 'error'
      
      // 상태 및 타입 매핑
      statusNames: {
        'PENDING': '대기중',
        'PROCESSING': '처리중',
        'COMPLETED': '완료',
        'FAILED': '실패'
      },
      typeNames: {
        'BULK_ASIN': '대량 ASIN 수집',
        'URL': 'URL 수집',
        'KEYWORD': '키워드 검색'
      }
    }
  },
  async mounted() {
    await this.loadJobs()
  },
  beforeUnmount() {
    if (this.refreshInterval) {
      clearInterval(this.refreshInterval)
    }
  },
  watch: {
    autoRefresh(newVal) {
      if (newVal) {
        this.refreshInterval = setInterval(() => {
          this.refreshJobs()
        }, 10000) // 10초마다 새로고침
      } else {
        if (this.refreshInterval) {
          clearInterval(this.refreshInterval)
          this.refreshInterval = null
        }
      }
    }
  },
  methods: {
    async loadJobs(page = 1) {
      this.loading = true
      this.error = null

      try {
        const params = {
          page,
          per_page: 10
        }

        if (this.filterStatus) params.status = this.filterStatus
        if (this.filterType) params.type = this.filterType

        const response = await api.get('/collected-products/jobs/list', { params })

        if (response.data.success) {
          this.jobs = response.data.data.data
          this.pagination = {
            current_page: response.data.data.current_page,
            last_page: response.data.data.last_page,
            total: response.data.data.total
          }
        }
      } catch (error) {
        this.error = error.response?.data?.message || '작업 목록을 불러올 수 없습니다.'
      } finally {
        this.loading = false
      }
    },

    async refreshJobs() {
      await this.loadJobs(this.pagination.current_page || 1)
    },

    async applyFilters() {
      await this.loadJobs(1)
    },

    async changePage(page) {
      if (page >= 1 && page <= this.pagination.last_page) {
        await this.loadJobs(page)
      }
    },

    async retryJob(job) {
      // 재시도 기능은 백엔드 구현 필요
      this.error = '재시도 기능은 아직 구현되지 않았습니다.'
    },

    async viewJobDetails(job) {
      try {
        this.loading = true
        
        // API로 작업 상세 정보 조회
        const response = await api.get(`/collected-products/jobs/${job.id}`)
        
        if (response.data.success) {
          // 상세 정보를 표시할 수 있도록 데이터 저장
          this.selectedJobDetails = response.data.data
          this.resultsFilter = 'all' // 필터 리셋
          this.showJobDetailsModal = true
        } else {
          console.error('작업 상세 정보 조회 실패:', response.data.message)
          alert('작업 상세 정보를 불러오는데 실패했습니다.')
        }
      } catch (error) {
        console.error('작업 상세 정보 조회 중 오류:', error)
        alert('작업 상세 정보 조회 중 오류가 발생했습니다.')
      } finally {
        this.loading = false
      }
    },

    getStatusName(status) {
      return this.statusNames[status] || status
    },

    getTypeName(type) {
      return this.typeNames[type] || type
    },

    getStatusClass(status) {
      const classes = {
        'PENDING': 'bg-gray-100 text-gray-800',
        'PROCESSING': 'bg-blue-100 text-blue-800',
        'COMPLETED': 'bg-green-100 text-green-800',
        'FAILED': 'bg-red-100 text-red-800'
      }
      return classes[status] || 'bg-gray-100 text-gray-800'
    },

    getProgressPercentage(job) {
      // 백엔드에서 이미 계산된 progress_percent가 있으면 우선 사용
      if (job.progress_percent !== undefined && job.progress_percent !== null) {
        return Math.round(job.progress_percent)
      }
      
      // 없으면 progress와 total_items로 계산
      if (!job.total_items) return 0
      const progress = job.progress || 0
      return Math.round((progress / job.total_items) * 100)
    },

    formatDate(dateString) {
      if (!dateString) return ''
      const date = new Date(dateString)
      return date.toLocaleString('ko-KR', {
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
      })
    },

    truncateUrl(url, maxLength = 50) {
      if (!url) return ''
      return url.length > maxLength ? url.substring(0, maxLength) + '...' : url
    },

    getFilteredResults(filter) {
      if (!this.selectedJobDetails?.results) return []
      
      if (filter === 'all') {
        return this.selectedJobDetails.results
      }
      
      return this.selectedJobDetails.results.filter(result => result.status === filter)
    },

    formatResultDate(dateString) {
      if (!dateString) return ''
      const date = new Date(dateString)
      return date.toLocaleString('ko-KR', {
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit'
      })
    }
  }
}
</script>

<style scoped>
/* 추가 스타일링 */
</style>