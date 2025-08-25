<template>
  <div class="language-toggle-container">
    <!-- 모바일용 간단 토글 -->
    <div class="md:hidden">
      <button
        @click="languageStore.toggleLanguage()"
        class="p-2 rounded-md hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
        :title="`${languageStore.currentLanguage === 'ko' ? '日本語に切り替え' : '한국어로 전환'}`"
      >
        <span class="text-lg">
          {{ languageStore.getCurrentLanguageInfo?.flag }}
        </span>
      </button>
    </div>

    <!-- 데스크톱용 드롭다운 -->
    <div class="hidden md:block relative" ref="dropdownRef">
      <button
        @click="isOpen = !isOpen"
        class="flex items-center space-x-2 px-3 py-2 rounded-md hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
        :class="{ 'bg-gray-100 dark:bg-gray-800': isOpen }"
      >
        <!-- 현재 언어 플래그 및 이름 -->
        <span class="text-sm">{{ languageStore.getCurrentLanguageInfo?.flag }}</span>
        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
          {{ languageStore.getCurrentLanguageInfo?.name }}
        </span>
        
        <!-- 드롭다운 화살표 -->
        <svg class="w-4 h-4 text-gray-400 transition-transform" :class="{ 'rotate-180': isOpen }" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
        </svg>
      </button>

      <!-- 드롭다운 메뉴 -->
      <transition
        enter-active-class="transition ease-out duration-200"
        enter-from-class="opacity-0 scale-95"
        enter-to-class="opacity-100 scale-100"
        leave-active-class="transition ease-in duration-75"
        leave-from-class="opacity-100 scale-100"
        leave-to-class="opacity-0 scale-95"
      >
        <div
          v-show="isOpen"
          class="absolute right-0 mt-2 w-40 bg-white dark:bg-gray-800 rounded-md shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none z-50"
        >
          <div class="py-1">
            <!-- 언어 옵션들 -->
            <button
              v-for="language in languageStore.availableLanguages"
              :key="language.code"
              @click="selectLanguage(language.code)"
              class="flex items-center w-full px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
              :class="{ 'bg-gray-100 dark:bg-gray-700': languageStore.currentLanguage === language.code }"
            >
              <span class="mr-3">{{ language.flag }}</span>
              {{ language.name }}
              
              <!-- 선택 표시 -->
              <svg v-if="languageStore.currentLanguage === language.code" class="w-4 h-4 ml-auto text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
              </svg>
            </button>
          </div>
        </div>
      </transition>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { useLanguageStore } from '../stores/language.js'

const languageStore = useLanguageStore()
const isOpen = ref(false)
const dropdownRef = ref(null)

// 언어 선택
const selectLanguage = (languageCode) => {
  languageStore.setLanguage(languageCode)
  isOpen.value = false
}

// 외부 클릭 시 드롭다운 닫기
const handleClickOutside = (event) => {
  if (dropdownRef.value && !dropdownRef.value.contains(event.target)) {
    isOpen.value = false
  }
}

onMounted(() => {
  document.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside)
})
</script>

<style scoped>
/* 추가 스타일이 필요한 경우 여기에 작성 */
</style>