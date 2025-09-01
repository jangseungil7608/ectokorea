<template>
  <div class="product-description">
    <h3 class="text-lg font-semibold mb-4 text-gray-800 dark:text-gray-200">
      {{ $t('product.description') }}
    </h3>
    
    <div 
      v-if="sanitizedDescription"
      class="description-content prose prose-sm max-w-none dark:prose-invert"
      v-html="sanitizedDescription"
    ></div>
    
    <div 
      v-else-if="plainTextDescription"
      class="description-content text-gray-700 dark:text-gray-300 leading-relaxed"
    >
      <p>{{ plainTextDescription }}</p>
    </div>
    
    <div 
      v-else
      class="description-placeholder text-gray-500 dark:text-gray-400 italic"
    >
      {{ $t('product.noDescription') }}
    </div>

    <!-- 설명 영역 이미지들 -->
    <div 
      v-if="descriptionImages.length > 0"
      class="description-images mt-6"
    >
      <h4 class="text-md font-medium mb-3 text-gray-700 dark:text-gray-300">
        {{ $t('product.additionalImages') }}
      </h4>
      <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
        <div
          v-for="(image, index) in descriptionImages"
          :key="index"
          class="description-image-item cursor-pointer"
          @click="openImageModal(image)"
        >
          <img 
            :src="image"
            :alt="`설명 이미지 ${index + 1}`"
            class="w-full h-24 object-cover rounded-lg hover:shadow-md transition-shadow duration-200"
            @error="handleImageError"
          />
        </div>
      </div>
    </div>

    <!-- 이미지 모달 -->
    <div 
      v-if="showImageModal"
      class="image-modal-overlay fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center z-50"
      @click="closeImageModal"
    >
      <div class="image-modal-content relative max-w-4xl max-h-full p-4">
        <button
          @click="closeImageModal"
          class="absolute top-2 right-2 text-white text-2xl z-10 bg-black bg-opacity-50 rounded-full w-8 h-8 flex items-center justify-center hover:bg-opacity-75"
        >
          ×
        </button>
        <img 
          :src="selectedImage"
          :alt="'설명 이미지'"
          class="max-w-full max-h-full object-contain"
          @click.stop
        />
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'

const props = defineProps({
  htmlContent: {
    type: String,
    default: ''
  },
  product: {
    type: Object,
    default: () => ({})
  }
})

const showImageModal = ref(false)
const selectedImage = ref('')

// HTML 설명 정리 및 안전성 검사
const sanitizedDescription = computed(() => {
  // htmlContent prop 우선 사용, 없으면 product.description 사용
  const description = props.htmlContent || props.product?.description
  
  if (!description || typeof description !== 'string') {
    return null
  }
  
  // HTML 태그가 포함되어 있는지 확인
  if (!/<[^>]*>/g.test(description)) {
    return null // HTML이 아니면 plainTextDescription에서 처리
  }
  
  // 기본적인 HTML 정리
  let cleaned = description
  
  // 위험한 태그와 속성 제거
  cleaned = cleaned.replace(/<script[^>]*>[\s\S]*?<\/script>/gi, '')
  cleaned = cleaned.replace(/<style[^>]*>[\s\S]*?<\/style>/gi, '')
  cleaned = cleaned.replace(/on\w+="[^"]*"/gi, '') // 이벤트 핸들러 제거
  cleaned = cleaned.replace(/javascript:/gi, '') // javascript: 프로토콜 제거
  
  // 외부 링크는 새 창에서 열기
  cleaned = cleaned.replace(/<a\s+([^>]*?)href="([^"]*)"([^>]*?)>/gi, 
    '<a $1href="$2"$3 target="_blank" rel="noopener noreferrer">')
  
  // 이미지 로딩 에러 처리 추가
  cleaned = cleaned.replace(/<img([^>]*?)>/gi, 
    '<img$1 onerror="this.style.display=\'none\'">')
  
  return cleaned
})

// 일반 텍스트 설명
const plainTextDescription = computed(() => {
  // htmlContent prop 우선 사용, 없으면 product.description 사용
  const description = props.htmlContent || props.product?.description
  
  if (!description || typeof description !== 'string') {
    return null
  }
  
  // HTML 태그가 없으면 일반 텍스트로 처리
  if (/<[^>]*>/g.test(description)) {
    return null // HTML이면 sanitizedDescription에서 처리
  }
  
  return description
})

// 설명 영역 이미지들
const descriptionImages = computed(() => {
  const images = props.product?.description_images || []
  
  // 유효한 이미지 URL만 필터링
  return images.filter(img => 
    img && 
    typeof img === 'string' && 
    img.startsWith('http') &&
    !img.includes('grey-pixel.gif') // Amazon의 placeholder 이미지 제외
  )
})

// 이미지 모달 열기/닫기
const openImageModal = (imageUrl) => {
  selectedImage.value = imageUrl
  showImageModal.value = true
  document.body.style.overflow = 'hidden'
}

const closeImageModal = () => {
  showImageModal.value = false
  selectedImage.value = ''
  document.body.style.overflow = ''
}

// 이미지 로드 에러 처리
const handleImageError = (event) => {
  event.target.style.display = 'none'
}

// ESC 키로 모달 닫기
const handleKeydown = (event) => {
  if (event.key === 'Escape' && showImageModal.value) {
    closeImageModal()
  }
}

// 키보드 이벤트 리스너
document.addEventListener('keydown', handleKeydown)
</script>

<style scoped>
.description-content {
  line-height: 1.7;
}

/* HTML 콘텐츠 스타일링 */
.description-content :deep(h1),
.description-content :deep(h2), 
.description-content :deep(h3),
.description-content :deep(h4) {
  @apply font-semibold text-gray-800 dark:text-gray-200 mt-4 mb-2;
}

.description-content :deep(h1) {
  @apply text-xl;
}

.description-content :deep(h2) {
  @apply text-lg;
}

.description-content :deep(h3) {
  @apply text-base;
}

.description-content :deep(p) {
  @apply mb-3 text-gray-700 dark:text-gray-300;
}

.description-content :deep(img) {
  @apply max-w-full h-auto rounded-md my-4 shadow-sm;
}

.description-content :deep(ul),
.description-content :deep(ol) {
  @apply ml-6 mb-3;
}

.description-content :deep(li) {
  @apply mb-1 text-gray-700 dark:text-gray-300;
}

.description-content :deep(a) {
  @apply text-blue-600 dark:text-blue-400 hover:underline;
}

.description-content :deep(strong),
.description-content :deep(b) {
  @apply font-semibold text-gray-800 dark:text-gray-200;
}

.description-content :deep(span) {
  @apply text-gray-700 dark:text-gray-300;
}

.description-content :deep(div) {
  @apply mb-2;
}

/* 모달 스타일 */
.image-modal-overlay {
  backdrop-filter: blur(4px);
}

.description-image-item:hover img {
  transform: scale(1.05);
  transition: transform 0.2s ease;
}
</style>