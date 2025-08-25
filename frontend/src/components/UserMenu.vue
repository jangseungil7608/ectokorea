<template>
  <div class="relative">
    <!-- 로그인한 경우만 표시 -->
    <div v-if="authStore.isLoggedIn" class="flex items-center space-x-4">
      <!-- 사용자 정보 -->
      <div class="flex items-center space-x-2">
        <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center">
          <span class="text-white text-sm font-medium">
            {{ userInitial }}
          </span>
        </div>
        <div class="text-sm">
          <div class="font-medium text-gray-900 dark:text-white">
            {{ authStore.user?.name }}
          </div>
          <div class="text-gray-500 dark:text-gray-400 text-xs">
            {{ authStore.user?.email }}
          </div>
        </div>
      </div>

      <!-- 로그아웃 버튼 -->
      <button
        @click="handleLogout"
        :disabled="loading"
        class="px-3 py-1 text-sm bg-red-500 hover:bg-red-600 disabled:bg-red-300 text-white rounded-md transition-colors"
      >
        <span v-if="loading" class="flex items-center">
          <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          로그아웃 중...
        </span>
        <span v-else>로그아웃</span>
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useAuthStore } from '../stores/auth.js'

const emit = defineEmits(['logout-success'])

const authStore = useAuthStore()
const loading = ref(false)

// 사용자 이름의 첫 글자
const userInitial = computed(() => {
  return authStore.user?.name?.charAt(0)?.toUpperCase() || 'U'
})

// 로그아웃 처리
const handleLogout = async () => {
  loading.value = true
  
  try {
    await authStore.logout()
    emit('logout-success')
    console.log('로그아웃 성공')
  } catch (error) {
    console.error('로그아웃 실패:', error)
  } finally {
    loading.value = false
  }
}
</script>