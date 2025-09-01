<template>
  <div class="profit-calculator bg-white rounded-lg shadow-lg p-6 max-w-4xl mx-auto">
    <h2 class="text-2xl font-bold text-gray-800 mb-6 text-center">
      🧮 {{ $t('calculator.title') }}
    </h2>

    <!-- 현재 환율 표시 -->
    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
      <div class="flex items-center justify-between">
        <div>
          <span class="text-sm text-gray-600">{{ $t('exchange.currentRate') }} (JPY → KRW)</span>
          <div class="text-lg font-semibold text-blue-800">
            ¥1 = ₩{{ exchangeRate.toFixed(2) }}
          </div>
          <span class="text-xs text-gray-500">
            {{ $t('exchange.lastUpdate') }}: {{ lastUpdated }}
          </span>
        </div>
        <button
          @click="refreshExchangeRate"
          :disabled="loading.exchangeRate"
          class="px-3 py-1 bg-blue-500 text-white rounded hover:bg-blue-600 disabled:opacity-50 text-sm"
        >
          {{ loading.exchangeRate ? $t('common.loading') : $t('exchange.updateRate') }}
        </button>
      </div>
    </div>

    <form @submit.prevent="calculateProfit" class="space-y-6">
      <!-- 일본 상품 정보 -->
      <div class="grid md:grid-cols-2 gap-6">
        <div class="space-y-4">
          <h3 class="text-lg font-semibold text-gray-700 border-b pb-2">
            🇯🇵 {{ $t('calculator.productInfo') }}
          </h3>
          
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
              {{ $t('calculator.productPrice') }} *
            </label>
            <input
              v-model.number="form.product_price_jpy"
              type="number"
              min="0"
              step="1"
              required
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              placeholder="예: 3000"
            />
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
              {{ $t('calculator.domesticShipping') }}
            </label>
            <input
              v-model.number="form.japan_shipping_jpy"
              type="number"
              min="0"
              step="1"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              placeholder="예: 500"
            />
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
              {{ $t('calculator.productWeight') }} *
            </label>
            <input
              v-model.number="form.product_weight_g"
              type="number"
              min="1"
              step="1"
              required
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              placeholder="예: 250"
            />
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
              {{ $t('calculator.shippingMethod') }}
            </label>
            <select
              v-model="form.shipping_method"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
              <option value="economy">HANIRO LINE Economy (일반 요금제)</option>
              <option value="premium">HANIRO LINE Premium (프리미엄 요금제)</option>
            </select>
            <p class="text-xs text-gray-500 mt-1">
              무게별 배송료 자동 계산 (P단위, 1P=100엔, 최대 70KG)
            </p>
          </div>

          <!-- 대분류 카테고리 -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
              카테고리 (대분류)
            </label>
            <select
              v-model="form.category"
              @change="onCategoryChange"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
              <option value="electronics">가전디지털 (7.8% 기본)</option>
              <option value="daily_necessities">생활용품 (7.8%)</option>
              <option value="beauty">뷰티 (9.6%)</option>
              <option value="baby">출산/유아 (10.0%)</option>
              <option value="automotive">자동차용품 (10.0%)</option>
              <option value="fashion">패션 (10.5%)</option>
              <option value="food">식품 (10.6%)</option>
              <option value="books">도서 (10.8%)</option>
              <option value="toys_hobbies">완구/취미 (10.8%)</option>
              <option value="sports">스포츠/레저 (10.8%)</option>
            </select>
          </div>

          <!-- 중분류 서브카테고리 -->
          <div v-if="availableSubcategories.length > 0">
            <label class="block text-sm font-medium text-gray-700 mb-1">
              세부 카테고리 (선택사항)
              <span class="text-green-600 font-semibold" v-if="currentSubcategoryDiscount">
                {{ currentSubcategoryDiscount }}
              </span>
            </label>
            <select
              v-model="form.subcategory"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
              <option value="">기본 요율 사용</option>
              <option 
                v-for="subcategory in availableSubcategories" 
                :key="subcategory.id" 
                :value="subcategory.id"
              >
                {{ subcategory.name }} ({{ subcategory.rate }}%)
                <span v-if="subcategory.discount" class="text-green-600">
                  {{ subcategory.discount }}
                </span>
              </option>
            </select>
            <p class="text-xs text-gray-500 mt-1">
              세부 카테고리 선택 시 더 정확한 수수료가 적용됩니다
            </p>
          </div>
        </div>

        <!-- 한국 판매 정보 -->
        <div class="space-y-4">
          <h3 class="text-lg font-semibold text-gray-700 border-b pb-2">
            🇰🇷 {{ $t('calculator.selling') }}
          </h3>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
              {{ $t('calculator.sellingPrice') }} *
            </label>
            <input
              v-model.number="form.sell_price_krw"
              type="number"
              min="0"
              step="100"
              required
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              placeholder="예: 45000"
            />
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
              {{ $t('calculator.koreaShipping') }}
            </label>
            <input
              v-model.number="form.korea_shipping_krw"
              type="number"
              min="0"
              step="100"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              placeholder="예: 3000"
            />
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
              {{ $t('calculator.packaging') }}
            </label>
            <input
              v-model.number="form.packaging_fee_krw"
              type="number"
              min="0"
              step="100"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              placeholder="예: 1000"
            />
          </div>

          <!-- 목표 이익률 계산 -->
          <div class="bg-gray-50 p-4 rounded-lg">
            <label class="block text-sm font-medium text-gray-700 mb-1">
              {{ $t('calculator.targetProfit') }}
            </label>
            <input
              v-model.number="targetProfitMargin"
              type="number"
              min="5"
              max="50"
              step="1"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              placeholder="20"
            />
            <button
              type="button"
              @click="calculateRecommendedPrice"
              :disabled="loading.calculation"
              class="mt-2 w-full px-4 py-2 bg-green-500 text-white rounded-md hover:bg-green-600 disabled:opacity-50"
            >
              {{ loading.calculation ? $t('common.loading') : $t('calculator.calculateRecommended') }}
            </button>
          </div>
        </div>
      </div>

      <!-- 계산 버튼 -->
      <div class="text-center">
        <button
          type="submit"
          :disabled="loading.calculation"
          class="px-8 py-3 bg-blue-600 text-white text-lg font-semibold rounded-lg hover:bg-blue-700 disabled:opacity-50 transition-colors"
        >
          {{ loading.calculation ? $t('common.loading') : $t('calculator.calculate') }}
        </button>
      </div>
    </form>

    <!-- 계산 결과 -->
    <div v-if="result" class="mt-8">
      <h3 class="text-xl font-bold text-gray-800 mb-4 text-center">{{ $t('calculator.results') }}</h3>
      
      <!-- 요약 -->
      <div class="grid md:grid-cols-3 gap-4 mb-6">
        <div class="bg-blue-50 p-4 rounded-lg text-center">
          <div class="text-sm text-gray-600">{{ $t('calculator.totalCost') }}</div>
          <div class="text-xl font-bold text-blue-800">
            ₩{{ result.costs.total_cost_krw.toLocaleString() }}
          </div>
        </div>
        <div class="bg-green-50 p-4 rounded-lg text-center">
          <div class="text-sm text-gray-600">{{ $t('calculator.netProfit') }}</div>
          <div class="text-xl font-bold" :class="result.profit.net_profit > 0 ? 'text-green-800' : 'text-red-800'">
            ₩{{ result.profit.net_profit.toLocaleString() }}
          </div>
        </div>
        <div class="bg-purple-50 p-4 rounded-lg text-center">
          <div class="text-sm text-gray-600">{{ $t('calculator.profitRate') }}</div>
          <div class="text-xl font-bold" :class="result.profit.profit_margin_percent > 0 ? 'text-purple-800' : 'text-red-800'">
            {{ result.profit.profit_margin_percent }}%
          </div>
        </div>
      </div>

      <!-- 상세 비용 분석 -->
      <div class="bg-gray-50 p-4 rounded-lg">
        <h4 class="font-semibold text-gray-700 mb-3">{{ $t('calculator.detailedCostAnalysis') }}</h4>
        <div class="grid md:grid-cols-2 gap-4 text-sm">
          <div>
            <div class="flex justify-between py-1">
              <span>{{ $t('calculator.japanProductShipping') }}:</span>
              <span>¥{{ (result.costs.japan_costs_jpy).toLocaleString() }}</span>
            </div>
            <div class="flex justify-between py-1">
              <span>{{ $t('calculator.internationalShipping') }}:</span>
              <span>¥{{ result.costs.international_shipping_jpy.toLocaleString() }}</span>
            </div>
            <div class="flex justify-between py-1 font-medium border-t pt-1">
              <span>{{ $t('calculator.totalJPYCost') }}:</span>
              <span>¥{{ result.costs.total_jpy_cost.toLocaleString() }}</span>
            </div>
          </div>
          <div>
            <div class="flex justify-between py-1">
              <span>{{ $t('calculator.krwConversion') }}:</span>
              <span>₩{{ Math.round(result.costs.krw_cost_before_tax).toLocaleString() }}</span>
            </div>
            <div class="flex justify-between py-1">
              <span>{{ $t('calculator.koreaShippingPackaging') }}:</span>
              <span>₩{{ result.costs.korea_local_costs.toLocaleString() }}</span>
            </div>
            <div class="flex justify-between py-1">
              <span>{{ $t('calculator.platformFee') }}:</span>
              <span>₩{{ result.costs.platform_fees.total_fee.toLocaleString() }}</span>
            </div>
          </div>
        </div>
      </div>

    </div>

    <!-- 추천 가격 결과 -->
    <div v-if="recommendedPriceResult" class="mt-6 bg-green-50 border border-green-200 rounded-lg p-4">
      <h4 class="font-semibold text-green-800 mb-2">{{ $t('calculator.recommendedSellingPrice') }}</h4>
      <div class="text-lg">
        {{ $t('calculator.targetProfitRecommend', [recommendedPriceResult.target_profit_margin]) }}:
        <strong class="text-green-700">₩{{ recommendedPriceResult.recommended_price.toLocaleString() }}</strong>
      </div>
      <div class="text-sm text-gray-600 mt-1">
        {{ $t('calculator.actualProfitRate', [recommendedPriceResult.actual_profit_margin]) }}
      </div>
    </div>

    <!-- 에러 메시지 -->
    <div v-if="error" class="mt-4 p-4 bg-red-50 border border-red-200 rounded-lg">
      <div class="text-red-700">
        <strong>오류:</strong> {{ error }}
      </div>
    </div>
  </div>
</template>

<script>
import api from '@/utils/api'

export default {
  name: 'ProfitCalculator',
  data() {
    return {
      form: {
        product_price_jpy: 3000,
        japan_shipping_jpy: 500,
        product_weight_g: 250,
        shipping_method: 'economy',
        category: 'electronics',
        subcategory: '',
        sell_price_krw: 45000,
        korea_shipping_krw: 3000,
        packaging_fee_krw: 0
      },
      targetProfitMargin: 20,
      exchangeRate: 9.0,
      lastUpdated: '',
      result: null,
      recommendedPriceResult: null,
      error: null,
      loading: {
        calculation: false,
        exchangeRate: false
      }
    }
  },
  async mounted() {
    await this.loadExchangeRate()
  },
  computed: {
    // 선택된 카테고리의 서브카테고리 목록
    availableSubcategories() {
      const subcategories = {
        electronics: [
          { id: 'computers', name: '컴퓨터', rate: 5.0, discount: '(-2.8%P)' },
          { id: 'keyboards_mouse', name: '마우스/키보드', rate: 6.5, discount: '(-1.3%P)' },
          { id: 'cameras', name: '카메라/카메라용품', rate: 5.8, discount: '(-2.0%P)' },
          { id: 'tablets', name: '태블릿PC', rate: 5.0, discount: '(-2.8%P)' },
          { id: 'games', name: '게임', rate: 6.8, discount: '(-1.0%P)' },
          { id: 'monitors', name: '모니터', rate: 4.5, discount: '(-3.3%P)' },
          { id: 'tv', name: 'TV', rate: 5.8, discount: '(-2.0%P)' }
        ],
        toys_hobbies: [
          { id: 'rc_toys', name: 'RC완구', rate: 7.8, discount: '(-3.0%P)' },
          { id: 'figures', name: '피규어/장난감', rate: 10.8, discount: '' }
        ],
        fashion: [
          { id: 'clothing', name: '패션의류', rate: 10.5, discount: '' },
          { id: 'accessories', name: '패션잡화', rate: 10.5, discount: '' }
        ]
      }
      return subcategories[this.form.category] || []
    },

    // 현재 선택된 서브카테고리의 할인 정보
    currentSubcategoryDiscount() {
      if (!this.form.subcategory) return ''
      const subcategory = this.availableSubcategories.find(sub => sub.id === this.form.subcategory)
      return subcategory?.discount || ''
    }
  },
  methods: {
    async loadExchangeRate() {
      try {
        this.loading.exchangeRate = true
        const response = await api.get('/exchange-rate/current')
        if (response.data.success) {
          this.exchangeRate = response.data.data.rate
          this.lastUpdated = new Date(response.data.data.last_updated).toLocaleString('ko-KR')
        }
      } catch (error) {
        console.error('환율 로드 실패:', error)
        this.error = '환율 정보를 불러올 수 없습니다.'
      } finally {
        this.loading.exchangeRate = false
      }
    },

    async refreshExchangeRate() {
      try {
        this.loading.exchangeRate = true
        const response = await api.post('/exchange-rate/refresh')
        if (response.data.success) {
          this.exchangeRate = response.data.data.rate
          this.lastUpdated = new Date(response.data.data.last_updated).toLocaleString('ko-KR')
          this.error = null
        }
      } catch (error) {
        console.error('환율 갱신 실패:', error)
        this.error = '환율 갱신에 실패했습니다.'
      } finally {
        this.loading.exchangeRate = false
      }
    },

    // 카테고리 변경 시 서브카테고리 초기화
    onCategoryChange() {
      this.form.subcategory = ''
    },

    async calculateProfit() {
      try {
        this.loading.calculation = true
        this.error = null
        this.recommendedPriceResult = null

        const response = await api.post('/profit-calculator/calculate', this.form)
        
        if (response.data.success) {
          this.result = response.data.data
        } else {
          this.error = response.data.message || '계산에 실패했습니다.'
        }
      } catch (error) {
        console.error('이익 계산 실패:', error)
        this.error = error.response?.data?.message || '계산 중 오류가 발생했습니다.'
      } finally {
        this.loading.calculation = false
      }
    },

    async calculateRecommendedPrice() {
      try {
        this.loading.calculation = true
        this.error = null
        this.result = null

        const requestData = {
          ...this.form,
          target_profit_margin: this.targetProfitMargin
        }

        const response = await api.post('/profit-calculator/recommend-price', requestData)
        
        if (response.data.success) {
          this.recommendedPriceResult = response.data.data
          if (response.data.data.calculation) {
            this.result = response.data.data.calculation
          }
        } else {
          this.error = response.data.message || '추천 가격 계산에 실패했습니다.'
        }
      } catch (error) {
        console.error('추천 가격 계산 실패:', error)
        this.error = error.response?.data?.message || '추천 가격 계산 중 오류가 발생했습니다.'
      } finally {
        this.loading.calculation = false
      }
    }
  }
}
</script>

<style scoped>
.profit-calculator {
  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
}
</style>