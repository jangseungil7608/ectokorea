<template>
  <div class="bg-white rounded-lg shadow-lg p-6 max-w-2xl mx-auto">
    <h2 class="text-2xl font-bold text-gray-800 mb-6">
      📦 {{ $t('products.addProduct') }}
    </h2>

    <!-- ASIN으로 상품 정보 가져오기 -->
    <div class="mb-6 p-4 border border-gray-200 rounded-lg">
      <h3 class="text-lg font-semibold text-gray-700 mb-3">
        🔍 {{ $t('products.asinFetch') }}
      </h3>
      <div class="flex gap-2">
        <input
          v-model="asinInput"
          type="text"
          placeholder="예: B08N5WRWNW"
          class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
        />
        <button
          @click="fetchProductInfo"
          :disabled="loading || !asinInput.trim()"
          class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 disabled:opacity-50"
        >
          {{ loading ? $t('common.loading') : $t('common.search') }}
        </button>
      </div>
      <p class="text-sm text-gray-500 mt-1">
        {{ $t('products.testAsin') }}
      </p>
    </div>

    <!-- 변형 상품 선택 -->
    <div v-if="variants.length > 1" class="mb-6 p-4 border border-orange-200 rounded-lg bg-orange-50">
      <h3 class="text-lg font-semibold text-gray-700 mb-3">
        🔄 변형 상품 선택
      </h3>
      <div class="space-y-2">
        <div 
          v-for="variant in variants" 
          :key="variant.variant_name"
          class="flex items-center justify-between p-3 border rounded-lg cursor-pointer transition-colors"
          :class="selectedVariant?.variant_name === variant.variant_name 
            ? 'border-blue-500 bg-blue-50' 
            : 'border-gray-300 bg-white hover:bg-gray-50'"
          @click="selectVariant(variant)"
        >
          <div class="flex-1">
            <div class="font-medium text-gray-800">{{ variant.variant_name }}</div>
            <div class="text-sm text-gray-600 space-x-4">
              <span v-if="variant.price">¥{{ variant.price?.toLocaleString() }}</span>
              <span v-if="variant.weight">무게: {{ variant.weight }}g</span>
            </div>
          </div>
          <div class="text-right">
            <input 
              type="radio" 
              :checked="selectedVariant?.variant_name === variant.variant_name"
              class="w-4 h-4 text-blue-600"
              readonly
            />
          </div>
        </div>
      </div>
      <p class="text-sm text-orange-600 mt-2">
        💡 변형을 선택하면 해당 옵션의 가격과 무게 정보가 적용됩니다.
      </p>
    </div>

    <!-- 상품 정보 표시 및 수정 -->
    <form @submit.prevent="submitProduct" class="space-y-4">
      <div class="grid md:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">{{ $t('products.productName') }} *</label>
          <input
            v-model="product.name"
            type="text"
            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            required
          />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">{{ $t('products.price') }}</label>
          <input
            v-model="product.price"
            type="text"
            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            placeholder="¥0"
          />
        </div>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">{{ $t('products.asinCode') }}</label>
        <input
          v-model="product.asin"
          type="text"
          class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
        />
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">{{ $t('products.url') }}</label>
        <input
          v-model="product.url"
          type="url"
          class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
        />
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">{{ $t('products.image') }}</label>
        <input
          v-model="product.image"
          type="url"
          class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
        />
      </div>

      <!-- 이미지 미리보기 -->
      <div v-if="product.image" class="text-center">
        <img :src="product.image" :alt="$t('products.image')" class="max-w-xs mx-auto rounded-lg shadow-md">
      </div>

      <div class="text-center pt-4">
        <button
          type="submit"
          class="px-6 py-3 bg-green-600 text-white font-semibold rounded-lg hover:bg-green-700 transition-colors"
        >
          📦 {{ $t('products.registerProduct') }}
        </button>
      </div>
    </form>

    <!-- 메시지 표시 -->
    <div v-if="message" class="mt-4 p-3 rounded-lg" :class="messageClass">
      {{ message }}
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import axios from 'axios'

const { t } = useI18n()

const asinInput = ref('')
const loading = ref(false)
const product = ref({
  name: '',
  price: '',
  asin: '',
  url: '',
  image: ''
})
const message = ref('')
const isError = ref(false)

// 변형 상품 관련
const variants = ref([])
const selectedVariant = ref(null)

const messageClass = computed(() => ({
  'bg-green-50 border border-green-200 text-green-700': !isError.value,
  'bg-red-50 border border-red-200 text-red-700': isError.value
}))

const emit = defineEmits(['product-registered'])

// ASIN으로 상품 정보 가져오기
const fetchProductInfo = async () => {
  if (!asinInput.value.trim()) return
  
  loading.value = true
  isError.value = false
  
  try {
    // 먼저 변형 상품 목록 조회
    const variantsResponse = await axios.get(`/product-variants/${asinInput.value.trim()}`)
    
    if (variantsResponse.data.success && variantsResponse.data.data.length > 0) {
      variants.value = variantsResponse.data.data
      
      // 기본 변형 선택 (is_default: true 또는 첫 번째)
      const defaultVariant = variants.value.find(v => v.is_default) || variants.value[0]
      selectVariant(defaultVariant)
      
      message.value = `변형 상품 ${variants.value.length}개를 찾았습니다.`
      isError.value = false
    } else {
      // 변형 상품이 없으면 기존 방식으로 단일 상품 조회
      const response = await axios.get(`/amazon/${asinInput.value.trim()}`)
      
      // API 응답을 폼에 채우기
      product.value = {
        name: response.data.name || response.data.title,
        price: response.data.price,
        asin: asinInput.value.trim(),
        url: response.data.url,
        image: response.data.image_url || response.data.image
      }
      
      variants.value = []
      selectedVariant.value = null
      message.value = t('products.fetchSuccess')
      isError.value = false
    }
  } catch (error) {
    console.error('상품 정보 조회 실패:', error)
    message.value = t('products.fetchFailed') + ': ' + (error.response?.data?.message || error.message)
    isError.value = true
    variants.value = []
    selectedVariant.value = null
  } finally {
    loading.value = false
  }
}

// 변형 상품 선택
const selectVariant = (variant) => {
  selectedVariant.value = variant
  
  // 선택한 변형의 정보를 폼에 적용
  product.value = {
    name: variant.name,
    price: variant.price,
    asin: variant.parent_asin,
    url: variant.url,
    image: variant.image_url
  }
}

// 가격에서 숫자만 추출하는 함수
const extractPriceNumber = (priceString) => {
  if (!priceString) return 0
  // 숫자가 아닌 모든 문자 제거 (¥, 쉼표 등)
  const numberOnly = priceString.toString().replace(/[^\d]/g, '')
  return parseInt(numberOnly) || 0
}

// 상품 등록
const submitProduct = async () => {
  if (!product.value.name.trim()) {
    message.value = t('products.nameRequired')
    isError.value = true
    return
  }

  loading.value = true
  
  try {
    const response = await axios.post('/products', {
      name: product.value.name,
      price: extractPriceNumber(product.value.price),
      asin: product.value.asin,
      url: product.value.url,
      image_url: product.value.image
    })
    
    message.value = t('products.registerSuccessMsg')
    isError.value = false
    
    // 폼 초기화
    product.value = { name: '', price: '', asin: '', url: '', image: '' }
    asinInput.value = ''
    
    emit('product-registered')
    console.log('상품 등록 성공:', response.data)
  } catch (error) {
    console.error('상품 등록 실패:', error)
    message.value = t('products.registerFailedMsg') + ': ' + (error.response?.data?.message || error.message)
    isError.value = true
  } finally {
    loading.value = false
  }
}
</script>
