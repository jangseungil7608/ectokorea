<template>
  <div class="image-gallery">
    <!-- 메인 이미지 표시 영역 -->
    <div class="main-image-container mb-4">
      <img 
        :src="currentLargeImage || fallbackImage"
        :alt="product?.title || '상품 이미지'"
        class="main-image w-full h-96 object-contain bg-gray-50 rounded-lg shadow-md cursor-zoom-in"
        @click="openLightbox"
        @error="handleImageError"
      />
    </div>

    <!-- 썸네일 이미지 목록 -->
    <div class="thumbnail-container" v-if="thumbnailImages.length > 0">
      <div class="flex gap-2 overflow-x-auto pb-2">
        <button
          v-for="(thumbnail, index) in thumbnailImages"
          :key="index"
          @click="selectImage(index)"
          :class="[
            'thumbnail-button flex-shrink-0 border-2 rounded-lg overflow-hidden',
            'transition-all duration-200 hover:shadow-md',
            selectedIndex === index 
              ? 'border-blue-500 shadow-md' 
              : 'border-gray-200 hover:border-gray-300'
          ]"
        >
          <img 
            :src="thumbnail"
            :alt="`상품 이미지 ${index + 1}`"
            class="w-16 h-16 object-cover"
            @error="handleThumbnailError(index)"
          />
        </button>
      </div>
    </div>

    <!-- 라이트박스 모달 -->
    <div 
      v-if="showLightbox"
      class="lightbox-overlay fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center z-50"
      @click="closeLightbox"
    >
      <div class="lightbox-content relative max-w-4xl max-h-full p-4" @click.stop>
        <!-- 닫기 버튼 -->
        <button
          @click="closeLightbox"
          class="absolute top-2 right-2 text-white text-2xl z-10 bg-black bg-opacity-50 rounded-full w-8 h-8 flex items-center justify-center hover:bg-opacity-75"
        >
          ×
        </button>
        
        <!-- 대형 이미지 -->
        <img 
          :src="currentLargeImage || fallbackImage"
          :alt="product?.title || '상품 이미지'"
          class="max-w-full max-h-full object-contain"
          @click.stop
        />
        
        <!-- 이전/다음 버튼 -->
        <button
          v-if="thumbnailImages.length > 1"
          @click.stop="previousImage"
          class="absolute left-2 top-1/2 transform -translate-y-1/2 text-white text-2xl bg-black bg-opacity-50 rounded-full w-10 h-10 flex items-center justify-center hover:bg-opacity-75 z-20"
        >
          ‹
        </button>
        <button
          v-if="thumbnailImages.length > 1"
          @click.stop="nextImage"
          class="absolute right-2 top-1/2 transform -translate-y-1/2 text-white text-2xl bg-black bg-opacity-50 rounded-full w-10 h-10 flex items-center justify-center hover:bg-opacity-75 z-20"
        >
          ›
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'

const props = defineProps({
  product: {
    type: Object,
    default: () => ({})
  }
})

const selectedIndex = ref(0)
const showLightbox = ref(false)
const fallbackImage = '/placeholder-product.svg'

// 썸네일 이미지 배열
const thumbnailImages = computed(() => {
  const thumbnails = props.product?.thumbnail_images || []
  const images = props.product?.images || []
  const mainImage = props.product?.main_image || props.product?.image_url
  
  // 썸네일이 있으면 사용
  if (thumbnails.length > 0) {
    return thumbnails
  }
  
  // 썸네일이 없으면 일반 images 배열 사용
  if (images.length > 0) {
    return images
  }
  
  // 그것도 없으면 메인 이미지를 사용
  if (mainImage) {
    return [mainImage]
  }
  
  return []
})

// 큰 이미지 배열
const largeImages = computed(() => {
  const large = props.product?.large_images || []
  const images = props.product?.images || []
  const mainImage = props.product?.main_image || props.product?.image_url
  
  // 큰 이미지가 있으면 사용
  if (large.length > 0) {
    return large
  }
  
  // 큰 이미지가 없으면 썸네일 사용
  if (thumbnailImages.value.length > 0) {
    return thumbnailImages.value
  }
  
  // 썸네일도 없으면 일반 images 사용
  if (images.length > 0) {
    return images
  }
  
  // 그것도 없으면 메인 이미지 사용
  if (mainImage) {
    return [mainImage]
  }
  
  return []
})

// 현재 선택된 큰 이미지
const currentLargeImage = computed(() => {
  return largeImages.value[selectedIndex.value] || largeImages.value[0]
})

// 이미지 선택
const selectImage = (index) => {
  selectedIndex.value = index
}

// 라이트박스 열기/닫기
const openLightbox = () => {
  showLightbox.value = true
}

const closeLightbox = () => {
  showLightbox.value = false
}

// 이전/다음 이미지
const previousImage = () => {
  selectedIndex.value = selectedIndex.value > 0 
    ? selectedIndex.value - 1 
    : thumbnailImages.value.length - 1
}

const nextImage = () => {
  selectedIndex.value = selectedIndex.value < thumbnailImages.value.length - 1 
    ? selectedIndex.value + 1 
    : 0
}

// 이미지 로드 에러 처리
const handleImageError = (event) => {
  event.target.src = fallbackImage
}

const handleThumbnailError = (index) => {
  console.warn(`썸네일 이미지 로드 실패: ${index}`)
}

// 키보드 이벤트 처리
const handleKeydown = (event) => {
  if (!showLightbox.value) return
  
  switch (event.key) {
    case 'Escape':
      closeLightbox()
      break
    case 'ArrowLeft':
      previousImage()
      break
    case 'ArrowRight':
      nextImage()
      break
  }
}

// 컴포넌트 마운트 시 키보드 이벤트 리스너 추가
watch(showLightbox, (newValue) => {
  if (newValue) {
    document.addEventListener('keydown', handleKeydown)
    document.body.style.overflow = 'hidden'
  } else {
    document.removeEventListener('keydown', handleKeydown)
    document.body.style.overflow = ''
  }
})

// 상품이 바뀔 때 선택 인덱스 초기화
watch(() => props.product, () => {
  selectedIndex.value = 0
})
</script>

<style scoped>
.main-image {
  transition: transform 0.2s ease;
}

.main-image:hover {
  transform: scale(1.02);
}

.thumbnail-button {
  min-width: 4rem;
}

.lightbox-overlay {
  backdrop-filter: blur(4px);
}

.thumbnail-container {
  scrollbar-width: thin;
}

.thumbnail-container::-webkit-scrollbar {
  height: 6px;
}

.thumbnail-container::-webkit-scrollbar-track {
  background: #f1f1f1;
  border-radius: 3px;
}

.thumbnail-container::-webkit-scrollbar-thumb {
  background: #c1c1c1;
  border-radius: 3px;
}

.thumbnail-container::-webkit-scrollbar-thumb:hover {
  background: #a8a8a8;
}
</style>