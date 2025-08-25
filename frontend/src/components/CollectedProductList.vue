<template>
  <div class="collected-product-list bg-white rounded-lg shadow-lg p-6 max-w-6xl mx-auto">
    <div class="flex justify-between items-center mb-6">
      <h2 class="text-2xl font-bold text-gray-800">
        📦 수집 상품 목록
      </h2>
      <button
        @click="refreshList"
        :disabled="loading"
        class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 disabled:opacity-50"
      >
        {{ loading ? '로딩중...' : '새로고침' }}
      </button>
    </div>

    <!-- 필터 및 검색 -->
    <div class="mb-6 space-y-4">
      <!-- 필터 바 -->
      <div class="flex flex-wrap gap-4">
        <select
          v-model="filters.status"
          @change="applyFilters"
          class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
        >
          <option value="">전체 상태</option>
          <option value="PENDING">수집대기</option>
          <option value="COLLECTING">수집중</option>
          <option value="COLLECTED">수집완료</option>
          <option value="ANALYZED">분석완료</option>
          <option value="READY_TO_LIST">등록대기</option>
          <option value="LISTED">판매중</option>
          <option value="ERROR">오류</option>
        </select>

        <select
          v-model="filters.profitable"
          @change="applyFilters"
          class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
        >
          <option value="">수익성 전체</option>
          <option value="true">수익성 있음</option>
          <option value="false">수익성 없음</option>
        </select>

        <select
          v-model="filters.favorite"
          @change="applyFilters"
          class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
        >
          <option value="">즐겨찾기 전체</option>
          <option value="true">즐겨찾기만</option>
          <option value="false">일반</option>
        </select>

        <select
          v-model="sortBy"
          @change="applyFilters"
          class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
        >
          <option value="created_at">등록일순</option>
          <option value="collected_at">수집일순</option>
          <option value="profit_margin">수익률순</option>
          <option value="price_jpy">가격순</option>
          <option value="title">제목순</option>
        </select>

        <select
          v-model="sortOrder"
          @change="applyFilters"
          class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
        >
          <option value="desc">내림차순</option>
          <option value="asc">오름차순</option>
        </select>
      </div>

      <!-- 검색 -->
      <div class="flex space-x-3">
        <input
          v-model="searchQuery"
          type="text"
          placeholder="상품명 또는 ASIN으로 검색..."
          class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
          @keyup.enter="applyFilters"
        />
        <button
          @click="applyFilters"
          class="px-4 py-2 bg-gray-500 text-white rounded-md hover:bg-gray-600"
        >
          검색
        </button>
      </div>
    </div>

    <!-- 상품 목록 -->
    <div v-if="products.length === 0 && !loading" class="text-center py-12 text-gray-500">
      수집된 상품이 없습니다.
    </div>

    <div v-else class="space-y-4">
      <div
        v-for="product in products"
        :key="product.id"
        class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow overflow-hidden"
      >
        <div class="flex flex-col sm:flex-row space-y-4 sm:space-y-0 sm:space-x-4 min-w-0">
          <!-- 상품 이미지 -->
          <div class="flex-shrink-0 self-center sm:self-start">
            <img
              :src="getProductImage(product)"
              :alt="product.title"
              class="w-24 h-24 object-cover rounded-lg mx-auto sm:mx-0"
              @error="handleImageError"
            />
          </div>

          <!-- 상품 정보 -->
          <div class="flex-1 min-w-0">
            <div class="flex justify-between items-start mb-2">
              <div class="flex-1 min-w-0 pr-2">
                <h3 class="text-lg font-semibold text-gray-900 break-words leading-tight">
                  {{ product.title }}
                </h3>
                <p class="text-sm text-gray-500 mt-1 break-all">
                  ASIN: {{ product.asin }} | 
                  <a :href="product.amazon_url" target="_blank" class="text-blue-500 hover:underline whitespace-nowrap">
                    Amazon에서 보기 ↗
                  </a>
                </p>
              </div>

              <!-- 즐겨찾기 버튼 -->
              <button
                @click="toggleFavorite(product)"
                class="ml-2 p-2 hover:bg-gray-100 rounded-full transition-colors"
                :title="product.is_favorite ? '즐겨찾기 해제' : '즐겨찾기 추가'"
              >
                <span 
                  :class="product.is_favorite ? 'text-yellow-500' : 'text-gray-400'" 
                  class="text-xl transition-colors"
                >
                  {{ product.is_favorite ? '★' : '☆' }}
                </span>
              </button>
            </div>

            <!-- 상태 및 기본 정보 -->
            <div class="flex flex-wrap items-center gap-4 mb-3">
              <span
                :class="getStatusClass(product.status)"
                class="px-2 py-1 text-xs font-medium rounded-full"
              >
                {{ getStatusName(product.status) }}
              </span>

              <span v-if="product.price_jpy" class="text-sm text-gray-600">
                ¥{{ formatPrice(product.price_jpy) }}
              </span>

              <span v-if="product.weight_g" class="text-sm text-gray-600">
                {{ product.weight_g }}g
              </span>

              <span v-if="product.category" class="text-sm text-gray-600">
                {{ product.category }}
              </span>
            </div>

            <!-- 수익성 정보 -->
            <div v-if="product.profit_margin !== null" class="mb-3">
              <div class="flex items-center space-x-4">
                <span
                  :class="getProfitColorClass(product.profit_margin)"
                  class="text-sm font-semibold"
                >
                  수익률: {{ parseFloat(product.profit_margin || 0).toFixed(1) }}%
                </span>

                <span v-if="product.recommended_price" class="text-sm text-gray-600">
                  추천가: ₩{{ product.recommended_price?.toLocaleString() }}
                </span>

                <span
                  :class="product.is_profitable ? 'text-green-600' : 'text-red-600'"
                  class="text-xs px-2 py-1 rounded-full bg-opacity-20"
                  :style="{ backgroundColor: product.is_profitable ? '#10b981' : '#ef4444' }"
                >
                  {{ product.is_profitable ? '수익성 ✓' : '수익성 ✗' }}
                </span>
              </div>
            </div>

            <!-- 액션 버튼 -->
            <div class="flex space-x-2">
              <button
                @click="viewDetails(product)"
                class="px-3 py-1 text-sm bg-blue-500 text-white rounded hover:bg-blue-600"
              >
                상세보기
              </button>

              <button
                v-if="product.status === 'COLLECTED' && !product.profit_analysis"
                @click="reanalyze(product)"
                :disabled="analyzingProducts.has(product.id)"
                class="px-3 py-1 text-sm bg-green-500 text-white rounded hover:bg-green-600 disabled:opacity-50"
              >
                {{ analyzingProducts.has(product.id) ? '분석중...' : '수익성 분석' }}
              </button>

              <button
                v-else-if="product.profit_analysis"
                @click="reanalyze(product)"
                :disabled="analyzingProducts.has(product.id)"
                class="px-3 py-1 text-sm bg-yellow-500 text-white rounded hover:bg-yellow-600 disabled:opacity-50"
              >
                {{ analyzingProducts.has(product.id) ? '재분석중...' : '재분석' }}
              </button>

              <button
                @click="deleteProduct(product)"
                class="px-3 py-1 text-sm bg-red-500 text-white rounded hover:bg-red-600"
              >
                삭제
              </button>
            </div>
          </div>
        </div>

        <!-- 에러 메시지 -->
        <div v-if="product.error_message" class="mt-3 p-2 bg-red-50 border border-red-200 rounded text-sm text-red-600">
          {{ product.error_message }}
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

    <!-- 상세보기 모달 -->
    <div v-if="showDetailModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" @click="closeDetailModal">
      <div class="bg-white rounded-lg p-6 max-w-4xl max-h-[90vh] overflow-y-auto m-4" @click.stop>
        <div class="flex justify-between items-start mb-4">
          <h3 class="text-xl font-bold text-gray-900">📦 상품 상세 정보</h3>
          <button @click="closeDetailModal" class="text-gray-400 hover:text-gray-600 text-2xl">&times;</button>
        </div>

        <div v-if="selectedProduct" class="space-y-6">
          <!-- 기본 정보 -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- 이미지 갤러리 -->
            <div class="space-y-4">
              <ImageGallery :product="selectedProduct" />
              <div class="flex flex-wrap gap-2">
                <span 
                  :class="getStatusClass(selectedProduct.status)"
                  class="px-3 py-1 text-sm font-medium rounded-full"
                >
                  {{ getStatusName(selectedProduct.status) }}
                </span>
                <span 
                  :class="selectedProduct.is_profitable ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'"
                  class="px-3 py-1 text-sm font-medium rounded-full"
                >
                  {{ selectedProduct.is_profitable ? '수익성 ✓' : '수익성 ✗' }}
                </span>
              </div>
            </div>

            <!-- 기본 정보 -->
            <div class="space-y-4">
              <div>
                <h4 class="font-semibold text-lg mb-2">{{ selectedProduct.title }}</h4>
                <div class="space-y-2 text-sm">
                  <p><strong>ASIN:</strong> {{ selectedProduct.asin }}</p>
                  <p><strong>가격:</strong> ¥{{ formatPrice(selectedProduct.price_jpy) }}</p>
                  <p><strong>무게:</strong> {{ selectedProduct.weight_g }}g</p>
                  <p><strong>치수:</strong> {{ selectedProduct.dimensions || 'N/A' }}</p>
                  <p><strong>카테고리:</strong> {{ selectedProduct.category || 'N/A' }}</p>
                  <p v-if="selectedProduct.subcategory"><strong>서브카테고리:</strong> {{ selectedProduct.subcategory }}</p>
                  <p><strong>수집일:</strong> {{ formatDate(selectedProduct.collected_at) }}</p>
                  <p v-if="selectedProduct.analyzed_at"><strong>분석일:</strong> {{ formatDate(selectedProduct.analyzed_at) }}</p>
                </div>
                <div class="mt-3">
                  <a :href="selectedProduct.amazon_url" target="_blank" 
                     class="inline-flex items-center px-4 py-2 bg-orange-500 text-white rounded-md hover:bg-orange-600 text-sm">
                    Amazon에서 보기 ↗
                  </a>
                </div>
              </div>
            </div>
          </div>

          <!-- 수익성 분석 -->
          <div v-if="selectedProduct.profit_analysis" class="bg-gray-50 rounded-lg p-4">
            <h4 class="font-semibold text-lg mb-4">💰 수익성 분석</h4>
            
            <!-- 요약 정보 -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm mb-6">
              <div>
                <p class="font-medium text-gray-700">추천 판매가</p>
                <p class="text-lg font-bold text-blue-600">₩{{ selectedProduct.recommended_price?.toLocaleString() }}</p>
              </div>
              <div>
                <p class="font-medium text-gray-700">예상 수익률</p>
                <p :class="getProfitColorClass(selectedProduct.profit_margin)" class="text-lg font-bold">
                  {{ parseFloat(selectedProduct.profit_margin || 0).toFixed(1) }}%
                </p>
              </div>
              <div>
                <p class="font-medium text-gray-700">예상 순이익</p>
                <p class="text-lg font-bold text-green-600">
                  ₩{{ selectedProduct.profit_analysis?.calculation?.profit?.net_profit?.toLocaleString() }}
                </p>
              </div>
            </div>

            <!-- 상세 비용 분석 -->
            <div v-if="selectedProduct.profit_analysis?.calculation" class="bg-white rounded-lg p-4 border border-gray-200">
              <h5 class="font-semibold text-md mb-3 text-gray-800">📊 상세 비용 분석</h5>
              
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-sm">
                <!-- 일본 비용 -->
                <div>
                  <h6 class="font-medium text-gray-700 mb-2 border-b border-gray-200 pb-1">🇯🇵 일본 비용</h6>
                  <div class="space-y-1">
                    <div class="flex justify-between">
                      <span>상품가격:</span>
                      <span>¥{{ formatPrice(selectedProduct.profit_analysis.calculation.input.product_price_jpy) }}</span>
                    </div>
                    <div class="flex justify-between">
                      <span>일본 배송비:</span>
                      <span>¥{{ formatPrice(selectedProduct.profit_analysis.calculation.input.japan_shipping_jpy) }}</span>
                    </div>
                    <div class="flex justify-between">
                      <span>국제배송비:</span>
                      <span>¥{{ formatPrice(selectedProduct.profit_analysis.calculation.costs.international_shipping_jpy) }}</span>
                    </div>
                    <div class="flex justify-between font-medium border-t border-gray-200 pt-1">
                      <span>총 일본 비용:</span>
                      <span>¥{{ formatPrice(selectedProduct.profit_analysis.calculation.costs.total_jpy_cost) }}</span>
                    </div>
                  </div>
                </div>

                <!-- 한국 비용 -->
                <div>
                  <h6 class="font-medium text-gray-700 mb-2 border-b border-gray-200 pb-1">🇰🇷 한국 비용</h6>
                  <div class="space-y-1">
                    <div class="flex justify-between">
                      <span>환율 적용 비용:</span>
                      <span>₩{{ Math.floor(selectedProduct.profit_analysis.calculation.costs.krw_cost_before_tax).toLocaleString() }}</span>
                    </div>
                    <div class="flex justify-between">
                      <span>한국 배송비:</span>
                      <span>₩{{ selectedProduct.profit_analysis.calculation.input.korea_shipping_krw?.toLocaleString() }}</span>
                    </div>
                    <div class="flex justify-between">
                      <span>포장비:</span>
                      <span>₩{{ selectedProduct.profit_analysis.calculation.input.packaging_fee_krw?.toLocaleString() }}</span>
                    </div>
                    <div class="flex justify-between">
                      <span>플랫폼 수수료:</span>
                      <span>₩{{ selectedProduct.profit_analysis.calculation.costs.platform_fees?.total_fee?.toLocaleString() }} 
                        ({{ selectedProduct.profit_analysis.calculation.costs.platform_fees?.coupang_fee_rate_percent }}%)</span>
                    </div>
                    <div class="flex justify-between font-medium border-t border-gray-200 pt-1">
                      <span>총 비용:</span>
                      <span>₩{{ Math.floor(selectedProduct.profit_analysis.calculation.costs.total_cost_krw).toLocaleString() }}</span>
                    </div>
                  </div>
                </div>
              </div>

              <!-- 수익 계산 -->
              <div class="mt-4 bg-green-50 rounded-lg p-3 border border-green-200">
                <h6 class="font-medium text-green-800 mb-2">💵 수익 계산</h6>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                  <div class="flex justify-between">
                    <span>판매가:</span>
                    <span class="font-medium">₩{{ selectedProduct.profit_analysis.calculation.profit.sell_price_krw?.toLocaleString() }}</span>
                  </div>
                  <div class="flex justify-between">
                    <span>총 비용:</span>
                    <span class="font-medium">₩{{ Math.floor(selectedProduct.profit_analysis.calculation.costs.total_cost_krw).toLocaleString() }}</span>
                  </div>
                  <div class="flex justify-between">
                    <span>순이익:</span>
                    <span class="font-bold text-green-600">₩{{ Math.floor(selectedProduct.profit_analysis.calculation.profit.net_profit).toLocaleString() }}</span>
                  </div>
                </div>
              </div>

              <!-- 환율 정보 -->
              <div class="mt-3 text-xs text-gray-500 text-center">
                환율: 1¥ = ₩{{ selectedProduct.profit_analysis.calculation.exchange_rate }} 
                (분석일: {{ formatDate(selectedProduct.profit_analysis.calculation.calculated_at) }})
              </div>
            </div>
          </div>

          <!-- 상품 설명 -->
          <div v-if="selectedProduct.description" class="bg-gray-50 rounded-lg p-4">
            <ProductDescription :product="selectedProduct" />
          </div>

          <!-- 특징 -->
          <div v-if="selectedProduct.features && selectedProduct.features.length > 0" class="bg-gray-50 rounded-lg p-4">
            <h4 class="font-semibold text-lg mb-3">⭐ 주요 특징</h4>
            <ul class="space-y-2 text-sm">
              <li v-for="(feature, index) in selectedProduct.features" :key="index" class="flex items-start">
                <span class="text-blue-500 mr-2">•</span>
                <span>{{ feature }}</span>
              </li>
            </ul>
          </div>

          <!-- 사양 -->
          <div v-if="selectedProduct.specifications && Object.keys(selectedProduct.specifications).length > 0" class="bg-gray-50 rounded-lg p-4">
            <h4 class="font-semibold text-lg mb-3">🔧 사양</h4>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
              <div v-for="(value, key) in selectedProduct.specifications" :key="key" class="flex justify-between">
                <span class="font-medium text-gray-700">{{ key }}:</span>
                <span>{{ value }}</span>
              </div>
            </div>
          </div>

          <!-- 액션 버튼 -->
          <div class="flex space-x-3 pt-4 border-t">
            <button
              v-if="selectedProduct.profit_analysis"
              @click="reanalyzeFromModal"
              class="px-4 py-2 bg-yellow-500 text-white rounded-md hover:bg-yellow-600"
            >
              재분석
            </button>
            <button
              @click="toggleFavoriteFromModal"
              :class="selectedProduct.is_favorite ? 'bg-yellow-500 hover:bg-yellow-600' : 'bg-gray-500 hover:bg-gray-600'"
              class="px-4 py-2 text-white rounded-md"
            >
              {{ selectedProduct.is_favorite ? '즐겨찾기 해제' : '즐겨찾기 추가' }}
            </button>
            <button
              @click="deleteFromModal"
              class="px-4 py-2 bg-red-500 text-white rounded-md hover:bg-red-600"
            >
              삭제
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios'
import ImageGallery from './ImageGallery.vue'
import ProductDescription from './ProductDescription.vue'

export default {
  name: 'CollectedProductList',
  components: {
    ImageGallery,
    ProductDescription
  },
  data() {
    return {
      products: [],
      pagination: {},
      loading: false,
      error: null,
      analyzingProducts: new Set(),

      // 필터 및 검색
      filters: {
        status: '',
        profitable: '',
        favorite: ''
      },
      searchQuery: '',
      sortBy: 'created_at',
      sortOrder: 'desc',

      // 모달 관련
      showDetailModal: false,
      selectedProduct: null,

      // 상태 매핑
      statusNames: {
        'PENDING': '수집대기',
        'COLLECTING': '수집중',
        'COLLECTED': '수집완료',
        'ANALYZED': '분석완료',
        'READY_TO_LIST': '등록대기',
        'LISTED': '판매중',
        'ERROR': '오류'
      }
    }
  },
  async mounted() {
    await this.loadProducts()
  },
  methods: {
    async loadProducts(page = 1) {
      this.loading = true
      this.error = null

      try {
        const params = {
          page,
          per_page: 20,
          sort_by: this.sortBy,
          sort_order: this.sortOrder
        }

        // 필터 적용
        if (this.filters.status) params.status = this.filters.status
        if (this.filters.profitable !== '') params.profitable = this.filters.profitable
        if (this.filters.favorite !== '') params.favorite = this.filters.favorite
        if (this.searchQuery) params.search = this.searchQuery

        const response = await axios.get('/collected-products', { params })

        if (response.data.success) {
          this.products = response.data.data.data
          this.pagination = {
            current_page: response.data.data.current_page,
            last_page: response.data.data.last_page,
            total: response.data.data.total
          }
        }
      } catch (error) {
        this.error = error.response?.data?.message || '상품 목록을 불러올 수 없습니다.'
      } finally {
        this.loading = false
      }
    },

    async refreshList() {
      await this.loadProducts(this.pagination.current_page || 1)
    },

    async applyFilters() {
      await this.loadProducts(1) // 첫 페이지부터 다시 로드
    },

    async changePage(page) {
      if (page >= 1 && page <= this.pagination.last_page) {
        await this.loadProducts(page)
      }
    },

    async toggleFavorite(product) {
      try {
        const response = await axios.put(`/collected-products/${product.id}`, {
          is_favorite: !product.is_favorite
        })
        
        if (response.data.success) {
          product.is_favorite = !product.is_favorite
        }
      } catch (error) {
        this.error = error.response?.data?.message || '즐겨찾기 설정에 실패했습니다.'
      }
    },

    async reanalyze(product) {
      // 목표 수익률 입력 받기
      const targetMarginInput = prompt(
        '목표 수익률을 입력하세요 (5-50% 범위)', 
        '10'
      )
      
      if (targetMarginInput === null) return // 취소됨
      
      const targetMargin = parseFloat(targetMarginInput)
      if (isNaN(targetMargin) || targetMargin < 5 || targetMargin > 50) {
        alert('올바른 수익률을 입력하세요 (5-50% 범위)')
        return
      }

      // 배송비 입력 받기
      const japanShippingInput = prompt(
        '일본 배송비를 입력하세요 (¥, 기본값: 0)', 
        '0'
      )
      
      if (japanShippingInput === null) return // 취소됨
      
      const koreaShippingInput = prompt(
        '한국 배송비를 입력하세요 (₩, 기본값: 0)', 
        '0'
      )
      
      if (koreaShippingInput === null) return // 취소됨

      const japanShipping = parseFloat(japanShippingInput) || 0
      const koreaShipping = parseFloat(koreaShippingInput) || 0

      this.analyzingProducts.add(product.id)

      try {
        const response = await axios.post(`/collected-products/${product.id}/reanalyze`, {
          target_margin: targetMargin,
          japan_shipping_jpy: japanShipping,
          korea_shipping_krw: koreaShipping
        })

        if (response.data.success) {
          // 상품 정보 업데이트
          Object.assign(product, response.data.data)
        }
      } catch (error) {
        this.error = error.response?.data?.message || '수익성 분석에 실패했습니다.'
      } finally {
        this.analyzingProducts.delete(product.id)
      }
    },

    async deleteProduct(product) {
      if (!confirm(`"${product.title}" 상품을 삭제하시겠습니까?`)) return

      try {
        const response = await axios.delete(`/collected-products/${product.id}`)

        if (response.data.success) {
          this.products = this.products.filter(p => p.id !== product.id)
        }
      } catch (error) {
        this.error = error.response?.data?.message || '상품 삭제에 실패했습니다.'
      }
    },

    viewDetails(product) {
      this.selectedProduct = product
      this.showDetailModal = true
    },

    closeDetailModal() {
      this.showDetailModal = false
      this.selectedProduct = null
    },

    async reanalyzeFromModal() {
      if (!this.selectedProduct) return
      await this.reanalyze(this.selectedProduct)
      // 모달 내 데이터 업데이트
      const updatedProduct = this.products.find(p => p.id === this.selectedProduct.id)
      if (updatedProduct) {
        this.selectedProduct = { ...updatedProduct }
      }
    },

    async toggleFavoriteFromModal() {
      if (!this.selectedProduct) return
      await this.toggleFavorite(this.selectedProduct)
      // 모달 내 데이터 업데이트
      this.selectedProduct = { ...this.selectedProduct, is_favorite: !this.selectedProduct.is_favorite }
    },

    async deleteFromModal() {
      if (!this.selectedProduct) return
      if (confirm(`"${this.selectedProduct.title}" 상품을 삭제하시겠습니까?`)) {
        await this.deleteProduct(this.selectedProduct)
        this.closeDetailModal()
      }
    },

    formatDate(dateString) {
      if (!dateString) return 'N/A'
      return new Date(dateString).toLocaleString('ko-KR')
    },

    formatPrice(price) {
      if (!price) return '0'
      // 문자열이면 숫자로 변환하고, 정수 부분만 추출하여 천단위 구분자 추가
      const numPrice = typeof price === 'string' ? parseFloat(price) : price
      return Math.floor(numPrice).toLocaleString()
    },

    getStatusName(status) {
      return this.statusNames[status] || status
    },

    getStatusClass(status) {
      const classes = {
        'PENDING': 'bg-gray-100 text-gray-800',
        'COLLECTING': 'bg-blue-100 text-blue-800',
        'COLLECTED': 'bg-green-100 text-green-800',
        'ANALYZED': 'bg-purple-100 text-purple-800',
        'READY_TO_LIST': 'bg-yellow-100 text-yellow-800',
        'LISTED': 'bg-indigo-100 text-indigo-800',
        'ERROR': 'bg-red-100 text-red-800'
      }
      return classes[status] || 'bg-gray-100 text-gray-800'
    },

    getProfitColorClass(margin) {
      const numMargin = parseFloat(margin || 0)
      if (numMargin >= 20) return 'text-green-600'
      if (numMargin >= 10) return 'text-yellow-600'
      return 'text-red-600'
    },

    handleImageError(event) {
      event.target.src = '/ectokorea/placeholder-product.svg'
    },

    getProductImage(product) {
      // 썸네일 이미지가 있으면 첫 번째 썸네일 사용
      if (product.thumbnail_images && product.thumbnail_images.length > 0) {
        return product.thumbnail_images[0]
      }
      // 메인 이미지 사용
      if (product.main_image) {
        return product.main_image
      }
      // 기존 이미지 URL 사용
      if (product.image_url) {
        return product.image_url
      }
      // 기본 플레이스홀더
      return '/ectokorea/placeholder-product.svg'
    }
  }
}
</script>

<style scoped>
/* 카드 레이아웃 개선 */
.collected-product-list {
  word-break: break-word;
  overflow-wrap: break-word;
}

/* 이미지 컨테이너 고정 */
.flex-shrink-0 {
  min-width: 96px; /* w-24 */
}
</style>