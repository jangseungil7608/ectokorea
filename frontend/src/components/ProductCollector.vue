<template>
  <div class="product-collector bg-white rounded-lg shadow-lg p-6 max-w-4xl mx-auto">
    <h2 class="text-2xl font-bold text-gray-800 mb-6 text-center">
      🔍 상품 수집
    </h2>

    <!-- 수집 방법 탭 -->
    <div class="mb-6">
      <div class="border-b border-gray-200">
        <nav class="-mb-px flex space-x-8">
          <button
            @click="activeTab = 'asin'"
            :class="[
              'py-2 px-1 border-b-2 font-medium text-sm',
              activeTab === 'asin' 
                ? 'border-blue-500 text-blue-600' 
                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
            ]"
          >
            ASIN 수집
          </button>
          <button
            @click="activeTab = 'bulk'"
            :class="[
              'py-2 px-1 border-b-2 font-medium text-sm',
              activeTab === 'bulk' 
                ? 'border-blue-500 text-blue-600' 
                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
            ]"
          >
            대량 수집
          </button>
          <button
            @click="activeTab = 'url'"
            :class="[
              'py-2 px-1 border-b-2 font-medium text-sm',
              activeTab === 'url' 
                ? 'border-blue-500 text-blue-600' 
                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
            ]"
          >
            URL 수집
          </button>
          <button
            @click="activeTab = 'keyword'"
            :class="[
              'py-2 px-1 border-b-2 font-medium text-sm',
              activeTab === 'keyword' 
                ? 'border-blue-500 text-blue-600' 
                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
            ]"
          >
            키워드 수집
          </button>
        </nav>
      </div>
    </div>

    <!-- ASIN 단일 수집 -->
    <div v-if="activeTab === 'asin'" class="space-y-4">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">
          ASIN 코드 *
        </label>
        <div class="flex space-x-3">
          <input
            v-model="singleAsin"
            type="text"
            placeholder="예: B08N5WRWNW"
            class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            :disabled="loading.single"
            @keyup.enter="collectSingleAsin"
          />
          <button
            @click="collectSingleAsin"
            :disabled="!singleAsin || loading.single"
            class="px-6 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {{ loading.single ? '수집중...' : '수집' }}
          </button>
        </div>
        <p class="text-xs text-gray-500 mt-1">
          Amazon.co.jp 상품 페이지의 ASIN 코드를 입력하세요
        </p>
      </div>

      <div class="space-y-3">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">
            목표 수익률 (%)
          </label>
          <input
            v-model.number="targetMargin"
            type="number"
            min="5"
            max="50"
            step="1"
            class="w-32 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            :disabled="loading.single"
          />
          <p class="text-xs text-gray-500 mt-1">
            기본값: 10% (5~50% 범위)
          </p>
        </div>
        
        <!-- 배송비 설정 -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
              일본 배송비 (¥)
            </label>
            <input
              v-model.number="japanShippingJpy"
              type="number"
              min="0"
              max="10000"
              step="100"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              :disabled="loading.single"
              placeholder="0"
            />
            <p class="text-xs text-gray-500 mt-1">
              기본값: 0¥ (Amazon 내 일본 배송비)
            </p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
              한국 배송비 (₩)
            </label>
            <input
              v-model.number="koreaShippingKrw"
              type="number"
              min="0"
              max="50000"
              step="1000"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              :disabled="loading.single"
              placeholder="0"
            />
            <p class="text-xs text-gray-500 mt-1">
              기본값: 0₩ (한국 내 배송비)
            </p>
          </div>
        </div>
        <div class="flex items-center">
          <input
            v-model="autoAnalyze"
            type="checkbox"
            id="auto-analyze"
            class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
          />
          <label for="auto-analyze" class="ml-2 block text-sm text-gray-700">
            수집 후 자동으로 수익성 분석
          </label>
        </div>
      </div>
    </div>

    <!-- URL 수집 -->
    <div v-if="activeTab === 'url'" class="space-y-4">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">
          Amazon URL *
        </label>
        <div class="flex space-x-3">
          <input
            v-model="collectionUrl"
            type="url"
            placeholder="https://www.amazon.co.jp/dp/B08N5WRWNW 또는 검색/카테고리 URL"
            class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            :disabled="loading.url"
            @keyup.enter="collectByUrl"
          />
          <button
            @click="collectByUrl"
            :disabled="!collectionUrl || loading.url"
            class="px-6 py-2 bg-purple-500 text-white rounded-md hover:bg-purple-600 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {{ loading.url ? '수집중...' : '수집' }}
          </button>
        </div>
        <p class="text-xs text-gray-500 mt-1">
          상품 페이지, 검색 결과, 카테고리 페이지 URL 지원
        </p>
      </div>

      <div class="flex items-center justify-between">
        <div class="flex items-center space-x-4">
          <div class="flex items-center">
            <input
              v-model="urlAutoAnalyze"
              type="checkbox"
              id="url-auto-analyze"
              class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
            />
            <label for="url-auto-analyze" class="ml-2 block text-sm text-gray-700">
              수집 후 자동으로 수익성 분석
            </label>
          </div>
          <div>
            <label class="block text-xs text-gray-500 mb-1">최대 수집 개수</label>
            <select
              v-model="urlMaxResults"
              class="px-2 py-1 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-blue-500"
            >
              <option value="10">10개</option>
              <option value="20">20개</option>
              <option value="50">50개</option>
              <option value="100">100개</option>
            </select>
          </div>
        </div>
      </div>
    </div>

    <!-- 키워드 수집 -->
    <div v-if="activeTab === 'keyword'" class="space-y-4">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">
          검색 키워드 *
        </label>
        <div class="flex space-x-3">
          <input
            v-model="searchKeyword"
            type="text"
            placeholder="예: 무선 이어폰, マウス, キーボード"
            class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            :disabled="loading.keyword"
            @keyup.enter="collectByKeyword"
          />
          <button
            @click="collectByKeyword"
            :disabled="!searchKeyword || loading.keyword"
            class="px-6 py-2 bg-orange-500 text-white rounded-md hover:bg-orange-600 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {{ loading.keyword ? '검색중...' : '검색' }}
          </button>
        </div>
        <p class="text-xs text-gray-500 mt-1">
          Amazon.co.jp에서 키워드로 검색하여 상품을 수집합니다
        </p>
      </div>

      <div class="flex items-center justify-between">
        <div class="flex items-center space-x-4">
          <div class="flex items-center">
            <input
              v-model="keywordAutoAnalyze"
              type="checkbox"
              id="keyword-auto-analyze"
              class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
            />
            <label for="keyword-auto-analyze" class="ml-2 block text-sm text-gray-700">
              수집 후 자동으로 수익성 분석
            </label>
          </div>
          <div>
            <label class="block text-xs text-gray-500 mb-1">최대 수집 개수</label>
            <select
              v-model="keywordMaxResults"
              class="px-2 py-1 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-blue-500"
            >
              <option value="20">20개</option>
              <option value="50">50개</option>
              <option value="100">100개</option>
            </select>
          </div>
        </div>
      </div>
    </div>

    <!-- 대량 ASIN 수집 -->
    <div v-if="activeTab === 'bulk'" class="space-y-4">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">
          ASIN 목록 (한 줄에 하나씩)
        </label>
        <textarea
          v-model="bulkAsins"
          rows="8"
          placeholder="B08N5WRWNW&#10;B07XYZ1234&#10;B09ABC5678"
          class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
          :disabled="loading.bulk"
        ></textarea>
        <p class="text-xs text-gray-500 mt-1">
          최대 100개까지 입력 가능합니다. 현재: {{ bulkAsinList.length }}개
        </p>
      </div>

      <div class="flex items-center justify-between">
        <div class="flex items-center">
          <input
            v-model="bulkAutoAnalyze"
            type="checkbox"
            id="bulk-auto-analyze"
            class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
          />
          <label for="bulk-auto-analyze" class="ml-2 block text-sm text-gray-700">
            수집 후 자동으로 수익성 분석
          </label>
        </div>

        <button
          @click="collectBulkAsins"
          :disabled="bulkAsinList.length === 0 || loading.bulk"
          class="px-6 py-2 bg-green-500 text-white rounded-md hover:bg-green-600 disabled:opacity-50 disabled:cursor-not-allowed"
        >
          {{ loading.bulk ? '작업 생성중...' : `${bulkAsinList.length}개 수집` }}
        </button>
      </div>
    </div>

    <!-- 수집 결과 -->
    <div v-if="result" class="mt-6 p-4 border rounded-lg">
      <div v-if="result.success" class="text-green-600">
        <h3 class="font-semibold mb-2">✅ {{ result.message }}</h3>
        <div v-if="result.data" class="text-sm text-gray-600">
          <p><strong>ASIN:</strong> {{ result.data.asin }}</p>
          <p><strong>상태:</strong> {{ result.data.status_name || result.data.status }}</p>
          <p v-if="result.data.title && result.data.title !== '수집 중...'">
            <strong>제목:</strong> {{ result.data.title }}
          </p>
        </div>
      </div>
      <div v-else class="text-red-600">
        <h3 class="font-semibold mb-2">❌ {{ result.message }}</h3>
        <p v-if="result.error" class="text-sm">{{ result.error }}</p>
      </div>
    </div>

    <!-- 에러 메시지 -->
    <div v-if="error" class="mt-4 p-4 bg-red-50 border border-red-200 rounded-lg">
      <p class="text-red-600">{{ error }}</p>
    </div>

    <!-- 최근 수집 작업 -->
    <div class="mt-8">
      <h3 class="text-lg font-semibold text-gray-800 mb-4">📊 수집 통계</h3>
      <div v-if="stats" class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-blue-50 p-4 rounded-lg text-center">
          <div class="text-2xl font-bold text-blue-600">{{ stats.total_products }}</div>
          <div class="text-sm text-gray-600">총 수집 상품</div>
        </div>
        <div class="bg-green-50 p-4 rounded-lg text-center">
          <div class="text-2xl font-bold text-green-600">{{ stats.profitable_count }}</div>
          <div class="text-sm text-gray-600">수익성 상품</div>
        </div>
        <div class="bg-yellow-50 p-4 rounded-lg text-center">
          <div class="text-2xl font-bold text-yellow-600">{{ stats.by_status.collected + stats.by_status.analyzed }}</div>
          <div class="text-sm text-gray-600">수집 완료</div>
        </div>
        <div class="bg-purple-50 p-4 rounded-lg text-center">
          <div class="text-2xl font-bold text-purple-600">{{ stats.favorite_count }}</div>
          <div class="text-sm text-gray-600">즐겨찾기</div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios'

export default {
  name: 'ProductCollector',
  data() {
    return {
      activeTab: 'asin',
      
      // 단일 수집
      singleAsin: '',
      autoAnalyze: true,
      targetMargin: 10,
      japanShippingJpy: 0,
      koreaShippingKrw: 0,
      
      // 대량 수집
      bulkAsins: '',
      bulkAutoAnalyze: true,
      
      // URL 수집
      collectionUrl: '',
      urlAutoAnalyze: true,
      urlMaxResults: 20,
      
      // 키워드 수집
      searchKeyword: '',
      keywordAutoAnalyze: true,
      keywordMaxResults: 50,
      
      // 상태
      loading: {
        single: false,
        bulk: false,
        url: false,
        keyword: false
      },
      result: null,
      error: null,
      stats: null
    }
  },
  computed: {
    bulkAsinList() {
      return this.bulkAsins
        .split('\n')
        .map(line => line.trim().toUpperCase())
        .filter(asin => asin && /^[A-Z0-9]{10}$/.test(asin))
    }
  },
  async mounted() {
    await this.loadStats()
  },
  methods: {
    async collectSingleAsin() {
      if (!this.singleAsin || !/^[A-Z0-9]{10}$/i.test(this.singleAsin)) {
        this.error = '올바른 ASIN 코드를 입력하세요 (10자리 영숫자)'
        return
      }

      this.loading.single = true
      this.error = null
      this.result = null

      try {
        const response = await axios.post('/collected-products/collect/asin', {
          asin: this.singleAsin.toUpperCase(),
          auto_analyze: this.autoAnalyze,
          target_margin: this.targetMargin,
          japan_shipping_jpy: this.japanShippingJpy || 0,
          korea_shipping_krw: this.koreaShippingKrw || 0
        })

        this.result = response.data
        this.singleAsin = ''
        
        // 통계 새로고침
        await this.loadStats()
        
        // 수집 완료 이벤트 발생
        this.$emit('collected', response.data.data)

      } catch (error) {
        this.error = error.response?.data?.message || '수집에 실패했습니다.'
        this.result = error.response?.data
      } finally {
        this.loading.single = false
      }
    },

    async collectBulkAsins() {
      if (this.bulkAsinList.length === 0) {
        this.error = '수집할 ASIN을 입력하세요'
        return
      }

      if (this.bulkAsinList.length > 100) {
        this.error = '최대 100개까지만 입력 가능합니다'
        return
      }

      this.loading.bulk = true
      this.error = null
      this.result = null

      try {
        const response = await axios.post('/collected-products/collect/bulk-asin', {
          asins: this.bulkAsinList,
          auto_analyze: this.bulkAutoAnalyze
        })

        this.result = response.data
        this.bulkAsins = ''
        
        // 통계 새로고침
        await this.loadStats()
        
        // 대량 수집 작업 생성 이벤트 발생
        this.$emit('bulk-job-created', response.data.data)

      } catch (error) {
        this.error = error.response?.data?.message || '대량 수집 작업 생성에 실패했습니다.'
        this.result = error.response?.data
      } finally {
        this.loading.bulk = false
      }
    },

    async collectByUrl() {
      if (!this.collectionUrl || !this.isValidUrl(this.collectionUrl)) {
        this.error = '올바른 Amazon URL을 입력하세요'
        return
      }

      this.loading.url = true
      this.error = null
      this.result = null

      try {
        const response = await axios.post('/collected-products/collect/url', {
          url: this.collectionUrl,
          auto_analyze: this.urlAutoAnalyze,
          max_results: parseInt(this.urlMaxResults)
        })

        this.result = response.data
        this.collectionUrl = ''
        
        // 통계 새로고침
        await this.loadStats()
        
        // 적절한 이벤트 발생
        if (response.data.data?.type === 'single') {
          this.$emit('collected', response.data.data.products[0])
        } else {
          this.$emit('bulk-job-created', response.data.data.job)
        }

      } catch (error) {
        this.error = error.response?.data?.message || 'URL 수집에 실패했습니다.'
        this.result = error.response?.data
      } finally {
        this.loading.url = false
      }
    },

    async collectByKeyword() {
      if (!this.searchKeyword || this.searchKeyword.trim().length < 2) {
        this.error = '2글자 이상의 키워드를 입력하세요'
        return
      }

      this.loading.keyword = true
      this.error = null
      this.result = null

      try {
        const response = await axios.post('/collected-products/collect/keyword', {
          keyword: this.searchKeyword.trim(),
          max_results: parseInt(this.keywordMaxResults),
          auto_analyze: this.keywordAutoAnalyze
        })

        this.result = response.data
        this.searchKeyword = ''
        
        // 통계 새로고침
        await this.loadStats()
        
        // 키워드 수집 작업 생성 이벤트 발생
        this.$emit('bulk-job-created', response.data.data)

      } catch (error) {
        this.error = error.response?.data?.message || '키워드 수집에 실패했습니다.'
        this.result = error.response?.data
      } finally {
        this.loading.keyword = false
      }
    },

    isValidUrl(url) {
      try {
        const urlObj = new URL(url)
        return urlObj.hostname.includes('amazon.co.jp') || urlObj.hostname.includes('amazon.com')
      } catch {
        return false
      }
    },

    async loadStats() {
      try {
        const response = await axios.get('/collected-products/stats/overview')
        if (response.data.success) {
          this.stats = response.data.data
        }
      } catch (error) {
        console.error('통계 로드 실패:', error)
      }
    }
  }
}
</script>

<style scoped>
/* 추가 스타일링이 필요하면 여기에 */
</style>