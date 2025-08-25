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
  </div>
</template>

<script>
import axios from 'axios'

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

        const response = await axios.get('/collected-products/jobs/list', { params })

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

    viewJobDetails(job) {
      // 상세 보기 구현 (모달 또는 페이지)
      this.$emit('view-job-details', job)
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
    }
  }
}
</script>

<style scoped>
/* 추가 스타일링 */
</style>